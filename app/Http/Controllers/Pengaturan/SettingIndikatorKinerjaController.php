<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\SimpanManualDataRequest;
use App\Http\Requests\Pengaturan\StoreIndikatorKinerjaRequest;
use App\Http\Requests\Pengaturan\StoreVariabelManualRequest;
use App\Http\Requests\Pengaturan\UpdateIndikatorKinerjaRequest;
use App\Http\Requests\Pengaturan\UpdateVariabelManualRequest;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaManualData;
use App\Models\IndikatorKinerjaVariabelManual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingIndikatorKinerjaController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'indikator');
        if (! in_array($tab, ['indikator', 'manual'], true)) {
            $tab = 'indikator';
        }

        $tahun = (int) $request->query('tahun', now()->year);
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) now()->year;
        }

        $q = trim((string) $request->query('q', ''));
        $pola = trim((string) $request->query('pola', 'ALL'));

        $summaryCounts = [
            'total' => IndikatorKinerja::count(),
            'aktif' => IndikatorKinerja::where('status_aktif', true)->count(),
            'nonaktif' => IndikatorKinerja::where('status_aktif', false)->count(),
        ];

        $indikatorQuery = IndikatorKinerja::query()
            ->with(['variabelManual', 'mappingCoa.coa'])
            ->orderBy('urutan');

        if ($pola !== 'ALL' && $pola !== '') {
            $indikatorQuery->where('pola_evaluasi', $pola);
        }

        if ($q !== '') {
            $needle = '%'.$q.'%';
            $indikatorQuery->where(function ($sub) use ($needle) {
                $sub->where('nama', 'like', $needle)
                    ->orWhere('kode', 'like', $needle)
                    ->orWhere('definisi_operasional', 'like', $needle);
            });
        }

        $indikators = $indikatorQuery->get();

        $variabelManuals = IndikatorKinerjaVariabelManual::query()
            ->with(['manualData' => fn ($q) => $q->where('tahun', $tahun)])
            ->orderBy('id')
            ->get();

        $satuanOptions = SatuanIndikator::cases();
        $polaEvaluasiOptions = PolaEvaluasi::cases();

        return view('pengaturan.setting-indikator-kinerja.index', [
            'tab' => $tab,
            'tahun' => $tahun,
            'q' => $q,
            'pola' => $pola,
            'summaryCounts' => $summaryCounts,
            'indikators' => $indikators,
            'variabelManuals' => $variabelManuals,
            'satuanOptions' => $satuanOptions,
            'polaEvaluasiOptions' => $polaEvaluasiOptions,
        ]);
    }

    public function create(): View
    {
        $coas = Coa::query()
            ->where(function ($query) {
                $query->where('status_aktif', 1)
                    ->orWhereNull('status_aktif');
            })
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'status_aktif']);
        $variabelManuals = IndikatorKinerjaVariabelManual::orderBy('id')->get();

        return view('pengaturan.setting-indikator-kinerja.create', [
            'coas' => $coas,
            'variabelManuals' => $variabelManuals,
            'satuanOptions' => SatuanIndikator::cases(),
            'tipeKalkulasiOptions' => TipeKalkulasi::cases(),
            'basisPembilangOptions' => BasisPembilang::cases(),
            'basisPenyebutOptions' => BasisPenyebut::cases(),
            'polaEvaluasiOptions' => PolaEvaluasi::cases(),
            'peranCoaOptions' => PeranCoa::cases(),
        ]);
    }

    public function store(StoreIndikatorKinerjaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except('mappings');
            if (! isset($data['urutan']) || $data['urutan'] === null) {
                $data['urutan'] = ((int) IndikatorKinerja::max('urutan')) + 1;
            }

            $indikator = IndikatorKinerja::create($data);

            $mappings = $request->validated('mappings') ?? [];
            if (is_array($mappings)) {
                $this->syncMappings($indikator, $mappings);
            }
        });

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator'])
            ->with('success', 'Indikator kinerja berhasil ditambahkan.');
    }

    public function edit(IndikatorKinerja $indikator): View
    {
        $indikator->load(['mappingCoa.coa', 'variabelManual']);
        $existingCoaIds = $indikator->mappingCoa->pluck('coa_id')->filter()->all();

        $coas = Coa::query()
            ->where(function ($query) use ($existingCoaIds) {
                $query->where('status_aktif', 1)
                    ->orWhereNull('status_aktif');
                if (! empty($existingCoaIds)) {
                    $query->orWhereIn('id', $existingCoaIds);
                }
            })
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'status_aktif']);

        $variabelManuals = IndikatorKinerjaVariabelManual::orderBy('id')->get();

        return view('pengaturan.setting-indikator-kinerja.edit', [
            'indikator' => $indikator,
            'coas' => $coas,
            'variabelManuals' => $variabelManuals,
            'satuanOptions' => SatuanIndikator::cases(),
            'tipeKalkulasiOptions' => TipeKalkulasi::cases(),
            'basisPembilangOptions' => BasisPembilang::cases(),
            'basisPenyebutOptions' => BasisPenyebut::cases(),
            'polaEvaluasiOptions' => PolaEvaluasi::cases(),
            'peranCoaOptions' => PeranCoa::cases(),
        ]);
    }

    public function update(UpdateIndikatorKinerjaRequest $request, IndikatorKinerja $indikator): RedirectResponse
    {
        DB::transaction(function () use ($request, $indikator): void {
            $data = $request->safe()->except('mappings');
            if (! isset($data['urutan']) || $data['urutan'] === null) {
                $data['urutan'] = $indikator->urutan ?? 0;
            }

            $indikator->update($data);

            $mappings = $request->validated('mappings') ?? [];
            if (is_array($mappings)) {
                $this->syncMappings($indikator, $mappings);
            }
        });

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator'])
            ->with('success', 'Indikator kinerja berhasil diperbarui.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function syncMappings(IndikatorKinerja $indikator, array $mappings): void
    {
        $indikator->mappingCoa()->delete();

        foreach ($mappings as $mapping) {
            if (! empty($mapping['coa_id'])) {
                $indikator->mappingCoa()->create([
                    'coa_id' => (int) $mapping['coa_id'],
                    'peran' => $mapping['peran'] ?? PeranCoa::NUMERATOR->value,
                    'is_excluded' => ! empty($mapping['is_excluded']),
                ]);
            }
        }
    }

    public function destroy(IndikatorKinerja $indikator): RedirectResponse
    {
        DB::transaction(function () use ($indikator): void {
            $indikator->delete();
        });

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator'])
            ->with('success', 'Indikator kinerja berhasil dihapus.');
    }

    public function storeVariabel(StoreVariabelManualRequest $request): RedirectResponse
    {
        IndikatorKinerjaVariabelManual::create($request->validated());

        $tahun = (int) $request->input('tahun', $request->query('tahun', now()->year));

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
            ->with('success', 'Variabel manual berhasil ditambahkan.');
    }

    public function simpanManualData(SimpanManualDataRequest $request): RedirectResponse
    {
        $tahun = (int) $request->input('tahun');
        $data = $request->input('data', []);

        DB::transaction(function () use ($tahun, $data): void {
            foreach ($data as $variabelId => $months) {
                if (! is_array($months)) {
                    continue;
                }

                foreach ($months as $bulan => $nilai) {
                    $bulan = (int) $bulan;
                    if ($bulan < 1 || $bulan > 12) {
                        continue;
                    }

                    $formattedNilai = ($nilai === '' || $nilai === null) ? null : (float) $nilai;

                    IndikatorKinerjaManualData::updateOrCreate(
                        [
                            'variabel_id' => (int) $variabelId,
                            'tahun' => $tahun,
                            'bulan' => $bulan,
                        ],
                        [
                            'nilai' => $formattedNilai,
                        ]
                    );
                }
            }
        });

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
            ->with('success', 'Data bulanan indikator kinerja manual berhasil disimpan.');
    }

    public function updateVariabel(UpdateVariabelManualRequest $request, IndikatorKinerjaVariabelManual $variabelManual): RedirectResponse
    {
        $variabelManual->update($request->validated());

        $tahun = (int) $request->input('tahun', $request->query('tahun', now()->year));

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
            ->with('success', "Variabel manual '{$variabelManual->nama}' berhasil diperbarui.");
    }

    public function destroyVariabel(Request $request, IndikatorKinerjaVariabelManual $variabelManual): RedirectResponse
    {
        if ($variabelManual->indikatorKinerja()->exists()) {
            $listIndikator = $variabelManual->indikatorKinerja->pluck('nama')->join(', ');

            return redirect()
                ->back()
                ->with('error', "Variabel manual '{$variabelManual->nama}' tidak dapat dihapus karena masih digunakan oleh indikator kinerja: {$listIndikator}.");
        }

        DB::transaction(function () use ($variabelManual): void {
            $variabelManual->manualData()->delete();
            $variabelManual->delete();
        });

        $tahun = (int) $request->input('tahun', $request->query('tahun', now()->year));

        return redirect()
            ->route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
            ->with('success', "Variabel manual '{$variabelManual->nama}' berhasil dihapus.");
    }
}
