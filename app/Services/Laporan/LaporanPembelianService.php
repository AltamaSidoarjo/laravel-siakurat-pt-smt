<?php

namespace App\Services\Laporan;

use App\Models\FakturPembelian;
use App\Models\PembayaranPembelianRinci;
use App\Models\PreferensiPerusahaan;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class LaporanPembelianService
{
    public function getIdentitasLaporan(): array
    {
        $preferensi = PreferensiPerusahaan::query()->first();

        return [
            'logoRsUrl' => $preferensi?->logo_perusahaan ?? '',
            'namaRumahSakit' => $preferensi?->nama_perusahaan ?: config('siakurat.rs_name'),
        ];
    }

    public function getSupplierTerpilih(array $supplierIds): Collection
    {
        return Supplier::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('kode_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function getSupplierOptions(): Collection
    {
        return Supplier::query()
            ->whereHas('fakturPembelians')
            ->orderBy('kode_supplier')
            ->orderBy('nama_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function searchSupplierHutang(string $search): Collection
    {
        return Supplier::query()
            ->whereHas('fakturPembelians')
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

    public function getBukuPembantuHutang(
        string $startDate,
        string $endDate,
        array $supplierIds,
        string $statusSaldo = 'semua',
    ): array {
        if ($supplierIds === []) {
            return $this->emptyReport();
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

    private function emptyReport(): array
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

    private function formatCsvNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
