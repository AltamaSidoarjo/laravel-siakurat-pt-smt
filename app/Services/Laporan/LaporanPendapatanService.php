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
use Illuminate\Support\Collection;
use RuntimeException;

class LaporanPendapatanService
{
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
            ->get(['id', 'kode_pelanggan', 'nama_pelanggan']);
    }

    public function searchPelangganPiutang(string $search): Collection
    {
        return Pelanggan::query()
            ->whereHas('fakturPenjualans')
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('kode_pelanggan', 'like', $like)
                        ->orWhere('nama_pelanggan', 'like', $like);
                });
            })
            ->orderBy('kode_pelanggan')
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

    public function getBukuPembantuPiutang(
        string $startDate,
        string $endDate,
        array $pelangganIds,
        ?string $akunPiutang = null,
        string $statusSaldo = 'semua',
    ): array {
        if ($pelangganIds === []) {
            return $this->emptyBukuPembantuPiutang();
        }

        $pelanggan = $this->getPelangganTerpilih($pelangganIds)->keyBy('id');
        $transactionsByPelanggan = $pelanggan->map(fn () => [])->all();

        $fakturs = FakturPenjualan::query()
            ->select([
                'id',
                'pelanggan_id',
                'akun_piutang_id',
                'nomor_faktur',
                'tanggal_faktur',
                'keterangan',
                'grandtotal',
                'sudah_terbayar',
                'nama_pasien',
                'nomer_rekam_medis',
            ])
            ->with('akunPiutang:id,kode,nama')
            ->withSum('penerimaanPenjualanRincis as total_alokasi', 'nominal_bayar')
            ->whereIn('pelanggan_id', $pelanggan->keys())
            ->whereNotNull('tanggal_faktur')
            ->whereDate('tanggal_faktur', '<=', $endDate)
            ->get();

        foreach ($fakturs as $faktur) {
            $pelangganId = (int) $faktur->pelanggan_id;
            $akunId = $faktur->akun_piutang_id === null ? null : (int) $faktur->akun_piutang_id;

            if (! $this->matchesAkunFilter($akunId, $akunPiutang)) {
                continue;
            }

            $tanggal = $faktur->tanggal_faktur->format('Y-m-d');
            $accountDisplay = $this->formatAccount($faktur->akunPiutang);
            $description = $this->formatInvoiceDescription($faktur);

            $transactionsByPelanggan[$pelangganId][] = [
                'tanggal' => $tanggal,
                'nomor' => (string) $faktur->nomor_faktur,
                'jenis' => 'Faktur',
                'referensi_faktur' => (string) $faktur->nomor_faktur,
                'keterangan' => $description,
                'akun_id' => $akunId,
                'akun' => $accountDisplay,
                'debit' => round((float) $faktur->grandtotal, 2),
                'kredit' => 0.0,
                'urutan_jenis' => 10,
                'urutan_id' => (int) $faktur->id,
                'faktur_id' => (int) $faktur->id,
                'penerimaan_id' => null,
            ];

            $pembayaranLangsung = max(
                0,
                round((float) $faktur->sudah_terbayar - (float) ($faktur->total_alokasi ?? 0), 2),
            );

            if ($pembayaranLangsung > 0) {
                $transactionsByPelanggan[$pelangganId][] = [
                    'tanggal' => $tanggal,
                    'nomor' => (string) $faktur->nomor_faktur,
                    'jenis' => 'Pembayaran langsung',
                    'referensi_faktur' => (string) $faktur->nomor_faktur,
                    'keterangan' => 'Pembayaran yang telah melekat saat faktur dibuat',
                    'akun_id' => $akunId,
                    'akun' => $accountDisplay,
                    'debit' => 0.0,
                    'kredit' => $pembayaranLangsung,
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
            ->whereHas('fakturPenjualan', fn (Builder $query) => $query->whereIn('pelanggan_id', $pelanggan->keys()))
            ->whereHas('penerimaanPenjualan', fn (Builder $query) => $query->whereDate('tanggal', '<=', $endDate))
            ->get(['id', 'penerimaan_penjualan_id', 'faktur_penjualan_id', 'nominal_bayar']);

        foreach ($penerimaanRincis as $rincian) {
            $penerimaan = $rincian->penerimaanPenjualan;
            $faktur = $rincian->fakturPenjualan;

            if ($penerimaan === null || $faktur === null || $penerimaan->tanggal === null) {
                continue;
            }

            $akunId = $penerimaan->akun_piutang_id === null ? null : (int) $penerimaan->akun_piutang_id;

            if (! $this->matchesAkunFilter($akunId, $akunPiutang)) {
                continue;
            }

            $pelangganId = (int) $faktur->pelanggan_id;
            $transactionsByPelanggan[$pelangganId][] = [
                'tanggal' => $penerimaan->tanggal->format('Y-m-d'),
                'nomor' => (string) $penerimaan->nomer,
                'jenis' => 'Penerimaan',
                'referensi_faktur' => (string) $faktur->nomor_faktur,
                'keterangan' => (string) ($penerimaan->keterangan ?: 'Pembayaran faktur '.$faktur->nomor_faktur),
                'akun_id' => $akunId,
                'akun' => $this->formatAccount($penerimaan->akunPiutang),
                'debit' => 0.0,
                'kredit' => round((float) $rincian->nominal_bayar, 2),
                'urutan_jenis' => 30,
                'urutan_id' => (int) $rincian->id,
                'faktur_id' => (int) $faktur->id,
                'penerimaan_id' => (int) $penerimaan->id,
            ];
        }

        $cards = [];

        foreach ($pelanggan as $pelangganId => $item) {
            $transactions = $transactionsByPelanggan[$pelangganId] ?? [];

            if ($transactions === []) {
                continue;
            }

            usort($transactions, function (array $left, array $right): int {
                return [$left['tanggal'], $left['urutan_jenis'], $left['urutan_id']]
                    <=> [$right['tanggal'], $right['urutan_jenis'], $right['urutan_id']];
            });

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

            if (! $this->matchesStatusSaldo($saldoBerjalan, $statusSaldo)) {
                continue;
            }

            $accounts = collect($transactions)
                ->pluck('akun')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $cards[] = [
                'pelanggan_id' => (int) $item->id,
                'kode_pelanggan' => (string) ($item->kode_pelanggan ?? ''),
                'nama_pelanggan' => (string) $item->nama_pelanggan,
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

    private function emptyBukuPembantuPiutang(): array
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

    private function matchesAkunFilter(?int $akunId, ?string $akunPiutang): bool
    {
        if ($akunPiutang === null || $akunPiutang === '') {
            return true;
        }

        if ($akunPiutang === 'tanpa-akun') {
            return $akunId === null;
        }

        return $akunId === (int) $akunPiutang;
    }

    private function matchesStatusSaldo(float $saldoAkhir, string $statusSaldo): bool
    {
        return match ($statusSaldo) {
            'masih-piutang' => $saldoAkhir > 0,
            'lunas' => abs($saldoAkhir) < 0.005,
            default => true,
        };
    }

    private function formatAccount(?Coa $coa): string
    {
        if ($coa === null) {
            return 'Tanpa akun piutang';
        }

        return sprintf('[%s] %s', $coa->kode, $coa->nama);
    }

    private function formatInvoiceDescription(FakturPenjualan $faktur): string
    {
        $patient = trim(implode(' / ', array_filter([
            $faktur->nama_pasien,
            $faktur->nomer_rekam_medis,
        ])));

        return $patient !== '' ? $patient : (string) ($faktur->keterangan ?: 'Faktur pendapatan');
    }
}
