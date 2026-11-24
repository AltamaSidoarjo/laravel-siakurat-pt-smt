<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateReferensiKelompokArusKasRequest;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReferensiKelompokArusKasController extends Controller
{
    public function __construct(
        private readonly ReferensiKelompokArusKasService $service,
    ) {}

    /**
     * Display a listing of reference groups with filters and eager COA usage counts.
     */
    public function index(Request $request): View
    {
        $aktivitas = $request->query('aktivitas', 'semua');
        $search = trim((string) $request->query('search', ''));

        $query = ReferensiKelompokArusKas::query();

        if ($aktivitas && in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true)) {
            $query->where('aktivitas', $aktivitas);
        }

        if ($search !== '') {
            $query->where('nama', 'like', "%{$search}%");
        }

        $referensiList = $query->orderBy('nama', 'asc')->paginate(25)->withQueryString();

        // Eager aggregate COA count per (aktivitas, kelompok) to prevent N+1 queries
        $coaCounts = Coa::query()
            ->whereNotNull('arus_kas_kelompok')
            ->selectRaw('LOWER(arus_kas_aktivitas) as act, LOWER(arus_kas_kelompok) as grp, COUNT(*) as aggregate_count')
            ->groupBy(DB::raw('LOWER(arus_kas_aktivitas)'), DB::raw('LOWER(arus_kas_kelompok)'))
            ->get()
            ->keyBy(fn ($item) => $item->act.'||'.$item->grp);

        foreach ($referensiList as $ref) {
            $key = strtolower($ref->aktivitas).'||'.strtolower($ref->nama);
            $ref->coa_count = (int) ($coaCounts->get($key)?->aggregate_count ?? 0);
        }

        $tabCounts = [
            'semua' => ReferensiKelompokArusKas::count(),
            'operasi' => ReferensiKelompokArusKas::where('aktivitas', 'operasi')->count(),
            'investasi' => ReferensiKelompokArusKas::where('aktivitas', 'investasi')->count(),
            'pendanaan' => ReferensiKelompokArusKas::where('aktivitas', 'pendanaan')->count(),
        ];

        return view('pengaturan.referensi-kelompok-arus-kas.index', compact(
            'referensiList',
            'aktivitas',
            'search',
            'tabCounts',
        ));
    }

    /**
     * Store a new reference group.
     */
    public function store(StoreReferensiKelompokArusKasRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()
            ->route('pengaturan.referensi-kelompok-arus-kas.index')
            ->with('success', 'Pos referensi kelompok arus kas berhasil ditambahkan.');
    }

    /**
     * Update an existing reference group.
     */
    public function update(UpdateReferensiKelompokArusKasRequest $request, ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        $this->service->update($referensi, $request->validated());

        return redirect()
            ->route('pengaturan.referensi-kelompok-arus-kas.index')
            ->with('success', 'Pos referensi kelompok arus kas berhasil diperbarui.');
    }

    /**
     * Delete a reference group with restricted delete checks.
     */
    public function destroy(ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        $can = $this->service->canDelete($referensi);

        if (! $can['allowed']) {
            return redirect()
                ->back()
                ->with('error', $can['reason'] ?? 'Pos referensi tidak dapat dihapus karena sedang digunakan oleh akun COA.');
        }

        $this->service->delete($referensi);

        return redirect()
            ->route('pengaturan.referensi-kelompok-arus-kas.index')
            ->with('success', 'Pos referensi kelompok arus kas berhasil dihapus.');
    }

    /**
     * Toggle active/inactive status of a reference group.
     */
    public function toggleStatus(ReferensiKelompokArusKas $referensi): RedirectResponse
    {
        $newStatus = $this->service->toggleStatus($referensi);

        $message = $newStatus
            ? 'Pos referensi berhasil diaktifkan.'
            : 'Pos referensi berhasil dinonaktifkan.';

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
