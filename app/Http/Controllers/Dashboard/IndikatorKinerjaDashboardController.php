<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\IndikatorKinerja\StatusEvaluasi;
use App\Http\Controllers\Controller;
use App\Services\IndikatorKinerja\IndikatorKinerjaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

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
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $q = trim((string) $request->query('q', ''));

        // Evaluasi semua indikator sekali saja
        $indikatorList = $this->indikatorKinerjaService->evaluasiSemua($startDate, $endDate);

        // Agregasi ringkasan eksekutif langsung in-memory dari koleksi evaluasi
        $ringkasanStatus = [
            'total' => $indikatorList->count(),
            'hijau' => $indikatorList->where('status', StatusEvaluasi::HIJAU)->count(),
            'kuning' => $indikatorList->where('status', StatusEvaluasi::KUNING)->count(),
            'merah' => $indikatorList->where('status', StatusEvaluasi::MERAH)->count(),
            'netral' => $indikatorList->where('status', StatusEvaluasi::NETRAL)->count(),
        ];

        // Filter pencarian indikator berdasarkan nama atau kode (case-insensitive)
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

    /**
     * Resolusi rentang tanggal dengan validasi format dan fallback aman ke tanggal default.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $defaultStart = now()->startOfMonth()->toDateString();
        $defaultEnd = now()->toDateString();

        $rawStart = $request->query('start_date', $request->query('dariTanggal', $defaultStart));
        $rawEnd = $request->query('end_date', $request->query('sampaiTanggal', $defaultEnd));

        try {
            $start = Carbon::parse($rawStart)->toDateString();
        } catch (Throwable) {
            $start = $defaultStart;
        }

        try {
            $end = Carbon::parse($rawEnd)->toDateString();
        } catch (Throwable) {
            $end = $defaultEnd;
        }

        return [$start, $end];
    }
}
