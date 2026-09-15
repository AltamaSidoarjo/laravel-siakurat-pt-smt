<?php

namespace App\Services\Laporan;

use App\Models\FakturPembelian;
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
