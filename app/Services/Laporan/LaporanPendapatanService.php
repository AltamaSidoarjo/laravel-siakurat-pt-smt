<?php

namespace App\Services\Laporan;

use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\Pelanggan;
use App\Models\PenerimaanPenjualanRinci;
use App\Models\PreferensiPerusahaan;
use App\Models\SimrsImportPendapatan;
use App\Models\SimrsImportPendapatanJualObat;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function getQueryPendapatanDokter(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): QueryBuilder {
        return $this->getPendapatanDokterBaseQuery($startDate, $endDate, $pelaksanaId, $poli, $penjamin)
            ->select([
                'pelaksana_id',
                'dokter',
                'tanggal',
                'kode_akun',
                'layanan',
            ])
            ->selectRaw('COUNT(DISTINCT faktur_id) AS jumlah_billing')
            ->selectRaw('SUM(nominal) AS total_pendapatan')
            ->groupBy('pelaksana_id', 'dokter', 'tanggal', 'coa_id', 'kode_akun', 'layanan')
            ->orderBy('dokter')
            ->orderBy('tanggal')
            ->orderBy('kode_akun');
    }

    public function getPendapatanDokterSummary(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
        string $search = '',
    ): array {
        $query = $this->getPendapatanDokterBaseQuery(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $this->applyPendapatanDokterSearch($query, $search);

        return [
            'totalBilling' => (clone $query)->distinct()->count('faktur_id'),
            'grandTotal' => (float) (clone $query)->sum('nominal'),
        ];
    }

    public function getPelaksanaOptions(): Collection
    {
        return DB::table('pelaksana')
            ->orderBy('nama_pelaksana')
            ->get(['id', 'nama_pelaksana']);
    }

    public function applyPendapatanDokterSearch(QueryBuilder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $likeSearch = '%'.$search.'%';

        $query->where(function (QueryBuilder $searchQuery) use ($likeSearch): void {
            $searchQuery
                ->where('dokter', 'like', $likeSearch)
                ->orWhere('no_proyek', 'like', $likeSearch)
                ->orWhere('kode_akun', 'like', $likeSearch)
                ->orWhere('layanan', 'like', $likeSearch)
                ->orWhere('nama_poli', 'like', $likeSearch)
                ->orWhere('nama_penjamin', 'like', $likeSearch);
        });
    }

    public function getQueryKunjungan(
        string $startDate,
        string $endDate,
        array $poli = [],
        array $penjamin = [],
    ): Builder {
        return SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->when($poli !== [], fn (Builder $query) => $query->whereIn('poli', $poli))
            ->when($penjamin !== [], fn (Builder $query) => $query->whereIn('penjamin', $penjamin))
            ->orderByDesc('tanggal_reg')
            ->orderByDesc('id');
    }

    public function getKunjunganPoliOptions(): Collection
    {
        return SimrsImportPendapatan::query()
            ->whereNotNull('poli')
            ->where('poli', '<>', '')
            ->distinct()
            ->orderBy('poli')
            ->pluck('poli');
    }

    public function getKunjunganPenjaminOptions(): Collection
    {
        return SimrsImportPendapatan::query()
            ->whereNotNull('penjamin')
            ->where('penjamin', '<>', '')
            ->distinct()
            ->orderBy('penjamin')
            ->pluck('penjamin');
    }

    public function getQueryPenjualanObat(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function streamKunjunganCsv(
        string $startDate,
        string $endDate,
        array $poli = [],
        array $penjamin = [],
    ): void {
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

        $this->getQueryKunjunganForExport($startDate, $endDate, $poli, $penjamin)
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

    /**
     * @return array{
     *     companyName: string,
     *     periodLabel: string,
     *     startDate: string,
     *     endDate: string,
     *     dokterGroups: Collection,
     *     grandTotalDebit: float,
     *     grandTotalKredit: float,
     *     grandTotalMutasi: float,
     * }
     */
    public function getAktivitasDokterData(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null
    ): array {
        $invoiceRows = DB::table('faktur_penjualan_rinci as fpr')
            ->join('faktur_penjualan as fp', 'fp.id', '=', 'fpr.faktur_penjualan_id')
            ->join('pelaksana as p', 'p.id', '=', 'fpr.pelaksana_id')
            ->join('coa as c', 'c.id', '=', 'fpr.coa_id')
            ->whereBetween('fp.tanggal_faktur', [$startDate, $endDate])
            ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
            ->select([
                'p.id as pelaksana_id',
                'p.nama_pelaksana as dokter',
                'p.no_proyek',
                'c.id as coa_id',
                'c.kode as kode_akun',
                'c.nama as nama_akun',
                DB::raw("'faktur_penjualan' as source_type"),
                'fp.id as source_id',
                'fp.nomor_faktur as no_ref',
                'fp.tanggal_faktur as tanggal',
                'fp.keterangan',
                'fp.nama_pasien',
                'fp.nama_poli',
                'fp.nama_penjamin',
                'fpr.id as rincian_id',
                DB::raw('CASE WHEN fpr.subtotal < 0 THEN ABS(fpr.subtotal) ELSE 0 END as debit'),
                DB::raw('CASE WHEN fpr.subtotal >= 0 THEN fpr.subtotal ELSE 0 END as kredit'),
            ]);

        if ($this->hasJurnalUmumPelaksanaSupport()) {
            $journalRows = DB::table('jurnal_umum_rinci as jur')
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jur.jurnal_umum_id')
                ->join('pelaksana as p', 'p.id', '=', 'jur.pelaksana_id')
                ->join('coa as c', 'c.id', '=', 'jur.coa_id')
                ->whereBetween('ju.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
                ->select([
                    'p.id as pelaksana_id',
                    'p.nama_pelaksana as dokter',
                    'p.no_proyek',
                    'c.id as coa_id',
                    'c.kode as kode_akun',
                    'c.nama as nama_akun',
                    DB::raw("'jurnal_umum' as source_type"),
                    'ju.id as source_id',
                    'ju.nomer as no_ref',
                    'ju.tanggal as tanggal',
                    DB::raw("COALESCE(NULLIF(TRIM(jur.catatan), ''), ju.keterangan) as keterangan"),
                    DB::raw('NULL as nama_pasien'),
                    DB::raw('NULL as nama_poli'),
                    DB::raw('NULL as nama_penjamin'),
                    'jur.id as rincian_id',
                    DB::raw('COALESCE(jur.debit, 0) as debit'),
                    DB::raw('COALESCE(jur.kredit, 0) as kredit'),
                ]);

            $invoiceRows->unionAll($journalRows);
        }

        if ($this->hasKasbankPembayaranPelaksanaSupport()) {
            $kasbankRows = DB::table('kasbank_pembayaran_rinci as kbpr')
                ->join('kasbank_pembayaran as kbp', 'kbp.id', '=', 'kbpr.kasbank_pembayaran_id')
                ->join('pelaksana as p', 'p.id', '=', 'kbpr.pelaksana_id')
                ->join('coa as c', 'c.id', '=', 'kbpr.coa_id')
                ->whereBetween('kbp.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
                ->select([
                    'p.id as pelaksana_id',
                    'p.nama_pelaksana as dokter',
                    'p.no_proyek',
                    'c.id as coa_id',
                    'c.kode as kode_akun',
                    'c.nama as nama_akun',
                    DB::raw("'kasbank_pembayaran' as source_type"),
                    'kbp.id as source_id',
                    'kbp.nomer as no_ref',
                    'kbp.tanggal as tanggal',
                    DB::raw("COALESCE(NULLIF(TRIM(kbpr.catatan), ''), kbp.keterangan) as keterangan"),
                    DB::raw('NULL as nama_pasien'),
                    DB::raw('NULL as nama_poli'),
                    DB::raw('NULL as nama_penjamin'),
                    'kbpr.id as rincian_id',
                    DB::raw('COALESCE(kbpr.nominal, 0) as debit'),
                    DB::raw('0 as kredit'),
                ]);

            $invoiceRows->unionAll($kasbankRows);
        }

        $rows = DB::query()
            ->fromSub($invoiceRows, 'akt')
            ->orderBy('dokter')
            ->orderBy('nama_akun')
            ->orderBy('tanggal')
            ->orderBy('no_ref')
            ->orderBy('source_type')
            ->orderBy('rincian_id')
            ->get();

        $dokterGroups = $rows
            ->groupBy('pelaksana_id')
            ->map(function (Collection $doctorRows): array {
                $firstRow = $doctorRows->first();
                $doctorTotalDebit = 0.0;
                $doctorTotalKredit = 0.0;

                $kategoriGroups = $doctorRows
                    ->groupBy('coa_id')
                    ->map(function (Collection $coaRows) use (&$doctorTotalDebit, &$doctorTotalKredit): array {
                        $firstCoaRow = $coaRows->first();
                        $runningMutasi = 0.0;
                        $subtotalDebit = 0.0;
                        $subtotalKredit = 0.0;

                        $rincian = $coaRows->map(function (object $row) use (&$runningMutasi, &$subtotalDebit, &$subtotalKredit): array {
                            $debit = (float) $row->debit;
                            $kredit = (float) $row->kredit;
                            $nominal = $kredit - $debit;
                            $runningMutasi += $nominal;
                            $subtotalDebit += $debit;
                            $subtotalKredit += $kredit;

                            $isJournal = $row->source_type === 'jurnal_umum';
                            $isKasbank = $row->source_type === 'kasbank_pembayaran';
                            $deskripsi = trim((string) ($row->keterangan ?: $row->nama_pasien ?: '-'));

                            return [
                                'source_type' => (string) $row->source_type,
                                'source_id' => (int) $row->source_id,
                                'faktur_id' => ($isJournal || $isKasbank) ? null : (int) $row->source_id,
                                'tanggal' => Carbon::parse($row->tanggal)->format('Y-m-d'),
                                'tanggal_format' => Carbon::parse($row->tanggal)->format('d/m/Y'),
                                'tipe' => match ((string) $row->source_type) {
                                    'jurnal_umum' => 'Jurnal Umum',
                                    'kasbank_pembayaran' => 'Kasbank Pembayaran',
                                    default => 'Faktur Penjualan',
                                },
                                'no_ref' => (string) $row->no_ref,
                                'nama_pasien' => (string) ($row->nama_pasien ?? ''),
                                'poli' => (string) ($row->nama_poli ?? ''),
                                'penjamin' => (string) ($row->nama_penjamin ?? ''),
                                'deskripsi' => $deskripsi,
                                'debit' => $debit,
                                'kredit' => $kredit,
                                'nominal' => $nominal,
                                'mutasi' => $runningMutasi,
                            ];
                        })->values();

                        $doctorTotalDebit += $subtotalDebit;
                        $doctorTotalKredit += $subtotalKredit;

                        return [
                            'coa_id' => (int) $firstCoaRow->coa_id,
                            'kode_akun' => (string) $firstCoaRow->kode_akun,
                            'nama_akun' => mb_strtoupper((string) $firstCoaRow->nama_akun),
                            'total_debit' => $subtotalDebit,
                            'total_kredit' => $subtotalKredit,
                            'total_mutasi' => $runningMutasi,
                            'rincian' => $rincian,
                        ];
                    })->values();

                $doctorTotalMutasi = $doctorTotalKredit - $doctorTotalDebit;

                return [
                    'pelaksana_id' => (int) $firstRow->pelaksana_id,
                    'dokter' => mb_strtoupper((string) $firstRow->dokter),
                    'no_proyek' => (string) $firstRow->no_proyek,
                    'jumlah_transaksi' => $doctorRows->count(),
                    'kategori' => $kategoriGroups,
                    'total_debit' => $doctorTotalDebit,
                    'total_kredit' => $doctorTotalKredit,
                    'total_mutasi' => $doctorTotalMutasi,
                ];
            })->values();

        $grandTotalDebit = (float) $dokterGroups->sum('total_debit');
        $grandTotalKredit = (float) $dokterGroups->sum('total_kredit');
        $grandTotalMutasi = $grandTotalKredit - $grandTotalDebit;

        $companyName = config('siakurat.rs_name', 'RSA BOJONEGORO');
        $logoRsUrl = '';

        if (Schema::hasTable('preferensi_perusahaan')) {
            $preferensi = PreferensiPerusahaan::query()->first();
            $companyName = $preferensi?->nama_perusahaan ?: $companyName;
            $logoRsUrl = $preferensi?->logo_perusahaan ?? '';
        }

        return [
            'companyName' => $companyName,
            'logoRsUrl' => $logoRsUrl,
            'periodLabel' => Carbon::parse($startDate)->locale('id')->translatedFormat('j F Y')
                .' - '.Carbon::parse($endDate)->locale('id')->translatedFormat('j F Y'),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalTransaksi' => $rows->count(),
            'totalKategori' => $rows->pluck('coa_id')->unique()->count(),
            'dokterGroups' => $dokterGroups,
            'grandTotalDebit' => $grandTotalDebit,
            'grandTotalKredit' => $grandTotalKredit,
            'grandTotalMutasi' => $grandTotalMutasi,
        ];
    }

    public function renderAktivitasDokterPdf(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null
    ): string {
        $report = $this->getAktivitasDokterData(
            $startDate,
            $endDate,
            $pelaksanaId
        );

        $html = view('laporan.pendapatan.aktivitas-dokter-pdf', [
            'companyName' => $report['companyName'],
            'logoRsUrl' => $report['logoRsUrl'],
            'periodLabel' => $report['periodLabel'],
            'totalTransaksi' => $report['totalTransaksi'],
            'totalKategori' => $report['totalKategori'],
            'dokterGroups' => $report['dokterGroups'],
            'grandTotalDebit' => $report['grandTotalDebit'],
            'grandTotalKredit' => $report['grandTotalKredit'],
            'grandTotalMutasi' => $report['grandTotalMutasi'],
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function streamAktivitasDokterExcel(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null
    ): void {
        $report = $this->getAktivitasDokterData(
            $startDate,
            $endDate,
            $pelaksanaId
        );

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle('Aktivitas Dokter')
            ->setSubject('Laporan aktivitas dokter');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Aktivitas Dokter');
        $sheet->setShowGridlines(true);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        foreach (['A1:G1', 'A2:G2', 'A3:G3'] as $range) {
            $sheet->mergeCells($range);
        }

        $sheet->setCellValue('A1', mb_strtoupper($report['companyName']));
        $sheet->setCellValue('A2', 'AKTIVITAS DOKTER');
        $sheet->setCellValue('A3', $report['periodLabel']);
        $sheet->getStyle('A1:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->setColor(new Color('0076B5'));
        $sheet->getStyle('A3')->getFont()->setSize(10)->setColor(new Color('555555'));

        $currentRow = 5;

        if ($report['dokterGroups']->isEmpty()) {
            $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data aktivitas dokter pada periode ini.');
            $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        } else {
            foreach ($report['dokterGroups'] as $dokter) {
                // Doctor Header
                $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $dokter['dokter'].(! empty($dokter['no_proyek']) ? ' ('.$dokter['no_proyek'].')' : ''));
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true)->setSize(10)->setColor(new Color('0F172A'));
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getLeft()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->setColor(new Color('006837'));
                $currentRow++;

                // Table Header
                $sheet->fromArray(['#', 'TANGGAL', 'NO. REFERENSI', 'PASIEN & KETERANGAN', 'KATEGORI TINDAKAN', 'NOMINAL JASA (RP)', 'KUMULATIF (RP)'], null, "A{$currentRow}");
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true)->setSize(9)->setColor(new Color(Color::COLOR_WHITE));
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF334155');
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$currentRow}:G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $currentRow++;

                $rowNum = 1;
                foreach ($dokter['kategori'] as $kategori) {
                    // Category Name Row
                    $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", 'Kategori: '.$kategori['nama_akun']);
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setColor(new Color('0F172A'));
                    $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF8FAFC');
                    $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getLeft()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->setColor(new Color('0284C7'));
                    $currentRow++;

                    foreach ($kategori['rincian'] as $item) {
                        $desc = $item['nama_pasien'] ?: $item['deskripsi'];
                        if (($item['source_type'] ?? 'faktur_penjualan') === 'jurnal_umum') {
                            $desc = '[Jurnal Umum] '.$desc;
                        } elseif (($item['source_type'] ?? '') === 'kasbank_pembayaran') {
                            $desc = '[Kasbank Pembayaran] '.$desc;
                        } elseif (! empty($item['poli']) || ! empty($item['penjamin'])) {
                            $desc .= ' ('.implode(' - ', array_filter([$item['poli'], $item['penjamin']])).')';
                        }

                        $sheet->setCellValue("A{$currentRow}", $rowNum++);
                        $sheet->setCellValueExplicit("B{$currentRow}", $item['tanggal_format'] ?? $item['tanggal'], DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit("C{$currentRow}", $item['no_ref'], DataType::TYPE_STRING);
                        $sheet->setCellValue("D{$currentRow}", $desc);
                        $sheet->setCellValue("E{$currentRow}", $kategori['nama_akun']);
                        $sheet->setCellValue("F{$currentRow}", (float) $item['nominal']);
                        $sheet->setCellValue("G{$currentRow}", (float) $item['mutasi']);
                        $currentRow++;
                    }

                    // Category Subtotal Row
                    $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", 'TOTAL '.$kategori['nama_akun'].':');
                    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->setCellValue("F{$currentRow}", (float) $kategori['total_mutasi']);
                    $sheet->setCellValue("G{$currentRow}", (float) $kategori['total_mutasi']);
                    $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->setColor(new Color('CBD5E1'));
                    $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->setColor(new Color('CBD5E1'));
                    $currentRow++;
                }

                // Doctor Total Row
                $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'TOTAL JASA '.$dokter['dokter'].':');
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->setCellValue("F{$currentRow}", (float) $dokter['total_mutasi']);
                $sheet->setCellValue("G{$currentRow}", (float) $dokter['total_mutasi']);
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0FDF4');
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->setColor(new Color('22C55E'));
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->setColor(new Color('22C55E'));
                $currentRow += 2;
            }

            // Grand Total if multiple doctors
            if ($report['dokterGroups']->count() > 1) {
                $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'TOTAL KESELURUHAN SELURUH DOKTER:');
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->setCellValue("F{$currentRow}", (float) $report['grandTotalMutasi']);
                $sheet->setCellValue("G{$currentRow}", (float) $report['grandTotalMutasi']);
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->setColor(new Color(Color::COLOR_BLACK));
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_DOUBLE)
                    ->setColor(new Color(Color::COLOR_BLACK));
                $currentRow++;
            }
        }

        $lastRow = $currentRow - 1;
        $sheet->getStyle("F5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F5:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(46);
        $sheet->getColumnDimension('E')->setWidth(26);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->freezePane('A6');

        try {
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function renderPendapatanDokterPdf(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): string {
        $report = $this->getPendapatanDokterReportData(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $html = view('laporan.pendapatan.dokter-pdf', [
            'companyName' => $report['companyName'],
            'periodLabel' => $report['periodLabel'],
            'dokterGroups' => $report['dokterGroups'],
            'grandTotal' => $report['grandTotal'],
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Serif');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function streamPendapatanDokterExcel(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): void {
        $report = $this->getPendapatanDokterReportData(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle('Pendapatan Dokter')
            ->setSubject('Laporan pendapatan dokter');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pendapatan Dokter');
        $sheet->setShowGridlines(true);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.55)
            ->setRight(0.45)
            ->setBottom(0.65)
            ->setLeft(0.45);

        foreach (['A1:C1', 'A2:C2', 'A3:C3'] as $range) {
            $sheet->mergeCells($range);
        }

        $sheet->setCellValue('A1', mb_strtoupper($report['companyName']));
        $sheet->setCellValue('A2', 'PENDAPATAN DOKTER');
        $sheet->setCellValue('A3', $report['periodLabel']);
        $sheet->getStyle('A1:C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->setColor(new Color('0076B5'));
        $sheet->getStyle('A3')->getFont()->setSize(11)->setColor(new Color('D53B32'));
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(21);
        $sheet->getRowDimension(3)->setRowHeight(20);

        $sheet->fromArray(['Kode Akun', 'Keterangan', 'Saldo'], null, 'A5');
        $sheet->getStyle('A5:C5')->getFont()->setBold(true);
        $sheet->getStyle('A5:C5')->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->setColor(new Color('C8C8C8'));
        $sheet->getStyle('C5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $currentRow = 6;
        $doctorResultCells = [];

        foreach ($report['dokterGroups'] as $dokter) {
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $dokter['nama']);
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
            $currentRow++;

            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Pendapatan');
            $sheet->getStyle("A{$currentRow}")->getFont()->setColor(new Color('0076B5'));
            $currentRow++;

            $groupSubtotalCells = [];

            foreach ($dokter['kelompok'] as $kelompok) {
                $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $kelompok['nama']);
                $sheet->getStyle("A{$currentRow}")->getFont()->setColor(new Color('0076B5'));
                $currentRow++;

                $detailStartRow = $currentRow;

                foreach ($kelompok['rincian'] as $rincian) {
                    $sheet->setCellValueExplicit("A{$currentRow}", $rincian->kode_akun_format, DataType::TYPE_STRING);
                    $sheet->setCellValue("B{$currentRow}", $rincian->nama_akun);
                    $sheet->setCellValue("C{$currentRow}", (float) $rincian->total_pendapatan);
                    $currentRow++;
                }

                $detailEndRow = $currentRow - 1;
                $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'Total '.$kelompok['nama']);
                $sheet->setCellValue("C{$currentRow}", "=SUM(C{$detailStartRow}:C{$detailEndRow})");
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setColor(new Color('24BD72'));
                $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new Color('24BD72'));
                $groupSubtotalCells[] = "C{$currentRow}";
                $currentRow++;
            }

            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan');
            $sheet->setCellValue("C{$currentRow}", '=SUM('.implode(',', $groupSubtotalCells).')');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setColor(new Color('24BD72'));
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color('24BD72'));
            $doctorTotalCell = "C{$currentRow}";
            $currentRow++;

            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan Dokter '.$dokter['nama']);
            $sheet->setCellValue("C{$currentRow}", "={$doctorTotalCell}");
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color(Color::COLOR_BLACK));
            $doctorResultCells[] = "C{$currentRow}";
            $currentRow += 2;
        }

        if ($doctorResultCells === []) {
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data pendapatan dokter pada periode ini.');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        } else {
            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan Seluruh Dokter');
            $sheet->setCellValue("C{$currentRow}", '=SUM('.implode(',', $doctorResultCells).')');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_DOUBLE)
                ->setColor(new Color(Color::COLOR_BLACK));
            $currentRow++;
        }

        $lastRow = $currentRow - 1;
        $sheet->getStyle("C6:C{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(58);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->freezePane('A6');
        $sheet->getPageSetup()->setPrintArea("A1:C{$lastRow}");

        try {
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function getQueryKunjunganForExport(
        string $startDate,
        string $endDate,
        array $poli,
        array $penjamin,
    ): Builder {
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
            ->when($poli !== [], fn (Builder $query) => $query->whereIn('poli', $poli))
            ->when($penjamin !== [], fn (Builder $query) => $query->whereIn('penjamin', $penjamin))
            ->orderBy('id');
    }

    private function getPendapatanDokterReportRows(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): Collection {
        return $this->getPendapatanDokterBaseQuery($startDate, $endDate, $pelaksanaId, $poli, $penjamin)
            ->leftJoin('coa as pc', 'pc.id', '=', 'pd.parent_coa')
            ->selectRaw('pd.pelaksana_id')
            ->selectRaw('pd.dokter')
            ->selectRaw('pd.tanggal')
            ->selectRaw('COALESCE(pc.id, pd.coa_id) AS kelompok_id')
            ->selectRaw('COALESCE(pc.kode, pd.kode_akun) AS kode_kelompok')
            ->selectRaw('COALESCE(pc.nama, pd.layanan) AS kelompok')
            ->selectRaw('pd.kode_akun')
            ->selectRaw('pd.layanan AS nama_akun')
            ->selectRaw('SUM(pd.nominal) AS total_pendapatan')
            ->groupBy(
                'pd.pelaksana_id',
                'pd.dokter',
                'pd.tanggal',
                'pc.id',
                'pc.kode',
                'pc.nama',
                'pd.coa_id',
                'pd.kode_akun',
                'pd.layanan',
            )
            ->orderBy('pd.dokter')
            ->orderBy('pd.tanggal')
            ->orderByRaw('COALESCE(pc.kode, pd.kode_akun)')
            ->orderBy('pd.kode_akun')
            ->get()
            ->map(function (object $row): object {
                $row->kode_akun_format = $this->formatCoaCode((string) $row->kode_akun);

                return $row;
            });
    }

    /**
     * @return array{companyName: string, periodLabel: string, dokterGroups: Collection, grandTotal: float}
     */
    private function getPendapatanDokterReportData(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): array {
        $rows = $this->getPendapatanDokterReportRows($startDate, $endDate, $pelaksanaId, $poli, $penjamin);
        $dokterGroups = $rows
            ->groupBy('pelaksana_id')
            ->map(function (Collection $doctorRows): array {
                return [
                    'nama' => (string) $doctorRows->first()->dokter,
                    'kelompok' => $doctorRows
                        ->groupBy('kelompok_id')
                        ->map(fn (Collection $accountRows): array => [
                            'nama' => (string) $accountRows->first()->kelompok,
                            'rincian' => $accountRows->values(),
                            'subtotal' => (float) $accountRows->sum('total_pendapatan'),
                        ])
                        ->values(),
                    'total' => (float) $doctorRows->sum('total_pendapatan'),
                ];
            })
            ->values();

        return [
            'companyName' => (Schema::hasTable('preferensi_perusahaan')
                ? PreferensiPerusahaan::query()->value('nama_perusahaan')
                : null) ?: config('siakurat.rs_name', 'RSA BOJONEGORO'),
            'periodLabel' => Carbon::parse($startDate)->locale('id')->translatedFormat('j F Y')
                .' - '.Carbon::parse($endDate)->locale('id')->translatedFormat('j F Y'),
            'dokterGroups' => $dokterGroups,
            'grandTotal' => (float) $rows->sum('total_pendapatan'),
        ];
    }

    private function formatCoaCode(string $code): string
    {
        if (preg_match('/^\d{9}$/', $code) !== 1) {
            return $code;
        }

        return substr($code, 0, 4).'-'.substr($code, 4, 2).'-'.substr($code, 6, 3);
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

    private function hasJurnalUmumPelaksanaSupport(): bool
    {
        return Schema::hasTable('jurnal_umum')
            && Schema::hasTable('jurnal_umum_rinci')
            && Schema::hasColumn('jurnal_umum_rinci', 'pelaksana_id')
            && Schema::hasColumn('coa', 'tipe_coa');
    }

    private function hasKasbankPembayaranPelaksanaSupport(): bool
    {
        return Schema::hasTable('kasbank_pembayaran')
            && Schema::hasTable('kasbank_pembayaran_rinci')
            && Schema::hasColumn('kasbank_pembayaran_rinci', 'pelaksana_id')
            && Schema::hasColumn('coa', 'tipe_coa');
    }

    private function getPendapatanDokterBaseQuery(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): QueryBuilder {
        $invoiceRows = DB::table('faktur_penjualan_rinci as fpr')
            ->join('faktur_penjualan as fp', 'fp.id', '=', 'fpr.faktur_penjualan_id')
            ->join('pelaksana as p', 'p.id', '=', 'fpr.pelaksana_id')
            ->join('coa as c', 'c.id', '=', 'fpr.coa_id')
            ->whereBetween('fp.tanggal_faktur', [$startDate, $endDate])
            ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
            ->when($poli !== '', fn (QueryBuilder $query) => $query->where('fp.nama_poli', 'like', '%'.$poli.'%'))
            ->when($penjamin !== '', fn (QueryBuilder $query) => $query->where('fp.nama_penjamin', 'like', '%'.$penjamin.'%'))
            ->select([
                'p.id as pelaksana_id',
                'p.nama_pelaksana as dokter',
                'p.no_proyek',
                'fp.tanggal_faktur as tanggal',
                'c.id as coa_id',
                'c.parent_coa',
                'c.kode as kode_akun',
                'c.nama as layanan',
                'fp.nama_poli',
                'fp.nama_penjamin',
                'fp.id as faktur_id',
                'fpr.subtotal as nominal',
            ]);

        if ($poli === '' && $penjamin === '' && $this->hasJurnalUmumPelaksanaSupport()) {
            $journalRows = DB::table('jurnal_umum_rinci as jur')
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jur.jurnal_umum_id')
                ->join('pelaksana as p', 'p.id', '=', 'jur.pelaksana_id')
                ->join('coa as c', 'c.id', '=', 'jur.coa_id')
                ->whereBetween('ju.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
                ->select([
                    'p.id as pelaksana_id',
                    'p.nama_pelaksana as dokter',
                    'p.no_proyek',
                    'ju.tanggal as tanggal',
                    'c.id as coa_id',
                    'c.parent_coa',
                    'c.kode as kode_akun',
                    'c.nama as layanan',
                    DB::raw('NULL as nama_poli'),
                    DB::raw('NULL as nama_penjamin'),
                    DB::raw('NULL as faktur_id'),
                    DB::raw('(COALESCE(jur.kredit, 0) - COALESCE(jur.debit, 0)) as nominal'),
                ]);

            $invoiceRows->unionAll($journalRows);
        }

        if ($poli === '' && $penjamin === '' && $this->hasKasbankPembayaranPelaksanaSupport()) {
            $kasbankRows = DB::table('kasbank_pembayaran_rinci as kbpr')
                ->join('kasbank_pembayaran as kbp', 'kbp.id', '=', 'kbpr.kasbank_pembayaran_id')
                ->join('pelaksana as p', 'p.id', '=', 'kbpr.pelaksana_id')
                ->join('coa as c', 'c.id', '=', 'kbpr.coa_id')
                ->whereBetween('kbp.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
                ->select([
                    'p.id as pelaksana_id',
                    'p.nama_pelaksana as dokter',
                    'p.no_proyek',
                    'kbp.tanggal as tanggal',
                    'c.id as coa_id',
                    'c.parent_coa',
                    'c.kode as kode_akun',
                    'c.nama as layanan',
                    DB::raw('NULL as nama_poli'),
                    DB::raw('NULL as nama_penjamin'),
                    DB::raw('NULL as faktur_id'),
                    DB::raw('(0 - COALESCE(kbpr.nominal, 0)) as nominal'),
                ]);

            $invoiceRows->unionAll($kasbankRows);
        }

        return DB::query()->fromSub($invoiceRows, 'pd');
    }

    public function getCoaPendapatanOptions(): Collection
    {
        $hasChildren = Coa::query()
            ->whereNotNull('parent_coa')
            ->pluck('parent_coa')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return Coa::query()
            ->where(function ($q): void {
                $q->whereIn('tipe_coa', ['Pendapatan', 'Pendapatan lain'])
                    ->orWhereRaw("LOWER(COALESCE(tipe_coa, '')) LIKE ?", ['%pendapatan%']);
            })
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'parent_coa'])
            ->reject(fn (Coa $c) => $hasChildren->has((int) $c->id))
            ->values();
    }

    public function getRekonsiliasiDokterData(
        string $startDate,
        string $endDate,
        ?int $coaId = null,
        bool $hanyaBerselisih = true
    ): array {
        $hasChildren = Coa::query()
            ->whereNotNull('parent_coa')
            ->pluck('parent_coa')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $allCoas = Coa::query()
            ->where(function ($q): void {
                $q->whereIn('tipe_coa', ['Pendapatan', 'Pendapatan lain'])
                    ->orWhereRaw("LOWER(COALESCE(tipe_coa, '')) LIKE ?", ['%pendapatan%']);
            })
            ->when($coaId !== null, fn ($q) => $q->where('id', $coaId))
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'tipe_coa', 'parent_coa'])
            ->reject(fn (Coa $c) => $hasChildren->has((int) $c->id))
            ->values();

        // 1. Mutasi buku besar bersih (Kredit - Debit)
        $bukuBesarRows = DB::table('bukubesar')
            ->selectRaw('coa_id, (SUM(CASE WHEN tipe_mutasi = "K" THEN nominal ELSE 0 END) - SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE 0 END)) as nominal_buku_besar')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->when($coaId !== null, fn ($q) => $q->where('coa_id', $coaId))
            ->groupBy('coa_id')
            ->pluck('nominal_buku_besar', 'coa_id');

        // 2. Faktur Penjualan Rinci (dengan dokter vs tanpa dokter)
        $invoiceRows = DB::table('faktur_penjualan_rinci as fpr')
            ->join('faktur_penjualan as fp', 'fp.id', '=', 'fpr.faktur_penjualan_id')
            ->whereBetween('fp.tanggal_faktur', [$startDate, $endDate])
            ->when($coaId !== null, fn ($q) => $q->where('fpr.coa_id', $coaId))
            ->selectRaw('
                fpr.coa_id,
                SUM(CASE WHEN fpr.pelaksana_id IS NOT NULL THEN fpr.subtotal ELSE 0 END) as dengan_dokter,
                SUM(CASE WHEN fpr.pelaksana_id IS NULL THEN fpr.subtotal ELSE 0 END) as tanpa_dokter,
                SUM(CASE WHEN fpr.pelaksana_id IS NULL THEN 1 ELSE 0 END) as count_tanpa_dokter
            ')
            ->groupBy('fpr.coa_id')
            ->get()
            ->keyBy('coa_id');

        // 3. Jurnal Umum Rinci (dengan dokter vs tanpa dokter)
        $journalRows = collect();
        if ($this->hasJurnalUmumPelaksanaSupport()) {
            $journalRows = DB::table('jurnal_umum_rinci as jur')
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jur.jurnal_umum_id')
                ->join('coa as c', 'c.id', '=', 'jur.coa_id')
                ->whereBetween('ju.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($coaId !== null, fn ($q) => $q->where('jur.coa_id', $coaId))
                ->selectRaw('
                    jur.coa_id,
                    SUM(CASE WHEN jur.pelaksana_id IS NOT NULL THEN (COALESCE(jur.kredit, 0) - COALESCE(jur.debit, 0)) ELSE 0 END) as dengan_dokter,
                    SUM(CASE WHEN jur.pelaksana_id IS NULL THEN (COALESCE(jur.kredit, 0) - COALESCE(jur.debit, 0)) ELSE 0 END) as tanpa_dokter,
                    SUM(CASE WHEN jur.pelaksana_id IS NULL THEN 1 ELSE 0 END) as count_tanpa_dokter
                ')
                ->groupBy('jur.coa_id')
                ->get()
                ->keyBy('coa_id');
        }

        // 4. Kas & Bank Pembayaran Rinci (dengan dokter vs tanpa dokter)
        $kasbankRows = collect();
        if ($this->hasKasbankPembayaranPelaksanaSupport()) {
            $kasbankRows = DB::table('kasbank_pembayaran_rinci as kbpr')
                ->join('kasbank_pembayaran as kbp', 'kbp.id', '=', 'kbpr.kasbank_pembayaran_id')
                ->join('coa as c', 'c.id', '=', 'kbpr.coa_id')
                ->whereBetween('kbp.tanggal', [$startDate, $endDate])
                ->whereRaw("LOWER(COALESCE(c.tipe_coa, '')) LIKE ?", ['%pendapatan%'])
                ->when($coaId !== null, fn ($q) => $q->where('kbpr.coa_id', $coaId))
                ->selectRaw('
                    kbpr.coa_id,
                    SUM(CASE WHEN kbpr.pelaksana_id IS NOT NULL THEN (0 - COALESCE(kbpr.nominal, 0)) ELSE 0 END) as dengan_dokter,
                    SUM(CASE WHEN kbpr.pelaksana_id IS NULL THEN (0 - COALESCE(kbpr.nominal, 0)) ELSE 0 END) as tanpa_dokter,
                    SUM(CASE WHEN kbpr.pelaksana_id IS NULL THEN 1 ELSE 0 END) as count_tanpa_dokter
                ')
                ->groupBy('kbpr.coa_id')
                ->get()
                ->keyBy('coa_id');
        }

        $allRows = $allCoas->map(function (Coa $c) use ($bukuBesarRows, $invoiceRows, $journalRows, $kasbankRows): array {
            $cid = (int) $c->id;
            $bb = (float) ($bukuBesarRows[$cid] ?? 0);

            $invDokter = (float) ($invoiceRows[$cid]->dengan_dokter ?? 0);
            $invTanpa = (float) ($invoiceRows[$cid]->tanpa_dokter ?? 0);
            $invCount = (int) ($invoiceRows[$cid]->count_tanpa_dokter ?? 0);

            $jurDokter = (float) ($journalRows[$cid]->dengan_dokter ?? 0);
            $jurTanpa = (float) ($journalRows[$cid]->tanpa_dokter ?? 0);
            $jurCount = (int) ($journalRows[$cid]->count_tanpa_dokter ?? 0);

            $kbDokter = (float) ($kasbankRows[$cid]->dengan_dokter ?? 0);
            $kbTanpa = (float) ($kasbankRows[$cid]->tanpa_dokter ?? 0);
            $kbCount = (int) ($kasbankRows[$cid]->count_tanpa_dokter ?? 0);

            $totalDokter = $invDokter + $jurDokter + $kbDokter;
            $totalTanpaDokter = $invTanpa + $jurTanpa + $kbTanpa;
            $countTanpaDokter = $invCount + $jurCount + $kbCount;

            $selisih = $bb - $totalDokter;
            $adaSelisih = abs($selisih) > 0.005 || abs($totalTanpaDokter) > 0.005;

            return [
                'coa_id' => $cid,
                'kode_akun' => (string) $c->kode,
                'nama_akun' => (string) $c->nama,
                'tipe_coa' => (string) $c->tipe_coa,
                'total_buku_besar' => $bb,
                'total_pendapatan_dokter' => $totalDokter,
                'total_tanpa_dokter' => $totalTanpaDokter,
                'selisih' => $selisih,
                'ada_selisih' => $adaSelisih,
                'jumlah_transaksi_tanpa_dokter' => $countTanpaDokter,
            ];
        });

        $filteredRows = $allRows->filter(function (array $r) use ($hanyaBerselisih, $coaId): bool {
            if ($coaId !== null) {
                return true;
            }

            if ($hanyaBerselisih) {
                return $r['ada_selisih'];
            }

            return abs($r['total_buku_besar']) > 0.005
                || abs($r['total_pendapatan_dokter']) > 0.005
                || abs($r['total_tanpa_dokter']) > 0.005;
        })->values();

        $summary = [
            'totalBukuBesar' => (float) $filteredRows->sum('total_buku_besar'),
            'totalPendapatanDokter' => (float) $filteredRows->sum('total_pendapatan_dokter'),
            'totalSelisih' => (float) $filteredRows->sum('selisih'),
            'totalTanpaDokter' => (float) $filteredRows->sum('total_tanpa_dokter'),
            'jumlahAkunBerselisih' => $filteredRows->where('ada_selisih', true)->count(),
            'jumlahTotalAkun' => $filteredRows->count(),
        ];

        return [
            'summary' => $summary,
            'rows' => $filteredRows,
        ];
    }

    public function getDetailTransaksiTanpaDokter(string $startDate, string $endDate, int $coaId): Collection
    {
        $details = collect();

        // 1. Faktur Penjualan Rinci
        $invoiceRows = DB::table('faktur_penjualan_rinci as fpr')
            ->join('faktur_penjualan as fp', 'fp.id', '=', 'fpr.faktur_penjualan_id')
            ->where('fpr.coa_id', $coaId)
            ->whereNull('fpr.pelaksana_id')
            ->whereBetween('fp.tanggal_faktur', [$startDate, $endDate])
            ->select([
                'fpr.id as id',
                'fp.id as source_id',
                'fp.tanggal_faktur as tanggal',
                'fp.nomor_faktur as nomor_ref',
                DB::raw("'Invoice Pendapatan' as sumber"),
                'fp.nama_pasien',
                'fp.nomer_rekam_medis',
                'fp.nama_poli',
                'fp.nama_penjamin',
                'fpr.kode_proyek',
                'fpr.catatan',
                'fpr.subtotal as nominal',
            ])
            ->orderBy('fp.tanggal_faktur')
            ->orderBy('fp.id')
            ->get();

        foreach ($invoiceRows as $row) {
            $statusSimrs = filled($row->kode_proyek)
                ? 'Kode SIMRS: '.$row->kode_proyek.' (Belum di Master)'
                : 'Kosong dari SIMRS / Manual';

            $details->push([
                'id' => (int) $row->id,
                'source_id' => (int) $row->source_id,
                'tanggal' => Carbon::parse($row->tanggal)->format('Y-m-d'),
                'nomor_ref' => (string) $row->nomor_ref,
                'sumber' => (string) $row->sumber,
                'nama_pasien' => filled($row->nama_pasien) ? (string) $row->nama_pasien : '-',
                'nomer_rekam_medis' => filled($row->nomer_rekam_medis) ? (string) $row->nomer_rekam_medis : '-',
                'nama_poli' => filled($row->nama_poli) ? (string) $row->nama_poli : '-',
                'nama_penjamin' => filled($row->nama_penjamin) ? (string) $row->nama_penjamin : '-',
                'kode_proyek' => $row->kode_proyek ? (string) $row->kode_proyek : null,
                'status_simrs' => $statusSimrs,
                'catatan' => filled($row->catatan) ? (string) $row->catatan : '-',
                'nominal' => (float) $row->nominal,
                'link_url' => route('pendapatan.invoice.edit', $row->source_id),
                'link_label' => 'Buka Faktur',
            ]);
        }

        // 2. Jurnal Umum Rinci
        if ($this->hasJurnalUmumPelaksanaSupport()) {
            $journalRows = DB::table('jurnal_umum_rinci as jur')
                ->join('jurnal_umum as ju', 'ju.id', '=', 'jur.jurnal_umum_id')
                ->where('jur.coa_id', $coaId)
                ->whereNull('jur.pelaksana_id')
                ->whereBetween('ju.tanggal', [$startDate, $endDate])
                ->select([
                    'jur.id as id',
                    'ju.id as source_id',
                    'ju.tanggal as tanggal',
                    'ju.nomer as nomor_ref',
                    DB::raw("'Jurnal Umum' as sumber"),
                    DB::raw("'-' as nama_pasien"),
                    DB::raw("'-' as nomer_rekam_medis"),
                    DB::raw("'-' as nama_poli"),
                    DB::raw("'-' as nama_penjamin"),
                    DB::raw("NULL as kode_proyek"),
                    DB::raw("COALESCE(NULLIF(TRIM(jur.catatan), ''), ju.keterangan, '-') as catatan"),
                    DB::raw('(COALESCE(jur.kredit, 0) - COALESCE(jur.debit, 0)) as nominal'),
                ])
                ->orderBy('ju.tanggal')
                ->orderBy('ju.id')
                ->get();

            foreach ($journalRows as $row) {
                $details->push([
                    'id' => (int) $row->id,
                    'source_id' => (int) $row->source_id,
                    'tanggal' => Carbon::parse($row->tanggal)->format('Y-m-d'),
                    'nomor_ref' => (string) $row->nomor_ref,
                    'sumber' => (string) $row->sumber,
                    'nama_pasien' => '-',
                    'nomer_rekam_medis' => '-',
                    'nama_poli' => '-',
                    'nama_penjamin' => '-',
                    'kode_proyek' => null,
                    'status_simrs' => 'Jurnal Manual Tanpa Pelaksana',
                    'catatan' => (string) $row->catatan,
                    'nominal' => (float) $row->nominal,
                    'link_url' => route('bukubesar.jurnal-umum.edit', $row->source_id),
                    'link_label' => 'Buka Jurnal',
                ]);
            }
        }

        // 3. Kas & Bank Pembayaran Rinci
        if ($this->hasKasbankPembayaranPelaksanaSupport()) {
            $kasbankRows = DB::table('kasbank_pembayaran_rinci as kbpr')
                ->join('kasbank_pembayaran as kbp', 'kbp.id', '=', 'kbpr.kasbank_pembayaran_id')
                ->where('kbpr.coa_id', $coaId)
                ->whereNull('kbpr.pelaksana_id')
                ->whereBetween('kbp.tanggal', [$startDate, $endDate])
                ->select([
                    'kbpr.id as id',
                    'kbp.id as source_id',
                    'kbp.tanggal as tanggal',
                    'kbp.nomer as nomor_ref',
                    DB::raw("'Kas & Bank Pembayaran' as sumber"),
                    DB::raw("'-' as nama_pasien"),
                    DB::raw("'-' as nomer_rekam_medis"),
                    DB::raw("'-' as nama_poli"),
                    DB::raw("'-' as nama_penjamin"),
                    DB::raw("NULL as kode_proyek"),
                    DB::raw("COALESCE(NULLIF(TRIM(kbpr.catatan), ''), kbp.keterangan, '-') as catatan"),
                    DB::raw('(0 - COALESCE(kbpr.nominal, 0)) as nominal'),
                ])
                ->orderBy('kbp.tanggal')
                ->orderBy('kbp.id')
                ->get();

            foreach ($kasbankRows as $row) {
                $details->push([
                    'id' => (int) $row->id,
                    'source_id' => (int) $row->source_id,
                    'tanggal' => Carbon::parse($row->tanggal)->format('Y-m-d'),
                    'nomor_ref' => (string) $row->nomor_ref,
                    'sumber' => (string) $row->sumber,
                    'nama_pasien' => '-',
                    'nomer_rekam_medis' => '-',
                    'nama_poli' => '-',
                    'nama_penjamin' => '-',
                    'kode_proyek' => null,
                    'status_simrs' => 'Kas/Bank Tanpa Pelaksana',
                    'catatan' => (string) $row->catatan,
                    'nominal' => (float) $row->nominal,
                    'link_url' => route('kasbank.pembayaran.edit', $row->source_id),
                    'link_label' => 'Buka Kas/Bank',
                ]);
            }
        }

        return $details->sortBy('tanggal')->values();
    }

    public function streamRekonsiliasiDokterCsv(
        string $startDate,
        string $endDate,
        ?int $coaId = null,
        bool $hanyaBerselisih = true
    ): void {
        $data = $this->getRekonsiliasiDokterData($startDate, $endDate, $coaId, $hanyaBerselisih);
        $handle = fopen('php://output', 'wb');

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($handle, ['REKONSILIASI PENDAPATAN DOKTER (AUDIT TANPA PELAKSANA)']);
        fputcsv($handle, ['Periode', $startDate.' s/d '.$endDate]);
        fputcsv($handle, []);

        fputcsv($handle, [
            'No',
            'Kode Akun',
            'Nama Akun',
            'Tipe COA',
            'Total Buku Besar (Laba Rugi)',
            'Total Pendapatan Dokter',
            'Total Tanpa Dokter',
            'Selisih',
            'Jml Trx Tanpa Dokter',
            'Status',
        ]);

        $no = 1;
        foreach ($data['rows'] as $row) {
            fputcsv($handle, [
                $no++,
                $row['kode_akun'],
                $row['nama_akun'],
                $row['tipe_coa'],
                $this->formatCsvNumber($row['total_buku_besar']),
                $this->formatCsvNumber($row['total_pendapatan_dokter']),
                $this->formatCsvNumber($row['total_tanpa_dokter']),
                $this->formatCsvNumber($row['selisih']),
                $row['jumlah_transaksi_tanpa_dokter'],
                $row['ada_selisih'] ? 'Ada Selisih' : 'Sesuai',
            ]);
        }

        fputcsv($handle, [
            'TOTAL',
            '',
            '',
            '',
            $this->formatCsvNumber($data['summary']['totalBukuBesar']),
            $this->formatCsvNumber($data['summary']['totalPendapatanDokter']),
            $this->formatCsvNumber($data['summary']['totalTanpaDokter']),
            $this->formatCsvNumber($data['summary']['totalSelisih']),
            '',
            '',
        ]);

        fputcsv($handle, []);
        fputcsv($handle, ['RINCIAN TRANSAKSI TANPA PELAKSANA']);
        fputcsv($handle, [
            'No',
            'Kode Akun',
            'Nama Akun',
            'Tanggal',
            'No Referensi',
            'Sumber',
            'Nama Pasien',
            'No RM',
            'Poli',
            'Penjamin',
            'Status SIMRS',
            'Catatan',
            'Nominal',
        ]);

        $detailNo = 1;
        foreach ($data['rows'] as $r) {
            if ($r['jumlah_transaksi_tanpa_dokter'] > 0) {
                $trxList = $this->getDetailTransaksiTanpaDokter($startDate, $endDate, $r['coa_id']);
                foreach ($trxList as $trx) {
                    fputcsv($handle, [
                        $detailNo++,
                        $r['kode_akun'],
                        $r['nama_akun'],
                        $trx['tanggal'],
                        $trx['nomor_ref'],
                        $trx['sumber'],
                        $trx['nama_pasien'],
                        $trx['nomer_rekam_medis'],
                        $trx['nama_poli'],
                        $trx['nama_penjamin'],
                        $trx['status_simrs'],
                        $trx['catatan'],
                        $this->formatCsvNumber($trx['nominal']),
                    ]);
                }
            }
        }

        fclose($handle);
    }

    public function streamRekonsiliasiDokterExcel(
        string $startDate,
        string $endDate,
        ?int $coaId = null,
        bool $hanyaBerselisih = true
    ): void {
        $identitas = $this->getIdentitasLaporan();
        $data = $this->getRekonsiliasiDokterData($startDate, $endDate, $coaId, $hanyaBerselisih);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle('Rekonsiliasi Pendapatan Dokter')
            ->setSubject('Audit pendapatan tanpa pelaksana');

        // Sheet 1: Ringkasan Rekonsiliasi
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan Rekonsiliasi');
        $sheet->setShowGridlines(true);

        foreach (['A1:I1', 'A2:I2', 'A3:I3'] as $range) {
            $sheet->mergeCells($range);
        }

        $sheet->setCellValue('A1', mb_strtoupper($identitas['namaRumahSakit']));
        $sheet->setCellValue('A2', 'REKONSILIASI PENDAPATAN DOKTER (AUDIT TANPA PELAKSANA)');
        $sheet->setCellValue('A3', 'Periode: '.$startDate.' s/d '.$endDate);
        $sheet->getStyle('A1:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->setColor(new Color('DC2626'));
        $sheet->getStyle('A3')->getFont()->setSize(10)->setColor(new Color('555555'));

        $headers = [
            'A5' => 'NO',
            'B5' => 'KODE AKUN',
            'C5' => 'NAMA AKUN',
            'D5' => 'TIPE COA',
            'E5' => 'BUKU BESAR (LABA RUGI)',
            'F5' => 'PENDAPATAN DOKTER',
            'G5' => 'TOTAL TANPA DOKTER',
            'H5' => 'SELISIH',
            'I5' => 'STATUS',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A5:I5')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A5:I5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E293B');
        $sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNum = 6;
        $idx = 1;
        foreach ($data['rows'] as $row) {
            $sheet->setCellValue("A{$rowNum}", $idx++);
            $sheet->setCellValueExplicit("B{$rowNum}", $row['kode_akun'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$rowNum}", $row['nama_akun']);
            $sheet->setCellValue("D{$rowNum}", $row['tipe_coa']);
            $sheet->setCellValue("E{$rowNum}", (float) $row['total_buku_besar']);
            $sheet->setCellValue("F{$rowNum}", (float) $row['total_pendapatan_dokter']);
            $sheet->setCellValue("G{$rowNum}", (float) $row['total_tanpa_dokter']);
            $sheet->setCellValue("H{$rowNum}", (float) $row['selisih']);
            $sheet->setCellValue("I{$rowNum}", $row['ada_selisih'] ? 'Ada Selisih' : 'Sesuai');

            if ($row['ada_selisih']) {
                $sheet->getStyle("I{$rowNum}")->getFont()->setColor(new Color('DC2626'))->setBold(true);
            } else {
                $sheet->getStyle("I{$rowNum}")->getFont()->setColor(new Color('16A34A'));
            }

            $rowNum++;
        }

        // Total Row
        $sheet->mergeCells("A{$rowNum}:D{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL');
        $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("E{$rowNum}", (float) $data['summary']['totalBukuBesar']);
        $sheet->setCellValue("F{$rowNum}", (float) $data['summary']['totalPendapatanDokter']);
        $sheet->setCellValue("G{$rowNum}", (float) $data['summary']['totalTanpaDokter']);
        $sheet->setCellValue("H{$rowNum}", (float) $data['summary']['totalSelisih']);
        $sheet->getStyle("A{$rowNum}:I{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:I{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        $sheet->getStyle("E6:H{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A5:I{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('CBD5E1'));

        foreach (['A' => 6, 'B' => 16, 'C' => 38, 'D' => 16, 'E' => 22, 'F' => 22, 'G' => 22, 'H' => 18, 'I' => 15] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Sheet 2: Rincian Transaksi Tanpa Pelaksana
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Rincian Tanpa Pelaksana');
        $sheet2->setShowGridlines(true);

        foreach (['A1:L1', 'A2:L2'] as $range) {
            $sheet2->mergeCells($range);
        }
        $sheet2->setCellValue('A1', 'RINCIAN TRANSAKSI TANPA PELAKSANA');
        $sheet2->setCellValue('A2', 'Periode: '.$startDate.' s/d '.$endDate);
        $sheet2->getStyle('A1:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(12)->setColor(new Color('DC2626'));
        $sheet2->getStyle('A2')->getFont()->setSize(10)->setColor(new Color('555555'));

        $headers2 = [
            'A4' => 'NO',
            'B4' => 'KODE AKUN',
            'C4' => 'NAMA AKUN',
            'D4' => 'TANGGAL',
            'E4' => 'NO REFERENSI',
            'F4' => 'SUMBER',
            'G4' => 'NAMA PASIEN',
            'H4' => 'POLI',
            'I4' => 'PENJAMIN',
            'J4' => 'STATUS SIMRS',
            'K4' => 'CATATAN',
            'L4' => 'NOMINAL',
        ];

        foreach ($headers2 as $cell => $text) {
            $sheet2->setCellValue($cell, $text);
        }
        $sheet2->getStyle('A4:L4')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet2->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E293B');
        $sheet2->getStyle('A4:L4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNum2 = 5;
        $idx2 = 1;
        $totalNominalTanpaDokter = 0;
        foreach ($data['rows'] as $r) {
            if ($r['jumlah_transaksi_tanpa_dokter'] > 0) {
                $trxList = $this->getDetailTransaksiTanpaDokter($startDate, $endDate, $r['coa_id']);
                foreach ($trxList as $trx) {
                    $sheet2->setCellValue("A{$rowNum2}", $idx2++);
                    $sheet2->setCellValueExplicit("B{$rowNum2}", $r['kode_akun'], DataType::TYPE_STRING);
                    $sheet2->setCellValue("C{$rowNum2}", $r['nama_akun']);
                    $sheet2->setCellValueExplicit("D{$rowNum2}", $trx['tanggal'], DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("E{$rowNum2}", $trx['nomor_ref'], DataType::TYPE_STRING);
                    $sheet2->setCellValue("F{$rowNum2}", $trx['sumber']);
                    $sheet2->setCellValue("G{$rowNum2}", $trx['nama_pasien']);
                    $sheet2->setCellValue("H{$rowNum2}", $trx['nama_poli']);
                    $sheet2->setCellValue("I{$rowNum2}", $trx['nama_penjamin']);
                    $sheet2->setCellValue("J{$rowNum2}", $trx['status_simrs']);
                    $sheet2->setCellValue("K{$rowNum2}", $trx['catatan']);
                    $sheet2->setCellValue("L{$rowNum2}", (float) $trx['nominal']);
                    $totalNominalTanpaDokter += (float) $trx['nominal'];
                    $rowNum2++;
                }
            }
        }

        // Total Sheet 2
        $sheet2->mergeCells("A{$rowNum2}:K{$rowNum2}");
        $sheet2->setCellValue("A{$rowNum2}", 'TOTAL');
        $sheet2->getStyle("A{$rowNum2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->setCellValue("L{$rowNum2}", (float) $totalNominalTanpaDokter);
        $sheet2->getStyle("A{$rowNum2}:L{$rowNum2}")->getFont()->setBold(true);
        $sheet2->getStyle("A{$rowNum2}:L{$rowNum2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        $sheet2->getStyle("L5:L{$rowNum2}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet2->getStyle("A4:L{$rowNum2}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('CBD5E1'));

        foreach (['A' => 6, 'B' => 14, 'C' => 30, 'D' => 14, 'E' => 18, 'F' => 18, 'G' => 24, 'H' => 16, 'I' => 16, 'J' => 28, 'K' => 30, 'L' => 20] as $col => $w) {
            $sheet2->getColumnDimension($col)->setWidth($w);
        }

        $spreadsheet->setActiveSheetIndex(0);

        try {
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }
}
