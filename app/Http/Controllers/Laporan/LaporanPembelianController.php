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
        $startDate = $data['startDate'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $data['endDate'] ?? now()->format('Y-m-d');
        $supplierIds = collect($data['supplierIds'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $statusSaldo = $data['statusSaldo'] ?? 'semua';
        $report = $this->laporanPembelianService->getBukuPembantuHutang(
            startDate: $startDate,
            endDate: $endDate,
            supplierIds: $supplierIds,
            statusSaldo: $statusSaldo,
        );

        return view('laporan.pembelian.buku-pembantu-hutang', array_merge(
            $this->laporanPembelianService->getIdentitasLaporan(),
            $report,
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'supplierIds' => $supplierIds,
                'supplierOptions' => $this->laporanPembelianService->getSupplierOptions(),
                'statusSaldo' => $statusSaldo,
                'hasSelection' => $supplierIds !== [],
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
        $supplierIds = collect($data['supplierIds'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $report = $this->laporanPembelianService->getBukuPembantuHutang(
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            supplierIds: $supplierIds,
            statusSaldo: $data['statusSaldo'] ?? 'semua',
        );
        $fileName = sprintf(
            'buku-pembantu-hutang-%s-%s.csv',
            str_replace('-', '', $data['startDate']),
            str_replace('-', '', $data['endDate']),
        );

        return response()->streamDownload(
            fn () => $this->laporanPembelianService->streamBukuPembantuHutangCsv($report),
            $fileName,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
