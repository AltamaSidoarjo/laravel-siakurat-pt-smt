<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\BukuBankRequest;
use App\Services\Kasbank\BukuBankService;
use App\Services\PreferensiPerusahaanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BukuBankController extends Controller
{
    public function __construct(
        private readonly BukuBankService $bukuBankService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {}

    public function index(BukuBankRequest $request): View
    {
        $startDate = $request->validated('startDate') ?: now()->startOfMonth()->toDateString();
        $endDate = $request->validated('endDate') ?: now()->toDateString();
        $coaIds = collect($request->validated('coaIds', []))->map(fn ($id) => (int) $id)->values()->all();
        $identity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('kasbank.buku-bank.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCoaIds' => $coaIds,
            'coaOptions' => $this->bukuBankService->getCoaOptions(),
            'rowsByCoa' => $this->bukuBankService->getBukuBank($startDate, $endDate, $coaIds),
            'namaRumahSakit' => $identity['namaRumahSakit'],
        ]);
    }

    public function searchCoa(Request $request): JsonResponse
    {
        $items = $this->bukuBankService->searchCoaOptions($request->string('q')->toString());

        return response()->json([
            'results' => $items->map(fn (array $coa) => [
                'id' => $coa['id'],
                'text' => '['.$coa['kode'].'] '.$coa['nama'],
            ]),
        ]);
    }
}
