<?php

namespace App\Http\Controllers\Bridging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bridging\BulkDeletePendapatanRequest;
use App\Http\Requests\Bridging\ImportPendapatanRequest;
use App\Models\SimrsImportPendapatan;
use App\Services\Bridging\BridgingPendapatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BridgingPendapatanController extends Controller
{
    public function __construct(
        private readonly BridgingPendapatanService $bridgingPendapatanService,
    ) {
    }

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

        $query = $this->bridgingPendapatanService->getQueryDataImport(
            $startDate,
            $endDate,
            $request->string('poli')->toString(),
            $request->string('penjamin')->toString(),
        );

        return DataTables::eloquent($query)
            ->addColumn('checkbox', fn (SimrsImportPendapatan $item) => $item->nomer_billing)
            ->addColumn('tanggal_reg_display', fn (SimrsImportPendapatan $item) => optional($item->tanggal_reg)->format('Y-m-d'))
            ->addColumn('total_tagihan_display', fn (SimrsImportPendapatan $item) => number_format((float) $item->total_tagihan, 0, ',', '.'))
            ->toJson();
    }

    public function tarikBillingSimrs(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pendapatan.tarik-billing-simrs', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'poli' => (string) $request->string('poli'),
            'penjamin' => (string) $request->string('penjamin'),
        ]);
    }

    public function loadBillingSimrs(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $rows = $this->bridgingPendapatanService->getKandidatBillingSimrs(
            $startDate,
            $endDate,
            $request->string('poli')->toString(),
            $request->string('penjamin')->toString(),
        );

        return DataTables::collection($rows)->toJson();
    }

    public function processImport(ImportPendapatanRequest $request): RedirectResponse
    {
        $results = $this->bridgingPendapatanService->imporBanyak(
            $request->validated('selectedNoRawat'),
            $request->validated('jenisProses'),
            $request->validated('basisTanggalPengakuan'),
            session('auth.preview_user.username', 'system'),
        );

        return redirect()
            ->route('bridging.pendapatan.index')
            ->with('bridging_pendapatan_results', $results)
            ->with('bridging_pendapatan_message', 'Proses import selesai.');
    }

    public function destroyBulk(BulkDeletePendapatanRequest $request): RedirectResponse
    {
        $results = $this->bridgingPendapatanService->hapusBanyak(
            $request->validated('selectedNoRawat'),
            session('auth.preview_user.username', 'system'),
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

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }
}
