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
        $user = $request->user();
        $canDelete = $user?->hasModuleAccess('pengaturan.setting-rba', 'delete') ?? false;

        $query = $this->settingRbaService->getIndexQuery($yearFrom, $yearTo);

        return DataTables::eloquent($query)
            ->addColumn('coa_display', fn (SettingRba $item) => trim(($item->coa?->kode ?? '').' - '.($item->coa?->nama ?? '')))
            ->addColumn('nominal_display', fn (SettingRba $item) => number_format((float) $item->total_nominal, 2, ',', '.'))
            ->filterColumn('coa_display', function ($query, $keyword) {
                $query->whereHas('coa', function ($q) use ($keyword) {
                    $q->where('kode', 'like', "%{$keyword}%")
                        ->orWhere('nama', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nominal_display', function ($query, $keyword) {
                $cleaned = preg_replace('/[^\d]/', '', (string) $keyword);
                if ($cleaned !== '') {
                    $query->where('setting_rba.total_nominal', 'like', "%{$cleaned}%");
                }
            })
            ->order(function ($query) use ($request) {
                $orders = $request->input('order', []);
                if (empty($orders)) {
                    $query->orderByDesc('setting_rba.tahun')->orderByDesc('setting_rba.id');

                    return;
                }

                $columns = $request->input('columns', []);
                foreach ($orders as $order) {
                    $colIdx = $order['column'] ?? null;
                    $dir = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    $colName = $columns[$colIdx]['name'] ?? $columns[$colIdx]['data'] ?? null;

                    if ($colName === 'coa_display' || $colName === 'coa_id' || $colName === 'coa.kode') {
                        $query->join('coa', 'setting_rba.coa_id', '=', 'coa.id')
                            ->orderBy('coa.kode', $dir)
                            ->select('setting_rba.*');
                    } elseif ($colName === 'tahun') {
                        $query->orderBy('setting_rba.tahun', $dir)->orderBy('setting_rba.id', $dir);
                    } elseif ($colName === 'nominal_display' || $colName === 'total_nominal') {
                        $query->orderBy('setting_rba.total_nominal', $dir);
                    } elseif ($colName === 'catatan') {
                        $query->orderBy('setting_rba.catatan', $dir);
                    }
                }
            })
            ->addColumn('destroy_url', fn (SettingRba $item) => route('pengaturan.setting-rba.destroy', $item))
            ->addColumn('can_delete', fn () => $canDelete)
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
