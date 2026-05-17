<?php

namespace App\Services\Bridging;

use App\Models\BukuBesar;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\LogHapusImportPendapatan;
use App\Models\MappingCoaSimrs;
use App\Models\SimrsImportPendapatanJualObat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BridgingPendapatanObatService
{
    private const IMPORT_JURNAL_UMUM = 'JurnalUmum';
    private const SUMBER_LOG = 'Jual Obat';
    private const SUMBER_TRANSAKSI_JURNAL = 'Jurnal Umum';

    public function getQueryDataImport(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function getKandidatTagihanSimrs(string $startDate, string $endDate): Collection
    {
        $nomerSudahDiimpor = SimrsImportPendapatanJualObat::query()
            ->betweenDates($startDate, $endDate)
            ->pluck('nomer_transaksi')
            ->all();

        $lookupSudahImpor = array_fill_keys($nomerSudahDiimpor, true);

        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_jual AS nomer_transaksi,
                p.tgl_jual AS tanggal,
                p.nm_pasien AS nama_pelanggan,
                p.keterangan AS keterangan,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                p.nama_bayar AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            WHERE p.status = 'Sudah Dibayar'
              AND p.tgl_jual BETWEEN ? AND ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.nm_pasien, p.keterangan, p.jns_jual,
                p.ongkir, p.ppn, p.kd_bangsal, p.nama_bayar
            ORDER BY p.tgl_jual DESC, p.nota_jual DESC
            SQL,
            [$startDate, $endDate]
        ))
            ->reject(fn (object $row) => isset($lookupSudahImpor[(string) $row->nomer_transaksi]))
            ->values()
            ->map(fn (object $row) => [
                'nomer_transaksi' => (string) $row->nomer_transaksi,
                'tanggal' => (string) $row->tanggal,
                'nama_pelanggan' => (string) $row->nama_pelanggan,
                'keterangan' => (string) ($row->keterangan ?? ''),
                'jenis_jual' => (string) $row->jenis_jual,
                'ongkir' => (float) $row->ongkir,
                'ppn' => (float) $row->ppn,
                'kode_gudang' => (string) $row->kode_gudang,
                'kode_rekening' => (string) $row->kode_rekening,
                'nama_rekening' => (string) $row->nama_rekening,
                'grandtotal' => (float) $row->grandtotal,
            ]);
    }

    public function imporBanyak(array $daftarNomerTransaksi, string $jenisProses, string $actor): array
    {
        $hasil = [];

        foreach (array_values(array_unique($daftarNomerTransaksi)) as $nomerTransaksi) {
            try {
                $hasil[] = $this->imporSatu((string) $nomerTransaksi, $jenisProses, $actor);
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    public function hapusBanyak(array $daftarNomerTransaksi, string $actor): array
    {
        $hasil = [];

        foreach (array_values(array_unique($daftarNomerTransaksi)) as $nomerTransaksi) {
            try {
                DB::transaction(function () use ($nomerTransaksi, $actor) {
                    $dataImport = SimrsImportPendapatanJualObat::query()
                        ->where('nomer_transaksi', $nomerTransaksi)
                        ->get();

                    if ($dataImport->isEmpty()) {
                        throw new RuntimeException('Data hasil import tidak ditemukan.');
                    }

                    $jurnal = JurnalUmum::query()
                        ->where('nomer', $nomerTransaksi)
                        ->first();

                    if ($jurnal !== null) {
                        JurnalUmumRinci::query()
                            ->where('jurnal_umum_id', (int) $jurnal->id)
                            ->delete();

                        BukuBesar::query()
                            ->where('nomer', $nomerTransaksi)
                            ->where('sumber_id', (int) $jurnal->id)
                            ->where('sumber_transaksi', self::SUMBER_TRANSAKSI_JURNAL)
                            ->delete();

                        $jurnal->delete();
                    }

                    foreach ($dataImport as $item) {
                        LogHapusImportPendapatan::query()->create([
                            'nomer' => $item->nomer_transaksi,
                            'dihapus_oleh' => $actor,
                            'created_at' => now(),
                            'sumber_transaksi' => self::SUMBER_LOG,
                        ]);
                    }

                    SimrsImportPendapatanJualObat::query()
                        ->where('nomer_transaksi', $nomerTransaksi)
                        ->delete();
                });

                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => true,
                    'alasan_gagal' => null,
                ];
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    private function imporSatu(string $nomerTransaksi, string $jenisProses, string $actor): array
    {
        if ($jenisProses !== 'JurnalUmum') {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Jurnal Umum yang didukung.',
            ];
        }

        if (SimrsImportPendapatanJualObat::query()->where('nomer_transaksi', $nomerTransaksi)->exists()) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Transaksi ini sudah pernah diimport.',
            ];
        }

        $tagihan = $this->ambilTagihanPerNomerTransaksi($nomerTransaksi);
        if ($tagihan === null) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Data tagihan obat tidak ditemukan di SIMRS.',
            ];
        }

        $rincianTagihan = $this->ambilRincianTagihanPerNomerTransaksi($nomerTransaksi);
        if ($rincianTagihan->isEmpty()) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Rincian tagihan obat tidak ditemukan.',
            ];
        }

        $nomerJurnalSimrs = $this->ambilNomerJurnalSimrsTerakhir($nomerTransaksi);
        if ($nomerJurnalSimrs === null) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Jurnal SIMRS tidak ditemukan untuk transaksi ini.',
            ];
        }

        $rincianJurnalSimrs = $this->ambilRincianJurnalSimrs($nomerJurnalSimrs);
        if ($rincianJurnalSimrs->isEmpty()) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Rincian jurnal SIMRS tidak ditemukan.',
            ];
        }

        return DB::transaction(function () use ($tagihan, $rincianTagihan, $rincianJurnalSimrs, $actor) {
            $mappingGeneral = MappingCoaSimrs::query()->get();

            $this->simpanHasilImport($tagihan);

            $jurnal = new JurnalUmum();
            $jurnal->nomer = $tagihan['nomer_transaksi'];
            $jurnal->tanggal = $tagihan['tanggal'];
            $jurnal->keterangan = $this->buatKeteranganJurnal($tagihan, $rincianTagihan);
            $jurnal->debit = (float) $tagihan['grandtotal'];
            $jurnal->kredit = (float) $tagihan['grandtotal'];
            $jurnal->save();

            $totalDebit = 0.0;
            $totalKredit = 0.0;

            // Detail jurnal lokal mengikuti jurnal SIMRS apa adanya, tetapi setiap kode rekening
            // wajib lolos mapping general lebih dulu agar COA yang dipakai tetap konsisten.
            foreach ($rincianJurnalSimrs as $rinci) {
                $mapping = $mappingGeneral->firstWhere('kode_rekening', $rinci['kd_rek']);
                if ($mapping === null) {
                    throw new RuntimeException('Mapping COA SIMRS belum dilakukan untuk kode rekening: '.$rinci['kd_rek']);
                }

                $debit = (float) $rinci['debet'];
                $kredit = (float) $rinci['kredit'];

                JurnalUmumRinci::query()->create([
                    'jurnal_umum_id' => (int) $jurnal->id,
                    'coa_id' => (int) $mapping->coa_id,
                    'debit' => $debit > 0 ? $debit : 0,
                    'kredit' => $kredit > 0 ? $kredit : 0,
                ]);

                BukuBesar::query()->create([
                    'coa_id' => (int) $mapping->coa_id,
                    'sumber_id' => (int) $jurnal->id,
                    'tanggal' => $tagihan['tanggal'],
                    'nomer' => $tagihan['nomer_transaksi'],
                    'sumber_transaksi' => self::SUMBER_TRANSAKSI_JURNAL,
                    'nominal' => $debit > 0 ? $debit : $kredit,
                    'tipe_mutasi' => $debit > 0 ? 'D' : 'K',
                    'keterangan' => 'Penjualan obat & BHP nomor: '.$tagihan['nomer_transaksi'],
                ]);

                $totalDebit += $debit > 0 ? $debit : 0;
                $totalKredit += $kredit > 0 ? $kredit : 0;
            }

            if (abs($totalDebit - $totalKredit) > 0.01) {
                throw new RuntimeException(sprintf(
                    'Jurnal tidak balance untuk transaksi %s. Debit %.2f, kredit %.2f.',
                    $tagihan['nomer_transaksi'],
                    $totalDebit,
                    $totalKredit,
                ));
            }

            return [
                'nomer_transaksi' => $tagihan['nomer_transaksi'],
                'berhasil' => true,
                'alasan_gagal' => null,
                'actor' => $actor,
            ];
        });
    }

    private function simpanHasilImport(array $tagihan): void
    {
        SimrsImportPendapatanJualObat::query()->create([
            'nomer_transaksi' => $tagihan['nomer_transaksi'],
            'tanggal' => $tagihan['tanggal'],
            'nama_pelanggan' => $tagihan['nama_pelanggan'],
            'keterangan' => $tagihan['keterangan'],
            'jenis_jual' => $tagihan['jenis_jual'],
            'ongkir' => $tagihan['ongkir'],
            'ppn' => $tagihan['ppn'],
            'kode_gudang' => $tagihan['kode_gudang'],
            'kode_rekening' => $tagihan['kode_rekening'],
            'nama_rekening' => $tagihan['nama_rekening'],
            'grandtotal' => $tagihan['grandtotal'],
            'import_ke' => self::IMPORT_JURNAL_UMUM,
        ]);
    }

    private function buatKeteranganJurnal(array $tagihan, Collection $rincianTagihan): string
    {
        $rincianText = $rincianTagihan
            ->map(fn (array $item) => sprintf(
                '%s - %s - %s - %s = %s',
                $item['kode_barang'],
                $item['nama_barang'],
                $this->formatNominal($item['kuantitas']),
                $this->formatNominal($item['harga_jual']),
                $this->formatNominal($item['total']),
            ))
            ->implode(PHP_EOL);

        return trim(($tagihan['keterangan'] !== '' ? $tagihan['keterangan'] : '-')
            .PHP_EOL.PHP_EOL
            .'Rincian:'.PHP_EOL
            .$rincianText);
    }

    private function ambilTagihanPerNomerTransaksi(string $nomerTransaksi): ?array
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_jual AS nomer_transaksi,
                p.tgl_jual AS tanggal,
                p.nm_pasien AS nama_pelanggan,
                p.keterangan AS keterangan,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                p.nama_bayar AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            WHERE p.nota_jual = ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.nm_pasien, p.keterangan, p.jns_jual,
                p.ongkir, p.ppn, p.kd_bangsal, p.nama_bayar
            LIMIT 1
            SQL,
            [$nomerTransaksi]
        ))->first();

        if ($row === null) {
            return null;
        }

        return [
            'nomer_transaksi' => (string) $row->nomer_transaksi,
            'tanggal' => (string) $row->tanggal,
            'nama_pelanggan' => (string) $row->nama_pelanggan,
            'keterangan' => (string) ($row->keterangan ?? ''),
            'jenis_jual' => (string) $row->jenis_jual,
            'ongkir' => (float) $row->ongkir,
            'ppn' => (float) $row->ppn,
            'kode_gudang' => (string) $row->kode_gudang,
            'kode_rekening' => (string) $row->kode_rekening,
            'nama_rekening' => (string) $row->nama_rekening,
            'grandtotal' => (float) $row->grandtotal,
        ];
    }

    private function ambilRincianTagihanPerNomerTransaksi(string $nomerTransaksi): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_jual AS nomer_transaksi,
                dj.kode_brng AS kode_barang,
                db.nama_brng AS nama_barang,
                dj.h_jual AS harga_jual,
                dj.jumlah AS kuantitas,
                dj.subtotal AS subtotal,
                dj.total AS total
            FROM detailjual dj
            JOIN penjualan p ON p.nota_jual = dj.nota_jual
            JOIN databarang db ON db.kode_brng = dj.kode_brng
            WHERE p.nota_jual = ?
            ORDER BY dj.kode_brng ASC
            SQL,
            [$nomerTransaksi]
        ))->map(fn (object $row) => [
            'nomer_transaksi' => (string) $row->nomer_transaksi,
            'kode_barang' => (string) $row->kode_barang,
            'nama_barang' => (string) $row->nama_barang,
            'harga_jual' => (float) $row->harga_jual,
            'kuantitas' => (float) $row->kuantitas,
            'subtotal' => (float) $row->subtotal,
            'total' => (float) $row->total,
        ]);
    }

    private function ambilNomerJurnalSimrsTerakhir(string $nomerTransaksi): ?string
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT j.no_jurnal
            FROM jurnal j
            WHERE j.no_bukti = ?
            ORDER BY j.no_jurnal DESC
            LIMIT 1
            SQL,
            [$nomerTransaksi]
        ))->first();

        return $row !== null ? (string) $row->no_jurnal : null;
    }

    private function ambilRincianJurnalSimrs(string $nomerJurnal): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                dj.no_jurnal,
                dj.kd_rek,
                dj.debet,
                dj.kredit
            FROM detailjurnal dj
            WHERE dj.no_jurnal = ?
            ORDER BY dj.kd_rek ASC
            SQL,
            [$nomerJurnal]
        ))->map(fn (object $row) => [
            'no_jurnal' => (string) $row->no_jurnal,
            'kd_rek' => (string) $row->kd_rek,
            'debet' => (float) ($row->debet ?? 0),
            'kredit' => (float) ($row->kredit ?? 0),
        ]);
    }

    private function formatNominal(float $nominal): string
    {
        $rounded = round($nominal, 2);

        if (abs($rounded - round($rounded)) < 0.00001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }
}
