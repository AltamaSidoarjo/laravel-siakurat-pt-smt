<?php

namespace App\Http\Controllers\Pengaturan;

use App\Exceptions\BillingApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreMappingPenjaminRequest;
use App\Models\MappingPenjaminPiutang;
use App\Services\Pengaturan\MappingPenjaminPiutangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Yajra\DataTables\Facades\DataTables;

class MappingPenjaminController extends Controller
{
    public function __construct(
        private readonly MappingPenjaminPiutangService $mappingPenjaminService,
    ) {}

    public function index(): View
    {
        return view('pengaturan.mapping-penjamin.index', [
            'page' => 'app',
        ]);
    }

    public function loadData(): JsonResponse
    {
        $query = $this->mappingPenjaminService->getIndexQuery();

        return DataTables::eloquent($query)
            ->addColumn('jenis_layanan_label', fn (MappingPenjaminPiutang $mapping) => match ($mapping->jenis_layanan) {
                'rawat_jalan' => 'Rawat Jalan',
                'rawat_inap' => 'Rawat Inap',
                'igd' => 'IGD',
                default => $mapping->jenis_layanan,
            })
            ->addColumn('kode_coa', fn (MappingPenjaminPiutang $mapping) => $mapping->coa_kode ?? '')
            ->addColumn('nama_coa', fn (MappingPenjaminPiutang $mapping) => $mapping->coa_nama ?? '')
            ->addColumn('aksi', fn (MappingPenjaminPiutang $mapping) => $mapping->id)
            ->toJson();
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
