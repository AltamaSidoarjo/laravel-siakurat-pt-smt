<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreMappingLawanPendapatanRequest;
use App\Http\Requests\Pengaturan\StoreMappingPendapatanRequest;
use App\Http\Requests\Pengaturan\StoreMappingPendapatanUmumRequest;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\MappingPendapatan;
use App\Models\MappingPendapatanKamar;
use App\Models\MappingPendapatanUmum;
use App\Services\Pengaturan\MappingPendapatanTindakanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MappingPendapatanController extends Controller
{
    public function __construct(
        private readonly MappingPendapatanTindakanService $mappingPendapatanTindakanService,
    ) {
    }

    public function index(Request $request): View
    {
        $selectedTypeKey = $this->mappingPendapatanTindakanService->resolveTypeKey(
            $request->string('jenisTindakan')->toString()
        );
        $selectedType = $this->mappingPendapatanTindakanService->getTypeDefinition($selectedTypeKey);

        return view('pengaturan.mapping-pendapatan.index', [
            'page' => 'app',
            'typeOptions' => $this->mappingPendapatanTindakanService->getTypeOptions(),
            'selectedTypeKey' => $selectedTypeKey,
            'selectedType' => $selectedType,
            'mappings' => $this->mappingPendapatanTindakanService->getIndexData($selectedTypeKey),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTypeKey = $this->mappingPendapatanTindakanService->resolveTypeKey(
            old('jenis_tindakan', $request->string('jenisTindakan')->toString())
        );
        $selectedType = $this->mappingPendapatanTindakanService->getTypeDefinition($selectedTypeKey);

        return view('pengaturan.mapping-pendapatan.create', [
            'page' => 'app',
            'typeOptions' => $this->mappingPendapatanTindakanService->getTypeOptions(),
            'selectedTypeKey' => $selectedTypeKey,
            'selectedType' => $selectedType,
            'coaOptions' => $this->mappingPendapatanTindakanService->getCoaOptions(),
            'tindakanOptions' => $this->mappingPendapatanTindakanService->getAvailableTindakan($selectedTypeKey),
        ]);
    }

    public function store(StoreMappingPendapatanRequest $request): RedirectResponse
    {
        $selectedTypeKey = $this->mappingPendapatanTindakanService->resolveTypeKey($request->input('jenis_tindakan'));
        $actor = session('auth.preview_user.username', 'system');

        $result = DB::transaction(fn () => $this->mappingPendapatanTindakanService->createMappings(
            $selectedTypeKey,
            $request->validated()['rincian'],
            $actor,
        ));

        return redirect()
            ->route('pengaturan.mapping-pendapatan.index', ['jenisTindakan' => $selectedTypeKey])
            ->with(
                'success',
                sprintf(
                    'Data berhasil disimpan. Berhasil mapping = %d dan gagal mapping = %d.',
                    $result['success'],
                    $result['failed'],
                )
            );
    }

    public function destroy(Request $request, MappingPendapatan $mappingPendapatan): RedirectResponse
    {
        $typeKey = $this->mappingPendapatanTindakanService->getTypeKeyFromSource($mappingPendapatan->sumber_tindakan);

        $this->mappingPendapatanTindakanService->delete($mappingPendapatan);

        return redirect()
            ->route('pengaturan.mapping-pendapatan.index', [
                'jenisTindakan' => $request->string('jenisTindakan')->toString() ?: $typeKey,
            ])
            ->with('success', 'Data berhasil dihapus.');
    }

    public function destroyKamar(MappingPendapatanKamar $mappingPendapatanKamar): RedirectResponse
    {
        $this->mappingPendapatanTindakanService->deleteKamar($mappingPendapatanKamar);

        return redirect()
            ->route('pengaturan.mapping-pendapatan.index', ['jenisTindakan' => 'kamar'])
            ->with('success', 'Data berhasil dihapus.');
    }

    public function indexUmum(): View
    {
        return view('pengaturan.mapping-pendapatan.umum-index', [
            'page' => 'app',
            'mappings' => $this->mappingPendapatanTindakanService->getUmumIndexData(),
        ]);
    }

    public function createUmum(): View
    {
        return view('pengaturan.mapping-pendapatan.umum-create', [
            'page' => 'app',
            'coaOptions' => $this->mappingPendapatanTindakanService->getCoaOptions(),
            'nameOptions' => $this->mappingPendapatanTindakanService->getUmumNameOptions(),
            'penjaminOptions' => $this->mappingPendapatanTindakanService->getPenjaminOptions(),
        ]);
    }

    public function storeUmum(StoreMappingPendapatanUmumRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->mappingPendapatanTindakanService->createUmumMapping($request->validated()));

        return redirect()
            ->route('pengaturan.mapping-pendapatan.umum.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    public function destroyUmum(MappingPendapatanUmum $mappingPendapatanUmum): RedirectResponse
    {
        $this->mappingPendapatanTindakanService->deleteUmum($mappingPendapatanUmum);

        return redirect()
            ->route('pengaturan.mapping-pendapatan.umum.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    public function indexLawanPendapatan(): View
    {
        return view('pengaturan.mapping-pendapatan.lawan-index', [
            'page' => 'app',
            'mappings' => $this->mappingPendapatanTindakanService->getLawanPendapatanIndexData(),
        ]);
    }

    public function createLawanPendapatan(): View
    {
        return view('pengaturan.mapping-pendapatan.lawan-create', [
            'page' => 'app',
            'coaOptions' => $this->mappingPendapatanTindakanService->getCoaOptions(),
            'rekeningOptions' => $this->mappingPendapatanTindakanService->getRekeningSimrsOptions(),
        ]);
    }

    public function storeLawanPendapatan(StoreMappingLawanPendapatanRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->mappingPendapatanTindakanService->createLawanPendapatanMapping($request->validated()));

        return redirect()
            ->route('pengaturan.mapping-pendapatan.lawan.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    public function destroyLawanPendapatan(MappingLawanPendapatanSimrs $mappingLawanPendapatanSimrs): RedirectResponse
    {
        $this->mappingPendapatanTindakanService->deleteLawanPendapatan($mappingLawanPendapatanSimrs);

        return redirect()
            ->route('pengaturan.mapping-pendapatan.lawan.index')
            ->with('success', 'Data berhasil dihapus.');
    }
}
