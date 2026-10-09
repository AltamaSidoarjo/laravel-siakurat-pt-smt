<?php

namespace App\Services\Bridging;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\LogHapusImportPendapatan;
use App\Models\MappingCoaSimrs;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\Pelanggan;
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
                p.nama_bayar AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            WHERE p.status = 'Sudah Dibayar'
              AND p.tgl_jual BETWEEN ? AND ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.no_rkm_medis, p.nm_pasien, p.keterangan, p.jns_jual,
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
                'nomer_rekam_medis' => (string) $row->nomer_rekam_medis,
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

                    foreach ($dataImport as $item) {
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
                            'sumber_transaksi' => self::SUMBER_LOG,
                        ]);
                    }

                    SimrsImportPendapatanJualObat::query()
                        ->where('nomer_transaksi', $nomerTransaksi)
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

    private function imporSatu(string $nomerTransaksi, string $jenisProses, string $actor): array
    {
        if (! in_array($jenisProses, [self::IMPORT_JURNAL_UMUM, 'InvoicePendapatan'], true)) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Tujuan import tidak didukung.',
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

        $result = DB::transaction(function () use ($tagihan, $rincianTagihan, $rincianJurnalSimrs, $actor, $jenisProses) {
            if ($jenisProses === 'InvoicePendapatan') {
                $this->simpanInvoicePendapatanObat($tagihan, $rincianTagihan, $rincianJurnalSimrs);

                return [
                    'nomer_transaksi' => $tagihan['nomer_transaksi'],
                    'berhasil' => true,
                    'alasan_gagal' => null,
                    'actor' => $actor,
                ];
            }

            $mappingGeneral = MappingCoaSimrs::query()->get();

            $this->simpanHasilImport($tagihan, self::IMPORT_JURNAL_UMUM);

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
                    ...BukuBesarService::resolvePeriode($tagihan['tanggal']),
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

    private function simpanInvoicePendapatanObat(array $tagihan, Collection $rincianTagihan, Collection $rincianJurnalSimrs): void
    {
        $pelanggan = Pelanggan::query()
            ->where('kode_pelanggan', $tagihan['nomer_rekam_medis'])
            ->first();

        if (trim($tagihan['nomer_rekam_medis']) === '' || trim($tagihan['nama_pelanggan']) === '') {
            throw new RuntimeException('Nomor RM atau nama pelanggan SIMRS kosong; invoice tidak dapat dibuat.');
        }

        if ($pelanggan === null) {
            $pelanggan = new Pelanggan();
            $pelanggan->status_aktif = true;
            $pelanggan->kode_pelanggan = $tagihan['nomer_rekam_medis'];
        }

        $pelanggan->nama_pelanggan = $tagihan['nama_pelanggan'];
        $pelanggan->jenis_pelanggan = 'Obat & BHP';
        $pelanggan->save();

        $mappingLawan = MappingLawanPendapatanSimrs::query()->get();
        $coaLookup = Coa::query()->get()->keyBy('id');
        $akunLawan = $this->pilihAkunLawanInvoicePendapatanObat(
            $rincianJurnalSimrs,
            $mappingLawan,
            $coaLookup,
            (float) $tagihan['grandtotal'],
        );

        $kasBank = (float) $akunLawan
            ->filter(fn (array $row) => strtolower((string) $row['coa']->tipe_coa) === 'kasbank')
            ->sum('debit');
        $piutangId = $akunLawan->count() === 1
            && str_contains(strtolower((string) $akunLawan->first()['coa']->tipe_coa), 'piutang')
                ? (int) $akunLawan->first()['coa']->id
                : null;

        $invoice = new FakturPenjualan();
        $invoice->pelanggan_id = (int) $pelanggan->id;
        $invoice->akun_piutang_id = $piutangId;
        $invoice->nomor_faktur = $tagihan['nomer_transaksi'];
        $invoice->tanggal_faktur = $tagihan['tanggal'];
        $invoice->keterangan = 'Bridging Pendapatan Obat - '.$this->buatKeteranganJurnal($tagihan, $rincianTagihan);
        $invoice->grandtotal = (float) $tagihan['grandtotal'];
        $invoice->sudah_terbayar = $kasBank;
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
        $kodeAkunLawanTerpilih = $akunLawan->pluck('kd_rek')->all();
        $mappingCoaSimrs = MappingCoaSimrs::query()->get();
        foreach ($rincianJurnalSimrs as $baris) {
            $coaId = $this->resolveCoaIdBarisJurnalInvoice(
                (string) $baris['kd_rek'],
                $kodeAkunLawanTerpilih,
                $mappingLawan,
                $mappingCoaSimrs,
                $coaLookup,
            );

            $debit = (float) $baris['debet'];
            $kredit = (float) $baris['kredit'];
            $mutasi[] = [
                'coa_id' => $coaId,
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => $debit > 0 ? $debit : $kredit,
                'tipe_mutasi' => $debit > 0 ? 'D' : 'K',
                'keterangan' => 'Penjualan obat & BHP nomor: '.$invoice->nomor_faktur,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($mutasi !== []) {
            BukuBesar::query()->insert($mutasi);
        }

        $this->simpanHasilImport($tagihan, self::IMPORT_INVOICE_PENDAPATAN);
    }

    private function pilihAkunLawanInvoicePendapatanObat(
        Collection $rincianJurnalSimrs,
        Collection $mappingLawan,
        Collection $coaLookup,
        float $nominalTarget,
    ): Collection {
        $akunDebit = $rincianJurnalSimrs
            ->filter(fn (array $baris) => (float) $baris['debet'] > 0)
            ->groupBy('kd_rek')
            ->map(function (Collection $baris, string $kode) use ($mappingLawan, $coaLookup) {
                $mapping = $mappingLawan->firstWhere('kode_coa_simrs', $kode);
                if ($mapping === null) {
                    return null;
                }

                $coa = $coaLookup->get((int) $mapping->coa_id);
                if ($coa === null) {
                    throw new RuntimeException('COA akun lawan pendapatan tidak ditemukan untuk kode SIMRS '.$kode.'.');
                }

                return [
                    'kd_rek' => $kode,
                    'coa' => $coa,
                    'debit' => (float) $baris->sum('debet'),
                ];
            })
            ->filter()
            ->values();

        if ($akunDebit->isEmpty()) {
            throw new RuntimeException('Akun lawan pendapatan tidak ditemukan di jurnal SIMRS.');
        }

        $akunKasAtauPiutang = $akunDebit
            ->filter(function (array $akun) {
                $tipeCoa = strtolower((string) $akun['coa']->tipe_coa);

                return $tipeCoa === 'kasbank' || str_contains($tipeCoa, 'piutang');
            })
            ->values();

        $kandidatAkunLawan = $akunKasAtauPiutang->isNotEmpty()
            ? $akunKasAtauPiutang
            : $akunDebit;

        $akunExactMatch = $kandidatAkunLawan
            ->filter(fn (array $akun) => abs((float) $akun['debit'] - $nominalTarget) < 0.01)
            ->values();

        if ($akunExactMatch->count() > 1) {
            throw new RuntimeException(sprintf(
                'Ditemukan lebih dari satu akun lawan pendapatan SIMRS dengan nominal %.2f.',
                $nominalTarget,
            ));
        }

        if ($akunExactMatch->count() === 1) {
            return $akunExactMatch;
        }

        if ($kandidatAkunLawan->count() === 1) {
            $akun = $kandidatAkunLawan->first();
            $akun['debit'] = $nominalTarget;

            return collect([$akun]);
        }

        if (abs((float) $kandidatAkunLawan->sum('debit') - $nominalTarget) <= 0.01) {
            return $kandidatAkunLawan;
        }

        throw new RuntimeException(sprintf(
            'Akun lawan pendapatan SIMRS tidak cocok dengan total tagihan. Target %.2f, jurnal SIMRS %.2f.',
            $nominalTarget,
            (float) $kandidatAkunLawan->sum('debit'),
        ));
    }

    private function resolveCoaIdBarisJurnalInvoice(
        string $kodeRekening,
        array $kodeAkunLawanTerpilih,
        Collection $mappingLawan,
        Collection $mappingCoaSimrs,
        Collection $coaLookup,
    ): int {
        if (in_array($kodeRekening, $kodeAkunLawanTerpilih, true)) {
            $mappingLawanTerpilih = $mappingLawan->firstWhere('kode_coa_simrs', $kodeRekening);
            if ($mappingLawanTerpilih === null) {
                throw new RuntimeException('Mapping akun lawan pendapatan belum disetting untuk kode COA SIMRS '.$kodeRekening.'.');
            }

            $coaId = (int) $mappingLawanTerpilih->coa_id;
            if ($coaLookup->get($coaId) === null) {
                throw new RuntimeException('COA akun lawan pendapatan tidak ditemukan untuk kode SIMRS '.$kodeRekening.'.');
            }

            return $coaId;
        }

        $mappingUmum = $mappingCoaSimrs->firstWhere('kode_rekening', $kodeRekening);

        if ($mappingUmum === null) {
            throw new RuntimeException('Mapping COA SIMRS belum dilakukan untuk kode rekening: '.$kodeRekening);
        }

        $coaId = (int) $mappingUmum->coa_id;
        if ($coaLookup->get($coaId) === null) {
            throw new RuntimeException('COA hasil mapping SIMRS tidak ditemukan untuk kode rekening: '.$kodeRekening);
        }

        return $coaId;
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
                p.nama_bayar AS kode_rekening,
                p.nama_bayar AS nama_rekening,
                SUM(dj.subtotal) + p.ongkir + p.ppn AS grandtotal
            FROM penjualan p
            JOIN detailjual dj ON dj.nota_jual = p.nota_jual
            WHERE p.nota_jual = ?
            GROUP BY
                p.nota_jual, p.tgl_jual, p.no_rkm_medis, p.nm_pasien, p.keterangan, p.jns_jual,
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
            'nomer_rekam_medis' => (string) $row->nomer_rekam_medis,
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
