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
    ) {
    }

    public function getImportedQuery(
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

    public function getSimrsBillingCandidates(
        string $startDate,
        string $endDate,
        ?string $poli = null,
        ?string $penjamin = null,
    ): Collection {
        $importedSet = SimrsImportPendapatan::query()
            ->pluck('nomer_billing')
            ->filter()
            ->all();

        $importedLookup = array_fill_keys($importedSet, true);

        $rows = collect(DB::connection('simrs')->select(
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

        return $rows
            ->reject(fn (object $row) => isset($importedLookup[(string) $row->no_rawat]))
            ->filter(function (object $row) use ($poli, $penjamin) {
                $matchPoli = $poli === null || $poli === ''
                    || str_contains(Str::lower((string) $row->nama_poli), Str::lower($poli));
                $matchPenjamin = $penjamin === null || $penjamin === ''
                    || str_contains(Str::lower((string) $row->penjamin), Str::lower($penjamin));

                return $matchPoli && $matchPenjamin;
            })
            ->values();
    }

    public function importMany(
        array $selectedNoRawat,
        string $jenisProses,
        string $basisTanggalPengakuan,
        string $actor,
    ): array {
        $results = [];

        foreach (array_values(array_unique($selectedNoRawat)) as $noRawat) {
            try {
                $results[] = $this->importSingle(
                    (string) $noRawat,
                    $jenisProses,
                    $basisTanggalPengakuan,
                    $actor,
                );
            } catch (\Throwable $exception) {
                $results[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function deleteMany(array $selectedNoRawat, string $actor): array
    {
        $results = [];

        foreach (array_values(array_unique($selectedNoRawat)) as $noRawat) {
            try {
                DB::transaction(function () use ($noRawat, $actor) {
                    $imports = SimrsImportPendapatan::query()
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

                    foreach ($imports as $import) {
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

                $results[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => true,
                    'alasan_gagal' => null,
                ];
            } catch (\Throwable $exception) {
                $results[] = [
                    'no_rawat' => (string) $noRawat,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function detectUnbalancedJournals(string $startDate, string $endDate): Collection
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
        ))->map(function (object $row) {
            $totalDebit = (float) $row->total_debit;
            $totalKredit = (float) $row->total_kredit;

            return [
                'no_rawat' => (string) $row->no_rawat,
                'tanggal_registrasi' => (string) $row->tanggal_registrasi,
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'selisih' => $totalDebit - $totalKredit,
            ];
        });
    }

    private function importSingle(
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

        $billing = $this->getBillingHeaderByNoRawat($noRawat);
        if ($billing === null) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Data billing tidak ditemukan di SIMRS.',
            ];
        }

        $tanggalPengakuan = $this->determineTanggalPengakuan($billing, $basisTanggalPengakuan);
        if ($tanggalPengakuan === null) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Tanggal pengakuan tidak dapat ditentukan.',
            ];
        }

        $details = $this->getBillingDetailsByNoRawat($noRawat);
        if ($details->isEmpty()) {
            return [
                'no_rawat' => $noRawat,
                'berhasil' => false,
                'alasan_gagal' => 'Rincian billing tidak ditemukan.',
            ];
        }

        return DB::transaction(function () use (
            $billing,
            $details,
            $tanggalPengakuan,
            $jenisProses,
            $actor
        ) {
            $mappings = $this->loadMappings();

            $resolvedRevenueRows = [];
            $lastKamarCoaId = null;

            foreach ($details as $detail) {
                $resolvedRevenueRows[] = $this->resolveRevenueRow(
                    $billing,
                    $detail,
                    $mappings,
                    $lastKamarCoaId,
                );
            }

            $counterAccounts = $this->resolveCounterAccounts($billing, $details, $mappings['lawan']);

            $this->createImportLog($billing, $actor, $jenisProses);

            if ($jenisProses === 'InvoicePendapatan') {
                $this->createInvoicePendapatan(
                    $billing,
                    $resolvedRevenueRows,
                    $counterAccounts,
                    $tanggalPengakuan,
                    $mappings['coa'],
                );
            } else {
                $this->createJurnalUmum(
                    $billing,
                    $resolvedRevenueRows,
                    $counterAccounts,
                    $tanggalPengakuan,
                );
            }

            return [
                'no_rawat' => $billing['no_rawat'],
                'berhasil' => true,
                'alasan_gagal' => null,
            ];
        });
    }

    private function createImportLog(array $billing, string $actor, string $jenisProses): void
    {
        $importKe = $jenisProses === 'InvoicePendapatan'
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
            'import_ke' => $importKe,
        ]);
    }

    private function createJurnalUmum(
        array $billing,
        array $resolvedRevenueRows,
        array $counterAccounts,
        string $tanggalPengakuan,
    ): void {
        $jurnal = new JurnalUmum();
        $jurnal->nomer = $billing['no_rawat'];
        $jurnal->tanggal = $tanggalPengakuan;
        $jurnal->keterangan = $this->buildNarration($billing);
        $jurnal->debit = 0;
        $jurnal->kredit = 0;
        $jurnal->save();

        $payload = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;

        foreach ($resolvedRevenueRows as $row) {
            $detail = new JurnalUmumRinci();
            $detail->jurnal_umum_id = (int) $jurnal->id;
            $detail->coa_id = (int) $row['coa_id'];
            $detail->debit = $row['debit'];
            $detail->kredit = $row['kredit'];
            $detail->catatan = $row['catatan'];
            $detail->save();

            $payload[] = [
                'coa_id' => (int) $row['coa_id'],
                'debit' => (float) $row['debit'],
                'kredit' => (float) $row['kredit'],
                'catatan' => $row['catatan'],
            ];

            $totalDebit += (float) $row['debit'];
            $totalKredit += (float) $row['kredit'];
        }

        foreach ($counterAccounts as $account) {
            $detail = new JurnalUmumRinci();
            $detail->jurnal_umum_id = (int) $jurnal->id;
            $detail->coa_id = (int) $account['coa_id'];
            $detail->debit = $account['debit'];
            $detail->kredit = 0;
            $detail->catatan = 'Akun lawan pendapatan';
            $detail->save();

            $payload[] = [
                'coa_id' => (int) $account['coa_id'],
                'debit' => (float) $account['debit'],
                'kredit' => 0,
                'catatan' => 'Akun lawan pendapatan',
            ];

            $totalDebit += (float) $account['debit'];
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
            $payload,
        );
    }

    private function createInvoicePendapatan(
        array $billing,
        array $resolvedRevenueRows,
        array $counterAccounts,
        string $tanggalPengakuan,
        Collection $coaLookup,
    ): void {
        $pelanggan = $this->findOrCreatePelanggan(
            $billing['kode_penjamin'],
            $billing['penjamin'],
        );

        $debitAccounts = collect($counterAccounts)
            ->map(function (array $account) use ($coaLookup) {
                return [
                    ...$account,
                    'coa' => $coaLookup->get((int) $account['coa_id']),
                ];
            });

        $sudahTerbayar = $debitAccounts
            ->filter(fn (array $account) => str_starts_with((string) optional($account['coa'])->kode, '111.'))
            ->sum('debit');

        $akunPiutangId = null;
        if ($debitAccounts->count() === 1) {
            $coa = $debitAccounts->first()['coa'] ?? null;
            if ($coa !== null && str_starts_with((string) $coa->kode, '112.')) {
                $akunPiutangId = (int) $coa->id;
            }
        }

        $invoice = new FakturPenjualan();
        $invoice->pelanggan_id = (int) $pelanggan->id;
        $invoice->akun_piutang_id = $akunPiutangId;
        $invoice->nomor_faktur = $billing['no_rawat'];
        $invoice->tanggal_faktur = $tanggalPengakuan;
        $invoice->keterangan = $this->buildNarration($billing);
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

        foreach ($resolvedRevenueRows as $row) {
            $rinci = new FakturPenjualanRinci();
            $rinci->faktur_penjualan_id = (int) $invoice->id;
            $rinci->harga = abs((float) $row['raw_total']);
            $rinci->kuantitas = (float) $row['quantity'];
            $rinci->subtotal = (float) $row['raw_total'];
            $rinci->catatan = $row['catatan'];
            $rinci->save();
        }

        $this->syncBukuBesarInvoicePendapatan(
            $invoice,
            $resolvedRevenueRows,
            $counterAccounts,
        );
    }

    private function syncBukuBesarInvoicePendapatan(
        FakturPenjualan $invoice,
        array $resolvedRevenueRows,
        array $counterAccounts,
    ): void {
        BukuBesar::query()
            ->where('sumber_transaksi', self::IMPORT_INVOICE_PENDAPATAN)
            ->where('sumber_id', (int) $invoice->id)
            ->delete();

        $payload = [];

        foreach ($resolvedRevenueRows as $row) {
            $payload[] = [
                'coa_id' => (int) $row['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => abs((float) $row['raw_total']),
                'tipe_mutasi' => $row['raw_total'] < 0 ? 'D' : 'K',
                'keterangan' => $row['catatan'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($counterAccounts as $account) {
            $payload[] = [
                'coa_id' => (int) $account['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $invoice->tanggal_faktur,
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => (float) $account['debit'],
                'tipe_mutasi' => 'D',
                'keterangan' => 'akun lawan pendapatan',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload !== []) {
            BukuBesar::query()->insert($payload);
        }
    }

    private function findOrCreatePelanggan(string $kodePenjamin, string $namaPenjamin): Pelanggan
    {
        $existing = Pelanggan::query()
            ->where('kode_pelanggan', $kodePenjamin)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $pelanggan = new Pelanggan();
        $pelanggan->status_aktif = true;
        $pelanggan->kode_pelanggan = $kodePenjamin;
        $pelanggan->nama_pelanggan = $namaPenjamin;
        $pelanggan->save();

        return $pelanggan;
    }

    private function loadMappings(): array
    {
        return [
            'tindakan' => MappingPendapatan::query()->get(),
            'umum' => MappingPendapatanUmum::query()->get(),
            'kamar' => MappingPendapatanKamar::query()->get(),
            'lawan' => MappingLawanPendapatanSimrs::query()->get(),
            'coa' => Coa::query()->get()->keyBy('id'),
        ];
    }

    private function resolveRevenueRow(
        array $billing,
        array $detail,
        array $mappings,
        ?int &$lastKamarCoaId,
    ): array {
        $status = (string) $detail['status_billing'];
        $namaPerawatan = trim((string) $detail['nama_perawatan']);
        $rawTotal = (float) $detail['total_biaya'];
        $coaId = null;

        if (in_array($status, self::KATEGORI_DENGAN_KODE, true)) {
            if ($status === 'Kamar' && $detail['pemisah'] === ':' && ! str_contains($namaPerawatan, ',')) {
                if ($lastKamarCoaId === null) {
                    throw new RuntimeException('Mapping kamar tidak ditemukan untuk biaya sekali rawat inap.');
                }

                $coaId = $lastKamarCoaId;
            } else {
                $kode = $this->resolveKodeKategori($status, $billing['no_rawat'], $namaPerawatan);
                if ($kode === null || $kode === '') {
                    throw new RuntimeException(sprintf(
                        'Kode tindakan tidak ditemukan untuk status "%s" dan tindakan "%s".',
                        $status,
                        $namaPerawatan,
                    ));
                }

                if ($status === 'Kamar') {
                    $mapping = $mappings['kamar']->firstWhere('kode_kamar', $kode);
                    if ($mapping === null) {
                        throw new RuntimeException(sprintf(
                            'Mapping kamar belum disetting untuk kode "%s" - "%s".',
                            $kode,
                            $namaPerawatan,
                        ));
                    }

                    $coaId = (int) $mapping->pendapatan_kamar_coa_id;
                    $lastKamarCoaId = $coaId;
                } else {
                    $source = $this->determineSumberTindakan($status);
                    $mapping = $mappings['tindakan']->first(function (MappingPendapatan $item) use ($kode, $namaPerawatan, $source) {
                        return $item->kode_jenis_perawatan === $kode
                            && $item->nm_perawatan === $namaPerawatan
                            && $item->sumber_tindakan === $source;
                    });

                    if ($mapping === null) {
                        throw new RuntimeException(sprintf(
                            'Mapping tindakan belum disetting untuk %s / %s - %s.',
                            $status,
                            $kode,
                            $namaPerawatan,
                        ));
                    }

                    $coaId = (int) $mapping->coa_id;
                }
            }
        } else {
            $mapping = $mappings['umum']->first(function (MappingPendapatanUmum $item) use ($status, $billing) {
                return $item->nama === $status
                    && $item->kode_penjamin === $billing['kode_penjamin'];
            });

            if ($mapping === null) {
                throw new RuntimeException(sprintf(
                    'Mapping pendapatan umum belum disetting untuk status "%s" dan penjamin "%s".',
                    $status,
                    $billing['penjamin'],
                ));
            }

            $coaId = (int) $mapping->coa_id;
        }

        $amount = abs($rawTotal);

        return [
            'coa_id' => $coaId,
            'debit' => $rawTotal < 0 ? $amount : 0,
            'kredit' => $rawTotal < 0 ? 0 : $amount,
            'raw_total' => $rawTotal,
            'quantity' => (float) $detail['jumlah'],
            'catatan' => $this->buildRevenueNote($status, $namaPerawatan),
        ];
    }

    private function resolveCounterAccounts(
        array $billing,
        Collection $details,
        Collection $mappingLawan,
    ): array {
        $hasilAkunLawan = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT d.kd_rek, COALESCE(d.debet, 0) AS debet
            FROM (
                SELECT j.no_jurnal
                FROM jurnal j
                WHERE j.no_bukti = ?
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

        $nominalYangDiabaikan = $details
            ->filter(fn (array $item) => in_array($item['status_billing'], ['Retur Obat', 'Potongan'], true))
            ->map(fn (array $item) => abs((float) $item['total_biaya']))
            ->filter(fn (float $value) => $value > 0)
            ->values()
            ->all();

        foreach ($nominalYangDiabaikan as $nominal) {
            $index = $hasilAkunLawan->search(fn (array $item) => abs($item['debet'] - $nominal) < 0.01);
            if ($index !== false) {
                $hasilAkunLawan->forget($index);
            }
        }

        $grouped = $hasilAkunLawan
            ->groupBy('kd_rek')
            ->map(fn (Collection $rows, string $kode) => [
                'kd_rek' => $kode,
                'debet' => (float) $rows->sum('debet'),
            ])
            ->values();

        $targetNominal = array_sum(array_map(fn (array $row) => (float) $row['kredit'], $this->normalizeRows($details)))
            - array_sum(array_map(fn (array $row) => (float) $row['debit'], $this->normalizeRows($details)));

        if (abs($grouped->sum('debet') - $targetNominal) > 0.01 && $grouped->count() === 1) {
            $grouped = collect([[
                'kd_rek' => $grouped->first()['kd_rek'],
                'debet' => $targetNominal,
            ]]);
        }

        if (abs($grouped->sum('debet') - $targetNominal) > 0.01) {
            throw new RuntimeException(sprintf(
                'Akun lawan pendapatan SIMRS tidak cocok dengan total billing. Target %.2f, jurnal SIMRS %.2f.',
                $targetNominal,
                (float) $grouped->sum('debet'),
            ));
        }

        return $grouped->map(function (array $item) use ($mappingLawan) {
            $mapping = $mappingLawan->firstWhere('kode_coa_simrs', $item['kd_rek']);

            if ($mapping === null) {
                throw new RuntimeException(sprintf(
                    'Mapping akun lawan pendapatan belum disetting untuk kode COA SIMRS "%s".',
                    $item['kd_rek'],
                ));
            }

            return [
                'coa_id' => (int) $mapping->coa_id,
                'debit' => (float) $item['debet'],
            ];
        })->all();
    }

    private function normalizeRows(Collection $details): array
    {
        return $details->map(function (array $detail) {
            $total = (float) $detail['total_biaya'];
            $amount = abs($total);

            return [
                'debit' => $total < 0 ? $amount : 0,
                'kredit' => $total < 0 ? 0 : $amount,
            ];
        })->all();
    }

    private function determineSumberTindakan(string $status): string
    {
        return match ($status) {
            'Ralan Dokter', 'Ralan Dokter Paramedis', 'Ralan Paramedis' => 'Rawat Jalan',
            'Ranap Dokter', 'Ranap Dokter Paramedis', 'Ranap Paramedis', 'Kamar' => 'Rawat Inap',
            'Laborat' => 'Laborat',
            'Radiologi' => 'Radiologi',
            default => throw new RuntimeException('Sumber tindakan tidak dikenali untuk status '.$status),
        };
    }

    private function resolveKodeKategori(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        return match ($status) {
            'Ralan Dokter', 'Ralan Dokter Paramedis', 'Ralan Paramedis' => $this->findKodeRalan($status, $noRawat, $namaPerawatan),
            'Ranap Dokter', 'Ranap Dokter Paramedis', 'Ranap Paramedis' => $this->findKodeRanap($status, $noRawat, $namaPerawatan),
            'Laborat' => $this->findSingleValue(
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
            'Radiologi' => $this->findSingleValue(
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
            'Kamar' => $this->findSingleValue(
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

    private function findKodeRalan(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        $table = match ($status) {
            'Ralan Dokter' => 'rawat_jl_dr',
            'Ralan Paramedis' => 'rawat_jl_pr',
            'Ralan Dokter Paramedis' => 'rawat_jl_drpr',
            default => null,
        };

        if ($table === null) {
            return null;
        }

        return $this->findSingleValue(
            sprintf(
                "SELECT %s.kd_jenis_prw
                FROM %s
                JOIN jns_perawatan ON jns_perawatan.kd_jenis_prw = %s.kd_jenis_prw
                JOIN billing ON billing.no_rawat = %s.no_rawat
                    AND billing.nm_perawatan = jns_perawatan.nm_perawatan
                WHERE %s.no_rawat = ? AND billing.nm_perawatan = ?
                LIMIT 1",
                $table,
                $table,
                $table,
                $table,
                $table,
            ),
            [$noRawat, $namaPerawatan]
        );
    }

    private function findKodeRanap(string $status, string $noRawat, string $namaPerawatan): ?string
    {
        $table = match ($status) {
            'Ranap Dokter' => 'rawat_inap_dr',
            'Ranap Paramedis' => 'rawat_inap_pr',
            'Ranap Dokter Paramedis' => 'rawat_inap_drpr',
            default => null,
        };

        if ($table === null) {
            return null;
        }

        return $this->findSingleValue(
            sprintf(
                "SELECT ri.kd_jenis_prw
                FROM %s ri
                JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = ri.kd_jenis_prw
                JOIN billing b ON b.no_rawat = ri.no_rawat
                    AND b.nm_perawatan = jpi.nm_perawatan
                WHERE b.no_rawat = ? AND b.nm_perawatan = ?
                LIMIT 1",
                $table,
            ),
            [$noRawat, $namaPerawatan]
        );
    }

    private function findSingleValue(string $sql, array $bindings): ?string
    {
        $row = collect(DB::connection('simrs')->select($sql, $bindings))->first();

        if ($row === null) {
            return null;
        }

        $value = (array) $row;

        return isset($value[array_key_first($value)])
            ? (string) $value[array_key_first($value)]
            : null;
    }

    private function determineTanggalPengakuan(array $billing, string $basisTanggalPengakuan): ?string
    {
        if ($basisTanggalPengakuan !== 'TanggalKeluarRanap' || $billing['status_lanjut'] !== 'Ranap') {
            return $billing['tanggal_registrasi'];
        }

        $tanggalKeluar = $this->findSingleValue(
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

    private function getBillingHeaderByNoRawat(string $noRawat): ?array
    {
        $row = collect(DB::connection('simrs')->select(
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

        if ($row === null) {
            return null;
        }

        return [
            'no_rekam_medis' => (string) $row->no_rekam_medis,
            'nama_pasien' => (string) $row->nama_pasien,
            'jenis_kelamin' => (string) $row->jenis_kelamin,
            'alamat' => (string) ($row->alamat ?? ''),
            'nama_kelurahan' => (string) ($row->nama_kelurahan ?? ''),
            'nama_kecamatan' => (string) ($row->nama_kecamatan ?? ''),
            'nama_kabupaten' => (string) ($row->nama_kabupaten ?? ''),
            'no_rawat' => (string) $row->no_rawat,
            'status_lanjut' => (string) $row->status_lanjut,
            'tanggal_registrasi' => (string) $row->tanggal_registrasi,
            'jam_registrasi' => (string) ($row->jam_registrasi ?? ''),
            'kode_dokter' => (string) $row->kode_dokter,
            'nama_dokter' => (string) $row->nama_dokter,
            'kode_poli' => (string) $row->kode_poli,
            'nama_poli' => (string) $row->nama_poli,
            'kode_penjamin' => (string) $row->kode_penjamin,
            'penjamin' => (string) $row->penjamin,
            'total_biaya' => (float) $row->total_biaya,
        ];
    }

    private function getBillingDetailsByNoRawat(string $noRawat): Collection
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
        ))->map(fn (object $row) => [
            'no_rawat' => (string) $row->no_rawat,
            'tanggal_bayar' => (string) $row->tgl_byr,
            'nama_perawatan' => (string) $row->nm_perawatan,
            'pemisah' => (string) ($row->pemisah ?? ''),
            'biaya' => (float) $row->biaya,
            'jumlah' => (float) $row->jumlah,
            'tambahan' => (float) $row->tambahan,
            'total_biaya' => (float) $row->totalbiaya,
            'status_billing' => (string) $row->status,
        ]);
    }

    private function buildNarration(array $billing): string
    {
        return sprintf(
            'Pendapatan dari pasien %s / (%s) / (%s)',
            $billing['nama_pasien'],
            $billing['no_rekam_medis'],
            $billing['no_rawat'],
        );
    }

    private function buildRevenueNote(string $status, string $namaPerawatan): string
    {
        return match ($status) {
            'Registrasi' => 'Registrasi',
            'Service' => 'Servis admin',
            'Potongan' => 'Potongan',
            'Retur Obat' => 'Retur Obat',
            default => $namaPerawatan !== '' && $namaPerawatan !== ':' ? $namaPerawatan : $status,
        };
    }
}
