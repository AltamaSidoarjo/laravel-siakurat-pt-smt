<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Models\FakturPembelian;
use App\Services\Pembelian\InvoicePembelianService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InvoicePembelianController extends Controller
{
    public function __construct(
        private readonly InvoicePembelianService $invoicePembelianService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('pembelian.invoice.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->invoicePembelianService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal_faktur', fn (FakturPembelian $fakturPembelian) => optional($fakturPembelian->tanggal_faktur)->format('Y-m-d'))
            ->editColumn('tanggal_jatuh_tempo', fn (FakturPembelian $fakturPembelian) => optional($fakturPembelian->tanggal_jatuh_tempo)->format('Y-m-d'))
            ->addColumn('nomer_link', fn (FakturPembelian $fakturPembelian) => route('pembelian.invoice.read', $fakturPembelian))
            ->addColumn('nama_supplier', fn (FakturPembelian $fakturPembelian) => $fakturPembelian->supplier?->nama_supplier)
            ->addColumn('grandtotal_display', fn (FakturPembelian $fakturPembelian) => number_format((float) $fakturPembelian->grandtotal, 0, ',', '.'))
            ->addColumn('sudah_terbayar_display', fn (FakturPembelian $fakturPembelian) => number_format((float) $fakturPembelian->sudah_terbayar, 0, ',', '.'))
            ->addColumn('status_text', function (FakturPembelian $fakturPembelian) {
                return (int) $fakturPembelian->sudah_terbayar >= (int) $fakturPembelian->grandtotal
                    ? 'Sudah Lunas'
                    : 'Belum Lunas';
            })
            ->toJson();
    }

    public function read(FakturPembelian $fakturPembelian): View
    {
        return view('pembelian.invoice.read', [
            'page' => 'app',
            'invoicePembelian' => $fakturPembelian->load(['supplier', 'rincian']),
        ]);
    }

    public function print(FakturPembelian $fakturPembelian): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('pembelian.invoice.print', [
            'page' => 'app',
            'invoicePembelian' => $fakturPembelian->load(['supplier', 'rincian']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
        ]);
    }
}
