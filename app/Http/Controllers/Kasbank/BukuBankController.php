<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Services\Kasbank\BukuBankService;
use App\Services\PreferensiPerusahaanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BukuBankController extends Controller
{
    public function __construct(
        private readonly BukuBankService $bukuBankService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'coaIds' => ['nullable', 'array'],
            'coaIds.*' => ['integer', 'distinct'],
        ]);

        $startDate = $validated['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['endDate'] ?? now()->toDateString();
        $selectedCoaIds = collect($validated['coaIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('kasbank.buku-bank.index', array_merge(
            $this->preferensiPerusahaanService->getPrintIdentity(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'coaOptions' => $this->bukuBankService->getCoaOptions(),
                'selectedCoaIds' => $selectedCoaIds,
                'rowsByCoa' => $this->bukuBankService->getReport($startDate, $endDate, $selectedCoaIds),
            ],
        ));
    }
}
