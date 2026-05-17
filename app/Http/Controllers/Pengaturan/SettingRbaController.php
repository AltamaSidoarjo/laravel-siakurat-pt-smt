<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreSettingRbaRequest;
use App\Models\SettingRba;
use App\Services\Pengaturan\SettingRbaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SettingRbaController extends Controller
{
    public function __construct(
        private readonly SettingRbaService $settingRbaService,
    ) {
    }

    public function index(Request $request): View
    {
        $currentYear = (int) now()->format('Y');
        $yearFrom = $request->integer('yearFrom') ?: $currentYear - 5;
        $yearTo = $request->integer('yearTo') ?: $currentYear;

        return view('pengaturan.setting-rba.index', [
            'page' => 'app',
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $yearFrom = $request->integer('yearFrom') ?: null;
        $yearTo = $request->integer('yearTo') ?: null;

        $query = $this->settingRbaService->getIndexQuery($yearFrom, $yearTo);

        return DataTables::eloquent($query)
            ->addColumn('coa_display', fn (SettingRba $item) => trim(($item->coa?->kode ?? '').' - '.($item->coa?->nama ?? '')))
            ->addColumn('nominal_display', fn (SettingRba $item) => number_format((float) $item->total_nominal, 2, ',', '.'))
            ->toJson();
    }

    public function create(): View
    {
        return view('pengaturan.setting-rba.create', [
            'page' => 'app',
            'coaOptions' => $this->settingRbaService->getCoaOptions(),
        ]);
    }

    public function store(StoreSettingRbaRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->settingRbaService->createMany($request->validated()));

        return redirect()
            ->route('pengaturan.setting-rba.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    public function destroy(SettingRba $settingRba): RedirectResponse
    {
        DB::transaction(fn () => $this->settingRbaService->delete($settingRba));

        return redirect()
            ->route('pengaturan.setting-rba.index')
            ->with('success', 'Data berhasil dihapus.');
    }
}
