<?php

namespace App\Services\Bridging;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\LogHapusImportPendapatan;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\Pelanggan;
use App\Models\SettingBridgingObat;
use App\Models\SimrsImportPendapatanJualObat;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BridgingPendapatanObatService
{
    private const IMPORT_JURNAL_UMUM = 'JurnalUmum';
    private const IMPORT_INVOICE_PENDAPATAN = 'Invoice Pendapatan';
    private const IMPORT_INVOICE_PIUTANG_OBAT = 'Invoice Piutang Obat';
    private const SUMBER_LOG = 'Jual Obat';
    private const SUMBER_TRANSAKSI_JURNAL = 'Jurnal Umum';

    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

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
                p.no_rkm_medis AS nomer_rekam_medis,
                p.nm_pasien AS nama_pelanggan,
                p.keterangan AS keterangan,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                COALESCE(NULLIF(p.kd_rek, ''), ab.kd_rek, p.nama_bayar) AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            LEFT JOIN akun_bayar ab ON ab.nama_bayar = p.nama_bayar
            WHERE p.status = 'Sudah Dibayar'
              AND p.tgl_jual BETWEEN ? AND ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.no_rkm_medis, p.nm_pasien, p.keterangan, p.jns_jual,
                p.ongkir, p.ppn, p.kd_bangsal, p.kd_rek, p.nama_bayar, ab.kd_rek
            ORDER BY p.tgl_jual DESC, p.nota_jual DESC
            SQL,
            [$startDate, $endDate]
        ))
            ->reject(fn (object $row) => isset($lookupSudahImpor[(string) $row->nomer_transaksi]))
            ->values()
            ->map(fn (object $row) => [
                'nomer_transaksi' => (string) $row->nomer_transaksi,
                'tanggal' => (string) $row->tanggal,
                'nomer_rekam_medis' => (string) ($row->nomer_rekam_medis ?? ''),
                'nama_pelanggan' => (string) ($row->nama_pelanggan ?? ''),
                'keterangan' => (string) ($row->keterangan ?? ''),
                'jenis_jual' => (string) ($row->jenis_jual ?? ''),
                'ongkir' => (float) ($row->ongkir ?? 0),
                'ppn' => (float) ($row->ppn ?? 0),
                'kode_gudang' => (string) ($row->kode_gudang ?? ''),
                'kode_rekening' => (string) ($row->kode_rekening ?? ''),
                'nama_rekening' => (string) ($row->nama_rekening ?? ''),
                'grandtotal' => (float) $row->grandtotal,
            ]);
    }

    public function getKandidatPiutangSimrs(string $startDate, string $endDate): Collection
    {
        $nomerSudahDiimpor = SimrsImportPendapatanJualObat::query()
            ->pluck('nomer_transaksi')
            ->map(fn ($nomer) => (string) $nomer)
            ->all();
        $lookupSudahImpor = array_fill_keys($nomerSudahDiimpor, true);

        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_piutang AS nomer_transaksi,
                p.tgl_piutang AS tanggal,
                p.no_rkm_medis AS nomer_rekam_medis,
                p.nm_pasien AS nama_pelanggan,
                p.catatan AS keterangan,
                p.tgltempo AS tanggal_jatuh_tempo,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                p.status AS status_piutang,
                COALESCE(d.total_detail, 0) + COALESCE(p.ongkir, 0) + COALESCE(p.ppn, 0) AS grandtotal,
                p.uangmuka AS uangmuka,
                p.sisapiutang AS sisapiutang
            FROM piutang p
            LEFT JOIN (
                SELECT nota_piutang, SUM(total) AS total_detail
                FROM detailpiutang
                GROUP BY nota_piutang
            ) d ON d.nota_piutang = p.nota_piutang
            WHERE p.sisapiutang > 0
              AND p.tgl_piutang BETWEEN ? AND ?
            ORDER BY p.tgl_piutang DESC, p.nota_piutang DESC
            SQL,
            [$startDate, $endDate]
        ))
            ->reject(fn (object $row) => isset($lookupSudahImpor[(string) $row->nomer_transaksi]))
            ->values()
            ->map(fn (object $row) => [
                'nomer_transaksi' => (string) $row->nomer_transaksi,
                'tanggal' => (string) $row->tanggal,
                'nomer_rekam_medis' => (string) ($row->nomer_rekam_medis ?? ''),
                'nama_pelanggan' => (string) ($row->nama_pelanggan ?? ''),
                'keterangan' => (string) ($row->keterangan ?? ''),
                'tanggal_jatuh_tempo' => (string) ($row->tanggal_jatuh_tempo ?? ''),
                'jenis_jual' => (string) ($row->jenis_jual ?? ''),
                'ongkir' => (float) ($row->ongkir ?? 0),
                'ppn' => (float) ($row->ppn ?? 0),
                'kode_gudang' => (string) ($row->kode_gudang ?? ''),
                'kode_rekening' => '',
                'nama_rekening' => (string) ($row->status_piutang ?? ''),
                'grandtotal' => (float) $row->grandtotal,
                'uangmuka' => (float) ($row->uangmuka ?? 0),
                'sisapiutang' => (float) ($row->sisapiutang ?? 0),
            ]);
    }

    public function getSettingBridgingObat(): SettingBridgingObat
    {
        $setting = SettingBridgingObat::query()->first();

        if ($setting === null
            || empty($setting->pendapatan_obat_coa_id)
            || empty($setting->hpp_obat_coa_id)
            || empty($setting->persediaan_obat_coa_id)
            || empty($setting->piutang_obat_coa_id)
            || empty($setting->kas_uang_muka_coa_id)
        ) {
            throw new RuntimeException('Pengaturan Akun Obat & BHP belum lengkap. Silakan konfigurasi seluruh akun di menu Pengaturan > Setting Akun Obat & BHP.');
        }

        return $setting;
    }

    public function imporPiutangBanyak(array $daftarNota, string $actor): array
    {
        $hasil = [];

        foreach (array_values(array_unique($daftarNota)) as $nota) {
            try {
                $hasil[] = $this->imporSatuPiutang((string) $nota, $actor);
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nota,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
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

                    foreach ($dataImport as $item) {
                        if ($item->import_ke === self::IMPORT_INVOICE_PIUTANG_OBAT) {
                            $nomorFakturPiutang = 'P-'.$nomerTransaksi;
                            $invoice = FakturPenjualan::query()
                                ->where('nomor_faktur', $nomorFakturPiutang)
                                ->where('keterangan', 'like', '%Bridging Piutang Obat & BHP%')
                                ->first();

                            if ($invoice !== null) {
                                FakturPenjualanRinci::query()
                                    ->where('faktur_penjualan_id', (int) $invoice->id)
                                    ->delete();

                                BukuBesar::query()
                                    ->where('nomer', $nomorFakturPiutang)
                                    ->where('sumber_id', (int) $invoice->id)
                                    ->where('sumber_transaksi', self::IMPORT_INVOICE_PIUTANG_OBAT)
                                    ->delete();

                                $invoice->delete();
                            }

                            continue;
                        }

                        if ($item->import_ke === self::IMPORT_INVOICE_PENDAPATAN) {
                            $invoice = FakturPenjualan::query()
                                ->where('nomor_faktur', $nomerTransaksi)
                                ->where('keterangan', 'like', '%Bridging Pendapatan Obat%')
                                ->first();

                            if ($invoice !== null) {
                                FakturPenjualanRinci::query()
                                    ->where('faktur_penjualan_id', (int) $invoice->id)
                                    ->delete();

                                BukuBesar::query()
                                    ->where('nomer', $nomerTransaksi)
                                    ->where('sumber_id', (int) $invoice->id)
                                    ->where('sumber_transaksi', self::IMPORT_INVOICE_PENDAPATAN)
                                    ->delete();

                                $invoice->delete();
                            }

                            continue;
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
                    }

                    foreach ($dataImport as $item) {
                        LogHapusImportPendapatan::query()->create([
                            'nomer' => $item->nomer_transaksi,
                            'dihapus_oleh' => $actor,
                            'created_at' => now(),
                            'sumber_transaksi' => $item->import_ke === self::IMPORT_INVOICE_PIUTANG_OBAT
                                ? 'Piutang Obat & BHP'
                                : self::SUMBER_LOG,
                        ]);
                    }

                    SimrsImportPendapatanJualObat::query()
                        ->whereIn('id', $dataImport->pluck('id'))
                        ->delete();
                });

                $this->logService->log('Bridging Pendapatan Obat', 'delete', [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                ]);

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

    private function imporSatuPiutang(string $nota, string $actor): array
    {
        if (SimrsImportPendapatanJualObat::query()->where('nomer_transaksi', $nota)->exists()) {
            throw new RuntimeException('Piutang ini sudah pernah diimport.');
        }

        $tagihan = $this->ambilPiutangPerNota($nota);
        if ($tagihan === null) {
            throw new RuntimeException('Data piutang tidak ditemukan di SIMRS.');
        }

        $rincian = $this->ambilRincianPiutang($nota);
        if ($rincian->isEmpty()) {
            throw new RuntimeException('Rincian piutang tidak ditemukan.');
        }

        $setting = $this->getSettingBridgingObat();

        $totalRincian = (float) $rincian->sum('total');
        $grandtotal = round($totalRincian + $tagihan['ppn'] + $tagihan['ongkir'], 2);
        $totalHeader = round($tagihan['uangmuka'] + $tagihan['sisapiutang'], 2);
        if (abs($grandtotal - $totalHeader) > 0.01) {
            throw new RuntimeException(sprintf(
                'Total piutang tidak rekonsiliasi. Detail + PPN + ongkir %.2f, uang muka + sisa piutang %.2f.',
                $grandtotal,
                $totalHeader,
            ));
        }

        DB::transaction(function () use ($tagihan, $rincian, $setting, $nota, $grandtotal) {
            $pelanggan = $this->cariAtauBuatPelanggan(
                $tagihan['nomer_rekam_medis'],
                $tagihan['nama_pelanggan'],
            );

            $invoice = new FakturPenjualan();
            $invoice->pelanggan_id = (int) $pelanggan->id;
            $invoice->akun_piutang_id = (int) $setting->piutang_obat_coa_id;
            $invoice->nomor_faktur = 'P-'.$nota;
            $invoice->tanggal_faktur = $tagihan['tanggal'];
            $invoice->tanggal_registrasi = $tagihan['tanggal'];
            $invoice->keterangan = 'Bridging Piutang Obat & BHP - '.$tagihan['keterangan'];
            $invoice->grandtotal = $grandtotal;
            $invoice->sudah_terbayar = (float) $tagihan['uangmuka'];
            $invoice->status_proses = 0;
            $invoice->created_by = 'system';
            $invoice->updated_by = 'system';
            $invoice->nama_pasien = $tagihan['nama_pelanggan'];
            $invoice->nomer_rekam_medis = $tagihan['nomer_rekam_medis'];
            $invoice->save();

            foreach ($rincian as $baris) {
                $rinci = new FakturPenjualanRinci();
                $rinci->faktur_penjualan_id = (int) $invoice->id;
                $rinci->harga = $baris['harga_jual'];
                $rinci->kuantitas = $baris['kuantitas'];
                $rinci->subtotal = $baris['total'];
                $rinci->catatan = implode(' - ', array_filter([
                    $baris['kode_barang'], $baris['kode_satuan'], $baris['no_batch'], $baris['aturan_pakai'],
                ]));
                $rinci->save();
            }

            $mutasi = [];

            // 1. Debit Piutang Obat (sebesar sisapiutang)
            if ($tagihan['sisapiutang'] > 0) {
                $mutasi[] = [
                    'coa_id' => (int) $setting->piutang_obat_coa_id,
                    'sumber_id' => (int) $invoice->id,
                    'tanggal' => $invoice->tanggal_faktur,
                    ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                    'nomer' => $invoice->nomor_faktur,
                    'sumber_transaksi' => self::IMPORT_INVOICE_PIUTANG_OBAT,
                    'nominal' => (float) $tagihan['sisapiutang'],
                    'tipe_mutasi' => 'D',
                    'keterangan' => 'Piutang obat & BHP nomor: '.$nota,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 2. Debit Kas / Uang Muka (jika uangmuka > 0)
            if ($tagihan['uangmuka'] > 0) {
                $mutasi[] = [
                    'coa_id' => (int) $setting->kas_uang_muka_coa_id,
                    'sumber_id' => (int) $invoice->id,
                    'tanggal' => $invoice->tanggal_faktur,
                    ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                    'nomer' => $invoice->nomor_faktur,
                    'sumber_transaksi' => self::IMPORT_INVOICE_PIUTANG_OBAT,
                    'nominal' => (float) $tagihan['uangmuka'],
                    'tipe_mutasi' => 'D',
                    'keterangan' => 'Uang muka piutang obat & BHP nomor: '.$nota,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 3. Kredit Persediaan Obat (sebesar grandtotal)
            $mutasi[] = [
                'coa_id' => (int) $setting->persediaan_obat_coa_id,
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PIUTANG_OBAT,
                'nominal' => $grandtotal,
                'tipe_mutasi' => 'K',
                'keterangan' => 'Piutang obat & BHP nomor: '.$nota,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            BukuBesar::query()->insert($mutasi);

            $this->simpanHasilImport($tagihan, self::IMPORT_INVOICE_PIUTANG_OBAT);
        });

        $this->logService->log('Bridging Pendapatan Obat', 'create', null, [
            'nomer_transaksi' => $nota,
            'sumber' => 'Piutang Obat & BHP',
        ]);

        return ['nomer_transaksi' => $nota, 'berhasil' => true, 'alasan_gagal' => null];
    }

    private function imporSatu(string $nomerTransaksi, string $jenisProses, string $actor): array
    {
        if (! in_array($jenisProses, [self::IMPORT_JURNAL_UMUM, self::IMPORT_INVOICE_PENDAPATAN, 'InvoicePendapatan'], true)) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Tujuan import tidak didukung.',
            ];
        }

        // Normalisasi nama jenis proses jika dikirimkan sebagai 'InvoicePendapatan'
        $jenisProsesNormal = $jenisProses === 'InvoicePendapatan'
            ? self::IMPORT_INVOICE_PENDAPATAN
            : $jenisProses;

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

        $setting = $this->getSettingBridgingObat();
        $mappingLawan = MappingLawanPendapatanSimrs::query()->get();
        $coaLookup = Coa::query()->get()->keyBy('id');
        $coaKasBank = $this->resolveAkunLawanCaraBayar($tagihan, $mappingLawan, $coaLookup);

        $totalHpp = round((float) $rincianTagihan->sum(fn ($b) => (float) $b['harga_beli'] * (float) $b['kuantitas']), 2);

        $result = DB::transaction(function () use (
            $tagihan,
            $rincianTagihan,
            $setting,
            $coaKasBank,
            $totalHpp,
            $actor,
            $jenisProsesNormal
        ) {
            if ($jenisProsesNormal === self::IMPORT_INVOICE_PENDAPATAN) {
                $this->simpanInvoicePendapatanObat(
                    $tagihan,
                    $rincianTagihan,
                    $setting,
                    $coaKasBank,
                    $totalHpp
                );

                return [
                    'nomer_transaksi' => $tagihan['nomer_transaksi'],
                    'berhasil' => true,
                    'alasan_gagal' => null,
                    'actor' => $actor,
                ];
            }

            $this->simpanHasilImport($tagihan, self::IMPORT_JURNAL_UMUM);

            $grandtotal = (float) $tagihan['grandtotal'];
            $totalNilaiJurnal = round($grandtotal + $totalHpp, 2);

            $jurnal = new JurnalUmum();
            $jurnal->nomer = $tagihan['nomer_transaksi'];
            $jurnal->tanggal = $tagihan['tanggal'];
            $jurnal->keterangan = $this->buatKeteranganJurnal($tagihan, $rincianTagihan);
            $jurnal->debit = $totalNilaiJurnal;
            $jurnal->kredit = $totalNilaiJurnal;
            $jurnal->save();

            // 1. Debit Kas/Bank dari Cara Bayar
            JurnalUmumRinci::query()->create([
                'jurnal_umum_id' => (int) $jurnal->id,
                'coa_id' => (int) $coaKasBank->id,
                'debit' => $grandtotal,
                'kredit' => 0,
            ]);

            BukuBesar::query()->create([
                'coa_id' => (int) $coaKasBank->id,
                'sumber_id' => (int) $jurnal->id,
                'tanggal' => $tagihan['tanggal'],
                ...BukuBesarService::resolvePeriode($tagihan['tanggal']),
                'nomer' => $tagihan['nomer_transaksi'],
                'sumber_transaksi' => self::SUMBER_TRANSAKSI_JURNAL,
                'nominal' => $grandtotal,
                'tipe_mutasi' => 'D',
                'keterangan' => 'Penjualan obat & BHP nomor: '.$tagihan['nomer_transaksi'],
            ]);

            // 2. Kredit Pendapatan Obat & BHP
            JurnalUmumRinci::query()->create([
                'jurnal_umum_id' => (int) $jurnal->id,
                'coa_id' => (int) $setting->pendapatan_obat_coa_id,
                'debit' => 0,
                'kredit' => $grandtotal,
            ]);

            BukuBesar::query()->create([
                'coa_id' => (int) $setting->pendapatan_obat_coa_id,
                'sumber_id' => (int) $jurnal->id,
                'tanggal' => $tagihan['tanggal'],
                ...BukuBesarService::resolvePeriode($tagihan['tanggal']),
                'nomer' => $tagihan['nomer_transaksi'],
                'sumber_transaksi' => self::SUMBER_TRANSAKSI_JURNAL,
                'nominal' => $grandtotal,
                'tipe_mutasi' => 'K',
                'keterangan' => 'Penjualan obat & BHP nomor: '.$tagihan['nomer_transaksi'],
            ]);

            // 3. Debit HPP Obat & Kredit Persediaan Obat (jika HPP > 0)
            if ($totalHpp > 0) {
                JurnalUmumRinci::query()->create([
                    'jurnal_umum_id' => (int) $jurnal->id,
                    'coa_id' => (int) $setting->hpp_obat_coa_id,
                    'debit' => $totalHpp,
                    'kredit' => 0,
                ]);

                BukuBesar::query()->create([
                    'coa_id' => (int) $setting->hpp_obat_coa_id,
                    'sumber_id' => (int) $jurnal->id,
                    'tanggal' => $tagihan['tanggal'],
                    ...BukuBesarService::resolvePeriode($tagihan['tanggal']),
                    'nomer' => $tagihan['nomer_transaksi'],
                    'sumber_transaksi' => self::SUMBER_TRANSAKSI_JURNAL,
                    'nominal' => $totalHpp,
                    'tipe_mutasi' => 'D',
                    'keterangan' => 'HPP penjualan obat & BHP nomor: '.$tagihan['nomer_transaksi'],
                ]);

                JurnalUmumRinci::query()->create([
                    'jurnal_umum_id' => (int) $jurnal->id,
                    'coa_id' => (int) $setting->persediaan_obat_coa_id,
                    'debit' => 0,
                    'kredit' => $totalHpp,
                ]);

                BukuBesar::query()->create([
                    'coa_id' => (int) $setting->persediaan_obat_coa_id,
                    'sumber_id' => (int) $jurnal->id,
                    'tanggal' => $tagihan['tanggal'],
                    ...BukuBesarService::resolvePeriode($tagihan['tanggal']),
                    'nomer' => $tagihan['nomer_transaksi'],
                    'sumber_transaksi' => self::SUMBER_TRANSAKSI_JURNAL,
                    'nominal' => $totalHpp,
                    'tipe_mutasi' => 'K',
                    'keterangan' => 'Pengurangan persediaan obat & BHP nomor: '.$tagihan['nomer_transaksi'],
                ]);
            }

            return [
                'nomer_transaksi' => $tagihan['nomer_transaksi'],
                'berhasil' => true,
                'alasan_gagal' => null,
                'actor' => $actor,
            ];
        });

        $this->logService->log('Bridging Pendapatan Obat', 'create', null, [
            'nomer_transaksi' => $nomerTransaksi,
        ]);

        return $result;
    }

    private function simpanHasilImport(array $tagihan, string $importKe): void
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
            'import_ke' => $importKe,
        ]);
    }

    private function simpanInvoicePendapatanObat(
        array $tagihan,
        Collection $rincianTagihan,
        SettingBridgingObat $setting,
        Coa $coaKasBank,
        float $totalHpp,
    ): void {
        $pelanggan = $this->cariAtauBuatPelanggan(
            $tagihan['nomer_rekam_medis'],
            $tagihan['nama_pelanggan'],
        );

        $grandtotal = (float) $tagihan['grandtotal'];

        $invoice = new FakturPenjualan();
        $invoice->pelanggan_id = (int) $pelanggan->id;
        $invoice->akun_piutang_id = null;
        $invoice->nomor_faktur = $tagihan['nomer_transaksi'];
        $invoice->tanggal_faktur = $tagihan['tanggal'];
        $invoice->keterangan = 'Bridging Pendapatan Obat - '.$this->buatKeteranganJurnal($tagihan, $rincianTagihan);
        $invoice->grandtotal = $grandtotal;
        $invoice->sudah_terbayar = $grandtotal;
        $invoice->status_proses = 0;
        $invoice->created_by = 'system';
        $invoice->updated_by = 'system';
        $invoice->nama_pasien = $tagihan['nama_pelanggan'];
        $invoice->nomer_rekam_medis = $tagihan['nomer_rekam_medis'];
        $invoice->tanggal_registrasi = $tagihan['tanggal'];
        $invoice->save();

        foreach ($rincianTagihan as $baris) {
            $rinci = new FakturPenjualanRinci();
            $rinci->faktur_penjualan_id = (int) $invoice->id;
            $rinci->harga = (float) $baris['harga_jual'];
            $rinci->kuantitas = (float) $baris['kuantitas'];
            $rinci->subtotal = (float) $baris['subtotal'];
            $rinci->catatan = trim($baris['kode_barang'].' - '.$baris['nama_barang']);
            $rinci->save();
        }

        $mutasi = [];

        // 1. Debit Kas/Bank dari Cara Bayar
        $mutasi[] = [
            'coa_id' => (int) $coaKasBank->id,
            'sumber_id' => (int) $invoice->id,
            'tanggal' => $invoice->tanggal_faktur,
            ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
            'nomer' => $invoice->nomor_faktur,
            'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
            'nominal' => $grandtotal,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Penjualan obat & BHP nomor: '.$invoice->nomor_faktur,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 2. Kredit Pendapatan Obat & BHP
        $mutasi[] = [
            'coa_id' => (int) $setting->pendapatan_obat_coa_id,
            'sumber_id' => (int) $invoice->id,
            'tanggal' => $invoice->tanggal_faktur,
            ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
            'nomer' => $invoice->nomor_faktur,
            'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
            'nominal' => $grandtotal,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Penjualan obat & BHP nomor: '.$invoice->nomor_faktur,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 3. Debit HPP Obat & Kredit Persediaan Obat (jika HPP > 0)
        if ($totalHpp > 0) {
            $mutasi[] = [
                'coa_id' => (int) $setting->hpp_obat_coa_id,
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => $totalHpp,
                'tipe_mutasi' => 'D',
                'keterangan' => 'HPP penjualan obat & BHP nomor: '.$invoice->nomor_faktur,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $mutasi[] = [
                'coa_id' => (int) $setting->persediaan_obat_coa_id,
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => $totalHpp,
                'tipe_mutasi' => 'K',
                'keterangan' => 'Pengurangan persediaan obat & BHP nomor: '.$invoice->nomor_faktur,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        BukuBesar::query()->insert($mutasi);

        $this->simpanHasilImport($tagihan, self::IMPORT_INVOICE_PENDAPATAN);
    }

    private function resolveAkunLawanCaraBayar(
        array $tagihan,
        Collection $mappingLawan,
        Collection $coaLookup,
    ): Coa {
        $kdRek = trim((string) ($tagihan['kd_rek'] ?? ''));
        $namaBayar = trim((string) ($tagihan['nama_bayar'] ?? ''));

        if ($kdRek === '' && $namaBayar !== '') {
            $rowAkunBayar = collect(DB::connection('simrs')->select(
                'SELECT kd_rek FROM akun_bayar WHERE nama_bayar = ? LIMIT 1',
                [$namaBayar]
            ))->first();

            if ($rowAkunBayar !== null && ! empty($rowAkunBayar->kd_rek)) {
                $kdRek = trim((string) $rowAkunBayar->kd_rek);
            }
        }

        $mapping = null;
        if ($kdRek !== '') {
            $mapping = $mappingLawan->firstWhere('kode_coa_simrs', $kdRek);
        }
        if ($mapping === null && $namaBayar !== '') {
            $mapping = $mappingLawan->firstWhere('nama_coa_simrs', $namaBayar);
        }

        if ($mapping === null) {
            $identitas = $kdRek !== '' ? "kode rekening '{$kdRek}'" : "cara bayar '{$namaBayar}'";
            throw new RuntimeException("Mapping akun lawan pendapatan belum disetting untuk {$identitas}. Silakan konfigurasi di menu Pengaturan > Mapping Pendapatan.");
        }

        $coa = $coaLookup->get((int) $mapping->coa_id);
        if ($coa === null) {
            throw new RuntimeException("COA akun lawan pendapatan tidak ditemukan untuk mapping SIMRS {$mapping->kode_coa_simrs}.");
        }

        return $coa;
    }

    private function cariAtauBuatPelanggan(?string $noRkmMedis, ?string $nmPasien): Pelanggan
    {
        $noRm = trim((string) $noRkmMedis);
        $nama = trim((string) $nmPasien);

        if ($noRm === '') {
            $noRm = 'UMUM';
        }
        if ($nama === '') {
            $nama = 'PASIEN UMUM';
        }

        $pelanggan = Pelanggan::query()
            ->where('kode_pelanggan', $noRm)
            ->first();

        if ($pelanggan === null) {
            $pelanggan = new Pelanggan();
            $pelanggan->status_aktif = true;
            $pelanggan->kode_pelanggan = $noRm;
        }

        $pelanggan->nama_pelanggan = $nama;
        $pelanggan->jenis_pelanggan = 'Obat & BHP';
        $pelanggan->save();

        return $pelanggan;
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
                p.no_rkm_medis AS nomer_rekam_medis,
                p.nm_pasien AS nama_pelanggan,
                p.keterangan AS keterangan,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                p.kd_rek AS kd_rek,
                p.nama_bayar AS nama_bayar,
                COALESCE(NULLIF(p.kd_rek, ''), ab.kd_rek, p.nama_bayar) AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            LEFT JOIN akun_bayar ab ON ab.nama_bayar = p.nama_bayar
            WHERE p.nota_jual = ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.no_rkm_medis, p.nm_pasien, p.keterangan, p.jns_jual,
                p.ongkir, p.ppn, p.kd_bangsal, p.kd_rek, p.nama_bayar, ab.kd_rek
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
            'nomer_rekam_medis' => (string) ($row->nomer_rekam_medis ?? ''),
            'nama_pelanggan' => (string) ($row->nama_pelanggan ?? ''),
            'keterangan' => (string) ($row->keterangan ?? ''),
            'jenis_jual' => (string) ($row->jenis_jual ?? ''),
            'ongkir' => (float) ($row->ongkir ?? 0),
            'ppn' => (float) ($row->ppn ?? 0),
            'kode_gudang' => (string) ($row->kode_gudang ?? ''),
            'kd_rek' => (string) ($row->kd_rek ?? ''),
            'nama_bayar' => (string) ($row->nama_bayar ?? ''),
            'kode_rekening' => (string) ($row->kode_rekening ?? ''),
            'nama_rekening' => (string) ($row->nama_rekening ?? ''),
            'grandtotal' => (float) $row->grandtotal,
        ];
    }

    private function ambilPiutangPerNota(string $nota): ?array
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_piutang AS nomer_transaksi,
                p.tgl_piutang AS tanggal,
                p.no_rkm_medis AS nomer_rekam_medis,
                p.nm_pasien AS nama_pelanggan,
                p.catatan AS keterangan,
                p.tgltempo AS tanggal_jatuh_tempo,
                p.jns_jual AS jenis_jual,
                p.ongkir AS ongkir,
                p.ppn AS ppn,
                p.kd_bangsal AS kode_gudang,
                p.status AS status_piutang,
                p.uangmuka AS uangmuka,
                p.sisapiutang AS sisapiutang,
                COALESCE(SUM(dp.total), 0) + COALESCE(p.ongkir, 0) + COALESCE(p.ppn, 0) AS grandtotal
            FROM piutang p
            LEFT JOIN detailpiutang dp ON dp.nota_piutang = p.nota_piutang
            WHERE p.nota_piutang = ?
            GROUP BY
                p.nota_piutang, p.tgl_piutang, p.no_rkm_medis, p.nm_pasien, p.catatan,
                p.jns_jual, p.ongkir, p.ppn, p.kd_bangsal, p.status, p.tgltempo, p.uangmuka, p.sisapiutang
            LIMIT 1
            SQL,
            [$nota]
        ))->first();

        if ($row === null) {
            return null;
        }

        return [
            'nomer_transaksi' => (string) $row->nomer_transaksi,
            'tanggal' => (string) $row->tanggal,
            'nomer_rekam_medis' => (string) ($row->nomer_rekam_medis ?? ''),
            'nama_pelanggan' => (string) ($row->nama_pelanggan ?? ''),
            'keterangan' => (string) ($row->keterangan ?? ''),
            'tanggal_jatuh_tempo' => (string) ($row->tanggal_jatuh_tempo ?? ''),
            'jenis_jual' => (string) ($row->jenis_jual ?? ''),
            'ongkir' => (float) ($row->ongkir ?? 0),
            'ppn' => (float) ($row->ppn ?? 0),
            'kode_gudang' => (string) ($row->kode_gudang ?? ''),
            'kode_rekening' => '',
            'nama_rekening' => (string) ($row->status_piutang ?? ''),
            'grandtotal' => (float) $row->grandtotal,
            'uangmuka' => (float) ($row->uangmuka ?? 0),
            'sisapiutang' => (float) ($row->sisapiutang ?? 0),
        ];
    }

    private function ambilRincianPiutang(string $nota): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                kode_brng AS kode_barang,
                kode_sat AS kode_satuan,
                h_jual AS harga_jual,
                jumlah AS kuantitas,
                total,
                no_batch,
                aturan_pakai
            FROM detailpiutang
            WHERE nota_piutang = ?
            ORDER BY kode_brng ASC
            SQL,
            [$nota]
        ))->map(fn (object $row) => [
            'kode_barang' => (string) $row->kode_barang,
            'kode_satuan' => (string) ($row->kode_satuan ?? ''),
            'harga_jual' => (float) ($row->harga_jual ?? 0),
            'kuantitas' => (float) ($row->kuantitas ?? 0),
            'total' => (float) ($row->total ?? 0),
            'no_batch' => (string) ($row->no_batch ?? ''),
            'aturan_pakai' => (string) ($row->aturan_pakai ?? ''),
        ]);
    }

    private function ambilRincianTagihanPerNomerTransaksi(string $nomerTransaksi): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.nota_jual AS nomer_transaksi,
                dj.kode_brng AS kode_barang,
                db.nama_brng AS nama_barang,
                dj.h_beli AS harga_beli,
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
            'harga_beli' => (float) ($row->harga_beli ?? 0),
            'harga_jual' => (float) ($row->harga_jual ?? 0),
            'kuantitas' => (float) ($row->kuantitas ?? 0),
            'subtotal' => (float) ($row->subtotal ?? 0),
            'total' => (float) ($row->total ?? 0),
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
