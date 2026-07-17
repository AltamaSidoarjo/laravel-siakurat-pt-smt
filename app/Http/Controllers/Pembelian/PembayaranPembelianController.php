<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Requests\Pembelian\StorePembayaranPembelianRequest;
use App\Http\Requests\Pembelian\UpdatePembayaranPembelianRequest;
use App\Models\PembayaranPembelian;
use App\Services\Pembelian\PembayaranPembelianService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class PembayaranPembelianController extends Controller
{
    use StreamsCsvExport;
    public function __construct(
        private readonly PembayaranPembelianService $pembayaranPembelianService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('pembelian.pembayaran.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->pembayaranPembelianService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (PembayaranPembelian $item) => optional($item->tanggal)->format('Y-m-d'))
            ->addColumn('supplier_display', fn (PembayaranPembelian $item) => trim(($item->supplier?->kode_supplier ?? '').' - '.($item->supplier?->nama_supplier ?? '')))
            ->addColumn('akun_bank_display', fn (PembayaranPembelian $item) => trim(($item->akunBank?->kode ?? '').' - '.($item->akunBank?->nama ?? '')))
            ->addColumn('total_bayar_display', fn (PembayaranPembelian $item) => number_format((float) $item->total_bayar, 0, ',', '.'))
            ->addColumn('potongan_admin_display', fn (PembayaranPembelian $item) => number_format((float) $item->potongan_admin, 0, ',', '.'))
            ->addColumn('nomer_link', fn (PembayaranPembelian $item) => route('pembelian.pembayaran.edit', $item))
            ->addColumn('print_link', fn (PembayaranPembelian $item) => route('pembelian.pembayaran.print', $item))
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate(['startDate' => ['required', 'date_format:Y-m-d'], 'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate']]);
        return $this->streamCsvExport($request, $this->pembayaranPembelianService->getIndexQuery($data['startDate'], $data['endDate']), 'pembayaran-pembelian', ['Nomor', 'Tanggal', 'Supplier', 'Akun Bank', 'Jumlah Pembayaran', 'Potongan Admin', 'Keterangan'], fn (PembayaranPembelian $item) => [(string) $item->nomer_pembayaran, optional($item->tanggal)->format('Y-m-d'), trim(($item->supplier?->kode_supplier ?? '').' - '.($item->supplier?->nama_supplier ?? '')), trim(($item->akunBank?->kode ?? '').' - '.($item->akunBank?->nama ?? '')), $this->csvNumber($item->total_bayar), $this->csvNumber($item->potongan_admin), (string) $item->keterangan]);
    }

    public function create(): View
    {
        return view('pembelian.pembayaran.create', [
            'page' => 'app',
            'coaOptions' => $this->pembayaranPembelianService->getCoaOptions(),
            'supplierOptions' => $this->pembayaranPembelianService->getSupplierOptions(),
        ]);
    }

    public function store(StorePembayaranPembelianRequest $request): RedirectResponse
    {
        $actor = auth()->user()?->name ?? auth()->user()?->email ?? 'system';
        $pembayaranPembelian = DB::transaction(fn () => $this->pembayaranPembelianService->create($request->validated(), $actor));

        if ($request->input('submit_action') === 'save-print') {
            return redirect()->route('pembelian.pembayaran.print', $pembayaranPembelian);
        }

        return redirect()
            ->route('pembelian.pembayaran.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$pembayaranPembelian->nomer_pembayaran);
    }

    public function edit(PembayaranPembelian $pembayaranPembelian): View
    {
        return view('pembelian.pembayaran.edit', [
            'page' => 'app',
            'pembayaranPembelian' => $pembayaranPembelian->load(['supplier', 'akunBank', 'akunHutang', 'akunPotonganAdmin', 'rincian.fakturPembelian']),
            'coaOptions' => $this->pembayaranPembelianService->getCoaOptions(),
        ]);
    }

    public function update(UpdatePembayaranPembelianRequest $request, PembayaranPembelian $pembayaranPembelian): RedirectResponse
    {
        $actor = auth()->user()?->name ?? auth()->user()?->email ?? 'system';
        $updated = DB::transaction(fn () => $this->pembayaranPembelianService->update($pembayaranPembelian, $request->validated(), $actor));

        if ($request->input('submit_action') === 'save-print') {
            return redirect()->route('pembelian.pembayaran.print', $updated);
        }

        return redirect()
            ->route('pembelian.pembayaran.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer_pembayaran);
    }

    public function destroy(PembayaranPembelian $pembayaranPembelian): RedirectResponse
    {
        $nomer = $pembayaranPembelian->nomer_pembayaran;

        DB::transaction(fn () => $this->pembayaranPembelianService->delete($pembayaranPembelian));

        return redirect()
            ->route('pembelian.pembayaran.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(PembayaranPembelian $pembayaranPembelian): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('pembelian.pembayaran.print', [
            'page' => 'app',
            'pembayaranPembelian' => $pembayaranPembelian->load(['supplier', 'akunBank', 'akunHutang', 'akunPotonganAdmin', 'rincian.fakturPembelian']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }

    public function apiGetInvBySupplier(Request $request): JsonResponse
    {
        $supplierId = (int) $request->integer('id');
        $supplier = $this->pembayaranPembelianService->getSupplierOptions()->firstWhere('id', $supplierId);

        if (! $supplier) {
            abort(404);
        }

        $fakturs = $this->pembayaranPembelianService->getAvailableInvoicesBySupplier($supplierId);

        return response()->json([
            'data' => [
                'supplier' => [
                    'id' => $supplier->id,
                    'nama_supplier' => $supplier->nama_supplier,
                    'kode_supplier' => $supplier->kode_supplier,
                    'faktur_pembelians' => $fakturs->map(fn ($item) => [
                        'id' => $item->id,
                        'nomer_faktur' => $item->nomer_faktur,
                        'tanggal_faktur' => optional($item->tanggal_faktur)->format('Y-m-d'),
                        'grandtotal' => (float) $item->grandtotal,
                        'sudah_terbayar' => (float) $item->sudah_terbayar,
                    ])->values(),
                ],
            ],
        ]);
    }
}
