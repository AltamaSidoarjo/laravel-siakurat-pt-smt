<?php

namespace App\Http\Controllers\Pengaturan;

use App\Exceptions\BillingApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreMappingPenjaminRequest;
use App\Models\MappingPenjaminPiutang;
use App\Services\Pengaturan\MappingPenjaminPiutangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class MappingPenjaminController extends Controller
{
    public function __construct(
        private readonly MappingPenjaminPiutangService $mappingPenjaminService,
    ) {}

    public function index(): View
    {
        return view('pengaturan.mapping-penjamin.index', [
            'page' => 'app',
            'mappings' => $this->mappingPenjaminService->getIndexData(),
        ]);
    }

    public function create(): View
    {
        $penjaminOptions = collect();
        $apiError = null;

        try {
            $penjaminOptions = $this->mappingPenjaminService->getAvailablePenjaminOptions();
        } catch (BillingApiException $exception) {
            $apiError = $exception->getMessage();
        }

        return view('pengaturan.mapping-penjamin.create', [
            'page' => 'app',
            'penjaminOptions' => $penjaminOptions,
            'coaOptions' => $this->mappingPenjaminService->getCoaOptions(),
            'apiError' => $apiError,
        ]);
    }

    public function store(StoreMappingPenjaminRequest $request): RedirectResponse
    {
        try {
            DB::transaction(fn () => $this->mappingPenjaminService->create($request->validated()));
        } catch (BillingApiException|RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('pengaturan.mapping-penjamin.index')
            ->with('success', 'Mapping penjamin berhasil disimpan.');
    }

    public function destroy(MappingPenjaminPiutang $mappingPenjaminPiutang): RedirectResponse
    {
        $this->mappingPenjaminService->delete($mappingPenjaminPiutang);

        return redirect()
            ->route('pengaturan.mapping-penjamin.index')
            ->with('success', 'Mapping penjamin berhasil dihapus.');
    }
}
