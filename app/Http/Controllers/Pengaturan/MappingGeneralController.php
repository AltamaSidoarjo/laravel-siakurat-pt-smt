<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreMappingGeneralRequest;
use App\Models\MappingCoaSimrs;
use App\Services\Pengaturan\MappingGeneralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MappingGeneralController extends Controller
{
    public function __construct(
        private readonly MappingGeneralService $mappingGeneralService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.mapping-general.index', [
            'page' => 'app',
            'mappings' => $this->mappingGeneralService->getIndexData(),
        ]);
    }

    public function create(): View
    {
        return view('pengaturan.mapping-general.create', [
            'page' => 'app',
            'coaOptions' => $this->mappingGeneralService->getCoaOptions(),
            'rekeningOptions' => $this->mappingGeneralService->getRekeningOptions(),
        ]);
    }

    public function store(StoreMappingGeneralRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->mappingGeneralService->create($request->validated()));

        return redirect()
            ->route('pengaturan.mapping-general.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    public function destroy(MappingCoaSimrs $mappingCoaSimrs): RedirectResponse
    {
        $this->mappingGeneralService->delete($mappingCoaSimrs);

        return redirect()
            ->route('pengaturan.mapping-general.index')
            ->with('success', 'Data berhasil dihapus.');
    }
}
