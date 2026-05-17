<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\StoreKasbankPenerimaanRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPenerimaanRequest;
use App\Models\KasbankPenerimaan;
use App\Services\Kasbank\KasbankPenerimaanService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class KasbankPenerimaanController extends Controller
{
    public function __construct(
        private readonly KasbankPenerimaanService $kasbankPenerimaanService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('kasbank.penerimaan.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->kasbankPenerimaanService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (KasbankPenerimaan $kasbankPenerimaan) => optional($kasbankPenerimaan->tanggal)->format('Y-m-d'))
            ->editColumn('total', fn (KasbankPenerimaan $kasbankPenerimaan) => number_format((float) $kasbankPenerimaan->total, 0, ',', '.'))
            ->addColumn('coa_display', fn (KasbankPenerimaan $kasbankPenerimaan) => $kasbankPenerimaan->coa?->nama ?: '-')
            ->addColumn('nomer_link', fn (KasbankPenerimaan $kasbankPenerimaan) => route('kasbank.penerimaan.edit', $kasbankPenerimaan))
            ->toJson();
    }

    public function create(): View
    {
        return view('kasbank.penerimaan.create', [
            'page' => 'app',
            'coaOptions' => $this->kasbankPenerimaanService->getCoaOptions(),
        ]);
    }

    public function store(StoreKasbankPenerimaanRequest $request): RedirectResponse
    {
        $kasbankPenerimaan = DB::transaction(fn () => $this->kasbankPenerimaanService->create($request->validated()));

        if ($request->input('action') === 'save_print') {
            return redirect()->route('kasbank.penerimaan.print', $kasbankPenerimaan);
        }

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$kasbankPenerimaan->nomer);
    }

    public function edit(KasbankPenerimaan $kasbankPenerimaan): View
    {
        return view('kasbank.penerimaan.edit', [
            'page' => 'app',
            'kasbankPenerimaan' => $kasbankPenerimaan->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->kasbankPenerimaanService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPenerimaanRequest $request, KasbankPenerimaan $kasbankPenerimaan): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->kasbankPenerimaanService->update($kasbankPenerimaan, $request->validated()));

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(KasbankPenerimaan $kasbankPenerimaan): RedirectResponse
    {
        $nomer = $kasbankPenerimaan->nomer;

        DB::transaction(fn () => $this->kasbankPenerimaanService->delete($kasbankPenerimaan));

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(KasbankPenerimaan $kasbankPenerimaan): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('kasbank.penerimaan.print', [
            'page' => 'app',
            'kasbankPenerimaan' => $kasbankPenerimaan->load(['coa', 'rincian.coa']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => session('auth.preview_user.username', '(Nama Petugas)'),
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }
}
