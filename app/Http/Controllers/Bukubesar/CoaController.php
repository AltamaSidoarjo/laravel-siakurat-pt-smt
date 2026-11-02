<?php

namespace App\Http\Controllers\Bukubesar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bukubesar\StoreCoaRequest;
use App\Http\Requests\Bukubesar\UpdateCoaRequest;
use App\Models\Coa;
use App\Services\Bukubesar\CoaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CoaController extends Controller
{
    public function __construct(
        private readonly CoaService $coaService,
    ) {
    }

    public function index(): View
    {
        return view('bukubesar.coa.index', [
            'page' => 'app',
            'rows' => $this->coaService->getTreeRows(),
        ]);
    }

    public function create(): View
    {
        return view('bukubesar.coa.create', [
            'page' => 'app',
            'parentOptions' => $this->coaService->getParentOptions(),
            'tipeOptions' => $this->coaService->getTipeOptions(),
        ]);
    }

    public function store(StoreCoaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $this->coaService->create($request->validated());
        });

        return redirect()
            ->route('bukubesar.coa.index')
            ->with('success', 'Data berhasil dibuat.');
    }

    public function edit(Coa $coa): View
    {
        return view('bukubesar.coa.edit', [
            'page' => 'app',
            'coa' => $coa,
            'hasChild' => $this->coaService->hasChildren((int) $coa->id),
            'canDelete' => $this->coaService->canDelete($coa),
            'parentOptions' => $this->coaService->getParentOptions((int) $coa->id),
            'tipeOptions' => $this->coaService->getTipeOptions(),
        ]);
    }

    public function update(UpdateCoaRequest $request, Coa $coa): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($coa, $data) {
            $this->coaService->update($coa, $data);
        });

        return redirect()
            ->route('bukubesar.coa.index')
            ->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Coa $coa): RedirectResponse
    {
        $check = $this->coaService->canDelete($coa);
        if (! $check['allowed']) {
            return redirect()
                ->route('bukubesar.coa.edit', $coa)
                ->with('error', $check['message']);
        }

        $this->coaService->delete($coa);

        return redirect()
            ->route('bukubesar.coa.index')
            ->with('success', 'Data berhasil dihapus.');
    }
}
