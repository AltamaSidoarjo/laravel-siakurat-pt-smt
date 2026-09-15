<?php

namespace App\Services\Laporan;

use App\Models\FakturPembelian;
use App\Models\PembayaranPembelianRinci;
use App\Models\PreferensiPerusahaan;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class LaporanPembelianService
{
    private const BUCKET_KEYS = [
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
            'namaRumahSakit' => $preferensi?->nama_perusahaan ?: config('siakurat.rs_name'),
        ];
    }

    public function getSupplierOptions(): Collection
    {
        return Supplier::query()
            ->orderBy('kode_supplier')
            ->orderBy('nama_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function searchSupplierHutang(string $search): Collection
    {
        return Supplier::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('kode_supplier', 'like', $like)
                        ->orWhere('nama_supplier', 'like', $like);
                });
            })
            ->orderBy('kode_supplier')
            ->limit(20)
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function getBukuPembantuHutang(string $reportDate, array $supplierIds = []): array
    {
        $reportDateCarbon = Carbon::parse($reportDate)->startOfDay();

        $fakturs = FakturPembelian::query()
            ->select([
                'id',
                'supplier_id',
                'nomer_faktur',
                'tanggal_faktur',
                'tanggal_jatuh_tempo',
                'grandtotal',
                'sudah_terbayar',
            ])
            ->with('supplier:id,kode_supplier,nama_supplier')
            ->withSum('pembayaranPembelianRincis as total_alokasi', 'nominal_bayar')
            ->withSum([
                'pembayaranPembelianRincis as total_alokasi_sampai_tanggal' => function (Builder $query) use ($reportDate) {
                    $query->whereHas(
                        'pembayaranPembelian',
                        fn (Builder $pembayaranQuery) => $pembayaranQuery->whereDate('tanggal', '<=', $reportDate),
                    );
                },
            ], 'nominal_bayar')
            ->whereNotNull('supplier_id')
            ->whereNotNull('tanggal_faktur')
            ->whereDate('tanggal_faktur', '<=', $reportDate)
            ->when(
                $supplierIds !== [],
                fn (Builder $query) => $query->whereIn('supplier_id', $supplierIds),
            )
            ->get();

        $cardsBySupplier = [];
        $summary = $this->emptyBuckets();

        foreach ($fakturs as $faktur) {
            if ($faktur->supplier === null || $faktur->tanggal_faktur === null) {
                continue;
            }

            $totalAlokasi = round((float) ($faktur->total_alokasi ?? 0), 2);
            $pembayaranLangsung = max(0, round((float) $faktur->sudah_terbayar - $totalAlokasi, 2));
            $pembayaranSampaiTanggal = round(
                $pembayaranLangsung + (float) ($faktur->total_alokasi_sampai_tanggal ?? 0),
                2,
            );
            $sisaHutang = max(0, round((float) $faktur->grandtotal - $pembayaranSampaiTanggal, 2));

            if ($sisaHutang < 0.005) {
                continue;
            }

            $tanggalJatuhTempo = ($faktur->tanggal_jatuh_tempo ?? $faktur->tanggal_faktur)->copy()->startOfDay();
            $umurHari = max(0, (int) $tanggalJatuhTempo->diffInDays($reportDateCarbon, false));
            $bucketKey = $this->bucketKeyForAge($umurHari);
            $supplierId = (int) $faktur->supplier_id;

            if (! isset($cardsBySupplier[$supplierId])) {
                $cardsBySupplier[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'kode_supplier' => (string) ($faktur->supplier->kode_supplier ?? ''),
                    'nama_supplier' => (string) $faktur->supplier->nama_supplier,
                    'rows' => [],
                    'totals' => $this->emptyBuckets(),
                    'saldo_hutang' => 0.0,
                ];
            }

            $bucketAmounts = $this->emptyBuckets();
            $bucketAmounts[$bucketKey] = $sisaHutang;

            $cardsBySupplier[$supplierId]['rows'][] = [
                'faktur_id' => (int) $faktur->id,
                'tanggal' => $faktur->tanggal_faktur->format('Y-m-d'),
                'tanggal_jatuh_tempo' => $tanggalJatuhTempo->format('Y-m-d'),
                'tipe' => 'FP',
                'nomor_referensi' => (string) $faktur->nomer_faktur,
                'umur_hari' => $umurHari,
                'sisa_hutang' => $sisaHutang,
                ...$bucketAmounts,
            ];

            $cardsBySupplier[$supplierId]['totals'][$bucketKey] = round(
                $cardsBySupplier[$supplierId]['totals'][$bucketKey] + $sisaHutang,
                2,
            );
            $cardsBySupplier[$supplierId]['saldo_hutang'] = round(
                $cardsBySupplier[$supplierId]['saldo_hutang'] + $sisaHutang,
                2,
            );
            $summary[$bucketKey] = round($summary[$bucketKey] + $sisaHutang, 2);
        }

        $cards = array_values($cardsBySupplier);

        foreach ($cards as &$card) {
            usort($card['rows'], fn (array $left, array $right): int => [
                $left['tanggal_jatuh_tempo'],
                $left['tanggal'],
                $left['nomor_referensi'],
                $left['faktur_id'],
            ] <=> [
                $right['tanggal_jatuh_tempo'],
                $right['tanggal'],
                $right['nomor_referensi'],
                $right['faktur_id'],
            ]);
        }
        unset($card);

        usort($cards, fn (array $left, array $right): int => [
            $left['kode_supplier'],
            $left['nama_supplier'],
            $left['supplier_id'],
        ] <=> [
            $right['kode_supplier'],
            $right['nama_supplier'],
            $right['supplier_id'],
        ]);

        $summary['saldo_hutang'] = round(array_sum($summary), 2);

        return [
            'cards' => $cards,
            'summary' => $summary,
        ];
    }

    public function getRangkumanBukuPembantuHutang(string $reportDate, array $supplierIds = []): array
    {
        $report = $this->getBukuPembantuHutang($reportDate, $supplierIds);

        return [
            'rows' => array_map(fn (array $card): array => [
                'supplier_id' => $card['supplier_id'],
                'kode_supplier' => $card['kode_supplier'],
                'nama_supplier' => $card['nama_supplier'],
                'days_0_30' => $card['totals']['days_0_30'],
                'days_31_60' => $card['totals']['days_31_60'],
                'days_61_90' => $card['totals']['days_61_90'],
                'days_over_90' => $card['totals']['days_over_90'],
                'total_hutang' => $card['saldo_hutang'],
            ], $report['cards']),
            'summary' => $report['summary'],
        ];
    }

    public function streamBukuPembantuHutangCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Supplier',
            'Nama Supplier',
            'Tanggal',
            'Jatuh Tempo',
            'Tipe',
            'No. Referensi',
            '0 - 30 Hari',
            '31 - 60 Hari',
            '61 - 90 Hari',
            '> 90 Hari',
        ]);

        foreach ($report['cards'] as $card) {
            foreach ($card['rows'] as $row) {
                fputcsv($handle, [
                    $card['kode_supplier'],
                    $card['nama_supplier'],
                    $row['tanggal'],
                    $row['tanggal_jatuh_tempo'],
                    $row['tipe'],
                    $row['nomor_referensi'],
                    $this->formatCsvBucket($row['days_0_30']),
                    $this->formatCsvBucket($row['days_31_60']),
                    $this->formatCsvBucket($row['days_61_90']),
                    $this->formatCsvBucket($row['days_over_90']),
                ]);
            }

            fputcsv($handle, [
                $card['kode_supplier'],
                $card['nama_supplier'],
                '',
                '',
                '',
                'Saldo '.$card['nama_supplier'],
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
            '',
            'GRAND TOTAL',
            $this->formatCsvNumber($report['summary']['days_0_30']),
            $this->formatCsvNumber($report['summary']['days_31_60']),
            $this->formatCsvNumber($report['summary']['days_61_90']),
            $this->formatCsvNumber($report['summary']['days_over_90']),
        ]);

        fclose($handle);
    }

    public function streamRangkumanBukuPembantuHutangCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Supplier',
            'Nama Supplier',
            '0 - 30 Hari',
            '31 - 60 Hari',
            '61 - 90 Hari',
            '> 90 Hari',
            'Total Hutang',
        ]);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, [
                $row['kode_supplier'],
                $row['nama_supplier'],
                $this->formatCsvNumber($row['days_0_30']),
                $this->formatCsvNumber($row['days_31_60']),
                $this->formatCsvNumber($row['days_61_90']),
                $this->formatCsvNumber($row['days_over_90']),
                $this->formatCsvNumber($row['total_hutang']),
            ]);
        }

        fputcsv($handle, [
            '',
            'GRAND TOTAL',
            $this->formatCsvNumber($report['summary']['days_0_30']),
            $this->formatCsvNumber($report['summary']['days_31_60']),
            $this->formatCsvNumber($report['summary']['days_61_90']),
            $this->formatCsvNumber($report['summary']['days_over_90']),
            $this->formatCsvNumber($report['summary']['saldo_hutang']),
        ]);

        fclose($handle);
    }

    public function getSupplierTerpilih(array $supplierIds): Collection
    {
        return Supplier::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('kode_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function getBukuPembantuHutangMutasi(
        string $startDate,
        string $endDate,
        array $supplierIds,
        string $statusSaldo = 'semua',
    ): array {
        if ($supplierIds === []) {
            return $this->emptyMutasiReport();
        }

        $suppliers = $this->getSupplierTerpilih($supplierIds)->keyBy('id');
        $transactionsBySupplier = $suppliers->map(fn () => [])->all();

        $fakturs = FakturPembelian::query()
            ->select([
                'id',
                'supplier_id',
                'nomer_faktur',
                'tanggal_faktur',
                'keterangan',
                'kategori_faktur',
                'grandtotal',
                'sudah_terbayar',
            ])
            ->withSum('pembayaranPembelianRincis as total_alokasi', 'nominal_bayar')
            ->whereIn('supplier_id', $suppliers->keys())
            ->whereNotNull('tanggal_faktur')
            ->whereDate('tanggal_faktur', '<=', $endDate)
            ->get();

        foreach ($fakturs as $faktur) {
            $supplierId = (int) $faktur->supplier_id;
            $tanggal = $faktur->tanggal_faktur->format('Y-m-d');

            $transactionsBySupplier[$supplierId][] = [
                'tanggal' => $tanggal,
                'nomor' => (string) $faktur->nomer_faktur,
                'jenis' => 'Faktur',
                'referensi_faktur' => (string) $faktur->nomer_faktur,
                'keterangan' => (string) ($faktur->keterangan ?: $faktur->kategori_faktur ?: 'Faktur pembelian'),
                'akun' => '-',
                'debit' => 0.0,
                'kredit' => round((float) $faktur->grandtotal, 2),
                'urutan_jenis' => 10,
                'urutan_id' => (int) $faktur->id,
                'faktur_id' => (int) $faktur->id,
                'pembayaran_id' => null,
            ];

            $pembayaranLangsung = max(
                0,
                round((float) $faktur->sudah_terbayar - (float) ($faktur->total_alokasi ?? 0), 2),
            );

            if ($pembayaranLangsung > 0) {
                $transactionsBySupplier[$supplierId][] = [
                    'tanggal' => $tanggal,
                    'nomor' => (string) $faktur->nomer_faktur,
                    'jenis' => 'Pembayaran langsung',
                    'referensi_faktur' => (string) $faktur->nomer_faktur,
                    'keterangan' => 'Pembayaran yang telah melekat saat faktur dibuat',
                    'akun' => '-',
                    'debit' => $pembayaranLangsung,
                    'kredit' => 0.0,
                    'urutan_jenis' => 20,
                    'urutan_id' => (int) $faktur->id,
                    'faktur_id' => (int) $faktur->id,
                    'pembayaran_id' => null,
                ];
            }
        }

        $pembayaranRincis = PembayaranPembelianRinci::query()
            ->with([
                'pembayaranPembelian:id,akun_hutang_id,nomer_pembayaran,tanggal,keterangan',
                'pembayaranPembelian.akunHutang:id,kode,nama',
                'fakturPembelian:id,supplier_id,nomer_faktur',
            ])
            ->whereHas('fakturPembelian', fn (Builder $query) => $query->whereIn('supplier_id', $suppliers->keys()))
            ->whereHas('pembayaranPembelian', fn (Builder $query) => $query->whereDate('tanggal', '<=', $endDate))
            ->get(['id', 'pembayaran_pembelian_id', 'faktur_pembelian_id', 'nominal_bayar']);

        foreach ($pembayaranRincis as $rincian) {
            $pembayaran = $rincian->pembayaranPembelian;
            $faktur = $rincian->fakturPembelian;

            if ($pembayaran === null || $faktur === null || $pembayaran->tanggal === null) {
                continue;
            }

            $supplierId = (int) $faktur->supplier_id;
            $transactionsBySupplier[$supplierId][] = [
                'tanggal' => $pembayaran->tanggal->format('Y-m-d'),
                'nomor' => (string) $pembayaran->nomer_pembayaran,
                'jenis' => 'Pembayaran',
                'referensi_faktur' => (string) $faktur->nomer_faktur,
                'keterangan' => (string) ($pembayaran->keterangan ?: 'Pembayaran faktur '.$faktur->nomer_faktur),
                'akun' => $this->formatAccount($pembayaran->akunHutang),
                'debit' => round((float) $rincian->nominal_bayar, 2),
                'kredit' => 0.0,
                'urutan_jenis' => 30,
                'urutan_id' => (int) $rincian->id,
                'faktur_id' => (int) $faktur->id,
                'pembayaran_id' => (int) $pembayaran->id,
            ];
        }

        $cards = [];

        foreach ($suppliers as $supplierId => $supplier) {
            $transactions = $transactionsBySupplier[$supplierId] ?? [];

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
                    $saldoAwal += $transaction['kredit'] - $transaction['debit'];

                    continue;
                }

                $rows[] = $transaction;
            }

            $saldoBerjalan = round($saldoAwal, 2);

            foreach ($rows as &$row) {
                $saldoBerjalan = round($saldoBerjalan + $row['kredit'] - $row['debit'], 2);
                $row['saldo'] = $saldoBerjalan;
            }
            unset($row);

            if (! $this->matchesStatusSaldo($saldoBerjalan, $statusSaldo)) {
                continue;
            }

            $accounts = collect($transactions)
                ->pluck('akun')
                ->reject(fn (string $akun) => $akun === '-')
                ->unique()
                ->values()
                ->all();

            $cards[] = [
                'supplier_id' => (int) $supplier->id,
                'kode_supplier' => (string) ($supplier->kode_supplier ?? ''),
                'nama_supplier' => (string) $supplier->nama_supplier,
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

    public function streamBukuPembantuHutangMutasiCsv(array $report): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Kode Supplier',
            'Nama Supplier',
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
                $card['kode_supplier'],
                $card['nama_supplier'],
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
                    $card['kode_supplier'],
                    $card['nama_supplier'],
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

    private function emptyMutasiReport(): array
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

    private function matchesStatusSaldo(float $saldoAkhir, string $statusSaldo): bool
    {
        return match ($statusSaldo) {
            'masih-hutang' => $saldoAkhir > 0,
            'lunas' => abs($saldoAkhir) < 0.005,
            default => true,
        };
    }

    private function formatAccount(mixed $coa): string
    {
        if ($coa === null) {
            return 'Tanpa akun hutang';
        }

        return sprintf('[%s] %s', $coa->kode, $coa->nama);
    }

    private function emptyBuckets(): array
    {
        return array_fill_keys(self::BUCKET_KEYS, 0.0);
    }

    private function bucketKeyForAge(int $umurHari): string
    {
        return match (true) {
            $umurHari <= 30 => 'days_0_30',
            $umurHari <= 60 => 'days_31_60',
            $umurHari <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }

    private function formatCsvBucket(mixed $value): string
    {
        return (float) $value > 0 ? $this->formatCsvNumber($value) : '';
    }

    private function formatCsvNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
