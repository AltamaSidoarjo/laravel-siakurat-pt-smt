<?php

namespace App\Http\Controllers\Bridging;

use App\Exceptions\BillingApiException;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bridging\BulkDeletePendapatanRequest;
use App\Http\Requests\Bridging\LoadBillingPendapatanApiRequest;
use App\Models\SimrsImportPendapatan;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Bridging\BridgingPendapatanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class BridgingPendapatanController extends Controller
{
    use StreamsCsvExport;

    public function __construct(
        private readonly BridgingPendapatanService $bridgingPendapatanService,
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
    ) {}

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pendapatan.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'poli' => (string) $request->string('poli'),
            'penjamin' => (string) $request->string('penjamin'),
            'results' => session('bridging_pendapatan_results', []),
            'message' => session('bridging_pendapatan_message'),
        ]);
    }

    public function loadImportedData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $baseQuery = $this->bridgingPendapatanService->getQueryDataImport(
            $startDate,
            $endDate,
            $request->string('poli')->toString(),
            $request->string('penjamin')->toString(),
        );

        $grandTotalQuery = clone $baseQuery;
        $this->applyDataTableSearch($grandTotalQuery, $request);

        return DataTables::eloquent($baseQuery)
            ->filter(function (Builder $query) use ($request) {
                $this->applyDataTableSearch($query, $request);
            }, false)
            ->addColumn('checkbox', fn (SimrsImportPendapatan $item) => $item->nomer_billing)
            ->addColumn('tanggal_reg_display', fn (SimrsImportPendapatan $item) => optional($item->tanggal_reg)->format('Y-m-d'))
            ->addColumn('total_tagihan_display', fn (SimrsImportPendapatan $item) => number_format((float) $item->total_tagihan, 0, ',', '.'))
            ->with('grandTotal', fn () => (float) $grandTotalQuery->sum('total_tagihan'))
            ->toJson();
    }

    public function tarikBillingSimrs(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $jenisLayanan = $this->resolveJenisLayanan($request);
        $dokterOptions = collect();
        $spesialisOptions = collect();
        $apiError = null;

        if ($jenisLayanan === BillingPendapatanApiService::RAWAT_JALAN) {
            try {
                $spesialisOptions = $this->billingPendapatanApiService->getSpesialisOptions();
                $dokterOptions = $this->billingPendapatanApiService->getDokterOptions();
            } catch (BillingApiException $exception) {
                $apiError = $exception->getMessage();
            }
        }

        return view('bridging.pendapatan.tarik-billing-simrs', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'jenisLayanan' => $jenisLayanan,
            'spesialisId' => $request->string('spesialisId')->toString(),
            'dokterId' => $request->string('dokterId')->toString(),
            'spesialisOptions' => $spesialisOptions,
            'dokterOptions' => $dokterOptions,
            'apiError' => $apiError,
        ]);
    }

    public function loadBillingSimrs(LoadBillingPendapatanApiRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $rows = $this->billingPendapatanApiService->getKandidat(
                $data['jenisLayanan'],
                $data['startDate'],
                $data['endDate'],
                $data['spesialisId'] ?? null,
                $data['dokterId'] ?? null,
            );
        } catch (BillingApiException $exception) {
            return response()->json([
                'draw' => $request->integer('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $exception->getMessage(),
            ]);
        }

        return DataTables::collection($rows)->toJson();
    }

    public function processImport(Request $request): RedirectResponse
    {
        return redirect()
            ->route('bridging.pendapatan.tarik-billing-simrs')
            ->with('error', 'Proses Jurnal Umum dan Invoice Pendapatan belum tersedia pada fase ini.');
    }

    public function destroyBulk(BulkDeletePendapatanRequest $request): RedirectResponse
    {
        $results = $this->bridgingPendapatanService->hapusBanyak(
            $request->validated('selectedNoRawat'),
            auth()->user()?->name ?? auth()->user()?->email ?? 'system',
        );

        return redirect()
            ->route('bridging.pendapatan.index')
            ->with('bridging_pendapatan_results', $results)
            ->with('bridging_pendapatan_message', 'Proses hapus selesai.');
    }

    public function dataTidakBalance(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pendapatan.data-tidak-balance', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function detectTidakBalance(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json([
            'success' => true,
            'data' => $this->bridgingPendapatanService
                ->deteksiJurnalTidakBalance($startDate, $endDate)
                ->values()
                ->all(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $query = $this->bridgingPendapatanService->getQueryDataImport(
            $data['startDate'],
            $data['endDate'],
            $request->string('poli')->toString(),
            $request->string('penjamin')->toString(),
        );

        return $this->streamCsvExport(
            $request,
            $query,
            'bridging-pendapatan',
            ['No. Billing', 'Tanggal Registrasi', 'Pasien', 'Dokter', 'Poli', 'Status Layanan', 'Penjamin', 'Total Tagihan', 'Import Ke'],
            fn (SimrsImportPendapatan $item) => [
                (string) $item->nomer_billing,
                optional($item->tanggal_reg)->format('Y-m-d'),
                (string) $item->nama_pasien,
                (string) $item->dokter,
                (string) $item->poli,
                (string) $item->status_layanan,
                (string) $item->penjamin,
                $this->csvNumber($item->total_tagihan),
                (string) $item->import_ke,
            ],
        );
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }

    private function resolveJenisLayanan(Request $request): string
    {
        $jenisLayanan = $request->string('jenisLayanan')->toString();

        return in_array($jenisLayanan, [
            BillingPendapatanApiService::RAWAT_JALAN,
            BillingPendapatanApiService::IGD,
        ], true) ? $jenisLayanan : BillingPendapatanApiService::RAWAT_JALAN;
    }

    private function applyDataTableSearch(Builder $query, Request $request): void
    {
        $searchValue = trim($request->input('search.value', ''));

        if ($searchValue === '') {
            return;
        }

        $likeSearch = '%'.$searchValue.'%';

        $query->where(function (Builder $searchQuery) use ($likeSearch) {
            $searchQuery
                ->where('nomer_billing', 'like', $likeSearch)
                ->orWhere('tanggal_reg', 'like', $likeSearch)
                ->orWhere('nama_pasien', 'like', $likeSearch)
                ->orWhere('dokter', 'like', $likeSearch)
                ->orWhere('poli', 'like', $likeSearch)
                ->orWhere('status_layanan', 'like', $likeSearch)
                ->orWhere('penjamin', 'like', $likeSearch)
                ->orWhere('import_ke', 'like', $likeSearch)
                ->orWhereRaw('CAST(total_tagihan AS CHAR) like ?', [$likeSearch]);
        });
    }
}
