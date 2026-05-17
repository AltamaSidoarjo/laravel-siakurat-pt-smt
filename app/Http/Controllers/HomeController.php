<?php

namespace App\Http\Controllers;

use App\Services\HomeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomeDashboardService $homeDashboardService,
    ) {
    }

    public function index(): View
    {
        return view('home', [
            'page' => 'app',
            'defaultStartDate' => now()->startOfMonth()->format('Y-m-d'),
            'defaultEndDate' => now()->format('Y-m-d'),
        ]);
    }

    public function kunjunganHarian(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json(
            $this->homeDashboardService->getKunjunganHarian($startDate, $endDate)
        );
    }

    public function distribusiPoli(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json(
            $this->homeDashboardService->getDistribusiPoli($startDate, $endDate)
        );
    }

    public function topDokter(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json(
            $this->homeDashboardService->getTopDokter($startDate, $endDate)
        );
    }

    public function pendapatanHarian(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json(
            $this->homeDashboardService->getPendapatanHarian($startDate, $endDate)
        );
    }

    public function komposisiPenjamin(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json(
            $this->homeDashboardService->getKomposisiPenjamin($startDate, $endDate)
        );
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('dariTanggal')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('sampaiTanggal')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }
}
