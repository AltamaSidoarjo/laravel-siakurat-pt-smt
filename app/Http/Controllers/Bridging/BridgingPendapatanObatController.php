<?php

namespace App\Http\Controllers\Bridging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bridging\BulkDeletePendapatanObatRequest;
use App\Http\Requests\Bridging\ImportPendapatanObatRequest;
use App\Models\SimrsImportPendapatanJualObat;
use App\Services\Bridging\BridgingPendapatanObatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BridgingPendapatanObatController extends Controller
{
    public function __construct(
        private readonly BridgingPendapatanObatService $bridgingPendapatanObatService,
    ) {
    }

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pendapatan-obat.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'results' => session('bridging_pendapatan_obat_results', []),
            'message' => session('bridging_pendapatan_obat_message'),
        ]);
    }

    public function loadImportedData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->bridgingPendapatanObatService->getQueryDataImport($startDate, $endDate);

        return DataTables::eloquent($query)
            ->addColumn('checkbox', fn (SimrsImportPendapatanJualObat $item) => $item->nomer_transaksi)
            ->addColumn('tanggal_display', fn (SimrsImportPendapatanJualObat $item) => optional($item->tanggal)->format('Y-m-d'))
            ->addColumn('grandtotal_display', fn (SimrsImportPendapatanJualObat $item) => number_format((float) $item->grandtotal, 0, ',', '.'))
            ->toJson();
    }

    public function tarikTagihan(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bridging.pendapatan-obat.tarik-tagihan', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadTagihanSimrs(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return DataTables::collection(
            $this->bridgingPendapatanObatService->getKandidatTagihanSimrs($startDate, $endDate)
        )->toJson();
    }

    public function processImport(ImportPendapatanObatRequest $request): RedirectResponse
    {
        $results = $this->bridgingPendapatanObatService->imporBanyak(
            $request->validated('selectedNoTransaksi'),
            $request->validated('jenisProses'),
            session('auth.preview_user.username', 'system'),
        );

        return redirect()
            ->route('bridging.pendapatan-obat.index')
            ->with('bridging_pendapatan_obat_results', $results)
            ->with('bridging_pendapatan_obat_message', 'Proses import selesai.');
    }

    public function destroyBulk(BulkDeletePendapatanObatRequest $request): RedirectResponse
    {
        $results = $this->bridgingPendapatanObatService->hapusBanyak(
            $request->validated('selectedNoTransaksi'),
            session('auth.preview_user.username', 'system'),
        );

        return redirect()
            ->route('bridging.pendapatan-obat.index')
            ->with('bridging_pendapatan_obat_results', $results)
            ->with('bridging_pendapatan_obat_message', 'Proses hapus selesai.');
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }
}
