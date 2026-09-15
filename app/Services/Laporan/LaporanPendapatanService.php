<?php

namespace App\Services\Laporan;

use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\Pelanggan;
use App\Models\PenerimaanPenjualanRinci;
use App\Models\PreferensiPerusahaan;
use App\Models\SimrsImportPendapatan;
use App\Models\SimrsImportPendapatanJualObat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class LaporanPendapatanService
{
    private const PIUTANG_BUCKET_KEYS = [
        'days_0_30',
        'days_31_60',
        'days_61_90',
        'days_over_90',
    ];

    public function getIdentitasLaporan(): array
    {
        $preferensi = PreferensiPerusahaan::query()->first();

        return [
            'logoRsUrl' => $preferensi?->logo_perusahaan ?? '',
            'namaRumahSakit' => $preferensi?->nama_perusahaan ?? 'Rumah Sakit',
        ];
    }

    public function getPelangganTerpilih(array $pelangganIds): Collection
    {
        if ($pelangganIds === []) {
            return collect();
        }

        return Pelanggan::query()
            ->whereIn('id', $pelangganIds)
            ->orderBy('kode_pelanggan')
            ->orderBy('nama_pelanggan')
            ->get(['id', 'kode_pelanggan', 'nama_pelanggan']);
    }

    public function getPelangganOptions(): Collection
    {
        return Pelanggan::query()
            ->orderBy('kode_pelanggan')
            ->orderBy('nama_pelanggan')
            ->get(['id', 'kode_pelanggan', 'nama_pelanggan']);
    }

    public function searchPelangganPiutang(string $search): Collection
    {
        return Pelanggan::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('kode_pelanggan', 'like', $like)
                        ->orWhere('nama_pelanggan', 'like', $like);
                });
            })
            ->orderBy('kode_pelanggan')
            ->orderBy('nama_pelanggan')
            ->limit(20)
            ->get(['id', 'kode_pelanggan', 'nama_pelanggan']);
    }

    public function searchCoaPiutang(string $search): Collection
    {
        return Coa::query()
            ->whereRaw('LOWER(COALESCE(tipe_coa, ?)) LIKE ?', ['', '%piutang%'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('kode', 'like', $like)
                        ->orWhere('nama', 'like', $like);
                });
            })
            ->orderBy('kode')
            ->limit(20)
            ->get(['id', 'kode', 'nama']);
    }

    public function getCoaPiutangTerpilih(?string $akunPiutang): ?Coa
    {
        if ($akunPiutang === null || ! ctype_digit($akunPiutang)) {
            return null;
        }

        return Coa::query()->find((int) $akunPiutang, ['id', 'kode', 'nama']);
    }

    public function getBukuPembantuPiutang(string $reportDate, array $pelangganIds = []): array
    {
        $reportDateCarbon = Carbon::parse($reportDate)->startOfDay();

        $fakturs = FakturPenjualan::query()
            ->select([
                'id',
                'pelanggan_id',
                'nomor_faktur',
                'tanggal_faktur',
                'keterangan',
                'grandtotal',
                'sudah_terbayar',
            ])
            ->with('pelanggan:id,kode_pelanggan,nama_pelanggan')
            ->withSum('penerimaanPenjualanRincis as total_alokasi', 'nominal_bayar')
            ->withSum([
                'penerimaanPenjualanRincis as total_alokasi_sampai_tanggal' => function (Builder $query) use ($reportDate) {
                    $query->whereHas(
                        'penerimaanPenjualan',
                        fn (Builder $penerimaanQuery) => $penerimaanQuery->whereDate('tanggal', '<=', $reportDate),
                    );
                },
            ], 'nominal_bayar')
            ->whereNotNull('pelanggan_id')
            ->whereNotNull('tanggal_faktur')
            ->whereDate('tanggal_faktur', '<=', $reportDate)
            ->when(
                $pelangganIds !== [],
                fn (Builder $query) => $query->whereIn('pelanggan_id', $pelangganIds),
            )
            ->get();

        $cardsByPelanggan = [];
        $summary = $this->emptyPiutangBuckets();

        foreach ($fakturs as $faktur) {
            if ($faktur->pelanggan === null || $faktur->tanggal_faktur === null) {
                continue;
            }

            $totalAlokasi = round((float) ($faktur->total_alokasi ?? 0), 2);
            $pembayaranLangsung = max(0, round((float) $faktur->sudah_terbayar - $totalAlokasi, 2));
            $pembayaranSampaiTanggal = round(
                $pembayaranLangsung + (float) ($faktur->total_alokasi_sampai_tanggal ?? 0),
                2,
            );
            $sisaPiutang = max(0, round((float) $faktur->grandtotal - $pembayaranSampaiTanggal, 2));

            if ($sisaPiutang < 0.005) {
                continue;
            }

            $tanggalFaktur = $faktur->tanggal_faktur->copy()->startOfDay();
            $umurHari = max(0, (int) $tanggalFaktur->diffInDays($reportDateCarbon, false));
            $bucketKey = $this->piutangBucketKeyForAge($umurHari);
            $pelangganId = (int) $faktur->pelanggan_id;

            if (! isset($cardsByPelanggan[$pelangganId])) {
                $cardsByPelanggan[$pelangganId] = [
                    'pelanggan_id' => $pelangganId,
                    'kode_pelanggan' => (string) ($faktur->pelanggan->kode_pelanggan ?? ''),
                    'nama_pelanggan' => (string) $faktur->pelanggan->nama_pelanggan,
                    'rows' => [],
                    'totals' => $this->emptyPiutangBuckets(),
                    'saldo_piutang' => 0.0,
                ];
            }

            $bucketAmounts = $this->emptyPiutangBuckets();
            $bucketAmounts[$bucketKey] = $sisaPiutang;

            $cardsByPelanggan[$pelangganId]['rows'][] = [
                'faktur_id' => (int) $faktur->id,
                'tanggal' => $tanggalFaktur->format('Y-m-d'),
                'tipe' => 'FJ',
                'nomor_referensi' => (string) $faktur->nomor_faktur,
                'keterangan' => (string) ($faktur->keterangan ?? ''),
                'umur_hari' => $umurHari,
                'sisa_piutang' => $sisaPiutang,
                ...$bucketAmounts,
            ];

            $cardsByPelanggan[$pelangganId]['totals'][$bucketKey] = round(
                $cardsByPelanggan[$pelangganId]['totals'][$bucketKey] + $sisaPiutang,
                2,
            );
            $cardsByPelanggan[$pelangganId]['saldo_piutang'] = round(
                $cardsByPelanggan[$pelangganId]['saldo_piutang'] + $sisaPiutang,
                2,
            );
            $summary[$bucketKey] = round($summary[$bucketKey] + $sisaPiutang, 2);
        }

        $cards = array_values($cardsByPelanggan);

        foreach ($cards as &$card) {
            usort($card['rows'], fn (array $left, array $right): int => [
                $left['tanggal'],
                $left['nomor_referensi'],
                $left['faktur_id'],
            ] <=> [
                $right['tanggal'],
                $right['nomor_referensi'],
                $right['faktur_id'],
            ]);
        }
        unset($card);

        usort($cards, fn (array $left, array $right): int => [
            $left['kode_pelanggan'],
            $left['nama_pelanggan'],
            $left['pelanggan_id'],
        ] <=> [
            $right['kode_pelanggan'],
            $right['nama_pelanggan'],
            $right['pelanggan_id'],
        ]);

        $summary['saldo_piutang'] = round(array_sum($summary), 2);

        return [
            'cards' => $cards,
            'summary' => $summary,
        ];
    }

    public function getRangkumanBukuPembantuPiutang(string $reportDate, array $pelangganIds = []): array
    {
        $report = $this->getBukuPembantuPiutang($reportDate, $pelangganIds);

        return [
            'rows' => array_map(fn (array $card): array => [
                'pelanggan_id' => $card['pelanggan_id'],
                'kode_pelanggan' => $card['kode_pelanggan'],
                'nama_pelanggan' => $card['nama_pelanggan'],
                'days_0_30' => $card['totals']['days_0_30'],
                'days_31_60' => $card['totals']['days_31_60'],
                'days_61_90' => $card['totals']['days_61_90'],
                'days_over_90' => $card['totals']['days_over_90'],
                'total_piutang' => $card['saldo_piutang'],
            ], $report['cards']),
            'summary' => $report['summary'],
        ];
    }

    public function streamBukuPembantuPiutangCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Pelanggan',
            'Nama Pelanggan',
            'Tanggal',
            'Tipe',
            'No. Referensi',
            'Keterangan',
            '0 - 30 Hari',
            '31 - 60 Hari',
            '61 - 90 Hari',
            '> 90 Hari',
        ]);

        foreach ($report['cards'] as $card) {
            foreach ($card['rows'] as $row) {
                fputcsv($handle, [
                    $card['kode_pelanggan'],
                    $card['nama_pelanggan'],
                    $row['tanggal'],
                    $row['tipe'],
                    $row['nomor_referensi'],
                    $row['keterangan'],
                    $this->formatCsvPiutangBucket($row['days_0_30']),
                    $this->formatCsvPiutangBucket($row['days_31_60']),
                    $this->formatCsvPiutangBucket($row['days_61_90']),
                    $this->formatCsvPiutangBucket($row['days_over_90']),
                ]);
            }

            fputcsv($handle, [
                $card['kode_pelanggan'],
                $card['nama_pelanggan'],
                '',
                '',
                'Saldo '.$card['nama_pelanggan'],
                '',
                $this->formatCsvNumber($card['totals']['days_0_30']),
                $this->formatCsvNumber($card['totals']['days_31_60']),
                $this->formatCsvNumber($card['totals']['days_61_90']),
                $this->formatCsvNumber($card['totals']['days_over_90']),
            ]);
        }

        fputcsv($handle, [
            '',
            '',
            '',
            '',
            'GRAND TOTAL',
            '',
            $this->formatCsvNumber($report['summary']['days_0_30']),
            $this->formatCsvNumber($report['summary']['days_31_60']),
            $this->formatCsvNumber($report['summary']['days_61_90']),
            $this->formatCsvNumber($report['summary']['days_over_90']),
        ]);

        fclose($handle);
    }

    public function streamRangkumanBukuPembantuPiutangCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Pelanggan',
            'Nama Pelanggan',
            '0 - 30 Hari',
            '31 - 60 Hari',
            '61 - 90 Hari',
            '> 90 Hari',
            'Total Piutang',
        ]);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, [
                $row['kode_pelanggan'],
                $row['nama_pelanggan'],
                $this->formatCsvNumber($row['days_0_30']),
                $this->formatCsvNumber($row['days_31_60']),
                $this->formatCsvNumber($row['days_61_90']),
                $this->formatCsvNumber($row['days_over_90']),
                $this->formatCsvNumber($row['total_piutang']),
            ]);
        }

        fputcsv($handle, [
            '',
            'GRAND TOTAL',
            $this->formatCsvNumber($report['summary']['days_0_30']),
            $this->formatCsvNumber($report['summary']['days_31_60']),
            $this->formatCsvNumber($report['summary']['days_61_90']),
            $this->formatCsvNumber($report['summary']['days_over_90']),
            $this->formatCsvNumber($report['summary']['saldo_piutang']),
        ]);

        fclose($handle);
    }

    public function getBukuPembantuPiutangMutasi(
        string $startDate,
        string $endDate,
        array $pelangganIds,
        string $statusSaldo = 'semua',
    ): array {
        if ($pelangganIds === []) {
            return $this->emptyPiutangMutasiReport();
        }

        $pelanggans = $this->getPelangganTerpilih($pelangganIds)->keyBy('id');
        $transactionsByPelanggan = $pelanggans->map(fn () => [])->all();

        $fakturs = FakturPenjualan::query()
            ->select([
                'id',
                'pelanggan_id',
                'akun_piutang_id',
                'nomor_faktur',
                'tanggal_faktur',
                'keterangan',
                'nama_pasien',
                'grandtotal',
                'sudah_terbayar',
            ])
            ->with('akunPiutang:id,kode,nama')
            ->withSum('penerimaanPenjualanRincis as total_alokasi', 'nominal_bayar')
            ->whereIn('pelanggan_id', $pelanggans->keys())
            ->whereNotNull('tanggal_faktur')
            ->whereDate('tanggal_faktur', '<=', $endDate)
            ->get();

        foreach ($fakturs as $faktur) {
            $pelangganId = (int) $faktur->pelanggan_id;
            $tanggal = $faktur->tanggal_faktur->format('Y-m-d');
            $akunPiutang = $this->formatAkunPiutang($faktur->akunPiutang);

            $transactionsByPelanggan[$pelangganId][] = [
                'tanggal' => $tanggal,
                'nomor' => (string) $faktur->nomor_faktur,
                'jenis' => 'Faktur',
                'referensi_faktur' => (string) $faktur->nomor_faktur,
                'keterangan' => (string) ($faktur->keterangan ?: $faktur->nama_pasien ?: 'Faktur pendapatan'),
                'akun' => $akunPiutang,
                'debit' => round((float) $faktur->grandtotal, 2),
                'kredit' => 0.0,
                'urutan_jenis' => 10,
                'urutan_id' => (int) $faktur->id,
                'faktur_id' => (int) $faktur->id,
                'penerimaan_id' => null,
            ];

            $penerimaanLangsung = max(
                0,
                round((float) $faktur->sudah_terbayar - (float) ($faktur->total_alokasi ?? 0), 2),
            );

            if ($penerimaanLangsung > 0) {
                $transactionsByPelanggan[$pelangganId][] = [
                    'tanggal' => $tanggal,
                    'nomor' => (string) $faktur->nomor_faktur,
                    'jenis' => 'Penerimaan langsung',
                    'referensi_faktur' => (string) $faktur->nomor_faktur,
                    'keterangan' => 'Penerimaan yang telah melekat saat faktur dibuat',
                    'akun' => $akunPiutang,
                    'debit' => 0.0,
                    'kredit' => $penerimaanLangsung,
                    'urutan_jenis' => 20,
                    'urutan_id' => (int) $faktur->id,
                    'faktur_id' => (int) $faktur->id,
                    'penerimaan_id' => null,
                ];
            }
        }

        $penerimaanRincis = PenerimaanPenjualanRinci::query()
            ->with([
                'penerimaanPenjualan:id,akun_piutang_id,nomer,tanggal,keterangan',
                'penerimaanPenjualan.akunPiutang:id,kode,nama',
                'fakturPenjualan:id,pelanggan_id,nomor_faktur',
            ])
            ->whereHas('fakturPenjualan', fn (Builder $query) => $query->whereIn('pelanggan_id', $pelanggans->keys()))
            ->whereHas('penerimaanPenjualan', fn (Builder $query) => $query->whereDate('tanggal', '<=', $endDate))
            ->get(['id', 'penerimaan_penjualan_id', 'faktur_penjualan_id', 'nominal_bayar']);

        foreach ($penerimaanRincis as $rincian) {
            $penerimaan = $rincian->penerimaanPenjualan;
            $faktur = $rincian->fakturPenjualan;

            if ($penerimaan === null || $faktur === null || $penerimaan->tanggal === null) {
                continue;
            }

            $pelangganId = (int) $faktur->pelanggan_id;
            $transactionsByPelanggan[$pelangganId][] = [
                'tanggal' => $penerimaan->tanggal->format('Y-m-d'),
                'nomor' => (string) $penerimaan->nomer,
                'jenis' => 'Penerimaan',
                'referensi_faktur' => (string) $faktur->nomor_faktur,
                'keterangan' => (string) ($penerimaan->keterangan ?: 'Penerimaan faktur '.$faktur->nomor_faktur),
                'akun' => $this->formatAkunPiutang($penerimaan->akunPiutang),
                'debit' => 0.0,
                'kredit' => round((float) $rincian->nominal_bayar, 2),
                'urutan_jenis' => 30,
                'urutan_id' => (int) $rincian->id,
                'faktur_id' => (int) $faktur->id,
                'penerimaan_id' => (int) $penerimaan->id,
            ];
        }

        $cards = [];

        foreach ($pelanggans as $pelangganId => $pelanggan) {
            $transactions = $transactionsByPelanggan[$pelangganId] ?? [];

            if ($transactions === []) {
                continue;
            }

            usort($transactions, fn (array $left, array $right): int => [
                $left['tanggal'],
                $left['urutan_jenis'],
                $left['urutan_id'],
            ] <=> [
                $right['tanggal'],
                $right['urutan_jenis'],
                $right['urutan_id'],
            ]);

            $saldoAwal = 0.0;
            $rows = [];

            foreach ($transactions as $transaction) {
                if ($transaction['tanggal'] < $startDate) {
                    $saldoAwal += $transaction['debit'] - $transaction['kredit'];

                    continue;
                }

                $rows[] = $transaction;
            }

            $saldoBerjalan = round($saldoAwal, 2);

            foreach ($rows as &$row) {
                $saldoBerjalan = round($saldoBerjalan + $row['debit'] - $row['kredit'], 2);
                $row['saldo'] = $saldoBerjalan;
            }
            unset($row);

            if (! $this->matchesPiutangStatusSaldo($saldoBerjalan, $statusSaldo)) {
                continue;
            }

            $accounts = collect($transactions)
                ->pluck('akun')
                ->unique()
                ->values()
                ->all();

            $cards[] = [
                'pelanggan_id' => (int) $pelanggan->id,
                'kode_pelanggan' => (string) ($pelanggan->kode_pelanggan ?? ''),
                'nama_pelanggan' => (string) $pelanggan->nama_pelanggan,
                'akun' => $accounts,
                'saldo_awal' => round($saldoAwal, 2),
                'total_debit' => round(collect($rows)->sum('debit'), 2),
                'total_kredit' => round(collect($rows)->sum('kredit'), 2),
                'saldo_akhir' => $saldoBerjalan,
                'rows' => $rows,
            ];
        }

        return [
            'cards' => $cards,
            'summary' => [
                'saldo_awal' => round(collect($cards)->sum('saldo_awal'), 2),
                'total_debit' => round(collect($cards)->sum('total_debit'), 2),
                'total_kredit' => round(collect($cards)->sum('total_kredit'), 2),
                'saldo_akhir' => round(collect($cards)->sum('saldo_akhir'), 2),
            ],
        ];
    }

    public function streamBukuPembantuPiutangMutasiCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Pelanggan',
            'Nama Pelanggan',
            'Akun',
            'Tanggal',
            'Nomor',
            'Jenis',
            'Referensi Faktur',
            'Keterangan',
            'Debit',
            'Kredit',
            'Saldo',
        ]);

        foreach ($report['cards'] as $card) {
            fputcsv($handle, [
                $card['kode_pelanggan'],
                $card['nama_pelanggan'],
                implode('; ', $card['akun']),
                '',
                '',
                'Saldo Awal',
                '',
                '',
                $this->formatCsvNumber(0),
                $this->formatCsvNumber(0),
                $this->formatCsvNumber($card['saldo_awal']),
            ]);

            foreach ($card['rows'] as $row) {
                fputcsv($handle, [
                    $card['kode_pelanggan'],
                    $card['nama_pelanggan'],
                    $row['akun'],
                    $row['tanggal'],
                    $row['nomor'],
                    $row['jenis'],
                    $row['referensi_faktur'],
                    $row['keterangan'],
                    $this->formatCsvNumber($row['debit']),
                    $this->formatCsvNumber($row['kredit']),
                    $this->formatCsvNumber($row['saldo']),
                ]);
            }
        }

        fclose($handle);
    }

    public function getQueryKunjungan(
        string $startDate,
        string $endDate,
        string $poli = '',
        string $penjamin = '',
    ): Builder {
        return SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->when($poli !== '', fn (Builder $query) => $query->where('poli', 'like', "%{$poli}%"))
            ->when($penjamin !== '', fn (Builder $query) => $query->where('penjamin', 'like', "%{$penjamin}%"))
            ->orderByDesc('tanggal_reg')
            ->orderByDesc('id');
    }

    public function getQueryPenjualanObat(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function streamKunjunganCsv(string $startDate, string $endDate): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'No. Billing',
            'Tanggal Registrasi',
            'Pasien',
            'Status Layanan',
            'Dokter',
            'Poli',
            'Penjamin',
            'Nominal',
        ]);

        $this->getQueryKunjunganForExport($startDate, $endDate)
            ->lazyById(1000, 'id')
            ->each(function (SimrsImportPendapatan $row) use ($handle) {
                fputcsv($handle, [
                    (string) $row->nomer_billing,
                    optional($row->tanggal_reg)->format('Y-m-d'),
                    (string) ($row->nama_pasien ?? ''),
                    (string) ($row->status_layanan ?? ''),
                    (string) ($row->dokter ?? ''),
                    (string) ($row->poli ?? ''),
                    (string) ($row->penjamin ?? ''),
                    $this->formatCsvNumber($row->total_tagihan),
                ]);
            });

        fclose($handle);
    }

    public function streamPenjualanObatCsv(string $startDate, string $endDate): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'No. Transaksi',
            'Tanggal',
            'Pelanggan',
            'Jenis',
            'Gudang',
            'Rekening',
            'Keterangan',
            'Ongkir',
            'PPN',
            'Nominal',
        ]);

        $this->getQueryPenjualanObatForExport($startDate, $endDate)
            ->lazyById(1000, 'id')
            ->each(function (SimrsImportPendapatanJualObat $row) use ($handle) {
                fputcsv($handle, [
                    (string) $row->nomer_transaksi,
                    optional($row->tanggal)->format('Y-m-d'),
                    (string) ($row->nama_pelanggan ?? ''),
                    (string) ($row->jenis_jual ?? ''),
                    (string) ($row->kode_gudang ?? ''),
                    (string) ($row->nama_rekening ?? ''),
                    (string) ($row->keterangan ?? ''),
                    $this->formatCsvNumber($row->ongkir),
                    $this->formatCsvNumber($row->ppn),
                    $this->formatCsvNumber($row->grandtotal),
                ]);
            });

        fclose($handle);
    }

    private function getQueryKunjunganForExport(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatan::query()
            ->select([
                'id',
                'nomer_billing',
                'tanggal_reg',
                'nama_pasien',
                'status_layanan',
                'dokter',
                'poli',
                'penjamin',
                'total_tagihan',
            ])
            ->betweenDates($startDate, $endDate)
            ->orderBy('id');
    }

    private function getQueryPenjualanObatForExport(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->select([
                'id',
                'nomer_transaksi',
                'tanggal',
                'nama_pelanggan',
                'jenis_jual',
                'kode_gudang',
                'nama_rekening',
                'keterangan',
                'ongkir',
                'ppn',
                'grandtotal',
            ])
            ->betweenDates($startDate, $endDate)
            ->orderBy('id');
    }

    private function formatCsvNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function emptyPiutangMutasiReport(): array
    {
        return [
            'cards' => [],
            'summary' => [
                'saldo_awal' => 0.0,
                'total_debit' => 0.0,
                'total_kredit' => 0.0,
                'saldo_akhir' => 0.0,
            ],
        ];
    }

    private function matchesPiutangStatusSaldo(float $saldoAkhir, string $statusSaldo): bool
    {
        return match ($statusSaldo) {
            'masih-piutang' => $saldoAkhir > 0,
            'lunas' => abs($saldoAkhir) < 0.005,
            default => true,
        };
    }

    private function formatAkunPiutang(mixed $coa): string
    {
        if ($coa === null) {
            return 'Tanpa akun piutang';
        }

        return sprintf('[%s] %s', $coa->kode, $coa->nama);
    }

    private function emptyPiutangBuckets(): array
    {
        return array_fill_keys(self::PIUTANG_BUCKET_KEYS, 0.0);
    }

    private function piutangBucketKeyForAge(int $umurHari): string
    {
        return match (true) {
            $umurHari <= 30 => 'days_0_30',
            $umurHari <= 60 => 'days_31_60',
            $umurHari <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }

    private function formatCsvPiutangBucket(mixed $value): string
    {
        return (float) $value > 0 ? $this->formatCsvNumber($value) : '';
    }
}
