<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Services\Laporan\LaporanKeuanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class LaporanKeuanganController extends Controller
{
    public function __construct(
        private readonly LaporanKeuanganService $laporanKeuanganService,
    ) {
    }

    public function index(): View
    {
        return view('laporan.keuangan.index', [
            'page' => 'app',
        ]);
    }

    public function rincianTransaksiBukubesar(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.keuangan.rincian-transaksi-bukubesar', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadRincianTransaksiBukubesar(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->laporanKeuanganService->getQueryRincianTransaksiBukubesar($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', function ($row) {
                if (empty($row->tanggal)) {
                    return '-';
                }

                return is_object($row->tanggal) && method_exists($row->tanggal, 'format')
                    ? $row->tanggal->format('Y-m-d')
                    : (string) $row->tanggal;
            })
            ->addColumn('coa', fn ($row) => trim(($row->kode_coa ?? '-').' - '.($row->nama_coa ?? '-')))
            ->addColumn('debit', fn ($row) => $row->tipe_mutasi === 'D' ? number_format((float) $row->nominal, 0, ',', '.') : '-')
            ->addColumn('kredit', fn ($row) => $row->tipe_mutasi === 'K' ? number_format((float) $row->nominal, 0, ',', '.') : '-')
            ->toJson();
    }

    public function deteksiJurnalTidakBalance(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.keuangan.deteksi-jurnal-tidak-balance', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadDeteksiJurnalTidakBalance(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $data = $this->laporanKeuanganService->getJurnalTidakBalance($startDate, $endDate);
        $grandTotalSelisih = $data->sum('selisih');

        return DataTables::collection($data)
            ->editColumn('total_debit', fn (array $row) => number_format((float) $row['total_debit'], 0, ',', '.'))
            ->editColumn('total_kredit', fn (array $row) => number_format((float) $row['total_kredit'], 0, ',', '.'))
            ->editColumn('selisih', fn (array $row) => number_format((float) $row['selisih'], 0, ',', '.'))
            ->with('grandTotalSelisih', number_format((float) $grandTotalSelisih, 0, ',', '.'))
            ->toJson();
    }

    public function labaRugiDetil(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.keuangan.laba-rugi-detil', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rows' => $this->laporanKeuanganService->getLabaRugiDetil($startDate, $endDate),
            ],
        ));
    }

    public function labaRugiStandard(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('laporan.keuangan.laba-rugi-standard', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rows' => $this->laporanKeuanganService->getLabaRugiStandard($startDate, $endDate),
            ],
        ));
    }

    public function labaRugiPerParentCoa(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $coaId = (int) $request->integer('coaId');
        $data = $this->laporanKeuanganService->getLabaRugiPerParentCoa($startDate, $endDate, $coaId);

        return view('laporan.keuangan.laba-rugi-per-parent-coa', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'parentCoa' => $data['parent'],
                'rows' => $data['rows'],
            ],
        ));
    }

    public function neracaStandard(Request $request): View
    {
        $perDate = $request->string('perDate')->toString() ?: now()->toDateString();
        $data = $this->laporanKeuanganService->getNeracaStandard($perDate);

        return view('laporan.keuangan.neraca-standard', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'perDate' => $perDate,
                'rows' => $data['rows'],
                'subtotalAktiva' => $data['subtotalAktiva'],
                'subtotalPasiva' => $data['subtotalPasiva'],
                'subtotalEkuitas' => $data['subtotalEkuitas'],
                'subtotalPasivaEkuitas' => $data['subtotalPasivaEkuitas'],
            ],
        ));
    }

    public function neracaPerParentCoa(Request $request): View
    {
        $perDate = $request->string('perDate')->toString() ?: now()->toDateString();
        $coaId = (int) $request->integer('coaId');
        $data = $this->laporanKeuanganService->getNeracaPerParentCoa($perDate, $coaId);

        return view('laporan.keuangan.neraca-per-parent-coa', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'perDate' => $perDate,
                'parentCoa' => $data['parent'],
                'rows' => $data['rows'],
            ],
        ));
    }

    public function bukubesar(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $coaIds = collect((array) $request->input('coaIds', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $data = $this->laporanKeuanganService->getBukubesar($startDate, $endDate, $coaIds);

        return view('laporan.keuangan.bukubesar', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'coaOptions' => $data['coa'],
                'selectedCoaIds' => $coaIds,
                'rowsByCoa' => $data['data'],
            ],
        ));
    }

    public function neracaSaldo(Request $request): View
    {
        $perDate = $request->string('perDate')->toString() ?: now()->toDateString();

        return view('laporan.keuangan.neraca-saldo', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'perDate' => $perDate,
                'rows' => $this->laporanKeuanganService->getNeracaSaldo($perDate),
            ],
        ));
    }

    public function neracaDetil(Request $request): View
    {
        $perDate = $request->string('perDate')->toString() ?: now()->toDateString();

        return view('laporan.keuangan.neraca-detil', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'perDate' => $perDate,
                'rows' => $this->laporanKeuanganService->getNeracaDetil($perDate),
            ],
        ));
    }

    public function neracaRinci(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $tipeCoa = $request->string('tipeCoa')->toString();

        return view('laporan.keuangan.neraca-rinci', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedTipeCoa' => $tipeCoa,
                'tipeCoaOptions' => $this->laporanKeuanganService->getDaftarTipeCoaAktif(),
                'rows' => $this->laporanKeuanganService->getNeracaRinci($startDate, $endDate, $tipeCoa),
            ],
        ));
    }

    public function arusKas(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $data = $this->laporanKeuanganService->getArusKas($startDate, $endDate);

        return view('laporan.keuangan.arus-kas', array_merge(
            $this->laporanKeuanganService->getIdentitasLaporan(),
            [
                'page' => 'app',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'detailRows' => $data['detail'],
                'summaryRows' => $data['summary'],
                'kasAwal' => $data['kas_awal'],
                'kenaikanPenurunan' => $data['kenaikan_penurunan'],
                'kasAkhir' => $data['kas_akhir'],
            ],
        ));
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->date('startDate')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->date('endDate')?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [$startDate, $endDate];
    }
}
