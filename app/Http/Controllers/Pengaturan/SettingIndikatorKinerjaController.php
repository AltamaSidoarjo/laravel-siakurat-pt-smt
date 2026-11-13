<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\SimpanManualDataRequest;
use App\Http\Requests\Pengaturan\StoreVariabelManualRequest;
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

        $indikators = IndikatorKinerja::query()
            ->with(['variabelManual', 'mappingCoa.coa'])
            ->orderBy('urutan')
            ->get();

        $variabelManuals = IndikatorKinerjaVariabelManual::query()
            ->with(['manualData' => fn ($q) => $q->where('tahun', $tahun)])
            ->orderBy('id')
            ->get();

        $satuanOptions = SatuanIndikator::cases();

        return view('pengaturan.setting-indikator-kinerja.index', [
            'tab' => $tab,
            'tahun' => $tahun,
            'indikators' => $indikators,
            'variabelManuals' => $variabelManuals,
            'satuanOptions' => $satuanOptions,
        ]);
    }

    public function storeVariabel(StoreVariabelManualRequest $request): RedirectResponse
    {
        IndikatorKinerjaVariabelManual::create($request->validated());

        $tahun = (int) $request->input('tahun', $request->query('tahun', now()->year));

        return redirect()
            ->route('setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
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
            ->route('setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun])
            ->with('success', 'Data bulanan indikator kinerja manual berhasil disimpan.');
    }
}
