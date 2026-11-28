<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\QuickMapReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\ReorderSettingArusKasRequest;
use App\Http\Requests\Pengaturan\StorePengecualianArusKasRequest;
use App\Http\Requests\Pengaturan\StoreReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\StoreSettingArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateSettingArusKasRequest;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasPengecualian;
use App\Services\Pengaturan\PengaturanArusKasService;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengaturanArusKasController extends Controller
{
    public function __construct(
        private readonly PengaturanArusKasService $service,
        private readonly ReferensiKelompokArusKasService $referensiService,
    ) {}

    /**
     * Display cash flow settings for direct or indirect method based on active tab.
     */
    public function index(Request $request): View
    {
        $requestedMetode = (string) $request->query('metode', 'langsung');
        $activeMetode = in_array($requestedMetode, ['langsung', 'tidak_langsung'], true) ? $requestedMetode : 'langsung';

        // Lightweight calculation of reference counts
        $rawRefCounts = ReferensiKelompokArusKas::query()
            ->selectRaw('aktivitas, COUNT(*) as total')
            ->groupBy('aktivitas')
            ->pluck('total', 'aktivitas');

        $referensiTabCounts = [
            'semua' => (int) $rawRefCounts->sum(),
            'operasi' => (int) ($rawRefCounts['operasi'] ?? 0),
            'investasi' => (int) ($rawRefCounts['investasi'] ?? 0),
            'pendanaan' => (int) ($rawRefCounts['pendanaan'] ?? 0),
        ];

        // Conditional loading based on active method
        if ($activeMetode === 'langsung') {
            $langsungData = $this->buildLangsungViewData($request);

            $rawPosCounts = SettingArusKas::query()
                ->selectRaw('kelompok_bagian, COUNT(*) as total')
                ->groupBy('kelompok_bagian')
                ->pluck('total', 'kelompok_bagian');
            $pengecualianTotal = SettingArusKasPengecualian::count();

            $tidakLangsungTabCounts = [
                'semua' => (int) $rawPosCounts->sum(),
                'penyesuaian_laba' => (int) ($rawPosCounts['penyesuaian_laba'] ?? 0),
                'modal_kerja' => (int) ($rawPosCounts['modal_kerja'] ?? 0),
                'investasi' => (int) ($rawPosCounts['investasi'] ?? 0),
                'pendanaan' => (int) ($rawPosCounts['pendanaan'] ?? 0),
                'pengecualian' => (int) $pengecualianTotal,
            ];

            $posList = collect();
            $pengecualianList = collect();
            $unmappedAccounts = collect();
            $coaOptionsData = [
                'leafCoas' => collect(),
                'parentCoas' => collect(),
                'mappedByPos' => [],
                'excludedCoaIds' => [],
            ];
            $activeBagian = 'semua';
            $kelompokBagianLabels = SettingArusKas::KELOMPOK_BAGIAN_LABELS;
            $sifatPosLabels = [
                'aset' => 'Aset (Neraca)',
                'liabilitas' => 'Liabilitas (Neraca)',
                'ekuitas' => 'Ekuitas (Neraca)',
                'penambah_laba' => 'Penambah Laba (Non-Kas)',
                'pengurang_laba' => 'Pengurang Laba (Non-Kas)',
            ];

            $referensiList = $langsungData['referensiList'];
            $aktivitas = $langsungData['aktivitas'];
            $search = $langsungData['search'];
            $unmappedLangsungAccounts = $langsungData['unmappedLangsungAccounts'];
            $langsungCoaOptionsData = $langsungData['langsungCoaOptionsData'];
            $activeReferensiOptions = $langsungData['activeReferensiOptions'];
        } else {
            $tidakLangsungData = $this->buildTidakLangsungViewData($request);

            $posList = $tidakLangsungData['posList'];
            $pengecualianList = $tidakLangsungData['pengecualianList'];
            $unmappedAccounts = $tidakLangsungData['unmappedAccounts'];
            $coaOptionsData = $tidakLangsungData['coaOptionsData'];
            $kelompokBagianLabels = $tidakLangsungData['kelompokBagianLabels'];
            $sifatPosLabels = $tidakLangsungData['sifatPosLabels'];
            $activeBagian = $tidakLangsungData['activeBagian'];
            $search = $tidakLangsungData['search'];

            $tidakLangsungTabCounts = [
                'semua' => $posList->count(),
                'penyesuaian_laba' => $posList->where('kelompok_bagian', 'penyesuaian_laba')->count(),
                'modal_kerja' => $posList->where('kelompok_bagian', 'modal_kerja')->count(),
                'investasi' => $posList->where('kelompok_bagian', 'investasi')->count(),
                'pendanaan' => $posList->where('kelompok_bagian', 'pendanaan')->count(),
                'pengecualian' => $pengecualianList->count(),
            ];

            $referensiList = null;
            $aktivitas = 'semua';
            $unmappedLangsungAccounts = collect();
            $langsungCoaOptionsData = [
                'leafCoas' => collect(),
                'parentCoas' => collect(),
                'mappedByCoaId' => [],
            ];
            $activeReferensiOptions = collect();
        }

        $activeTab = $activeBagian;
        $tabCounts = $tidakLangsungTabCounts;

        return view('pengaturan.arus-kas.index', compact(
            'activeMetode',
            'aktivitas',
            'activeBagian',
            'activeTab',
            'search',
            'referensiTabCounts',
            'tidakLangsungTabCounts',
            'tabCounts',
            'referensiList',
            'posList',
            'pengecualianList',
            'unmappedAccounts',
            'coaOptionsData',
            'kelompokBagianLabels',
            'sifatPosLabels',
            'unmappedLangsungAccounts',
            'langsungCoaOptionsData',
            'activeReferensiOptions',
        ));
    }

    /**
     * Store a new reference group for direct cash flow method.
     */
    public function storeLangsung(StoreReferensiKelompokArusKasRequest $request): RedirectResponse
    {
        try {
            $this->referensiService->store($request->validated());
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'langsung'])
            ->with('success', 'Pos referensi kelompok arus kas berhasil ditambahkan.');
    }

    /**
     * Update an existing reference group for direct cash flow method.
     */
    public function updateLangsung(UpdateReferensiKelompokArusKasRequest $request, ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        try {
            $this->referensiService->update($referensi, $request->validated());
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'langsung'])
            ->with('success', 'Pos referensi kelompok arus kas berhasil diperbarui.');
    }

    /**
     * Toggle active/inactive status of a reference group for direct cash flow method.
     */
    public function toggleStatusLangsung(ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        $newStatus = $this->referensiService->toggleStatus($referensi);

        $message = $newStatus
            ? 'Pos referensi berhasil diaktifkan.'
            : 'Pos referensi berhasil dinonaktifkan.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Delete a reference group with restricted delete checks for direct cash flow method.
     */
    public function destroyLangsung(ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        $can = $this->referensiService->canDelete($referensi);

        if (! $can['allowed']) {
            return redirect()
                ->back()
                ->with('error', $can['reason'] ?? 'Pos referensi tidak dapat dihapus karena sedang digunakan oleh akun COA.');
        }

        $this->referensiService->delete($referensi);

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'langsung'])
            ->with('success', 'Pos referensi kelompok arus kas berhasil dihapus.');
    }

    /**
     * Expand descendant leaf COAs under a parent COA for direct cash flow mapping modal.
     */
    public function parentCoaLeavesLangsung(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['required', 'integer', 'min:1'],
            'ignore_referensi_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->referensiService->getLeafDescendantsByParent(
            (int) $validated['parent_id'],
            isset($validated['ignore_referensi_id']) ? (int) $validated['ignore_referensi_id'] : null,
        );

        return response()->json($result);
    }

    /**
     * Quickly map an unmapped COA directly to an active reference group.
     */
    public function quickMapLangsung(QuickMapReferensiKelompokArusKasRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->referensiService->quickMapCoa(
                (int) $validated['coa_id'],
                (int) $validated['referensi_id'],
            );
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'langsung'])
            ->with('success', "Akun '{$result['coa']->kode} - {$result['coa']->nama}' berhasil dipetakan ke kelompok '{$result['referensi']->nama}'.");
    }

    /**
     * Expand descendant leaf COAs for a selected parent COA, classified into eligible vs skipped.
     */
    public function parentCoaLeaves(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['required', 'integer', 'min:1'],
            'sifat_pos' => ['nullable', 'string', Rule::in(array_merge(SettingArusKas::SIFAT_POS, ['pengecualian']))],
            'ignore_pos_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->service->getLeafDescendantsByParent(
            (int) $validated['parent_id'],
            $validated['sifat_pos'] ?? null,
            isset($validated['ignore_pos_id']) ? (int) $validated['ignore_pos_id'] : null,
        );

        return response()->json($result);
    }

    /**
     * Store a new indirect cash flow line and its COA mappings.
     */
    public function store(StoreSettingArusKasRequest $request): RedirectResponse
    {
        try {
            $this->service->store($request->validated());
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung'])
            ->with('success', 'Pos arus kas berhasil ditambahkan.');
    }

    /**
     * Update an existing indirect cash flow line and synchronize its COA mappings.
     */
    public function update(UpdateSettingArusKasRequest $request, SettingArusKas $settingArusKas): RedirectResponse
    {
        try {
            $this->service->update($settingArusKas, $request->validated());
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung'])
            ->with('success', 'Pos arus kas berhasil diperbarui.');
    }

    /**
     * Toggle active/inactive status of an indirect cash flow line.
     */
    public function toggleStatus(SettingArusKas $settingArusKas): RedirectResponse
    {
        $isActive = $this->service->toggleStatus($settingArusKas);

        $message = $isActive
            ? 'Pos arus kas berhasil diaktifkan.'
            : 'Pos arus kas berhasil dinonaktifkan.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Reorder cash flow lines within a section (supports both JSON fetch and standard HTTP form requests).
     */
    public function reorder(ReorderSettingArusKasRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->service->reorder(
                (string) $validated['kelompok_bagian'],
                (array) $validated['ordered_ids'],
            );
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Urutan pos arus kas berhasil diperbarui.',
                'ordered_ids' => array_map('intval', (array) $validated['ordered_ids']),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Urutan pos arus kas berhasil diperbarui.');
    }

    /**
     * Delete a cash flow line and cascade its COA mappings.
     */
    public function destroy(SettingArusKas $settingArusKas): RedirectResponse
    {
        $this->service->delete($settingArusKas);

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung'])
            ->with('success', 'Pos arus kas berhasil dihapus.');
    }

    /**
     * Add one or more Neraca COAs into the indirect cash flow exclusion list.
     */
    public function storePengecualian(StorePengecualianArusKasRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->service->storePengecualian(
                (array) $validated['coa_ids'],
                $validated['alasan'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung', 'bagian' => 'pengecualian'])
            ->with('success', 'Akun pengecualian arus kas berhasil ditambahkan.');
    }

    /**
     * Remove a COA from the indirect cash flow exclusion list.
     */
    public function destroyPengecualian(SettingArusKasPengecualian $pengecualian): RedirectResponse
    {
        $this->service->deletePengecualian($pengecualian);

        return redirect()
            ->route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung', 'bagian' => 'pengecualian'])
            ->with('success', 'Akun pengecualian arus kas berhasil dihapus.');
    }

    /**
     * Build view data specific to the direct cash flow method.
     */
    private function buildLangsungViewData(Request $request): array
    {
        $requestedAktivitas = (string) $request->query('aktivitas', 'semua');
        $validAktivitas = ['operasi', 'investasi', 'pendanaan'];
        $aktivitas = in_array($requestedAktivitas, $validAktivitas, true) ? $requestedAktivitas : 'semua';
        $search = trim((string) $request->query('search', ''));

        $query = ReferensiKelompokArusKas::query();

        if ($aktivitas !== 'semua') {
            $query->where('aktivitas', $aktivitas);
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('nama', 'like', "%{$search}%");
                if (Schema::hasTable('coa') && Schema::hasColumns('coa', ['arus_kas_aktivitas', 'arus_kas_kelompok'])) {
                    $sub->orWhereExists(function ($coaQuery) use ($search) {
                        $coaQuery->select(DB::raw(1))
                            ->from('coa')
                            ->whereRaw('LOWER(coa.arus_kas_aktivitas) = LOWER(referensi_kelompok_arus_kas.aktivitas)')
                            ->whereRaw('LOWER(coa.arus_kas_kelompok) = LOWER(referensi_kelompok_arus_kas.nama)');

                        if (Schema::hasColumn('coa', 'parent_coa')) {
                            $coaQuery->whereNotExists(function ($childQuery) {
                                $childQuery->select(DB::raw(1))
                                    ->from('coa as child_coa')
                                    ->whereColumn('child_coa.parent_coa', 'coa.id');
                            });
                        }

                        $coaQuery->where(function ($matchFilter) use ($search) {
                            $matchFilter->where('coa.kode', 'like', "%{$search}%")
                                ->orWhere('coa.nama', 'like', "%{$search}%");
                        });
                    });
                }
            });
        }

        $referensiList = $query->orderBy('nama', 'asc')->paginate(25)->withQueryString();

        if ($referensiList->isNotEmpty() && Schema::hasTable('coa') && Schema::hasColumns('coa', ['arus_kas_aktivitas', 'arus_kas_kelompok'])) {
            $pairs = $referensiList->map(fn ($ref) => [
                'aktivitas' => (string) $ref->aktivitas,
                'nama' => (string) $ref->nama,
            ]);

            $parentIds = [];
            if (Schema::hasColumn('coa', 'parent_coa')) {
                $parentIds = Coa::query()
                    ->whereNotNull('parent_coa')
                    ->where('parent_coa', '>', 0)
                    ->pluck('parent_coa')
                    ->mapWithKeys(fn ($id) => [(int) $id => true])
                    ->all();
            }

            $coas = Coa::query()
                ->whereNotNull('arus_kas_kelompok')
                ->whereNotNull('arus_kas_aktivitas')
                ->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $q->orWhere(function ($sub) use ($pair) {
                            $sub->whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower($pair['aktivitas'])])
                                ->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower($pair['nama'])]);
                        });
                    }
                })
                ->orderBy('kode')
                ->get();

            $groupedCoas = $coas->groupBy(function ($coa) {
                return mb_strtolower((string) $coa->arus_kas_aktivitas).'||'.mb_strtolower((string) $coa->arus_kas_kelompok);
            });

            foreach ($referensiList as $ref) {
                $refKey = mb_strtolower((string) $ref->aktivitas).'||'.mb_strtolower((string) $ref->nama);
                $matchedCoas = $groupedCoas->get($refKey, collect());

                $leafCoas = $matchedCoas->filter(fn ($c) => ! isset($parentIds[(int) $c->id]))->values();
                $parentCoas = $matchedCoas->filter(fn ($c) => isset($parentIds[(int) $c->id]))->values();

                $ref->mapped_leaf_coas = $leafCoas;
                $ref->mapped_parent_coas = $parentCoas;
                $ref->coa_count = $leafCoas->count();
                $ref->parent_coa_count = $parentCoas->count();
            }
        } else {
            foreach ($referensiList as $ref) {
                $ref->mapped_leaf_coas = collect();
                $ref->mapped_parent_coas = collect();
                $ref->coa_count = 0;
                $ref->parent_coa_count = 0;
            }
        }

        $unmappedLangsungAccounts = $this->referensiService->getUnmappedCashCounterpartAccounts();
        $langsungCoaOptionsData = $this->referensiService->getCoaOptionsData();
        $activeReferensiOptions = Schema::hasTable('referensi_kelompok_arus_kas')
            ? ReferensiKelompokArusKas::aktif()->orderBy('aktivitas')->orderBy('nama')->get(['id', 'aktivitas', 'nama'])
            : collect();

        return compact(
            'referensiList',
            'aktivitas',
            'search',
            'unmappedLangsungAccounts',
            'langsungCoaOptionsData',
            'activeReferensiOptions',
        );
    }

    /**
     * Build view data specific to the indirect cash flow method.
     */
    private function buildTidakLangsungViewData(Request $request): array
    {
        $validBagian = array_merge(['semua'], SettingArusKas::KELOMPOK_BAGIAN, ['pengecualian']);
        $requestedBagian = (string) $request->query('bagian', $request->query('tab', 'semua'));
        $activeBagian = in_array($requestedBagian, $validBagian, true) ? $requestedBagian : 'semua';
        $search = trim((string) $request->query('search', ''));

        $posList = SettingArusKas::query()
            ->with(['coas' => fn ($query) => $query->orderBy('kode')])
            ->urut()
            ->get();

        $pengecualianList = SettingArusKasPengecualian::query()
            ->with('coa')
            ->orderBy('id')
            ->get();

        $kelompokBagianLabels = SettingArusKas::KELOMPOK_BAGIAN_LABELS;
        $sifatPosLabels = [
            'aset' => 'Aset (Neraca)',
            'liabilitas' => 'Liabilitas (Neraca)',
            'ekuitas' => 'Ekuitas (Neraca)',
            'penambah_laba' => 'Penambah Laba (Non-Kas)',
            'pengurang_laba' => 'Pengurang Laba (Non-Kas)',
        ];

        $unmappedAccounts = $this->service->getUnmappedMutatedNeracaAccounts();
        $coaOptionsData = $this->service->getCoaOptionsData();

        return compact(
            'posList',
            'pengecualianList',
            'unmappedAccounts',
            'coaOptionsData',
            'kelompokBagianLabels',
            'sifatPosLabels',
            'activeBagian',
            'search',
        );
    }
}
