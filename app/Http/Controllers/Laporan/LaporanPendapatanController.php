<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Services\Laporan\LaporanPendapatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class LaporanPendapatanController extends Controller
{
    public function __construct(
        private readonly LaporanPendapatanService $laporanPendapatanService,
    ) {
    }

    public function index(): View
    {
        return view('laporan.pendapatan.index', [
            'page' => 'app',
        ]);
    }

    public function kunjungan(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.pendapatan.kunjungan', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'poli' => $request->string('poli')->trim()->toString(),
            'penjamin' => $request->string('penjamin')->trim()->toString(),
        ]);
    }

    public function loadKunjungan(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $poli = $request->string('poli')->trim()->toString();
        $penjamin = $request->string('penjamin')->trim()->toString();

        $query = $this->laporanPendapatanService->getQueryKunjungan(
            startDate: $startDate,
            endDate: $endDate,
            poli: $poli,
            penjamin: $penjamin,
        );

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request) {
                $search = trim((string) $request->input('search.value', ''));
                if ($search === '') {
                    return;
                }

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('nomer_billing', 'like', "%{$search}%")
                        ->orWhere('nama_pasien', 'like', "%{$search}%")
                        ->orWhere('status_layanan', 'like', "%{$search}%")
                        ->orWhere('dokter', 'like', "%{$search}%")
                        ->orWhere('poli', 'like', "%{$search}%")
                        ->orWhere('penjamin', 'like', "%{$search}%")
                        ->orWhereRaw('CAST(total_tagihan AS CHAR) like ?', ["%{$search}%"]);
                });
            })
            ->editColumn('tanggal_reg', fn ($row) => optional($row->tanggal_reg)->format('Y-m-d'))
            ->editColumn('total_tagihan', fn ($row) => number_format((float) $row->total_tagihan, 0, ',', '.'))
            ->toJson();
    }

    public function penjualanObat(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.pendapatan.penjualan-obat', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadPenjualanObat(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->laporanPendapatanService->getQueryPenjualanObat(
            startDate: $startDate,
            endDate: $endDate,
        );

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request) {
                $search = trim((string) $request->input('search.value', ''));
                if ($search === '') {
                    return;
                }

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('nomer_transaksi', 'like', "%{$search}%")
                        ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                        ->orWhere('jenis_jual', 'like', "%{$search}%")
                        ->orWhere('kode_gudang', 'like', "%{$search}%")
                        ->orWhere('nama_rekening', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereRaw('CAST(ongkir AS CHAR) like ?', ["%{$search}%"])
                        ->orWhereRaw('CAST(ppn AS CHAR) like ?', ["%{$search}%"])
                        ->orWhereRaw('CAST(grandtotal AS CHAR) like ?', ["%{$search}%"]);
                });
            })
            ->editColumn('tanggal', fn ($row) => optional($row->tanggal)->format('Y-m-d'))
            ->editColumn('ongkir', fn ($row) => number_format((float) $row->ongkir, 0, ',', '.'))
            ->editColumn('ppn', fn ($row) => number_format((float) $row->ppn, 0, ',', '.'))
            ->editColumn('grandtotal', fn ($row) => number_format((float) $row->grandtotal, 0, ',', '.'))
            ->toJson();
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }
}
