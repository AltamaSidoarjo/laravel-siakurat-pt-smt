<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\ReorderSettingArusKasRequest;
use App\Http\Requests\Pengaturan\StorePengecualianArusKasRequest;
use App\Http\Requests\Pengaturan\StoreSettingArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateSettingArusKasRequest;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasPengecualian;
use App\Services\Pengaturan\PengaturanArusKasService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengaturanArusKasController extends Controller
{
    public function __construct(
        private readonly PengaturanArusKasService $service,
    ) {
    }

    /**
     * Display indirect cash flow line settings, COA mappings, exclusions, and unmapped Neraca alerts.
     */
    public function index(Request $request): View
    {
        $validTabs = array_merge(['semua'], SettingArusKas::KELOMPOK_BAGIAN, ['pengecualian']);
        $requestedTab = (string) $request->query('tab', 'semua');
        $activeTab = in_array($requestedTab, $validTabs, true) ? $requestedTab : 'semua';
        $search = trim((string) $request->query('search', ''));

        $posList = SettingArusKas::query()
            ->with(['coas' => fn ($query) => $query->orderBy('kode')])
            ->urut()
            ->get();

        $pengecualianList = SettingArusKasPengecualian::query()
            ->with('coa')
            ->orderBy('id')
            ->get();

        $tabCounts = [
            'semua' => $posList->count(),
            'penyesuaian_laba' => $posList->where('kelompok_bagian', 'penyesuaian_laba')->count(),
            'modal_kerja' => $posList->where('kelompok_bagian', 'modal_kerja')->count(),
            'investasi' => $posList->where('kelompok_bagian', 'investasi')->count(),
            'pendanaan' => $posList->where('kelompok_bagian', 'pendanaan')->count(),
            'pengecualian' => $pengecualianList->count(),
        ];

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

        return view('pengaturan.arus-kas.index', compact(
            'posList',
            'pengecualianList',
            'unmappedAccounts',
            'coaOptionsData',
            'kelompokBagianLabels',
            'sifatPosLabels',
            'tabCounts',
            'activeTab',
            'search',
        ));
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
            ->route('pengaturan.arus-kas.index')
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
            ->route('pengaturan.arus-kas.index')
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
            ->route('pengaturan.arus-kas.index')
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
            ->route('pengaturan.arus-kas.index')
            ->with('success', 'Akun pengecualian arus kas berhasil ditambahkan.');
    }

    /**
     * Remove a COA from the indirect cash flow exclusion list.
     */
    public function destroyPengecualian(SettingArusKasPengecualian $pengecualian): RedirectResponse
    {
        $this->service->deletePengecualian($pengecualian);

        return redirect()
            ->route('pengaturan.arus-kas.index')
            ->with('success', 'Akun pengecualian arus kas berhasil dihapus.');
    }
}
