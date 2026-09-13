<?php

namespace App\Http\Controllers\Laporan;

use App\Exceptions\BillingApiException;
use App\Http\Controllers\Controller;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Laporan\LaporanPendapatanService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class LaporanPendapatanController extends Controller
{
    public function __construct(
        private readonly LaporanPendapatanService $laporanPendapatanService,
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
    ) {}

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

    public function dokter(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $poliOptions = collect();
        $penjaminOptions = collect();
        $apiOptionsError = null;

        try {
            $poliOptions = $this->billingPendapatanApiService->getSpesialisOptions();
            $penjaminOptions = $this->billingPendapatanApiService->getPenjaminOptions();
        } catch (BillingApiException $exception) {
            $apiOptionsError = $exception->getMessage();
        }

        return view('laporan.pendapatan.dokter', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'pelaksanaId' => $request->integer('pelaksanaId') ?: null,
            'poli' => $request->string('poli')->trim()->toString(),
            'penjamin' => $request->string('penjamin')->trim()->toString(),
            'pelaksanaOptions' => $this->laporanPendapatanService->getPelaksanaOptions(),
            'poliOptions' => $poliOptions,
            'penjaminOptions' => $penjaminOptions,
            'apiOptionsError' => $apiOptionsError,
        ]);
    }

    public function loadDokter(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $pelaksanaId = $request->integer('pelaksanaId') ?: null;
        $poli = $request->string('poli')->trim()->toString();
        $penjamin = $request->string('penjamin')->trim()->toString();
        $search = trim((string) $request->input('search.value', ''));

        $baseQuery = $this->laporanPendapatanService->getQueryPendapatanDokter(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $summary = $this->laporanPendapatanService->getPendapatanDokterSummary(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
            $search,
        );

        return DataTables::query($baseQuery)
            ->filter(function (QueryBuilder $query) use ($search): void {
                $this->laporanPendapatanService->applyPendapatanDokterSearch($query, $search);
            }, false)
            ->editColumn('tanggal', fn (object $row) => Carbon::parse($row->tanggal)->format('Y-m-d'))
            ->editColumn('jumlah_billing', fn (object $row) => (int) $row->jumlah_billing)
            ->editColumn('total_pendapatan', fn (object $row) => number_format((float) $row->total_pendapatan, 0, ',', '.'))
            ->with('totalBilling', $summary['totalBilling'])
            ->with('grandTotal', $summary['grandTotal'])
            ->toJson();
    }

    public function exportDokterPdf(Request $request): Response
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'pelaksanaId' => ['nullable', 'integer', 'exists:pelaksana,id'],
            'poli' => ['nullable', 'string', 'max:255'],
            'penjamin' => ['nullable', 'string', 'max:255'],
        ]);

        $fileName = sprintf(
            'pendapatan-dokter-%s-%s.pdf',
            str_replace('-', '', $validated['startDate']),
            str_replace('-', '', $validated['endDate'])
        );

        $pdf = $this->laporanPendapatanService->renderPendapatanDokterPdf(
            $validated['startDate'],
            $validated['endDate'],
            isset($validated['pelaksanaId']) ? (int) $validated['pelaksanaId'] : null,
            trim((string) ($validated['poli'] ?? '')),
            trim((string) ($validated['penjamin'] ?? '')),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
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

    public function exportKunjunganCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $fileName = sprintf(
            'pendapatan-kunjungan-%s-%s.csv',
            str_replace('-', '', $validated['startDate']),
            str_replace('-', '', $validated['endDate'])
        );

        return response()->streamDownload(
            fn () => $this->laporanPendapatanService->streamKunjunganCsv(
                startDate: $validated['startDate'],
                endDate: $validated['endDate'],
            ),
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
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

    public function exportPenjualanObatCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $fileName = sprintf(
            'pendapatan-penjualan-obat-%s-%s.csv',
            str_replace('-', '', $validated['startDate']),
            str_replace('-', '', $validated['endDate'])
        );

        return response()->streamDownload(
            fn () => $this->laporanPendapatanService->streamPenjualanObatCsv(
                startDate: $validated['startDate'],
                endDate: $validated['endDate'],
            ),
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
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
