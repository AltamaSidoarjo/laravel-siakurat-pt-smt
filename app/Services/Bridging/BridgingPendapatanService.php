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
use App\Models\MappingPendapatan;
use App\Models\MappingPendapatanKamar;
use App\Models\MappingPendapatanUmum;
use App\Models\Pelanggan;
use App\Models\SimrsImportPendapatan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BridgingPendapatanService
{
    private const IMPORT_JURNAL_UMUM = 'Jurnal Umum';

    private const IMPORT_INVOICE_PENDAPATAN = 'Invoice Pendapatan';

    private const KATEGORI_DENGAN_KODE = [
        'Ralan Dokter',
        'Ralan Dokter Paramedis',
        'Ralan Paramedis',
        'Ranap Dokter',
        'Ranap Dokter Paramedis',
        'Ranap Paramedis',
        'Laborat',
        'Radiologi',
        'Kamar',
    ];

    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logService,
    ) {}

    public function getQueryDataImport(
        string $startDate,
        string $endDate,
        ?string $poli = null,
        ?string $penjamin = null,
    ): Builder {
        return SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->when($poli, fn (Builder $query) => $query->where('poli', 'like', '%'.$poli.'%'))
            ->when($penjamin, fn (Builder $query) => $query->where('penjamin', 'like', '%'.$penjamin.'%'))
            ->orderByDesc('tanggal_reg')
            ->orderByDesc('id');
    }

    public function getKandidatBillingSimrs(
        string $startDate,
        string $endDate,
        ?string $poli = null,
        ?string $penjamin = null,
    ): Collection {
        $daftarNoRawatTerimpor = SimrsImportPendapatan::query()
            ->pluck('nomer_billing')
            ->filter()
            ->all();

        $lookupNoRawatTerimpor = array_fill_keys($daftarNoRawatTerimpor, true);

        $daftarBilling = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_rkm_medis AS no_rekam_medis,
                p.nm_pasien AS nama_pasien,
                p.jk AS jenis_kelamin,
                p.alamat AS alamat,
                kel.nm_kel AS nama_kelurahan,
                kec.nm_kec AS nama_kecamatan,
                kab.nm_kab AS nama_kabupaten,
                rp.no_rawat AS no_rawat,
                rp.status_lanjut AS status_lanjut,
                rp.tgl_registrasi AS tanggal_registrasi,
                rp.jam_reg AS jam_registrasi,
                rp.kd_dokter AS kode_dokter,
                d.nm_dokter AS nama_dokter,
                rp.kd_poli AS kode_poli,
                poli.nm_poli AS nama_poli,
                rp.kd_pj AS kode_penjamin,
                pj.png_jawab AS penjamin,
                COALESCE(SUM(bil.totalbiaya), 0) AS total_biaya
            FROM reg_periksa rp
            JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
            LEFT JOIN kelurahan kel ON kel.kd_kel = p.kd_kel
            LEFT JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
            LEFT JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
            JOIN billing bil ON bil.no_rawat = rp.no_rawat
            JOIN dokter d ON d.kd_dokter = rp.kd_dokter
            JOIN poliklinik poli ON poli.kd_poli = rp.kd_poli
            JOIN penjab pj ON pj.kd_pj = rp.kd_pj
            WHERE rp.status_bayar = 'Sudah Bayar'
              AND rp.tgl_registrasi BETWEEN ? AND ?
            GROUP BY
                p.no_rkm_medis, p.nm_pasien, p.jk, p.alamat,
                kel.nm_kel, kec.nm_kec, kab.nm_kab,
                rp.no_rawat, rp.status_lanjut, rp.tgl_registrasi, rp.jam_reg,
                rp.kd_dokter, d.nm_dokter, rp.kd_poli, poli.nm_poli,
                rp.kd_pj, pj.png_jawab
            ORDER BY rp.tgl_registrasi DESC, rp.no_rawat DESC
            SQL,
            [$startDate, $endDate]
        ));

        return $daftarBilling
            ->reject(fn (object $baris) => isset($lookupNoRawatTerimpor[(string) $baris->no_rawat]))
            ->filter(function (object $baris) use ($poli, $penjamin) {
                $sesuaiPoli = $poli === null || $poli === ''
                    || str_contains(Str::lower((string) $baris->nama_poli), Str::lower($poli));
                $sesuaiPenjamin = $penjamin === null || $penjamin === ''
                    || str_contains(Str::lower((string) $baris->penjamin), Str::lower($penjamin));

                return $sesuaiPoli && $sesuaiPenjamin;
            })
            ->values();
    }

    public function imporBanyak(
        array $selectedNoRawat,
        string $jenisProses,
        string $basisTanggalPengakuan,
        string $actor,
    ): array {
        $hasil = [];

        foreach (array_values(array_unique($selectedNoRawat)) as $noRawat) {
            try {
                $hasil[] = $this->imporSatu(
                    (string) $noRawat,
                    $jenisProses,
                    $basisTanggalPengakuan,
                    $actor,
                );
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    public function hapusBanyak(array $selectedNoRawat, string $actor): array
    {
        $hasil = [];

        foreach (array_values(array_unique($selectedNoRawat)) as $noRawat) {
            try {
                DB::transaction(function () use ($noRawat, $actor) {
                    $dataImport = SimrsImportPendapatan::query()
                        ->where('nomer_billing', $noRawat)
                        ->get();

                    $jurnal = JurnalUmum::query()
                        ->where('nomer', $noRawat)
                        ->first();

                    if ($jurnal !== null) {
                        $this->bukuBesarService->deleteBySource(self::IMPORT_JURNAL_UMUM, (int) $jurnal->id);
                        $jurnal->rincian()->delete();
                        $jurnal->delete();
                    }

                    $invoice = FakturPenjualan::query()
                        ->where('nomor_faktur', $noRawat)
                        ->first();

                    if ($invoice !== null) {
                        BukuBesar::query()
                            ->where('sumber_transaksi', self::IMPORT_INVOICE_PENDAPATAN)
                            ->where('sumber_id', (int) $invoice->id)
                            ->delete();
                        $invoice->rincian()->delete();
                        $invoice->delete();
                    }

                    foreach ($dataImport as $import) {
                        LogHapusImportPendapatan::query()->create([
                            'nomer' => $import->nomer_billing,
                            'dihapus_oleh' => $actor,
                            'created_at' => now(),
                            'sumber_transaksi' => $import->import_ke,
                        ]);
                    }

                    SimrsImportPendapatan::query()
                        ->where('nomer_billing', $noRawat)
                        ->delete();
                });

                $this->logService->log('Bridging Pendapatan', 'delete', [
                    'no_rawat' => (string) $noRawat,
                ]);

                $hasil[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => true,
                    'alasan_gagal' => null,
                ];
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    public function deteksiJurnalTidakBalance(string $startDate, string $endDate): Collection
    {
        return collect(DB::select(
            <<<'SQL'
            SELECT
                sip.nomer_billing AS no_rawat,
                sip.tanggal_reg AS tanggal_registrasi,
                COALESCE(SUM(jr.debit), 0) AS total_debit,
                COALESCE(SUM(jr.kredit), 0) AS total_kredit
            FROM simrs_import_pendapatan sip
            JOIN jurnal_umum ju
                ON ju.nomer COLLATE utf8mb4_unicode_ci = sip.nomer_billing COLLATE utf8mb4_unicode_ci
            LEFT JOIN jurnal_umum_rinci jr ON jr.jurnal_umum_id = ju.id
            WHERE sip.import_ke = 'Jurnal Umum'
              AND sip.tanggal_reg BETWEEN ? AND ?
            GROUP BY sip.nomer_billing, sip.tanggal_reg
            HAVING ABS(COALESCE(SUM(jr.debit), 0) - COALESCE(SUM(jr.kredit), 0)) > 0.01
            ORDER BY sip.tanggal_reg DESC, sip.nomer_billing DESC
            SQL,
            [$startDate, $endDate]
        ))->map(function (object $baris) {
            $totalDebit = (float) $baris->total_debit;
            $totalKredit = (float) $baris->total_kredit;

            return [
                'no_rawat' => (string) $baris->no_rawat,
                'tanggal_registrasi' => (string) $baris->tanggal_registrasi,
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'selisih' => $totalDebit - $totalKredit,
            ];
        });
    }

    private function imporSatu(
        string $noRawat,
        string $jenisProses,
        string $basisTanggalPengakuan,
        string $actor,
    ): array {
        if (SimrsImportPendapatan::query()->where('nomer_billing', $noRawat)->exists()) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'No rawat ini sudah pernah diimport.',
            ];
        }

        $dataBilling = $this->ambilHeaderBillingByNoRawat($noRawat);

        if ($dataBilling === null) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Data billing tidak ditemukan di SIMRS.',
            ];
        }

        $tanggalPengakuan = $this->tentukanTanggalPengakuan($dataBilling, $basisTanggalPengakuan);
        if ($tanggalPengakuan === null) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Tanggal pengakuan tidak dapat ditentukan.',
            ];
        }

        $rincianBilling = $this->ambilRincianBillingByNoRawat($noRawat);
        if ($rincianBilling->isEmpty()) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Rincian billing tidak ditemukan.',
            ];
        }

        return DB::transaction(function () use (
            $dataBilling,
            $rincianBilling,
            $tanggalPengakuan,
            $jenisProses,
            $actor
        ) {
            $dataMapping = $this->muatMapping();

            $barisPendapatanTerpetakan = [];
            $coaKamarTerakhir = null;

            // Setiap rincian billing harus lebih dulu dipetakan ke COA pendapatan yang tepat
            // sebelum jurnal atau invoice dibuat, supaya validasi bisnis berhenti di awal
            // ketika ada mapping yang belum lengkap.
            foreach ($rincianBilling as $rincian) {
                $barisPendapatanTerpetakan[] = $this->petakanBarisPendapatan(
                    $dataBilling,
                    $rincian,
                    $dataMapping,
                    $coaKamarTerakhir,
                );
            }

            $akunLawan = $this->tentukanAkunLawanPendapatan(
                $dataBilling,
                $rincianBilling,
                $dataMapping['lawan'],
                $dataMapping['coa'],
            );

            $this->simpanLogImport($dataBilling, $actor, $jenisProses);

            if ($jenisProses === 'InvoicePendapatan') {
                $this->simpanInvoicePendapatan(
                    $dataBilling,
                    $barisPendapatanTerpetakan,
                    $akunLawan,
                    $tanggalPengakuan,
                    $dataMapping['coa'],
                );
            } else {
                $this->simpanJurnalUmum(
                    $dataBilling,
                    $barisPendapatanTerpetakan,
                    $akunLawan,
                    $tanggalPengakuan,
                );
            }

            $this->logService->log('Bridging Pendapatan', 'create', null, [
                'no_rawat' => $dataBilling['no_rawat'],
                'nama_pasien' => $dataBilling['nama_pasien'],
                'penjamin' => $dataBilling['penjamin'],
                'total_biaya' => $dataBilling['total_biaya'],
                'jenis_proses' => $jenisProses,
            ]);

            return [
                'no_rawat' => $dataBilling['no_rawat'],
                'berhasil' => true,
                'alasan_gagal' => null,
            ];
        });
    }

    private function simpanLogImport(array $billing, string $actor, string $jenisProses): void
    {
        $tujuanImport = $jenisProses === 'InvoicePendapatan'
            ? self::IMPORT_INVOICE_PENDAPATAN
            : self::IMPORT_JURNAL_UMUM;

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $billing['no_rawat'],
            'tanggal_reg' => $billing['tanggal_registrasi'],
            'user_importer' => $actor,
            'import_time' => now(),
            'dokter' => $billing['nama_dokter'],
            'nama_pasien' => $billing['nama_pasien'],
            'penjamin' => $billing['penjamin'],
            'poli' => $billing['nama_poli'],
            'status_layanan' => $billing['status_lanjut'],
            'total_tagihan' => $billing['total_biaya'],
            'alamat' => $billing['alamat'],
            'jam_reg' => $billing['jam_registrasi'],
            'kode_dokter' => $billing['kode_dokter'],
            'kode_penjamin' => $billing['kode_penjamin'],
            'kode_poli' => $billing['kode_poli'],
            'nama_kabupaten' => $billing['nama_kabupaten'],
            'nama_kecamatan' => $billing['nama_kecamatan'],
            'nama_kelurahan' => $billing['nama_kelurahan'],
            'no_rekam_medis' => $billing['no_rekam_medis'],
            'import_ke' => $tujuanImport,
        ]);
    }

    private function simpanJurnalUmum(
        array $billing,
        array $resolvedRevenueRows,
        array $counterAccounts,
        string $tanggalPengakuan,
    ): void {
        $jurnal = new JurnalUmum;
        $jurnal->nomer = $billing['no_rawat'];
        $jurnal->tanggal = $tanggalPengakuan;
        $jurnal->keterangan = $this->buatNarasi($billing);
        $jurnal->debit = 0;
        $jurnal->kredit = 0;
        $jurnal->save();

        $muatanJurnal = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;

        foreach ($resolvedRevenueRows as $barisPendapatan) {
            $rincianJurnal = new JurnalUmumRinci;
            $rincianJurnal->jurnal_umum_id = (int) $jurnal->id;
            $rincianJurnal->coa_id = (int) $barisPendapatan['coa_id'];
            $rincianJurnal->debit = $barisPendapatan['debit'];
            $rincianJurnal->kredit = $barisPendapatan['kredit'];
            $rincianJurnal->catatan = $barisPendapatan['catatan'];
            $rincianJurnal->save();

            $muatanJurnal[] = [
                'coa_id' => (int) $barisPendapatan['coa_id'],
                'debit' => (float) $barisPendapatan['debit'],
                'kredit' => (float) $barisPendapatan['kredit'],
                'catatan' => $barisPendapatan['catatan'],
            ];

            $totalDebit += (float) $barisPendapatan['debit'];
            $totalKredit += (float) $barisPendapatan['kredit'];
        }

        foreach ($counterAccounts as $akunLawan) {
            $rincianJurnal = new JurnalUmumRinci;
            $rincianJurnal->jurnal_umum_id = (int) $jurnal->id;
            $rincianJurnal->coa_id = (int) $akunLawan['coa_id'];
            $rincianJurnal->debit = $akunLawan['debit'];
            $rincianJurnal->kredit = 0;
            $rincianJurnal->catatan = 'Akun lawan pendapatan';
            $rincianJurnal->save();

            $muatanJurnal[] = [
                'coa_id' => (int) $akunLawan['coa_id'],
                'debit' => (float) $akunLawan['debit'],
                'kredit' => 0,
                'catatan' => 'Akun lawan pendapatan',
            ];

            $totalDebit += (float) $akunLawan['debit'];
        }

        if (abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException(sprintf(
                'Jurnal tidak balance untuk no rawat %s. Debit %.2f, kredit %.2f.',
                $billing['no_rawat'],
                $totalDebit,
                $totalKredit,
            ));
        }

        $jurnal->debit = $totalDebit;
        $jurnal->kredit = $totalKredit;
        $jurnal->save();

        $this->bukuBesarService->syncFromJurnalUmum(
            (int) $jurnal->id,
            $billing['no_rawat'],
            $tanggalPengakuan,
            $jurnal->keterangan,
            $muatanJurnal,
        );
    }

    private function simpanInvoicePendapatan(
        array $billing,
        array $resolvedRevenueRows,
        array $counterAccounts,
        string $tanggalPengakuan,
        Collection $coaLookup,
    ): void {
        $pelanggan = $this->cariAtauBuatPelanggan(
            $billing['kode_penjamin'],
            $billing['penjamin'],
        );

        $daftarAkunLawan = collect($counterAccounts)
            ->map(function (array $akun) use ($coaLookup) {
                return [
                    ...$akun,
                    'coa' => $coaLookup->get((int) $akun['coa_id']),
                ];
            });

        // Untuk invoice bridging, akun lawan dari SIMRS bisa bercampur antara kas/bank dan piutang.
        // Dari sini kita turunkan dua informasi:
        // 1. `sudahTerbayar` jika lawannya kas/bank
        // 2. `akunPiutangId` jika seluruh lawannya murni piutang.
        $sudahTerbayar = $daftarAkunLawan
            ->filter(fn (array $akun) => str_starts_with((string) optional($akun['coa'])->kode, '111.'))
            ->sum('debit');

        $akunPiutangId = null;
        if ($daftarAkunLawan->count() === 1) {
            $coa = $daftarAkunLawan->first()['coa'] ?? null;
            if ($coa !== null && str_starts_with((string) $coa->kode, '112.')) {
                $akunPiutangId = (int) $coa->id;
            }
        }

        $invoice = new FakturPenjualan;
        $invoice->pelanggan_id = (int) $pelanggan->id;
        $invoice->akun_piutang_id = $akunPiutangId;
        $invoice->nomor_faktur = $billing['no_rawat'];
        $invoice->tanggal_faktur = $tanggalPengakuan;
        $invoice->keterangan = $this->buatNarasi($billing);
        $invoice->grandtotal = (float) $billing['total_biaya'];
        $invoice->sudah_terbayar = (float) $sudahTerbayar;
        $invoice->status_proses = 0;
        $invoice->created_by = 'system';
        $invoice->updated_by = 'system';
        $invoice->kode_poli = $billing['kode_poli'];
        $invoice->nama_poli = $billing['nama_poli'];
        $invoice->jam_registrasi = $billing['jam_registrasi'];
        $invoice->jenis_kelamin = $billing['jenis_kelamin'];
        $invoice->kode_dokter = $billing['kode_dokter'];
        $invoice->nama_dokter = $billing['nama_dokter'];
        $invoice->nama_pasien = $billing['nama_pasien'];
        $invoice->nomer_rawat = $billing['no_rawat'];
        $invoice->nomer_rekam_medis = $billing['no_rekam_medis'];
        $invoice->tanggal_registrasi = $billing['tanggal_registrasi'];
        $invoice->kode_penjamin = $billing['kode_penjamin'];
        $invoice->nama_penjamin = $billing['penjamin'];
        $invoice->save();

        foreach ($resolvedRevenueRows as $barisPendapatan) {
            $rinci = new FakturPenjualanRinci;
            $rinci->faktur_penjualan_id = (int) $invoice->id;
            $rinci->harga = abs((float) $barisPendapatan['raw_total']);
            $rinci->kuantitas = (float) $barisPendapatan['quantity'];
            $rinci->subtotal = (float) $barisPendapatan['raw_total'];
            $rinci->catatan = $barisPendapatan['catatan'];
            $rinci->save();
        }

        $this->sinkronkanBukuBesarInvoicePendapatan(
            $invoice,
            $resolvedRevenueRows,
            $counterAccounts,
        );
    }

    private function sinkronkanBukuBesarInvoicePendapatan(
        FakturPenjualan $invoice,
        array $resolvedRevenueRows,
        array $counterAccounts,
    ): void {
        BukuBesar::query()
            ->where('sumber_transaksi', self::IMPORT_INVOICE_PENDAPATAN)
            ->where('sumber_id', (int) $invoice->id)
            ->delete();

        $muatanBukuBesar = [];

        foreach ($resolvedRevenueRows as $barisPendapatan) {
            $muatanBukuBesar[] = [
                'coa_id' => (int) $barisPendapatan['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => abs((float) $barisPendapatan['raw_total']),
                'tipe_mutasi' => $barisPendapatan['raw_total'] < 0 ? 'D' : 'K',
                'keterangan' => $barisPendapatan['catatan'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($counterAccounts as $akunLawan) {
            $muatanBukuBesar[] = [
                'coa_id' => (int) $akunLawan['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                ...BukuBesarService::resolvePeriode($invoice->tanggal_faktur),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => (float) $akunLawan['debit'],
                'tipe_mutasi' => 'D',
                'keterangan' => 'akun lawan pendapatan',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($muatanBukuBesar !== []) {
            BukuBesar::query()->insert($muatanBukuBesar);
        }
    }

    private function cariAtauBuatPelanggan(string $kodePenjamin, string $namaPenjamin): Pelanggan
    {
        $pelangganTersedia = Pelanggan::query()
            ->where('kode_pelanggan', $kodePenjamin)
            ->first();

        if ($pelangganTersedia !== null) {
            return $pelangganTersedia;
        }

        $pelanggan = new Pelanggan;
        $pelanggan->status_aktif = true;
        $pelanggan->kode_pelanggan = $kodePenjamin;
        $pelanggan->nama_pelanggan = $namaPenjamin;
        $pelanggan->save();

        return $pelanggan;
    }

    private function muatMapping(): array
    {
        return [
            'tindakan' => MappingPendapatan::query()->get(),
            'umum' => MappingPendapatanUmum::query()->get(),
            'kamar' => MappingPendapatanKamar::query()->get(),
            'lawan' => MappingLawanPendapatanSimrs::query()->get(),
            'coa' => Coa::query()->get()->keyBy('id'),
        ];
    }

    private function petakanBarisPendapatan(
        array $billing,
        array $detail,
        array $mappings,
        ?int &$lastKamarCoaId,
    ): array {
        $status = (string) $detail['status_billing'];
        $namaPerawatan = trim((string) $detail['nama_perawatan']);
        $totalMentah = (float) $detail['total_biaya'];
        $coaId = null;

        // Pola pemetaan pendapatan mengikuti dua jalur:
        // - kategori yang butuh kode tindakan/kamar dari SIMRS
        // - kategori umum yang langsung dibaca dari mapping pendapatan umum per penjamin
        if (in_array($status, self::KATEGORI_DENGAN_KODE, true)) {
            if ($status === 'Kamar' && $detail['pemisah'] === ':' && ! str_contains($namaPerawatan, ',')) {
                if ($lastKamarCoaId === null) {
                    throw new RuntimeException('Mapping kamar tidak ditemukan untuk biaya sekali rawat inap.');
                }

                $coaId = $lastKamarCoaId;
            } else {
                $kode = $this->tentukanKodeKategori($status, $billing['no_rawat'], $namaPerawatan);
                if ($kode === null || $kode === '') {
                    throw new RuntimeException(sprintf(
                        'Kode tindakan tidak ditemukan untuk status "%s" dan tindakan "%s".',
                        $status,
                        $namaPerawatan,
                    ));
                }

                if ($status === 'Kamar') {
                    $mappingKamar = $mappings['kamar']->firstWhere('kode_kamar', $kode);
                    if ($mappingKamar === null) {
                        throw new RuntimeException(
                            'Mapping kamar belum disetting untuk kode '.$this->formatTeksTebal($kode)
                            .' - '.$this->formatTeksTebal($namaPerawatan).'.'
                        );
                    }

                    $coaId = (int) $mappingKamar->pendapatan_kamar_coa_id;
                    $lastKamarCoaId = $coaId;
                } else {
                    $sumberTindakan = $this->tentukanSumberTindakan($status);
                    $mappingTindakan = $mappings['tindakan']->first(
                        fn (MappingPendapatan $item) => $this->mappingTindakanSesuai($item, $kode, $namaPerawatan, $sumberTindakan)
                    );

                    if ($mappingTindakan === null) {
                        throw new RuntimeException(
                            'Mapping tindakan belum disetting untuk '.$status
                            .', kode '.$this->formatTeksTebal($kode)
                            .' - '.$this->formatTeksTebal($namaPerawatan).'.'
                        );
                    }

                    $coaId = (int) $mappingTindakan->coa_id;
                }
            }
        } else {
            $mappingUmum = $mappings['umum']->first(function (MappingPendapatanUmum $item) use ($status, $billing) {
                return $item->nama === $status
                    && $item->kode_penjamin === $billing['kode_penjamin'];
            });

            if ($mappingUmum === null) {
                throw new RuntimeException(
                    'Mapping pendapatan umum belum disetting untuk status '.$this->formatTeksTebal($status)
                    .' dan penjamin '.$this->formatTeksTebal($billing['penjamin']).'.'
                );
            }

            $coaId = (int) $mappingUmum->coa_id;
        }

        $nominal = abs($totalMentah);

        return [
            'coa_id' => $coaId,
            'debit' => $totalMentah < 0 ? $nominal : 0,
            'kredit' => $totalMentah < 0 ? 0 : $nominal,
            'raw_total' => $totalMentah,
            'quantity' => (float) $detail['jumlah'],
            'catatan' => $this->buatCatatanPendapatan($status, $namaPerawatan),
        ];
    }

    private function tentukanAkunLawanPendapatan(
        array $billing,
        Collection $details,
        Collection $mappingLawan,
        Collection $coaLookup,
    ): array {
        $hasilAkunLawan = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT d.kd_rek, COALESCE(d.debet, 0) AS debet
            FROM (
                SELECT j.no_jurnal
                FROM jurnal j
                WHERE j.no_bukti = ?
                AND (
                    j.keterangan LIKE 'PEMBAYARAN%'
                    OR j.keterangan LIKE 'PIUTANG%'
                )
                ORDER BY j.no_jurnal DESC
                LIMIT 1
            ) last_jurnal
            JOIN detailjurnal d ON d.no_jurnal = last_jurnal.no_jurnal
            WHERE COALESCE(d.debet, 0) > 0
            ORDER BY d.kd_rek
            SQL,
            [$billing['no_rawat']]
        ))->map(fn (object $row) => [
            'kd_rek' => (string) $row->kd_rek,
            'debet' => (float) $row->debet,
        ]);

        if ($hasilAkunLawan->isEmpty()) {
            throw new RuntimeException('Akun lawan pendapatan tidak ditemukan di jurnal SIMRS.');
        }

        // Nominal potongan/retur obat sudah diposting sebagai lawan debit di sisi pendapatan,
        // jadi nominal yang sama perlu dikeluarkan dari jurnal SIMRS sebelum akun lawan dipilih.
        $daftarNominalYangDiabaikan = $details
            ->filter(fn (array $item) => in_array($item['status_billing'], ['Retur Obat', 'Potongan'], true))
            ->map(fn (array $item) => abs((float) $item['total_biaya']))
            ->filter(fn (float $value) => $value > 0)
            ->values()
            ->all();

        foreach ($daftarNominalYangDiabaikan as $nominal) {
            $index = $hasilAkunLawan->search(fn (array $item) => abs($item['debet'] - $nominal) < 0.01);
            if ($index !== false) {
                $hasilAkunLawan->forget($index);
            }
        }

        $akunLawanTergabung = $hasilAkunLawan
            ->groupBy('kd_rek')
            ->map(fn (Collection $baris, string $kode) => [
                'kd_rek' => $kode,
                'debet' => (float) $baris->sum('debet'),
            ])
            ->values();

        $akunLawanTergabung = $this->prioritaskanAkunLawanKasAtauPiutang(
            $akunLawanTergabung,
            $mappingLawan,
            $coaLookup,
        );

        $nominalTarget = array_sum(array_map(fn (array $baris) => (float) $baris['kredit'], $this->normalisasiBarisNominal($details)))
            - array_sum(array_map(fn (array $baris) => (float) $baris['debit'], $this->normalisasiBarisNominal($details)));

        $akunLawanTergabung = $this->pilihAkunLawanPendapatanSimrs(
            $akunLawanTergabung,
            $nominalTarget,
        );

        if (abs($akunLawanTergabung->sum('debet') - $nominalTarget) > 0.01) {
            throw new RuntimeException(sprintf(
                'Akun lawan pendapatan SIMRS tidak cocok dengan total billing. Target %.2f, jurnal SIMRS %.2f.',
                $nominalTarget,
                (float) $akunLawanTergabung->sum('debet'),
            ));
        }

        return $akunLawanTergabung->map(function (array $item) use ($mappingLawan) {
            $mappingAkunLawan = $mappingLawan->firstWhere('kode_coa_simrs', $item['kd_rek']);

            if ($mappingAkunLawan === null) {
                throw new RuntimeException(
                    'Mapping akun lawan pendapatan belum disetting untuk kode COA SIMRS '
                    .$this->formatTeksTebal($item['kd_rek']).'.'
                );
            }

            return [
                'coa_id' => (int) $mappingAkunLawan->coa_id,
                'debit' => (float) $item['debet'],
            ];
        })->all();
    }

    private function prioritaskanAkunLawanKasAtauPiutang(
        Collection $akunLawanTergabung,
        Collection $mappingLawan,
        Collection $coaLookup,
    ): Collection {
        $akunKasAtauPiutang = $akunLawanTergabung
            ->filter(function (array $item) use ($mappingLawan, $coaLookup) {
                $mappingAkunLawan = $mappingLawan->firstWhere('kode_coa_simrs', $item['kd_rek']);
                if ($mappingAkunLawan === null) {
                    return false;
                }

                $coa = $coaLookup->get((int) $mappingAkunLawan->coa_id);
                if ($coa === null) {
                    return false;
                }

                $tipeCoa = Str::lower((string) $coa->tipe_coa);
                $kodeCoa = (string) $coa->kode;

                return $tipeCoa === 'kasbank'
                    || str_contains($tipeCoa, 'piutang')
                    || str_starts_with($kodeCoa, '111.')
                    || str_starts_with($kodeCoa, '112.');
            })
            ->values();

        return $akunKasAtauPiutang->isNotEmpty()
            ? $akunKasAtauPiutang
            : $akunLawanTergabung;
    }

    private function pilihAkunLawanPendapatanSimrs(Collection $akunLawanTergabung, float $nominalTarget): Collection
    {
        $akunExactMatch = $akunLawanTergabung
            ->filter(fn (array $item) => abs((float) $item['debet'] - $nominalTarget) < 0.01)
            ->values();

        if ($akunExactMatch->count() === 1) {
            return collect([$akunExactMatch->first()]);
        }

        if ($akunExactMatch->count() > 1) {
            throw new RuntimeException(sprintf(
                'Ditemukan lebih dari satu akun lawan pendapatan SIMRS dengan nominal %.2f.',
                $nominalTarget,
            ));
        }

        if (abs($akunLawanTergabung->sum('debet') - $nominalTarget) > 0.01 && $akunLawanTergabung->count() === 1) {
            return collect([[
                'kd_rek' => $akunLawanTergabung->first()['kd_rek'],
                'debet' => $nominalTarget,
            ]]);
        }

        return $akunLawanTergabung;
    }

    private function normalisasiBarisNominal(Collection $details): array
    {
        return $details->map(function (array $detail) {
            $total = (float) $detail['total_biaya'];
            $nominal = abs($total);

            return [
                'debit' => $total < 0 ? $nominal : 0,
                'kredit' => $total < 0 ? 0 : $nominal,
            ];
        })->all();
    }

    private function tentukanSumberTindakan(string $status): string
    {
        return match ($status) {
            'Ralan Dokter', 'Ralan Dokter Paramedis', 'Ralan Paramedis' => 'Rawat Jalan',
            'Ranap Dokter', 'Ranap Dokter Paramedis', 'Ranap Paramedis', 'Kamar' => 'Rawat Inap',
            'Laborat' => 'Laborat',
            'Radiologi' => 'Radiologi',
            default => throw new RuntimeException('Sumber tindakan tidak dikenali untuk status '.$status),
        };
    }

    private function tentukanKodeKategori(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        return match ($status) {
            'Ralan Dokter', 'Ralan Dokter Paramedis', 'Ralan Paramedis' => $this->cariKodeRalan($status, $noRawat, $namaPerawatan),
            'Ranap Dokter', 'Ranap Dokter Paramedis', 'Ranap Paramedis' => $this->cariKodeRanap($status, $noRawat, $namaPerawatan),
            'Laborat' => $this->ambilNilaiTunggal(
                <<<'SQL'
                SELECT pl.kd_jenis_prw
                FROM periksa_lab pl
                JOIN jns_perawatan_lab jpl ON jpl.kd_jenis_prw = pl.kd_jenis_prw
                JOIN billing b ON b.no_rawat = pl.no_rawat AND b.nm_perawatan = jpl.nm_perawatan
                WHERE b.no_rawat = ? AND b.nm_perawatan = ?
                LIMIT 1
                SQL,
                [$noRawat, $namaPerawatan]
            ),
            'Radiologi' => $this->ambilNilaiTunggal(
                <<<'SQL'
                SELECT jpr.kd_jenis_prw
                FROM periksa_radiologi pr
                JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
                JOIN billing b ON b.no_rawat = pr.no_rawat AND b.nm_perawatan = jpr.nm_perawatan
                WHERE pr.no_rawat = ? AND b.nm_perawatan = ?
                LIMIT 1
                SQL,
                [$noRawat, $namaPerawatan]
            ),
            'Kamar' => $this->ambilNilaiTunggal(
                <<<'SQL'
                SELECT ki.kd_kamar
                FROM kamar_inap ki
                JOIN kamar k ON k.kd_kamar = ki.kd_kamar
                JOIN bangsal bs ON bs.kd_bangsal = k.kd_bangsal
                JOIN billing b ON b.no_rawat = ki.no_rawat
                    AND b.nm_perawatan = CONCAT(ki.kd_kamar, ', ', bs.nm_bangsal)
                WHERE ki.no_rawat = ? AND b.nm_perawatan = ?
                LIMIT 1
                SQL,
                [$noRawat, $namaPerawatan]
            ),
            default => null,
        };
    }

    private function cariKodeRalan(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        $namaTabel = match ($status) {
            'Ralan Dokter' => 'rawat_jl_dr',
            'Ralan Paramedis' => 'rawat_jl_pr',
            'Ralan Dokter Paramedis' => 'rawat_jl_drpr',
            default => null,
        };

        if ($namaTabel === null) {
            return null;
        }

        return $this->ambilNilaiTunggal(
            sprintf(
                'SELECT %s.kd_jenis_prw
                FROM %s
                JOIN jns_perawatan ON jns_perawatan.kd_jenis_prw = %s.kd_jenis_prw
                JOIN billing ON billing.no_rawat = %s.no_rawat
                    AND billing.nm_perawatan = jns_perawatan.nm_perawatan
                WHERE %s.no_rawat = ? AND billing.nm_perawatan = ?
                LIMIT 1',
                $namaTabel,
                $namaTabel,
                $namaTabel,
                $namaTabel,
                $namaTabel,
            ),
            [$noRawat, $namaPerawatan]
        );
    }

    private function cariKodeRanap(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        $namaTabel = match ($status) {
            'Ranap Dokter' => 'rawat_inap_dr',
            'Ranap Paramedis' => 'rawat_inap_pr',
            'Ranap Dokter Paramedis' => 'rawat_inap_drpr',
            default => null,
        };

        if ($namaTabel === null) {
            return null;
        }

        return $this->ambilNilaiTunggal(
            sprintf(
                'SELECT ri.kd_jenis_prw
                FROM %s ri
                JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = ri.kd_jenis_prw
                JOIN billing b ON b.no_rawat = ri.no_rawat
                    AND b.nm_perawatan = jpi.nm_perawatan
                WHERE b.no_rawat = ? AND b.nm_perawatan = ?
                LIMIT 1',
                $namaTabel,
            ),
            [$noRawat, $namaPerawatan]
        );
    }

    private function ambilNilaiTunggal(string $sql, array $bindings): ?string
    {
        $baris = collect(DB::connection('simrs')->select($sql, $bindings))->first();

        if ($baris === null) {
            return null;
        }

        $nilaiBaris = (array) $baris;

        return isset($nilaiBaris[array_key_first($nilaiBaris)])
            ? (string) $nilaiBaris[array_key_first($nilaiBaris)]
            : null;
    }

    private function tentukanTanggalPengakuan(array $billing, string $basisTanggalPengakuan): ?string
    {
        // Ranap bisa memakai tanggal keluar RS sebagai basis pengakuan.
        // Selain itu, fallback-nya tetap tanggal registrasi agar proses tidak memaksa data SIMRS
        // yang belum memiliki tanggal keluar valid.
        if ($basisTanggalPengakuan !== 'TanggalKeluarRanap' || $billing['status_lanjut'] !== 'Ranap') {
            return $billing['tanggal_registrasi'];
        }

        $tanggalKeluar = $this->ambilNilaiTunggal(
            <<<'SQL'
            SELECT ki.tgl_keluar
            FROM kamar_inap ki
            WHERE ki.no_rawat = ?
              AND ki.stts_pulang <> 'Pindah Kamar'
              AND ki.tgl_keluar IS NOT NULL
              AND ki.tgl_keluar <> '0000-00-00'
            ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC
            LIMIT 1
            SQL,
            [$billing['no_rawat']]
        );

        return $tanggalKeluar ?: $billing['tanggal_registrasi'];
    }

    private function ambilHeaderBillingByNoRawat(string $noRawat): ?array
    {
        $baris = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_rkm_medis AS no_rekam_medis,
                p.nm_pasien AS nama_pasien,
                p.jk AS jenis_kelamin,
                p.alamat AS alamat,
                kel.nm_kel AS nama_kelurahan,
                kec.nm_kec AS nama_kecamatan,
                kab.nm_kab AS nama_kabupaten,
                rp.no_rawat AS no_rawat,
                rp.status_lanjut AS status_lanjut,
                rp.tgl_registrasi AS tanggal_registrasi,
                rp.jam_reg AS jam_registrasi,
                rp.kd_dokter AS kode_dokter,
                d.nm_dokter AS nama_dokter,
                rp.kd_poli AS kode_poli,
                poli.nm_poli AS nama_poli,
                rp.kd_pj AS kode_penjamin,
                pj.png_jawab AS penjamin,
                COALESCE(SUM(bil.totalbiaya), 0) AS total_biaya
            FROM reg_periksa rp
            JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
            LEFT JOIN kelurahan kel ON kel.kd_kel = p.kd_kel
            LEFT JOIN kecamatan kec ON kec.kd_kec = p.kd_kec
            LEFT JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
            JOIN billing bil ON bil.no_rawat = rp.no_rawat
            JOIN dokter d ON d.kd_dokter = rp.kd_dokter
            JOIN poliklinik poli ON poli.kd_poli = rp.kd_poli
            JOIN penjab pj ON pj.kd_pj = rp.kd_pj
            WHERE rp.no_rawat = ?
            GROUP BY
                p.no_rkm_medis, p.nm_pasien, p.jk, p.alamat,
                kel.nm_kel, kec.nm_kec, kab.nm_kab,
                rp.no_rawat, rp.status_lanjut, rp.tgl_registrasi, rp.jam_reg,
                rp.kd_dokter, d.nm_dokter, rp.kd_poli, poli.nm_poli,
                rp.kd_pj, pj.png_jawab
            LIMIT 1
            SQL,
            [$noRawat]
        ))->first();

        if ($baris === null) {
            return null;
        }

        return [
            'no_rekam_medis' => (string) $baris->no_rekam_medis,
            'nama_pasien' => (string) $baris->nama_pasien,
            'jenis_kelamin' => (string) $baris->jenis_kelamin,
            'alamat' => (string) ($baris->alamat ?? ''),
            'nama_kelurahan' => (string) ($baris->nama_kelurahan ?? ''),
            'nama_kecamatan' => (string) ($baris->nama_kecamatan ?? ''),
            'nama_kabupaten' => (string) ($baris->nama_kabupaten ?? ''),
            'no_rawat' => (string) $baris->no_rawat,
            'status_lanjut' => (string) $baris->status_lanjut,
            'tanggal_registrasi' => (string) $baris->tanggal_registrasi,
            'jam_registrasi' => (string) ($baris->jam_registrasi ?? ''),
            'kode_dokter' => (string) $baris->kode_dokter,
            'nama_dokter' => (string) $baris->nama_dokter,
            'kode_poli' => (string) $baris->kode_poli,
            'nama_poli' => (string) $baris->nama_poli,
            'kode_penjamin' => (string) $baris->kode_penjamin,
            'penjamin' => (string) $baris->penjamin,
            'total_biaya' => (float) $baris->total_biaya,
        ];
    }

    private function ambilRincianBillingByNoRawat(string $noRawat): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                b.no_rawat,
                b.tgl_byr,
                b.nm_perawatan,
                b.pemisah,
                b.biaya,
                b.jumlah,
                b.tambahan,
                b.totalbiaya,
                b.status
            FROM billing b
            WHERE b.no_rawat = ?
              AND b.totalbiaya <> 0
            ORDER BY b.totalbiaya DESC
            SQL,
            [$noRawat]
        ))->map(fn (object $baris) => [
            'no_rawat' => (string) $baris->no_rawat,
            'tanggal_bayar' => (string) $baris->tgl_byr,
            'nama_perawatan' => (string) $baris->nm_perawatan,
            'pemisah' => (string) ($baris->pemisah ?? ''),
            'biaya' => (float) $baris->biaya,
            'jumlah' => (float) $baris->jumlah,
            'tambahan' => (float) $baris->tambahan,
            'total_biaya' => (float) $baris->totalbiaya,
            'status_billing' => (string) $baris->status,
        ]);
    }

    private function buatNarasi(array $billing): string
    {
        return sprintf(
            'Pendapatan dari pasien %s / (%s) / (%s)',
            $billing['nama_pasien'],
            $billing['no_rekam_medis'],
            $billing['no_rawat'],
        );
    }

    private function buatCatatanPendapatan(string $status, string $namaPerawatan): string
    {
        return match ($status) {
            'Registrasi' => 'Registrasi',
            'Service' => 'Servis admin',
            'Potongan' => 'Potongan',
            'Retur Obat' => 'Retur Obat',
            default => $namaPerawatan !== '' && $namaPerawatan !== ':' ? $namaPerawatan : $status,
        };
    }

    private function normalisasiNamaPerawatan(?string $namaPerawatan): string
    {
        return trim((string) $namaPerawatan);
    }

    private function formatTeksTebal(?string $nilai): string
    {
        //return '<strong>&quot;'.e(trim((string) $nilai)).'&quot;</strong>';
        return '<strong>&quot;'.e((string) $nilai).'&quot;</strong>';
    }

    private function mappingTindakanSesuai(
        MappingPendapatan $mapping,
        string $kode,
        string $namaPerawatan,
        string $sumberTindakan,
    ): bool {
        return $mapping->kode_jenis_perawatan === $kode
            && $this->normalisasiNamaPerawatan($mapping->nm_perawatan) === $namaPerawatan
            && $mapping->sumber_tindakan === $sumberTindakan;
    }
}
