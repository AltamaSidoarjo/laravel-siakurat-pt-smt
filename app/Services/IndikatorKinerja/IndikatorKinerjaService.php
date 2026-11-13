<?php

namespace App\Services\IndikatorKinerja;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\StatusEvaluasi;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaManualData;
use App\Models\SettingRba;
use DateTimeImmutable;
use Illuminate\Support\Collection;

class IndikatorKinerjaService
{
    /**
     * @var Collection<int, Coa>|null
     */
    private ?Collection $cachedCoaTree = null;

    /**
     * Ambil seluruh COA tree (id, parent_coa) dengan in-memory memoization.
     *
     * @return Collection<int, Coa>
     */
    public function getCachedCoaTree(): Collection
    {
        return $this->cachedCoaTree ??= Coa::query()->get(['id', 'parent_coa']);
    }

    /**
     * Reset cache hierarki COA (berguna saat test atau perubahan dinamis).
     */
    public function clearCoaTreeCache(): void
    {
        $this->cachedCoaTree = null;
    }

    /**
     * Evaluasi seluruh indikator aktif untuk rentang tanggal tertentu.
     *
     * @return Collection<int, array>
     */
    public function evaluasiSemua(string $startDate, string $endDate): Collection
    {
        $indikators = IndikatorKinerja::query()
            ->aktif()
            ->with(['mappingCoa.coa', 'variabelManual'])
            ->get();

        return $indikators->map(function (IndikatorKinerja $indikator) use ($startDate, $endDate) {
            return $this->evaluasiIndikator($indikator, $startDate, $endDate);
        });
    }

    /**
     * Evaluasi satu indikator kinerja untuk rentang tanggal tertentu.
     *
     * @return array<string, mixed>
     */
    public function evaluasiIndikator(
        IndikatorKinerja $indikator,
        string $startDate,
        string $endDate,
        ?array $preloadedData = null
    ): array {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        $hariRiil = max(1, $start->diff($end)->days + 1);

        $mappings = $indikator->relationLoaded('mappingCoa')
            ? $indikator->mappingCoa
            : $indikator->mappingCoa()->get();

        $numMappings = $mappings->where('peran', PeranCoa::NUMERATOR)->where('is_excluded', false);
        $numExcluded = $mappings->where('peran', PeranCoa::NUMERATOR)->where('is_excluded', true);
        $numCoaIds = $numMappings->pluck('coa_id')->all();
        $numExcludedIds = $numExcluded->pluck('coa_id')->all();

        $pembilang = null;
        $penyebut = null;
        $catatan = null;

        // 1. Resolve Pembilang
        if ($indikator->basis_pembilang === BasisPembilang::COA) {
            $leafIds = $this->kumpulkanLeafDescendantCoaIds($numCoaIds, $numExcludedIds);
            if ($indikator->tipe_kalkulasi === TipeKalkulasi::TURNOVER) {
                $pembilang = $this->ambilSaldoNeracaCoa($leafIds, $endDate);
            } else {
                $pembilang = $this->ambilMutasiCoa($leafIds, $startDate, $endDate);
            }
        } elseif ($indikator->basis_pembilang === BasisPembilang::SURPLUS) {
            $pembilang = $this->ambilSurplusPeriode($startDate, $endDate);
        }

        // Additions & Deductions
        if ($pembilang !== null) {
            $addMappings = $mappings->where('peran', PeranCoa::ADDITION)->where('is_excluded', false);
            if ($addMappings->isNotEmpty()) {
                $addLeafIds = $this->kumpulkanLeafDescendantCoaIds($addMappings->pluck('coa_id')->all());
                $pembilang += $this->ambilMutasiCoa($addLeafIds, $startDate, $endDate);
            }

            $dedMappings = $mappings->where('peran', PeranCoa::DEDUCTION)->where('is_excluded', false);
            if ($dedMappings->isNotEmpty()) {
                $dedLeafIds = $this->kumpulkanLeafDescendantCoaIds($dedMappings->pluck('coa_id')->all());
                $pembilang -= $this->ambilMutasiCoa($dedLeafIds, $startDate, $endDate);
            }
        }

        // 2. Resolve Penyebut
        if ($indikator->basis_penyebut === BasisPenyebut::COA) {
            $denomMappings = $mappings->where('peran', PeranCoa::DENOMINATOR)->where('is_excluded', false);
            $denomExcluded = $mappings->where('peran', PeranCoa::DENOMINATOR)->where('is_excluded', true);
            $denomCoaIds = $denomMappings->pluck('coa_id')->all();
            $denomExcludedIds = $denomExcluded->pluck('coa_id')->all();
            $denomLeafIds = $this->kumpulkanLeafDescendantCoaIds($denomCoaIds, $denomExcludedIds);
            $penyebut = $this->ambilMutasiCoa($denomLeafIds, $startDate, $endDate);
        } elseif ($indikator->basis_penyebut === BasisPenyebut::RBA) {
            $leafIds = $this->kumpulkanLeafDescendantCoaIds($numCoaIds, $numExcludedIds);
            $rbaLeaf = $this->ambilRbaCoa($leafIds, $startDate, $endDate);
            if ($rbaLeaf > 0.0) {
                $penyebut = $rbaLeaf;
            } else {
                $penyebut = $this->ambilRbaCoa($numCoaIds, $startDate, $endDate);
            }
        } elseif ($indikator->basis_penyebut === BasisPenyebut::MANUAL) {
            if ($indikator->variabel_manual_id) {
                $manual = $this->ambilNilaiManualTerakhir((int) $indikator->variabel_manual_id, $endDate);
                $penyebut = $manual['nilai'];
                if ($manual['periode'] !== '-') {
                    $catatan = "Data per {$manual['periode']}";
                }
            }
        }

        // 3. Hitung Nilai berdasarkan Tipe Kalkulasi
        $nilai = null;
        if ($indikator->tipe_kalkulasi === TipeKalkulasi::MANUAL) {
            if ($indikator->variabel_manual_id) {
                $manual = $this->ambilNilaiManualTerakhir((int) $indikator->variabel_manual_id, $endDate);
                $nilai = $manual['nilai'];
                if ($manual['periode'] !== '-') {
                    $catatan = "Data per {$manual['periode']}";
                }
            }
        } elseif ($indikator->tipe_kalkulasi === TipeKalkulasi::TURNOVER) {
            $ratio = $this->safeDivide($pembilang, $penyebut);
            $nilai = $ratio !== null ? $ratio * $hariRiil : null;
        } elseif ($indikator->tipe_kalkulasi === TipeKalkulasi::RASIO) {
            if ($indikator->satuan === SatuanIndikator::JUTA_PER_ORG_BLN) {
                $startDay = (int) $start->format('j');
                $endDay = (int) $end->format('j');
                $endLastDay = (int) $end->format('t');
                $coveredMonths = array_sum($this->hitungAlokasiBulanPerTahun($startDate, $endDate));

                if ($startDay === 1 && $endDay === $endLastDay) {
                    $jumlahBulan = max(1.0, (float) $coveredMonths);
                } else {
                    $jumlahBulan = max(1.0 / 31.0, $hariRiil / 30.4166667);
                }

                $rataRataBulanan = $pembilang !== null ? ($pembilang / $jumlahBulan) : null;
                $perOrg = $this->safeDivide($rataRataBulanan, $penyebut);
                $nilai = $perOrg !== null ? ($perOrg / 1000000.0) : null;
            } elseif ($indikator->satuan === SatuanIndikator::PERSEN || $indikator->satuan?->value === '%') {
                $ratio = $this->safeDivide($pembilang, $penyebut);
                $nilai = $ratio !== null ? ($ratio * 100.0) : null;
            } else {
                $nilai = $this->safeDivide($pembilang, $penyebut);
            }
        }

        // 4. Evaluasi Status
        $status = $this->evaluasiStatus($nilai, $indikator);

        // 5. Format Nilai
        $nilaiFormatted = $nilai !== null
            ? number_format($nilai, 2, ',', '.').($indikator->satuan ? ' '.$indikator->satuan->value : '')
            : 'N/A';

        return [
            'indikator' => $indikator,
            'kode' => $indikator->kode,
            'nama' => $indikator->nama,
            'satuan' => $indikator->satuan?->value,
            'nilai' => $nilai !== null ? round($nilai, 2) : null,
            'nilai_formatted' => $nilaiFormatted,
            'status' => $status,
            'pembilang' => $pembilang !== null ? round($pembilang, 2) : null,
            'penyebut' => $penyebut !== null ? round($penyebut, 2) : null,
            'catatan' => $catatan,
        ];
    }

    /**
     * Hitung ringkasan status indikator (total, hijau, kuning, merah, netral).
     *
     * @return array<string, int>
     */
    public function getRingkasanStatus(string $startDate, string $endDate): array
    {
        $evaluasi = $this->evaluasiSemua($startDate, $endDate);

        return [
            'total' => $evaluasi->count(),
            'hijau' => $evaluasi->where('status', StatusEvaluasi::HIJAU)->count(),
            'kuning' => $evaluasi->where('status', StatusEvaluasi::KUNING)->count(),
            'merah' => $evaluasi->where('status', StatusEvaluasi::MERAH)->count(),
            'netral' => $evaluasi->where('status', StatusEvaluasi::NETRAL)->count(),
        ];
    }

    /**
     * Ambil data pergerakan tren bulanan 12 bulan untuk indikator tertentu.
     *
     * @return array<string, mixed>
     */
    public function getTrenBulanan(string $kode, int $tahun): array
    {
        $indikator = IndikatorKinerja::query()
            ->with(['mappingCoa.coa', 'variabelManual'])
            ->where('kode', $kode)
            ->firstOrFail();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $labelBulanList = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $data = [];
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $labelBulan = $labelBulanList[$bulan - 1];

            if ($tahun > $currentYear || ($tahun === $currentYear && $bulan > $currentMonth)) {
                $data[] = [
                    'bulan' => $bulan,
                    'label_bulan' => $labelBulan,
                    'nilai' => null,
                    'status' => StatusEvaluasi::NETRAL,
                ];

                continue;
            }

            $startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
            $endDate = (new DateTimeImmutable($startDate))->format('Y-m-t');

            $eval = $this->evaluasiIndikator($indikator, $startDate, $endDate);

            $data[] = [
                'bulan' => $bulan,
                'label_bulan' => $labelBulan,
                'nilai' => $eval['nilai'],
                'status' => $eval['status'],
            ];
        }

        return [
            'indikator' => [
                'kode' => $indikator->kode,
                'nama' => $indikator->nama,
                'satuan' => $indikator->satuan?->value,
            ],
            'data' => $data,
        ];
    }

    /**
     * Pembagian aman yang mencegah division by zero atau nilai null.
     */
    public function safeDivide(?float $num, ?float $denom): ?float
    {
        if ($num === null || $denom === null || (float) $denom === 0.0) {
            return null;
        }

        return (float) ($num / $denom);
    }

    /**
     * Evaluasi status RAG (Hijau, Kuning, Merah, Netral) berdasarkan pola evaluasi.
     */
    public function evaluasiStatus(?float $nilai, IndikatorKinerja $indikator): StatusEvaluasi
    {
        if ($nilai === null) {
            return StatusEvaluasi::NETRAL;
        }

        $pola = $indikator->pola_evaluasi;
        if ($pola === null) {
            return StatusEvaluasi::NETRAL;
        }

        $hijauMin = $indikator->target_hijau_min !== null ? (float) $indikator->target_hijau_min : null;
        $hijauMax = $indikator->target_hijau_max !== null ? (float) $indikator->target_hijau_max : null;
        $kuningMin = $indikator->target_kuning_min !== null ? (float) $indikator->target_kuning_min : null;
        $kuningMax = $indikator->target_kuning_max !== null ? (float) $indikator->target_kuning_max : null;

        if ($pola === PolaEvaluasi::HIGHER_IS_BETTER) {
            if ($hijauMin !== null && $nilai >= $hijauMin) {
                return StatusEvaluasi::HIJAU;
            }
            if ($kuningMin !== null && $nilai >= $kuningMin) {
                return StatusEvaluasi::KUNING;
            }
            if ($hijauMin !== null || $kuningMin !== null) {
                return StatusEvaluasi::MERAH;
            }

            return StatusEvaluasi::NETRAL;
        }

        if ($pola === PolaEvaluasi::LOWER_IS_BETTER) {
            if ($hijauMax !== null && $nilai <= $hijauMax) {
                return StatusEvaluasi::HIJAU;
            }
            if ($kuningMax !== null && $nilai <= $kuningMax) {
                return StatusEvaluasi::KUNING;
            }
            if ($hijauMax !== null || $kuningMax !== null) {
                return StatusEvaluasi::MERAH;
            }

            return StatusEvaluasi::NETRAL;
        }

        if ($pola === PolaEvaluasi::TARGET_BAND) {
            $hasHijau = $hijauMin !== null || $hijauMax !== null;
            $hasKuning = $kuningMin !== null || $kuningMax !== null;

            if (! $hasHijau && ! $hasKuning) {
                return StatusEvaluasi::NETRAL;
            }

            if ($hasHijau && ($hijauMin === null || $nilai >= $hijauMin) && ($hijauMax === null || $nilai <= $hijauMax)) {
                return StatusEvaluasi::HIJAU;
            }

            if ($hasKuning && ($kuningMin === null || $nilai >= $kuningMin) && ($kuningMax === null || $nilai <= $kuningMax)) {
                return StatusEvaluasi::KUNING;
            }

            return StatusEvaluasi::MERAH;
        }

        return StatusEvaluasi::NETRAL;
    }

    /**
     * Kumpulkan seluruh ID leaf account (akun cabang terdalam tanpa anak) dari kumpulan COA parent.
     * Mengabaikan akun-akun yang terdaftar dalam daftar excluded.
     *
     * @param  array<int, int>  $coaIds
     * @param  array<int, int>  $excludedIds
     * @return array<int, int>
     */
    public function kumpulkanLeafDescendantCoaIds(array $coaIds, array $excludedIds = []): array
    {
        if (empty($coaIds)) {
            return [];
        }

        $allCoas = $this->getCachedCoaTree();
        $childrenMap = [];
        foreach ($allCoas as $coa) {
            $parentId = (int) ($coa->parent_coa ?? 0);
            if ($parentId > 0) {
                $childrenMap[$parentId][] = (int) $coa->id;
            }
        }

        // Expand excluded IDs
        $allExcluded = [];
        $excludeQueue = array_map('intval', $excludedIds);
        while (! empty($excludeQueue)) {
            $currentExclude = array_shift($excludeQueue);
            if (! isset($allExcluded[$currentExclude])) {
                $allExcluded[$currentExclude] = true;
                if (isset($childrenMap[$currentExclude])) {
                    foreach ($childrenMap[$currentExclude] as $childId) {
                        if (! isset($allExcluded[$childId])) {
                            $excludeQueue[] = $childId;
                        }
                    }
                }
            }
        }

        // Traverse each root COA with cycle / visited guard
        $leafIds = [];
        $visited = [];
        $queue = array_map('intval', $coaIds);

        while (! empty($queue)) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            if (isset($allExcluded[$currentId])) {
                continue;
            }

            if (isset($childrenMap[$currentId]) && ! empty($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childId) {
                    if (! isset($allExcluded[$childId]) && ! isset($visited[$childId])) {
                        $queue[] = $childId;
                    }
                }
            } else {
                $leafIds[$currentId] = $currentId;
            }
        }

        return array_values($leafIds);
    }

    /**
     * Ambil mutasi transaksi (Kredit - Debet untuk Pendapatan, Debet - Kredit untuk Beban).
     *
     * @param  array<int, int>  $coaIds
     */
    public function ambilMutasiCoa(array $coaIds, string $startDate, string $endDate): float
    {
        if (empty($coaIds)) {
            return 0.0;
        }

        $coas = Coa::query()->whereIn('id', $coaIds)->get(['id', 'tipe_coa'])->keyBy('id');
        if ($coas->isEmpty()) {
            return 0.0;
        }

        $movements = BukuBesar::query()
            ->selectRaw('
                coa_id,
                SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE 0 END) as debit,
                SUM(CASE WHEN tipe_mutasi = "K" THEN nominal ELSE 0 END) as kredit
            ')
            ->whereIn('coa_id', $coaIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->groupBy('coa_id')
            ->get();

        $total = 0.0;
        foreach ($movements as $m) {
            $coa = $coas->get($m->coa_id);
            $tipeCoa = $coa ? (string) $coa->tipe_coa : '';
            $debit = (float) $m->debit;
            $kredit = (float) $m->kredit;

            if (in_array($tipeCoa, ['Pendapatan', 'Pendapatan lain', 'Ekuitas', 'Liabilitas Jangka Pendek', 'Utang Usaha', 'Hutang Lancar lainnya', 'Hutang Jangka Panjang', 'Akun Hutang'], true)) {
                $total += ($kredit - $debit);
            } else {
                $total += ($debit - $kredit);
            }
        }

        return (float) $total;
    }

    /**
     * Ambil saldo neraca kumulatif (Debet - Kredit) sampai dengan tanggal akhir periode.
     *
     * @param  array<int, int>  $coaIds
     */
    public function ambilSaldoNeracaCoa(array $coaIds, string $perDate): float
    {
        if (empty($coaIds)) {
            return 0.0;
        }

        $saldo = BukuBesar::query()
            ->whereIn('coa_id', $coaIds)
            ->where('tanggal', '<=', $perDate)
            ->selectRaw('SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE -nominal END) as saldo')
            ->value('saldo');

        return (float) ($saldo ?? 0.0);
    }

    /**
     * Ambil alokasi target RBA proporsional bulanan sesuai periode berjalan.
     *
     * @param  array<int, int>  $coaIds
     */
    public function ambilRbaCoa(array $coaIds, string $startDate, string $endDate): float
    {
        if (empty($coaIds)) {
            return 0.0;
        }

        $alokasiBulanPerTahun = $this->hitungAlokasiBulanPerTahun($startDate, $endDate);
        if (empty($alokasiBulanPerTahun)) {
            return 0.0;
        }

        $rows = SettingRba::query()
            ->whereIn('coa_id', $coaIds)
            ->whereIn('tahun', array_keys($alokasiBulanPerTahun))
            ->get(['coa_id', 'tahun', 'total_nominal']);

        $total = 0.0;
        foreach ($rows as $row) {
            $bulanTercakup = (int) ($alokasiBulanPerTahun[(int) $row->tahun] ?? 0);
            $total += (((float) $row->total_nominal / 12) * $bulanTercakup);
        }

        return (float) $total;
    }

    /**
     * Hitung total surplus usaha (Pendapatan - Beban) pada periode berjalan.
     */
    public function ambilSurplusPeriode(string $startDate, string $endDate): float
    {
        $movements = BukuBesar::query()
            ->join('coa', 'bukubesar.coa_id', '=', 'coa.id')
            ->whereBetween('bukubesar.tanggal', [$startDate, $endDate])
            ->whereIn('coa.tipe_coa', [
                'Pendapatan',
                'Pendapatan lain',
                'Beban Pokok Penjualan',
                'Beban',
                'Beban lain',
            ])
            ->selectRaw('
                coa.tipe_coa,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE 0 END) as debit,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "K" THEN bukubesar.nominal ELSE 0 END) as kredit
            ')
            ->groupBy('coa.tipe_coa')
            ->get();

        $totalPendapatan = 0.0;
        $totalBeban = 0.0;

        foreach ($movements as $m) {
            $debit = (float) $m->debit;
            $kredit = (float) $m->kredit;
            if (in_array($m->tipe_coa, ['Pendapatan', 'Pendapatan lain'], true)) {
                $totalPendapatan += ($kredit - $debit);
            } else {
                $totalBeban += ($debit - $kredit);
            }
        }

        return (float) ($totalPendapatan - $totalBeban);
    }

    /**
     * Ambil data manual terakhir pada atau sebelum tanggal akhir periode.
     *
     * @return array{nilai: float|null, periode: string}
     */
    public function ambilNilaiManualTerakhir(int $variabelId, string $endDate): array
    {
        $endYear = (int) date('Y', strtotime($endDate));
        $endMonth = (int) date('n', strtotime($endDate));

        $record = IndikatorKinerjaManualData::query()
            ->where('variabel_id', $variabelId)
            ->where(function ($q) use ($endYear, $endMonth) {
                $q->where('tahun', '<', $endYear)
                    ->orWhere(function ($q2) use ($endYear, $endMonth) {
                        $q2->where('tahun', $endYear)->where('bulan', '<=', $endMonth);
                    });
            })
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();

        if (! $record) {
            return [
                'nilai' => null,
                'periode' => '-',
            ];
        }

        $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$record->bulan - 1] ?? (string) $record->bulan;
        $periode = "{$namaBulan} {$record->tahun}";

        return [
            'nilai' => (float) $record->nilai,
            'periode' => $periode,
        ];
    }

    /**
     * Hitung alokasi bulan kalender per tahun untuk rentang tanggal tertentu.
     *
     * @return array<int, int>
     */
    public function hitungAlokasiBulanPerTahun(string $startDate, string $endDate): array
    {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        $hasil = [];

        for ($tahun = (int) $start->format('Y'); $tahun <= (int) $end->format('Y'); $tahun++) {
            $mulaiTahun = new DateTimeImmutable(sprintf('%d-01-01', $tahun));
            $akhirTahun = new DateTimeImmutable(sprintf('%d-12-31', $tahun));
            $periodeMulai = $start > $mulaiTahun ? $start : $mulaiTahun;
            $periodeAkhir = $end < $akhirTahun ? $end : $akhirTahun;

            if ($periodeMulai > $periodeAkhir) {
                $hasil[$tahun] = 0;

                continue;
            }

            $selisihTahun = ((int) $periodeAkhir->format('Y') - (int) $periodeMulai->format('Y')) * 12;
            $selisihBulan = (int) $periodeAkhir->format('n') - (int) $periodeMulai->format('n');
            $hasil[$tahun] = $selisihTahun + $selisihBulan + 1;
        }

        return $hasil;
    }
}
