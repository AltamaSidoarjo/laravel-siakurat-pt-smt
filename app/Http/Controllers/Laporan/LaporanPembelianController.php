<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laporan\BukuPembantuHutangRequest;
use App\Services\Laporan\LaporanPembelianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanPembelianController extends Controller
{
    public function __construct(
        private readonly LaporanPembelianService $laporanPembelianService,
    ) {}

    public function index(): View
    {
        return view('laporan.pembelian.index', ['page' => 'app']);
    }

    public function bukuPembantuHutang(BukuPembantuHutangRequest $request): View
    {
        $data = $request->validated();
        $reportDate = $data['reportDate'] ?? now()->format('Y-m-d');
        $supplierIds = collect($data['supplierIds'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $report = $this->laporanPembelianService->getBukuPembantuHutang(
            reportDate: $reportDate,
            supplierIds: $supplierIds,
        );

        return view('laporan.pembelian.buku-pembantu-hutang', array_merge(
            $this->laporanPembelianService->getIdentitasLaporan(),
            $report,
            [
                'page' => 'app',
                'reportDate' => $reportDate,
                'supplierIds' => $supplierIds,
                'supplierOptions' => $this->laporanPembelianService->getSupplierOptions(),
            ],
        ));
    }

    public function searchSupplier(Request $request): JsonResponse
    {
        $results = $this->laporanPembelianService
            ->searchSupplierHutang($request->string('q')->trim()->toString())
            ->map(fn ($supplier) => [
                'id' => (string) $supplier->id,
                'text' => trim(sprintf('[%s] %s', $supplier->kode_supplier, $supplier->nama_supplier)),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function exportCsv(BukuPembantuHutangRequest $request): StreamedResponse
    {
        $data = $request->validated();
        $reportDate = $data['reportDate'] ?? now()->format('Y-m-d');
        $supplierIds = collect($data['supplierIds'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $report = $this->laporanPembelianService->getBukuPembantuHutang(
            reportDate: $reportDate,
            supplierIds: $supplierIds,
        );
        $fileName = sprintf(
            'rincian-buku-pembantu-hutang-%s.csv',
            str_replace('-', '', $reportDate),
        );

        return response()->streamDownload(
            fn () => $this->laporanPembelianService->streamBukuPembantuHutangCsv($report),
            $fileName,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
