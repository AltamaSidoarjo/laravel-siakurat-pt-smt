<?php

namespace App\Http\Controllers\Pendapatan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Models\FakturPenjualan;
use App\Services\Pendapatan\InvoicePendapatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class InvoicePendapatanController extends Controller
{
    use StreamsCsvExport;
    public function __construct(
        private readonly InvoicePendapatanService $invoicePendapatanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('pendapatan.invoice.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->invoicePendapatanService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal_faktur', fn (FakturPenjualan $fakturPenjualan) => optional($fakturPenjualan->tanggal_faktur)->format('Y-m-d'))
            ->addColumn('nomer_link', fn (FakturPenjualan $fakturPenjualan) => route('pendapatan.invoice.read', $fakturPenjualan))
            ->addColumn('nominal', fn (FakturPenjualan $fakturPenjualan) => number_format((float) $fakturPenjualan->grandtotal, 0, ',', '.'))
            ->addColumn('sudah_bayar', fn (FakturPenjualan $fakturPenjualan) => number_format((float) $fakturPenjualan->sudah_terbayar, 0, ',', '.'))
            ->addColumn('kurang_bayar', fn (FakturPenjualan $fakturPenjualan) => number_format((float) ($fakturPenjualan->grandtotal - $fakturPenjualan->sudah_terbayar), 0, ',', '.'))
            ->addColumn('status_text', function (FakturPenjualan $fakturPenjualan) {
                return (float) $fakturPenjualan->sudah_terbayar >= (float) $fakturPenjualan->grandtotal
                    ? 'Sudah Lunas'
                    : 'Belum Lunas';
            })
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate(['startDate' => ['required', 'date_format:Y-m-d'], 'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate']]);
        return $this->streamCsvExport($request, $this->invoicePendapatanService->getIndexQuery($data['startDate'], $data['endDate']), 'invoice-pendapatan', ['Nomor', 'Tanggal', 'Dokter', 'No. RM', 'Pasien', 'Poli', 'Penjamin', 'Nominal', 'Sudah bayar', 'Kurang bayar', 'Status'], fn (FakturPenjualan $item) => [(string) $item->nomor_faktur, optional($item->tanggal_faktur)->format('Y-m-d'), (string) $item->dokter, (string) $item->nomer_rekam_medis, (string) $item->nama_pasien, (string) $item->poli, (string) $item->penjamin, $this->csvNumber($item->grandtotal), $this->csvNumber($item->sudah_terbayar), $this->csvNumber($item->grandtotal - $item->sudah_terbayar), (float) $item->sudah_terbayar >= (float) $item->grandtotal ? 'Sudah Lunas' : 'Belum Lunas']);
    }

    public function read(FakturPenjualan $fakturPenjualan): View
    {
        return view('pendapatan.invoice.read', [
            'page' => 'app',
            'invoicePendapatan' => $fakturPenjualan->load('rincian'),
        ]);
    }
}
