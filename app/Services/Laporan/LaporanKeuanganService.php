<?php

namespace App\Services\Laporan;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\PreferensiPerusahaan;
use App\Models\SettingRba;
use App\Models\TipeCoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanKeuanganService
{
    private const MAKS_LEVEL_NERACA_STANDARD = 3;

    private const TIPE_LABA_RUGI = [
        'Pendapatan',
        'Pendapatan lain',
        'Beban',
        'Beban lain',
        'Beban Pokok Penjualan',
    ];

    private const KELOMPOK_AKTIVA = [
        'Kasbank',
        'Akun Piutang',
        'Piutang Usaha',
        'Persediaan',
        'Aktiva Lancar lainnya',
        'Aset Lancar lainnya',
        'Akumulasi Penyusutan',
        'Aset Tetap',
        'Aktiva Lainnya',
    ];

    private const KELOMPOK_PASIVA = [
        'Akun Hutang',
        'Utang Usaha',
        'Hutang Jangka Panjang',
        'Hutang Lancar lainnya',
        'Liabilitas Jangka Pendek',
    ];

    private const KELOMPOK_EKUITAS = [
        'Ekuitas',
    ];

    public function getIdentitasLaporan(): array
    {
        $preferensi = PreferensiPerusahaan::query()->first();

        return [
            'logoRsUrl' => $preferensi?->logo_perusahaan ?? '',
            'namaRumahSakit' => $preferensi?->nama_perusahaan ?? 'Rumah Sakit',
        ];
    }

    public function getQueryRincianTransaksiBukubesar(string $startDate, string $endDate): Builder
    {
        return BukuBesar::query()
            ->select([
                'bukubesar.id',
                'bukubesar.nomer',
                'bukubesar.tanggal',
                'bukubesar.sumber_transaksi',
                'bukubesar.keterangan',
                'bukubesar.nominal',
                'bukubesar.tipe_mutasi',
                'coa.kode as kode_coa',
                'coa.nama as nama_coa',
            ])
            ->leftJoin('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->whereBetween('bukubesar.tanggal', [$startDate, $endDate])
            ->orderByDesc('bukubesar.tanggal')
            ->orderByDesc('bukubesar.id');
    }

    public function getJurnalTidakBalance(string $startDate, string $endDate): Collection
    {
        $rows = DB::table('bukubesar')
            ->leftJoin('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->selectRaw('
                MIN(bukubesar.tanggal) as tanggal,
                bukubesar.nomer,
                bukubesar.sumber_transaksi,
                GROUP_CONCAT(DISTINCT CONCAT(COALESCE(coa.kode, "-"), " - ", COALESCE(coa.nama, "-")) ORDER BY coa.kode SEPARATOR ", ") as daftar_coa,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE 0 END) as total_debit,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "K" THEN bukubesar.nominal ELSE 0 END) as total_kredit
            ')
            ->whereBetween('bukubesar.tanggal', [$startDate, $endDate])
            ->groupBy('bukubesar.nomer', 'bukubesar.sumber_transaksi', 'bukubesar.sumber_id')
            ->havingRaw('ABS(SUM(CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE 0 END) - SUM(CASE WHEN bukubesar.tipe_mutasi = "K" THEN bukubesar.nominal ELSE 0 END)) > 0.01')
            ->orderBy('tanggal')
            ->orderBy('bukubesar.nomer')
            ->get();

        return $rows->map(function (object $row) {
            $debit = (float) ($row->total_debit ?? 0);
            $kredit = (float) ($row->total_kredit ?? 0);

            return [
                'tanggal' => (string) $row->tanggal,
                'nomer' => (string) $row->nomer,
                'sumber_transaksi' => (string) $row->sumber_transaksi,
                'daftar_coa' => (string) ($row->daftar_coa ?? ''),
                'total_debit' => $debit,
                'total_kredit' => $kredit,
                'selisih' => $debit - $kredit,
            ];
        });
    }

    public function getLabaRugiDetil(string $startDate, string $endDate): Collection
    {
        $movements = $this->ambilMutasiPerCoa($startDate, $endDate);
        $hasChildren = Coa::query()
            ->whereNotNull('parent_coa')
            ->pluck('parent_coa')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return Coa::query()
            ->active()
            ->whereIn('tipe_coa', $this->ambilNamaTipeLabaRugiAktif())
            ->orderBy('tipe_coa')
            ->orderBy('kode')
            ->get()
            ->reject(fn (Coa $coa) => $hasChildren->has((int) $coa->id))
            ->map(function (Coa $coa) use ($movements) {
                $nominal = $this->hitungSaldoLabaRugi(
                    tipeCoa: (string) $coa->tipe_coa,
                    debit: (float) ($movements[$coa->id]['debit'] ?? 0),
                    kredit: (float) ($movements[$coa->id]['kredit'] ?? 0),
                );

                return [
                    'coa_id' => (int) $coa->id,
                    'kode' => (string) $coa->kode,
                    'deskripsi' => (string) $coa->nama,
                    'tipe_coa' => (string) $coa->tipe_coa,
                    'nominal' => $nominal,
                ];
            })
            ->filter(fn (array $row) => abs($row['nominal']) > 0.01)
            ->values();
    }

    public function getLabaRugiStandard(string $startDate, string $endDate): Collection
    {
        $targetCoa = Coa::query()
            ->active()
            ->whereIn('tipe_coa', $this->ambilNamaTipeLabaRugiAktif())
            ->orderBy('kode')
            ->get();

        $mutasiPerCoa = $this->ambilMutasiPerCoa($startDate, $endDate);
        $rbaPerCoa = $this->ambilRbaPerCoa($targetCoa->pluck('id')->all(), $startDate, $endDate);
        $groupedByRoot = $targetCoa->groupBy(fn (Coa $coa) => $this->urutanLabaRugi((string) $coa->tipe_coa));

        $hasil = collect();
        foreach ($groupedByRoot->sortKeys() as $rootOrder => $items) {
            $itemsById = $items->keyBy(fn (Coa $coa) => (int) $coa->id);
            $childrenMap = $items->groupBy(fn (Coa $coa) => (int) ($coa->parent_coa ?? 0));
            $membersById = $items->keyBy(fn (Coa $coa) => (int) $coa->id);
            $topLevelRows = $items
                ->filter(fn (Coa $coa) => $coa->parent_coa === null || ! $membersById->has((int) $coa->parent_coa))
                ->sortBy('kode')
                ->values();

            $childRows = collect();
            foreach ($topLevelRows as $coa) {
                $childRows = $childRows->concat($this->flattenSubtreeLabaRugiStandard(
                    coa: $coa,
                    allCoa: $itemsById,
                    childrenMap: $childrenMap,
                    mutasiPerCoa: $mutasiPerCoa,
                    rbaPerCoa: $rbaPerCoa,
                    level: 1,
                    rootOrder: (int) $rootOrder,
                ));
            }

            $rootRow = [
                'kode' => $this->kodeRootLabaRugi((int) $rootOrder),
                'deskripsi' => $this->labelRootLabaRugi((int) $rootOrder),
                'nominal' => (float) $childRows->sum('nominal'),
                'rba_nominal' => (float) $childRows->sum('rba_nominal'),
                'sort_level' => 0,
                'level' => 0,
                'coa_id' => (int) $rootOrder,
                'parent_coa' => null,
                'tipe_coa' => $this->labelRootLabaRugi((int) $rootOrder),
                'root_order' => (int) $rootOrder,
                'has_children' => $childRows->isNotEmpty(),
            ];

            $hasil->push($rootRow);

            foreach ($childRows as $childRow) {
                $hasil->push($childRow);
            }
        }

        return $hasil->values();
    }

    public function getLabaRugiPerParentCoa(string $startDate, string $endDate, int $coaId): array
    {
        $parentCoa = Coa::query()->find($coaId);
        if ($parentCoa === null) {
            return [
                'parent' => null,
                'rows' => collect(),
            ];
        }

        $allCoa = Coa::query()
            ->active()
            ->whereIn('tipe_coa', $this->ambilNamaTipeLabaRugiAktif())
            ->orderBy('kode')
            ->get()
            ->keyBy('id');

        $children = $allCoa
            ->filter(fn (Coa $coa) => (int) ($coa->parent_coa ?? 0) === $coaId)
            ->sortBy('kode')
            ->values();

        if ($children->isEmpty()) {
            return [
                'parent' => $parentCoa,
                'rows' => collect(),
            ];
        }

        $childrenMap = $allCoa->groupBy(fn (Coa $coa) => (int) ($coa->parent_coa ?? 0));
        $mutasiPerCoa = $this->ambilMutasiPerCoa($startDate, $endDate);
        $rbaPerCoa = $this->ambilRbaPerCoa($allCoa->keys()->all(), $startDate, $endDate);

        $rows = $children->map(function (Coa $child) use ($allCoa, $childrenMap, $mutasiPerCoa, $rbaPerCoa) {
            $nominal = $this->hitungNominalSubtreeLabaRugiLeaf(
                coaId: (int) $child->id,
                allCoa: $allCoa,
                childrenMap: $childrenMap,
                mutasiPerCoa: $mutasiPerCoa,
            );

            $rbaNominal = $this->hitungRbaSubtreeLabaRugiLeaf(
                coaId: (int) $child->id,
                childrenMap: $childrenMap,
                rbaPerCoa: $rbaPerCoa,
            );

            return [
                'kode' => (string) $child->kode,
                'deskripsi' => (string) $child->nama,
                'nominal' => (float) $nominal,
                'rba_nominal' => (float) $rbaNominal,
                'sort_level' => 1,
                'coa_id' => (int) $child->id,
                'tipe_coa' => (string) $child->tipe_coa,
                'has_children' => $childrenMap->has((int) $child->id),
            ];
        })->values();

        return [
            'parent' => $parentCoa,
            'rows' => $rows,
        ];
    }

    public function getNeracaStandard(string $perDate): array
    {
        return $this->bangunNeracaTree($perDate);
    }

    public function getNeracaPerParentCoa(string $perDate, int $coaId): array
    {
        $allCoa = Coa::query()
            ->active()
            ->orderBy('kode')
            ->get()
            ->keyBy('id');

        $target = $allCoa->get($coaId);
        if ($target === null) {
            return [
                'parent' => null,
                'rows' => collect(),
            ];
        }

        $saldoPerCoa = $this->ambilSaldoSampaiTanggal($perDate);
        $childrenMap = $allCoa->groupBy(fn (Coa $coa) => $coa->parent_coa ?: 0);

        return [
            'parent' => $target,
            'rows' => $this->flattenSubtree(
                coa: $target,
                allCoa: $allCoa,
                childrenMap: $childrenMap,
                saldoPerCoa: $saldoPerCoa,
                level: 0,
                tipeNeraca: $this->klasifikasiNeraca((string) $target->tipe_coa),
            ),
        ];
    }

    public function getNeracaSaldo(string $perDate): Collection
    {
        $saldoPerCoa = $this->ambilSaldoSampaiTanggal($perDate);

        return Coa::query()
            ->active()
            ->orderBy('kode')
            ->get()
            ->map(function (Coa $coa) use ($saldoPerCoa) {
                $saldo = (float) ($saldoPerCoa[$coa->id] ?? 0);

                return [
                    'kode_coa' => (string) $coa->kode,
                    'nama_coa' => (string) $coa->nama,
                    'saldo_debit' => $saldo > 0 ? $saldo : 0,
                    'saldo_kredit' => $saldo < 0 ? abs($saldo) : 0,
                ];
            })
            ->filter(fn (array $row) => abs($row['saldo_debit']) > 0.01 || abs($row['saldo_kredit']) > 0.01)
            ->values();
    }

    public function getNeracaDetil(string $perDate): Collection
    {
        $saldoPerCoa = $this->ambilSaldoSampaiTanggal($perDate);
        $hasChildren = Coa::query()
            ->whereNotNull('parent_coa')
            ->pluck('parent_coa')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return Coa::query()
            ->active()
            ->whereIn('tipe_coa', $this->ambilNamaTipeNeracaAktif())
            ->orderBy('tipe_coa')
            ->orderBy('kode')
            ->get()
            ->reject(fn (Coa $coa) => $hasChildren->has((int) $coa->id))
            ->map(function (Coa $coa) use ($saldoPerCoa) {
                return [
                    'coa_id' => (int) $coa->id,
                    'kode' => (string) $coa->kode,
                    'deskripsi' => (string) $coa->nama,
                    'tipe_coa' => (string) $coa->tipe_coa,
                    'saldo' => (float) ($saldoPerCoa[$coa->id] ?? 0),
                ];
            })
            ->filter(fn (array $row) => abs($row['saldo']) > 0.01)
            ->values();
    }

    public function getNeracaRinci(string $startDate, string $endDate, array $tipeCoaTerpilih = []): Collection
    {
        $query = DB::table('bukubesar')
            ->join('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->selectRaw('
                coa.kode as kode_coa,
                coa.nama as nama_coa,
                coa.tipe_coa,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE 0 END) as total_debit,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "K" THEN bukubesar.nominal ELSE 0 END) as total_kredit,
                SUM(CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE -bukubesar.nominal END) as saldo_akhir
            ')
            ->whereBetween('bukubesar.tanggal', [$startDate, $endDate])
            ->groupBy('coa.id', 'coa.kode', 'coa.nama', 'coa.tipe_coa')
            ->orderBy('coa.tipe_coa')
            ->orderBy('coa.kode');

        if ($tipeCoaTerpilih !== []) {
            $query->whereIn('coa.tipe_coa', $tipeCoaTerpilih);
        }

        return $query->get()
            ->map(fn (object $row) => [
                'kode_coa' => (string) $row->kode_coa,
                'nama_coa' => (string) $row->nama_coa,
                'tipe_coa' => (string) $row->tipe_coa,
                'total_debit' => (float) $row->total_debit,
                'total_kredit' => (float) $row->total_kredit,
                'saldo_akhir' => (float) $row->saldo_akhir,
            ])
            ->filter(fn (array $row) => abs($row['saldo_akhir']) > 0.01)
            ->values();
    }

    public function getBukubesar(string $startDate, string $endDate, array $coaIds = []): array
    {
        $coaQuery = Coa::query()->leaf()->orderBy('kode');
        if ($coaIds !== []) {
            $coaQuery->whereIn('id', $coaIds);
        }

        $coaList = $coaQuery->get();
        $selectedIds = $coaList->pluck('id')->all();

        if ($selectedIds === []) {
            return [
                'coa' => collect(),
                'data' => collect(),
            ];
        }

        $openingRows = BukuBesar::query()
            ->selectRaw('coa_id, SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE -nominal END) as opening_balance')
            ->whereIn('coa_id', $selectedIds)
            ->where('tanggal', '<', $startDate)
            ->groupBy('coa_id')
            ->pluck('opening_balance', 'coa_id');

        $transactions = BukuBesar::query()
            ->with('coa:id,kode,nama')
            ->whereIn('coa_id', $selectedIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('coa_id')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $groupedTransactions = $transactions->groupBy('coa_id');
        $hasil = collect();

        foreach ($coaList as $coa) {
            $runningBalance = (float) ($openingRows[$coa->id] ?? 0);
            $rows = collect();

            if ($openingRows->has($coa->id)) {
                $rows->push([
                    'urutan' => 0,
                    'tanggal' => $startDate,
                    'nomer' => '',
                    'sumber_transaksi' => 'SALDO AWAL',
                    'keterangan' => 'Saldo awal periode',
                    'debit' => 0,
                    'kredit' => 0,
                    'saldo_berjalan' => $runningBalance,
                ]);
            }

            foreach ($groupedTransactions->get($coa->id, collect()) as $index => $trx) {
                $nominal = (float) $trx->nominal;
                $debit = $trx->tipe_mutasi === 'D' ? $nominal : 0;
                $kredit = $trx->tipe_mutasi === 'K' ? $nominal : 0;
                $runningBalance += $trx->tipe_mutasi === 'D' ? $nominal : -$nominal;

                $rows->push([
                    'urutan' => $index + 1,
                    'tanggal' => optional($trx->tanggal)->format('Y-m-d'),
                    'nomer' => $trx->nomer,
                    'sumber_transaksi' => $trx->sumber_transaksi,
                    'keterangan' => $trx->keterangan,
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo_berjalan' => $runningBalance,
                ]);
            }

            if ($rows->isNotEmpty()) {
                $hasil->push([
                    'coa_id' => (int) $coa->id,
                    'kode_coa' => (string) $coa->kode,
                    'nama_coa' => (string) $coa->nama,
                    'rows' => $rows,
                ]);
            }
        }

        return [
            'coa' => $coaList->map(fn (Coa $coa) => [
                'id' => (int) $coa->id,
                'kode' => (string) $coa->kode,
                'nama' => (string) $coa->nama,
            ]),
            'data' => $hasil->values(),
        ];
    }

    public function getArusKas(string $startDate, string $endDate): array
    {
        $openingCash = (float) BukuBesar::query()
            ->join('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->where('coa.tipe_coa', 'Kasbank')
            ->where('bukubesar.tanggal', '<', $startDate)
            ->sum(DB::raw('CASE WHEN bukubesar.tipe_mutasi = "D" THEN bukubesar.nominal ELSE -bukubesar.nominal END'));

        $detail = BukuBesar::query()
            ->select([
                'bukubesar.tanggal',
                'bukubesar.nomer',
                'bukubesar.sumber_transaksi',
                'bukubesar.keterangan',
                'bukubesar.nominal',
                'bukubesar.tipe_mutasi',
                'coa.kode as kode_coa',
                'coa.nama as nama_coa',
            ])
            ->join('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->where('coa.tipe_coa', 'Kasbank')
            ->whereBetween('bukubesar.tanggal', [$startDate, $endDate])
            ->orderBy('bukubesar.tanggal')
            ->orderBy('bukubesar.id')
            ->get()
            ->map(function (object $row) {
                $kategori = $this->klasifikasiArusKas((string) $row->sumber_transaksi);
                $kasMasuk = $row->tipe_mutasi === 'D' ? (float) $row->nominal : 0;
                $kasKeluar = $row->tipe_mutasi === 'K' ? (float) $row->nominal : 0;

                return [
                    'tanggal' => (string) $row->tanggal,
                    'nomer' => (string) $row->nomer,
                    'sumber_transaksi' => (string) $row->sumber_transaksi,
                    'keterangan' => (string) ($row->keterangan ?? ''),
                    'kode_coa' => (string) $row->kode_coa,
                    'nama_coa' => (string) $row->nama_coa,
                    'kategori_arus_kas' => $kategori,
                    'kas_masuk' => $kasMasuk,
                    'kas_keluar' => $kasKeluar,
                    'kas_bersih' => $kasMasuk - $kasKeluar,
                ];
            });

        $summary = $detail
            ->groupBy('kategori_arus_kas')
            ->map(function (Collection $items, string $kategori) {
                return [
                    'kategori' => $kategori,
                    'total_kas_masuk' => $items->sum('kas_masuk'),
                    'total_kas_keluar' => $items->sum('kas_keluar'),
                    'total_kas_bersih' => $items->sum('kas_bersih'),
                ];
            })
            ->sortBy(fn (array $row) => $this->urutanKategoriArusKas($row['kategori']))
            ->values();

        return [
            'detail' => $detail,
            'summary' => $summary,
            'kas_awal' => $openingCash,
            'kenaikan_penurunan' => $detail->sum('kas_bersih'),
            'kas_akhir' => $openingCash + $detail->sum('kas_bersih'),
        ];
    }

    public function getDaftarTipeCoaAktif(): Collection
    {
        return TipeCoa::query()
            ->active()
            ->orderBy('nama')
            ->pluck('nama');
    }

    private function ambilNamaTipeNeracaAktif(): array
    {
        return TipeCoa::query()
            ->active()
            ->whereNotIn('nama', self::TIPE_LABA_RUGI)
            ->orderBy('id')
            ->pluck('nama')
            ->all();
    }

    private function ambilNamaTipeLabaRugiAktif(): array
    {
        return TipeCoa::query()
            ->active()
            ->whereIn('nama', self::TIPE_LABA_RUGI)
            ->orderBy('id')
            ->pluck('nama')
            ->all();
    }

    private function bangunNeracaTree(string $perDate): array
    {
        $allCoa = Coa::query()
            ->active()
            ->whereIn('tipe_coa', $this->ambilNamaTipeNeracaAktif())
            ->orderBy('kode')
            ->get()
            ->keyBy('id');

        $saldoPerCoa = $this->ambilSaldoSampaiTanggal($perDate);
        $labaTahunBerjalan = $this->hitungLabaTahunBerjalan($perDate);
        $childrenMap = $allCoa->groupBy(fn (Coa $coa) => $coa->parent_coa ?: 0);
        $groupedByType = $allCoa->groupBy(fn (Coa $coa) => $this->klasifikasiNeraca((string) $coa->tipe_coa));

        $rows = collect();
        $totals = [
            'aktiva' => 0.0,
            'pasiva' => 0.0,
            'ekuitas' => 0.0,
        ];

        foreach (['AKTIVA', 'PASIVA', 'EKUITAS'] as $rootLabel) {
            $members = $groupedByType->get($rootLabel, collect());
            $membersById = $members->keyBy(fn (Coa $coa) => (int) $coa->id);
            $topLevel = $members
                ->filter(function (Coa $coa) use ($membersById) {
                    return $coa->parent_coa === null || ! $membersById->has((int) $coa->parent_coa);
                })
                ->sortBy('kode')
                ->values();
            $perluBarisLabaTahunBerjalan = $rootLabel === 'EKUITAS' && abs($labaTahunBerjalan) > 0.01;

            if ($topLevel->isEmpty() && ! $perluBarisLabaTahunBerjalan) {
                continue;
            }

            $rows->push([
                'coa_id' => null,
                'parent_coa' => null,
                'kode_coa' => $rootLabel === 'AKTIVA' ? '1' : ($rootLabel === 'PASIVA' ? '2' : '3'),
                'nama_coa' => $rootLabel,
                'tipe_coa' => $rootLabel,
                'saldo' => 0,
                'display_saldo' => null,
                'level' => 0,
                'has_children' => true,
                'is_root' => true,
            ]);

            foreach ($topLevel as $coa) {
                $subtree = $this->flattenSubtreeNeracaStandard(
                    coa: $coa,
                    childrenMap: $childrenMap,
                    saldoPerCoa: $saldoPerCoa,
                    level: 1,
                    tipeNeraca: $rootLabel,
                );

                $totals[strtolower($rootLabel)] += (float) ($subtree->first()['saldo'] ?? 0);
                $rows = $rows->concat($subtree);
            }

            if ($perluBarisLabaTahunBerjalan) {
                /**
                 * Laba rugi periode berjalan belum diposting ke akun ekuitas permanen,
                 * jadi di neraca kita tampilkan sebagai baris sintetis agar
                 * Aktiva = Pasiva + Ekuitas tetap mencerminkan posisi buku besar.
                 */
                $rows->push([
                    'coa_id' => null,
                    'parent_coa' => null,
                    'kode_coa' => '-',
                    'nama_coa' => 'Laba Tahun Berjalan',
                    'tipe_coa' => 'EKUITAS',
                    'saldo' => $labaTahunBerjalan,
                    'display_saldo' => $labaTahunBerjalan,
                    'level' => 1,
                    'has_children' => false,
                    'is_root' => false,
                ]);

                $totals['ekuitas'] += $labaTahunBerjalan;
            }
        }

        return [
            'rows' => $rows->values(),
            'subtotalAktiva' => $totals['aktiva'],
            'subtotalPasiva' => $totals['pasiva'],
            'subtotalEkuitas' => $totals['ekuitas'],
            'subtotalPasivaEkuitas' => $totals['pasiva'] + $totals['ekuitas'],
        ];
    }

    private function flattenSubtreeNeracaStandard(
        Coa $coa,
        Collection $childrenMap,
        array $saldoPerCoa,
        int $level,
        string $tipeNeraca,
    ): Collection {
        $children = $childrenMap->get((int) $coa->id, collect())
            ->sortBy('kode')
            ->values();

        $saldoSelf = $this->normalisasiSaldoNeraca(
            tipeNeraca: $tipeNeraca,
            saldoRaw: (float) ($saldoPerCoa[$coa->id] ?? 0),
        );
        $rows = collect();
        $saldoTotal = $saldoSelf;
        $isMaxDisplayedLevel = $level >= self::MAKS_LEVEL_NERACA_STANDARD;

        if (! $isMaxDisplayedLevel) {
            foreach ($children as $child) {
                $childRows = $this->flattenSubtreeNeracaStandard(
                    coa: $child,
                    childrenMap: $childrenMap,
                    saldoPerCoa: $saldoPerCoa,
                    level: $level + 1,
                    tipeNeraca: $tipeNeraca,
                );

                $saldoTotal += (float) ($childRows->first()['saldo'] ?? 0);
                $rows = $rows->concat($childRows);
            }
        } else {
            $saldoTotal += $this->sumSaldoDescendantsNeracaStandard(
                children: $children,
                childrenMap: $childrenMap,
                saldoPerCoa: $saldoPerCoa,
                tipeNeraca: $tipeNeraca,
            );
        }

        $current = collect([[
            'coa_id' => (int) $coa->id,
            'parent_coa' => $coa->parent_coa ? (int) $coa->parent_coa : null,
            'kode_coa' => (string) $coa->kode,
            'nama_coa' => (string) $coa->nama,
            'tipe_coa' => $tipeNeraca,
            'saldo' => $saldoTotal,
            'display_saldo' => ($children->isEmpty() || $isMaxDisplayedLevel) ? $saldoTotal : null,
            'level' => $level,
            'has_children' => $children->isNotEmpty(),
            'is_root' => false,
        ]]);

        return $current->concat($rows);
    }

    /**
     * Saat tampilan neraca dibatasi sampai level tertentu, seluruh nominal
     * turunan tetap harus diakumulasikan ke baris level paling bawah yang
     * masih ditampilkan agar subtotal tidak terhitung ganda di parent atas.
     */
    private function sumSaldoDescendantsNeracaStandard(
        Collection $children,
        Collection $childrenMap,
        array $saldoPerCoa,
        string $tipeNeraca,
    ): float {
        $total = 0.0;

        foreach ($children as $child) {
            $saldoChild = $this->normalisasiSaldoNeraca(
                tipeNeraca: $tipeNeraca,
                saldoRaw: (float) ($saldoPerCoa[$child->id] ?? 0),
            );

            $total += $saldoChild;
            $total += $this->sumSaldoDescendantsNeracaStandard(
                children: $childrenMap->get((int) $child->id, collect()),
                childrenMap: $childrenMap,
                saldoPerCoa: $saldoPerCoa,
                tipeNeraca: $tipeNeraca,
            );
        }

        return $total;
    }

    private function ambilMutasiPerCoa(string $startDate, string $endDate): array
    {
        return BukuBesar::query()
            ->selectRaw('
                coa_id,
                SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE 0 END) as debit,
                SUM(CASE WHEN tipe_mutasi = "K" THEN nominal ELSE 0 END) as kredit
            ')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->groupBy('coa_id')
            ->get()
            ->mapWithKeys(fn (BukuBesar $row) => [
                (int) $row->coa_id => [
                    'debit' => (float) $row->debit,
                    'kredit' => (float) $row->kredit,
                ],
            ])
            ->all();
    }

    /**
     * Setting RBA disimpan per tahun, sehingga laporan periode parsial perlu
     * mengalokasikan nilai tahunannya menjadi porsi bulanan sesuai rentang laporan.
     */
    private function ambilRbaPerCoa(array $coaIds, string $startDate, string $endDate): array
    {
        if ($coaIds === []) {
            return [];
        }

        $alokasiBulanPerTahun = $this->hitungAlokasiBulanPerTahun($startDate, $endDate);
        $rows = SettingRba::query()
            ->whereIn('coa_id', $coaIds)
            ->whereIn('tahun', array_keys($alokasiBulanPerTahun))
            ->get(['coa_id', 'tahun', 'total_nominal']);

        $hasil = [];
        foreach ($rows as $row) {
            $bulanTercakup = (int) ($alokasiBulanPerTahun[(int) $row->tahun] ?? 0);
            $hasil[(int) $row->coa_id] = (float) ($hasil[(int) $row->coa_id] ?? 0)
                + (((float) $row->total_nominal / 12) * $bulanTercakup);
        }

        return $hasil;
    }

    private function hitungAlokasiBulanPerTahun(string $startDate, string $endDate): array
    {
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        $hasil = [];

        for ($tahun = (int) $start->format('Y'); $tahun <= (int) $end->format('Y'); $tahun++) {
            $mulaiTahun = new \DateTimeImmutable(sprintf('%d-01-01', $tahun));
            $akhirTahun = new \DateTimeImmutable(sprintf('%d-12-31', $tahun));
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

    private function kumpulkanDescendantCoaIds(int $coaId, Collection $childrenMap): array
    {
        $hasil = [$coaId];

        foreach ($childrenMap->get($coaId, collect()) as $child) {
            $hasil = array_merge($hasil, $this->kumpulkanDescendantCoaIds((int) $child->id, $childrenMap));
        }

        return array_values(array_unique($hasil));
    }

    private function kumpulkanLeafDescendantCoaIds(int $coaId, Collection $childrenMap): array
    {
        $children = $childrenMap->get($coaId, collect());
        if ($children->isEmpty()) {
            return [$coaId];
        }

        $hasil = [];
        foreach ($children as $child) {
            $hasil = array_merge($hasil, $this->kumpulkanLeafDescendantCoaIds((int) $child->id, $childrenMap));
        }

        return array_values(array_unique($hasil));
    }

    private function hitungNominalSubtreeLabaRugiLeaf(
        int $coaId,
        Collection $allCoa,
        Collection $childrenMap,
        array $mutasiPerCoa,
    ): float {
        return collect($this->kumpulkanLeafDescendantCoaIds($coaId, $childrenMap))
            ->sum(function (int $descendantId) use ($allCoa, $mutasiPerCoa) {
                $coa = $allCoa->get($descendantId);
                if ($coa === null) {
                    return 0;
                }

                return $this->hitungSaldoLabaRugi(
                    tipeCoa: (string) $coa->tipe_coa,
                    debit: (float) ($mutasiPerCoa[$descendantId]['debit'] ?? 0),
                    kredit: (float) ($mutasiPerCoa[$descendantId]['kredit'] ?? 0),
                );
            });
    }

    private function hitungRbaSubtreeLabaRugiLeaf(
        int $coaId,
        Collection $childrenMap,
        array $rbaPerCoa,
    ): float {
        return collect($this->kumpulkanLeafDescendantCoaIds($coaId, $childrenMap))
            ->sum(fn (int $descendantId) => (float) ($rbaPerCoa[$descendantId] ?? 0));
    }

    private function flattenSubtreeLabaRugiStandard(
        Coa $coa,
        Collection $allCoa,
        Collection $childrenMap,
        array $mutasiPerCoa,
        array $rbaPerCoa,
        int $level,
        int $rootOrder,
    ): Collection {
        $children = $childrenMap->get((int) $coa->id, collect())
            ->sortBy('kode')
            ->values();

        $rows = collect([[
            'kode' => (string) $coa->kode,
            'deskripsi' => (string) $coa->nama,
            'nominal' => $this->hitungNominalSubtreeLabaRugiLeaf(
                coaId: (int) $coa->id,
                allCoa: $allCoa,
                childrenMap: $childrenMap,
                mutasiPerCoa: $mutasiPerCoa,
            ),
            'rba_nominal' => $this->hitungRbaSubtreeLabaRugiLeaf(
                coaId: (int) $coa->id,
                childrenMap: $childrenMap,
                rbaPerCoa: $rbaPerCoa,
            ),
            'sort_level' => $level,
            'level' => $level,
            'coa_id' => (int) $coa->id,
            'parent_coa' => $coa->parent_coa ? (int) $coa->parent_coa : null,
            'tipe_coa' => (string) $coa->tipe_coa,
            'root_order' => $rootOrder,
            'has_children' => $children->isNotEmpty(),
        ]]);

        foreach ($children as $child) {
            $rows = $rows->concat($this->flattenSubtreeLabaRugiStandard(
                coa: $child,
                allCoa: $allCoa,
                childrenMap: $childrenMap,
                mutasiPerCoa: $mutasiPerCoa,
                rbaPerCoa: $rbaPerCoa,
                level: $level + 1,
                rootOrder: $rootOrder,
            ));
        }

        return $rows;
    }

    private function ambilSaldoSampaiTanggal(string $perDate): array
    {
        return BukuBesar::query()
            ->selectRaw('coa_id, SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE -nominal END) as saldo')
            ->where('tanggal', '<=', $perDate)
            ->groupBy('coa_id')
            ->pluck('saldo', 'coa_id')
            ->map(fn ($saldo) => (float) $saldo)
            ->all();
    }

    /**
     * Laba Tahun Berjalan pada neraca standard harus mencerminkan akumulasi
     * laba rugi sejak awal tahun kalender sampai tanggal laporan. Perhitungan
     * tetap memakai akun leaf agar parent tidak menambah hitungan ganda.
     */
    private function hitungLabaTahunBerjalan(string $perDate): float
    {
        $startOfPeriod = substr($perDate, 0, 4).'-01-01';
        $mutasiPerCoa = $this->ambilMutasiPerCoa($startOfPeriod, $perDate);

        return $this->queryCoaLabaRugiLeafAktif()
            ->get(['id', 'tipe_coa'])
            ->sum(function (Coa $coa) use ($mutasiPerCoa) {
                $nominal = $this->hitungSaldoLabaRugi(
                    tipeCoa: (string) $coa->tipe_coa,
                    debit: (float) ($mutasiPerCoa[$coa->id]['debit'] ?? 0),
                    kredit: (float) ($mutasiPerCoa[$coa->id]['kredit'] ?? 0),
                );

                return $this->isTipePendapatan((string) $coa->tipe_coa)
                    ? $nominal
                    : ($this->isTipeBiaya((string) $coa->tipe_coa) ? $nominal * -1 : 0);
            });
    }

    /**
     * Laba rugi periode dan laba tahun berjalan harus memakai akun leaf agar
     * akun parent tidak ikut terhitung dua kali bersama turunannya.
     */
    private function queryCoaLabaRugiLeafAktif(): Builder
    {
        return Coa::query()
            ->active()
            ->leaf()
            ->whereIn('tipe_coa', $this->ambilNamaTipeLabaRugiAktif());
    }

    private function hitungSaldoLabaRugi(string $tipeCoa, float $debit, float $kredit): float
    {
        return $this->isTipePendapatan($tipeCoa)
            ? $kredit - $debit
            : $debit - $kredit;
    }

    private function isTipePendapatan(string $tipeCoa): bool
    {
        return in_array($tipeCoa, ['Pendapatan', 'Pendapatan lain'], true);
    }

    private function isTipeBiaya(string $tipeCoa): bool
    {
        return in_array($tipeCoa, ['Beban Pokok Penjualan', 'Beban', 'Beban lain'], true);
    }

    private function urutanLabaRugi(string $tipeCoa): int
    {
        return match ($tipeCoa) {
            'Pendapatan' => 1,
            'Pendapatan lain' => 2,
            'Beban Pokok Penjualan' => 3,
            'Beban' => 4,
            'Beban lain' => 5,
            default => 99,
        };
    }

    private function kodeRootLabaRugi(int $rootOrder): string
    {
        return match ($rootOrder) {
            1 => '4',
            2 => '8',
            3 => '5',
            4 => '6',
            5 => '9',
            default => '0',
        };
    }

    private function labelRootLabaRugi(int $rootOrder): string
    {
        return match ($rootOrder) {
            1 => 'Pendapatan',
            2 => 'Pendapatan lain',
            3 => 'Beban Pokok Penjualan',
            4 => 'Beban',
            5 => 'Beban lain',
            default => 'Lainnya',
        };
    }

    private function klasifikasiNeraca(string $tipeCoa): string
    {
        if (in_array($tipeCoa, self::KELOMPOK_AKTIVA, true)) {
            return 'AKTIVA';
        }

        if (in_array($tipeCoa, self::KELOMPOK_PASIVA, true)) {
            return 'PASIVA';
        }

        if (in_array($tipeCoa, self::KELOMPOK_EKUITAS, true)) {
            return 'EKUITAS';
        }

        return 'LAINNYA';
    }

    private function normalisasiSaldoNeraca(string $tipeNeraca, float $saldoRaw): float
    {
        return in_array($tipeNeraca, ['PASIVA', 'EKUITAS'], true)
            ? $saldoRaw * -1
            : $saldoRaw;
    }

    private function klasifikasiArusKas(string $sumberTransaksi): string
    {
        return match ($sumberTransaksi) {
            'Kasbank Pembayaran', 'Kasbank Penerimaan', 'Penerimaan Pendapatan', 'Pembayaran Pembelian', 'Invoice Pendapatan', 'Invoice Pembelian' => 'Operasional',
            'Jurnal Umum' => 'Investasi',
            default => 'Pendanaan',
        };
    }

    private function urutanKategoriArusKas(string $kategori): int
    {
        return match ($kategori) {
            'Operasional' => 1,
            'Investasi' => 2,
            'Pendanaan' => 3,
            default => 99,
        };
    }
}
