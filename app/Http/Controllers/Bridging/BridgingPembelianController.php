<?php

namespace App\Http\Controllers\Bridging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bridging\BulkDeleteBridgingPembelianRequest;
use App\Http\Requests\Bridging\ImportPembelianNonMedisRequest;
use App\Http\Requests\Bridging\ImportPembelianObatRequest;
use App\Models\FakturPembelian;
use App\Services\Bridging\BridgingPembelianService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BridgingPembelianController extends Controller
{
    public function __construct(
        private readonly BridgingPembelianService $bridgingPembelianService,
    ) {
    }

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pembelian.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'results' => session('bridging_pembelian_results', []),
            'message' => session('bridging_pembelian_message'),
        ]);
    }

    public function loadImportedData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->bridgingPembelianService->getQueryHasilImport($startDate, $endDate);

        return DataTables::eloquent($query)
            ->addColumn('checkbox', fn (FakturPembelian $item) => $item->nomer_faktur)
            ->addColumn('tanggal_faktur_display', fn (FakturPembelian $item) => optional($item->tanggal_faktur)->format('Y-m-d'))
            ->addColumn('tanggal_jatuh_tempo_display', fn (FakturPembelian $item) => optional($item->tanggal_jatuh_tempo)->format('Y-m-d'))
            ->addColumn('nama_supplier', fn (FakturPembelian $item) => $item->supplier?->nama_supplier)
            ->addColumn('grandtotal_display', fn (FakturPembelian $item) => number_format((float) $item->grandtotal, 0, ',', '.'))
            ->toJson();
    }

    public function tarikPembelianObat(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pembelian.tarik-obat', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadTagihanObat(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->bridgingPembelianService->getKandidatPembelianObatQuery($startDate, $endDate);

        return DataTables::of($query)
            ->filter(function (Builder $query) use ($request) {
                $this->applyTagihanObatDataTableSearch($query, $request);
            }, false)
            ->toJson();
    }

    public function processImportObat(ImportPembelianObatRequest $request): RedirectResponse
    {
        $results = $this->bridgingPembelianService->imporBanyakPembelianObat(
            $request->validated('selectedNoTransaksi'),
            $request->validated('jenisProses'),
            $request->validated('metodeTanggalPengakuan'),
            auth()->user()?->name ?? auth()->user()?->email ?? 'system',
        );

        return redirect()
            ->route('bridging.pembelian.index')
            ->with('bridging_pembelian_results', $results)
            ->with('bridging_pembelian_message', 'Proses import pembelian obat & BHP selesai.');
    }

    public function tarikPembelianNonMedis(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pembelian.tarik-nonmedis', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadTagihanNonMedis(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return DataTables::collection(
            $this->bridgingPembelianService->getKandidatPembelianNonMedis($startDate, $endDate)
        )->toJson();
    }

    public function processImportNonMedis(ImportPembelianNonMedisRequest $request): RedirectResponse
    {
        $results = $this->bridgingPembelianService->imporBanyakPembelianNonMedis(
            $request->validated('selectedNoTransaksi'),
            $request->validated('jenisProses'),
            auth()->user()?->name ?? auth()->user()?->email ?? 'system',
        );

        return redirect()
            ->route('bridging.pembelian.index')
            ->with('bridging_pembelian_results', $results)
            ->with('bridging_pembelian_message', 'Proses import pembelian barang non medis selesai.');
    }

    public function destroyBulk(BulkDeleteBridgingPembelianRequest $request): RedirectResponse
    {
        $results = $this->bridgingPembelianService->hapusBanyak(
            $request->validated('selectedNoTransaksi'),
        );

        return redirect()
            ->route('bridging.pembelian.index')
            ->with('bridging_pembelian_results', $results)
            ->with('bridging_pembelian_message', 'Proses hapus selesai.');
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }

    private function applyTagihanObatDataTableSearch(Builder $query, Request $request): void
    {
        $globalSearch = trim($request->input('search.value', ''));

        if ($globalSearch !== '') {
            $this->applyTagihanObatSearchTerm($query, $globalSearch);
        }

        foreach ($this->tagihanObatSearchableColumns() as $index => $column) {
            $columnSearch = trim((string) $request->input("columns.$index.search.value", ''));

            if ($columnSearch === '') {
                continue;
            }

            $this->applyTagihanObatColumnSearch($query, $column, $columnSearch);
        }
    }

    private function applyTagihanObatSearchTerm(Builder $query, string $searchValue): void
    {
        $likeSearch = '%'.$searchValue.'%';

        $query->where(function (Builder $searchQuery) use ($likeSearch) {
            $searchQuery
                ->where('p.no_faktur', 'like', $likeSearch)
                ->orWhere('p.no_order', 'like', $likeSearch)
                ->orWhere('p.tgl_faktur', 'like', $likeSearch)
                ->orWhere('p.tgl_pesan', 'like', $likeSearch)
                ->orWhere('p.tgl_tempo', 'like', $likeSearch)
                ->orWhere('s.nama_suplier', 'like', $likeSearch)
                ->orWhere('p.kd_bangsal', 'like', $likeSearch)
                ->orWhere('p.status', 'like', $likeSearch)
                ->orWhereRaw('CAST(p.tagihan AS CHAR) like ?', [$likeSearch]);
        });
    }

    private function applyTagihanObatColumnSearch(Builder $query, string $column, string $searchValue): void
    {
        $likeSearch = '%'.$searchValue.'%';

        if ($column === 'tagihan') {
            $query->whereRaw('CAST(p.tagihan AS CHAR) like ?', [$likeSearch]);

            return;
        }

        $query->where($column, 'like', $likeSearch);
    }

    private function tagihanObatSearchableColumns(): array
    {
        return [
            1 => 'p.no_faktur',
            2 => 'p.no_order',
            3 => 'p.tgl_faktur',
            4 => 'p.tgl_pesan',
            5 => 'p.tgl_tempo',
            6 => 's.nama_suplier',
            7 => 'p.kd_bangsal',
            8 => 'p.status',
            9 => 'tagihan',
        ];
    }
}
