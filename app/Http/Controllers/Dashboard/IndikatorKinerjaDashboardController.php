<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\IndikatorKinerja\IndikatorKinerjaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndikatorKinerjaDashboardController extends Controller
{
    public function __construct(
        private readonly IndikatorKinerjaService $indikatorKinerjaService,
    ) {}

    /**
     * Tampilkan halaman dashboard indikator kinerja eksekutif.
     */
    public function index(Request $request): View
    {
        $startDate = (string) $request->query(
            'start_date',
            $request->query('dariTanggal', now()->startOfMonth()->toDateString())
        );

        $endDate = (string) $request->query(
            'end_date',
            $request->query('sampaiTanggal', now()->toDateString())
        );

        $q = trim((string) $request->query('q', ''));

        $indikatorList = $this->indikatorKinerjaService->evaluasiSemua($startDate, $endDate);
        $ringkasanStatus = $this->indikatorKinerjaService->getRingkasanStatus($startDate, $endDate);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $indikatorList = $indikatorList->filter(function (array $item) use ($needle) {
                $kode = mb_strtolower((string) ($item['kode'] ?? ''));
                $nama = mb_strtolower((string) ($item['nama'] ?? ''));

                return str_contains($kode, $needle) || str_contains($nama, $needle);
            })->values();
        }

        return view('dashboard.indikator-kinerja.index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'q' => $q,
            'indikatorList' => $indikatorList,
            'ringkasanStatus' => $ringkasanStatus,
        ]);
    }

    /**
     * Endpoint API JSON tren bulanan 12 bulan untuk modal drill-down Chart.js.
     */
    public function tren(Request $request, string $kode): JsonResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $tren = $this->indikatorKinerjaService->getTrenBulanan($kode, $tahun);

        return response()->json($tren);
    }
}
