<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
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

        $baseQuery = $this->laporanPendapatanService->getQueryKunjungan(
            startDate: $startDate,
            endDate: $endDate,
            poli: $poli,
            penjamin: $penjamin,
        );

        $grandTotalQuery = clone $baseQuery;
        $this->applyKunjunganDataTableSearch($grandTotalQuery, $request);

        return DataTables::eloquent($baseQuery)
            ->filter(function (Builder $query) use ($request) {
                $this->applyKunjunganDataTableSearch($query, $request);
            }, false)
            ->editColumn('tanggal_reg', fn ($row) => optional($row->tanggal_reg)->format('Y-m-d'))
            ->editColumn('total_tagihan', fn ($row) => number_format((float) $row->total_tagihan, 0, ',', '.'))
            ->with('grandTotal', fn () => (float) $grandTotalQuery->sum('total_tagihan'))
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

        $baseQuery = $this->laporanPendapatanService->getQueryPenjualanObat(
            startDate: $startDate,
            endDate: $endDate,
        );

        $grandTotalQuery = clone $baseQuery;
        $this->applyPenjualanObatDataTableSearch($grandTotalQuery, $request);

        return DataTables::eloquent($baseQuery)
            ->filter(function (Builder $query) use ($request) {
                $this->applyPenjualanObatDataTableSearch($query, $request);
            }, false)
            ->editColumn('tanggal', fn ($row) => optional($row->tanggal)->format('Y-m-d'))
            ->editColumn('ongkir', fn ($row) => number_format((float) $row->ongkir, 0, ',', '.'))
            ->editColumn('ppn', fn ($row) => number_format((float) $row->ppn, 0, ',', '.'))
            ->editColumn('grandtotal', fn ($row) => number_format((float) $row->grandtotal, 0, ',', '.'))
            ->with('grandTotal', fn () => (float) $grandTotalQuery->sum('grandtotal'))
            ->toJson();
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }

    private function applyKunjunganDataTableSearch(Builder $query, Request $request): void
    {
        $searchValue = trim((string) $request->input('search.value', ''));

        if ($searchValue === '') {
            return;
        }

        $likeSearch = '%'.$searchValue.'%';

        $query->where(function (Builder $searchQuery) use ($likeSearch) {
            $searchQuery
                ->where('nomer_billing', 'like', $likeSearch)
                ->orWhere('nama_pasien', 'like', $likeSearch)
                ->orWhere('status_layanan', 'like', $likeSearch)
                ->orWhere('dokter', 'like', $likeSearch)
                ->orWhere('poli', 'like', $likeSearch)
                ->orWhere('penjamin', 'like', $likeSearch)
                ->orWhereRaw('CAST(total_tagihan AS CHAR) like ?', [$likeSearch]);
        });
    }

    private function applyPenjualanObatDataTableSearch(Builder $query, Request $request): void
    {
        $searchValue = trim((string) $request->input('search.value', ''));

        if ($searchValue === '') {
            return;
        }

        $likeSearch = '%'.$searchValue.'%';

        $query->where(function (Builder $searchQuery) use ($likeSearch) {
            $searchQuery
                ->where('nomer_transaksi', 'like', $likeSearch)
                ->orWhere('nama_pelanggan', 'like', $likeSearch)
                ->orWhere('jenis_jual', 'like', $likeSearch)
                ->orWhere('kode_gudang', 'like', $likeSearch)
                ->orWhere('nama_rekening', 'like', $likeSearch)
                ->orWhere('keterangan', 'like', $likeSearch)
                ->orWhereRaw('CAST(ongkir AS CHAR) like ?', [$likeSearch])
                ->orWhereRaw('CAST(ppn AS CHAR) like ?', [$likeSearch])
                ->orWhereRaw('CAST(grandtotal AS CHAR) like ?', [$likeSearch]);
        });
    }
}
