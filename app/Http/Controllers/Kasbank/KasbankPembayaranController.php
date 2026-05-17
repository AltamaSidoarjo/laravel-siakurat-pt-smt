<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\StoreKasbankPembayaranRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPembayaranRequest;
use App\Models\KasbankPembayaran;
use App\Services\Kasbank\KasbankPembayaranService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class KasbankPembayaranController extends Controller
{
    public function __construct(
        private readonly KasbankPembayaranService $kasbankPembayaranService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('kasbank.pembayaran.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->kasbankPembayaranService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (KasbankPembayaran $kasbankPembayaran) => optional($kasbankPembayaran->tanggal)->format('Y-m-d'))
            ->editColumn('total', fn (KasbankPembayaran $kasbankPembayaran) => number_format((float) $kasbankPembayaran->total, 0, ',', '.'))
            ->addColumn('coa_display', fn (KasbankPembayaran $kasbankPembayaran) => $kasbankPembayaran->coa?->nama ?: '-')
            ->addColumn('nomer_link', fn (KasbankPembayaran $kasbankPembayaran) => route('kasbank.pembayaran.edit', $kasbankPembayaran))
            ->toJson();
    }

    public function create(): View
    {
        return view('kasbank.pembayaran.create', [
            'page' => 'app',
            'coaOptions' => $this->kasbankPembayaranService->getCoaOptions(),
        ]);
    }

    public function store(StoreKasbankPembayaranRequest $request): RedirectResponse
    {
        $kasbankPembayaran = DB::transaction(fn () => $this->kasbankPembayaranService->create($request->validated()));

        if ($request->input('action') === 'save_print') {
            return redirect()->route('kasbank.pembayaran.print', $kasbankPembayaran);
        }

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$kasbankPembayaran->nomer);
    }

    public function edit(KasbankPembayaran $kasbankPembayaran): View
    {
        return view('kasbank.pembayaran.edit', [
            'page' => 'app',
            'kasbankPembayaran' => $kasbankPembayaran->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->kasbankPembayaranService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPembayaranRequest $request, KasbankPembayaran $kasbankPembayaran): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->kasbankPembayaranService->update($kasbankPembayaran, $request->validated()));

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(KasbankPembayaran $kasbankPembayaran): RedirectResponse
    {
        $nomer = $kasbankPembayaran->nomer;

        DB::transaction(fn () => $this->kasbankPembayaranService->delete($kasbankPembayaran));

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(KasbankPembayaran $kasbankPembayaran): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('kasbank.pembayaran.print', [
            'page' => 'app',
            'kasbankPembayaran' => $kasbankPembayaran->load(['coa', 'rincian.coa']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => session('auth.preview_user.username', '(Nama Petugas)'),
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }
}
