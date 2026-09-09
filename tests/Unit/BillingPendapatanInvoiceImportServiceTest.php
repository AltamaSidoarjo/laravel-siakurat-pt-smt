<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Services\Bridging\BillingApiClient;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Bridging\BillingPendapatanInvoiceImportService;
use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BillingPendapatanInvoiceImportServiceTest extends TestCase
{
    private BillingPendapatanApiService $candidateService;

    private BillingApiClient $apiClient;

    private LogAktifitasService $logService;

    private BillingPendapatanInvoiceImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->candidateService = Mockery::mock(BillingPendapatanApiService::class);
        $this->apiClient = Mockery::mock(BillingApiClient::class);
        $this->logService = Mockery::mock(LogAktifitasService::class);
        $this->service = new BillingPendapatanInvoiceImportService(
            $this->candidateService,
            $this->apiClient,
            new BukuBesarService,
            $this->logService,
        );
    }

    public function test_import_maps_pxrs_to_receivable_coa_and_creates_balanced_invoice_ledgers(): void
    {
        $umum = $this->createCoa('103000021', 'Piutang Pasien Umum', 'Piutang Usaha');
        $bpjs = $this->createCoa('103000024', 'Piutang Pasien BPJS Kesehatan', 'Piutang Usaha');
        $asuransi = $this->createCoa('103000022', 'Piutang Asuransi (Non BPJS Kesehatan)', 'Piutang Usaha');
        $pendapatan = $this->createCoa('440410001', 'Pendapatan Poli', 'Pendapatan');

        $candidates = [
            $this->candidate('ext-umum', 'RJ-UMUM', ' U/Px ', 'Pasien Umum'),
            $this->candidate('ext-bpjs', 'RJ-BPJS', 'bpjs', 'Pasien BPJS'),
            $this->candidate('ext-insurance', 'RJ-INSURANCE', 'Asuransi ABC', 'Pasien Asuransi'),
            $this->candidate('ext-empty', 'RJ-EMPTY', '', 'Pasien Tanpa Penjamin'),
        ];

        $this->expectCandidates($candidates);
        foreach ($candidates as $candidate) {
            $this->apiClient->shouldReceive('getAkun')
                ->once()
                ->with($candidate['external_id'])
                ->andReturn([
                    ['akun' => '440410001', 'biaya' => 100_000, 'jml' => 2, 'job' => 'Pelayanan'],
                ]);
        }
        $this->logService->shouldReceive('log')->times(4);

        $results = $this->import(array_column($candidates, 'external_id'));

        $this->assertTrue(collect($results)->every(fn (array $row) => $row['berhasil']));
        $this->assertDatabaseHas('faktur_penjualan', [
            'nomor_faktur' => 'RJ-UMUM',
            'akun_piutang_id' => $umum->id,
            'tanggal_faktur' => '2026-08-15 00:00:00',
            'grandtotal' => 200_000,
            'sudah_terbayar' => 0,
            'status_proses' => 0,
        ]);
        $this->assertDatabaseHas('faktur_penjualan', [
            'nomor_faktur' => 'RJ-BPJS',
            'akun_piutang_id' => $bpjs->id,
        ]);
        $this->assertDatabaseHas('faktur_penjualan', [
            'nomor_faktur' => 'RJ-INSURANCE',
            'akun_piutang_id' => $asuransi->id,
        ]);
        $this->assertDatabaseHas('faktur_penjualan', [
            'nomor_faktur' => 'RJ-EMPTY',
            'akun_piutang_id' => $umum->id,
        ]);

        $invoiceId = (int) DB::table('faktur_penjualan')
            ->where('nomor_faktur', 'RJ-BPJS')
            ->value('id');
        $this->assertDatabaseHas('faktur_penjualan_rinci', [
            'faktur_penjualan_id' => $invoiceId,
            'harga' => 100_000,
            'kuantitas' => 2,
            'subtotal' => 200_000,
            'catatan' => 'Pelayanan',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $bpjs->id,
            'sumber_id' => $invoiceId,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'tanggal' => '2026-08-15',
            'tipe_mutasi' => 'D',
            'nominal' => 200_000,
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $pendapatan->id,
            'sumber_id' => $invoiceId,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'tipe_mutasi' => 'K',
            'nominal' => 200_000,
        ]);
        $this->assertSame(
            DB::table('bukubesar')->where('sumber_id', $invoiceId)->where('tipe_mutasi', 'D')->sum('nominal'),
            DB::table('bukubesar')->where('sumber_id', $invoiceId)->where('tipe_mutasi', 'K')->sum('nominal'),
        );
    }

    public function test_customer_uses_visit_number_and_patient_name_without_nik(): void
    {
        $this->createRequiredCoas();
        $candidate = $this->candidate('ext-1', 'RJ-001', 'BPJS', 'Nama Pasien');
        $candidate['nik'] = '3512345678901234';

        $this->expectCandidates([$candidate]);
        $this->expectRevenueDetails('ext-1');
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseHas('pelanggan', [
            'kode_pelanggan' => 'RJ-001',
            'nama_pelanggan' => 'Nama Pasien',
        ]);
        $this->assertDatabaseMissing('pelanggan', ['kode_pelanggan' => '3512345678901234']);
        $this->assertDatabaseHas('simrs_import_pendapatan', [
            'nomer_billing' => 'RJ-001',
            'penjamin' => 'BPJS',
            'kode_penjamin' => 'BPJS',
            'import_ke' => 'Invoice Pendapatan',
        ]);
    }

    public function test_negative_revenue_line_reverses_side_and_keeps_net_ledger_balanced(): void
    {
        $this->createRequiredCoas();
        $revenue = Coa::query()->where('kode', '440410001')->firstOrFail();

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '440410001', 'biaya' => 200_000, 'jml' => 1, 'job' => 'Pelayanan'],
            ['akun' => '440410001', 'biaya' => -50_000, 'jml' => 1, 'job' => 'Koreksi'],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseHas('faktur_penjualan', ['grandtotal' => 150_000]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $revenue->id,
            'tipe_mutasi' => 'D',
            'nominal' => 50_000,
        ]);
    }

    public function test_invalid_receivable_coa_rolls_back_all_writes(): void
    {
        $this->createCoa('103000024', 'Piutang Pasien BPJS Kesehatan', 'Piutang Usaha', false);
        $this->createCoa('440410001', 'Pendapatan Poli', 'Pendapatan');

        $this->expectCandidates([$this->candidate()]);
        $this->expectRevenueDetails('ext-1');
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('COA piutang tidak aktif', $result[0]['alasan_gagal']);
        $this->assertNoImportWrites();
    }

    public function test_duplicate_receivable_name_is_rejected_as_ambiguous(): void
    {
        $this->createCoa('103000024-A', 'Piutang Pasien BPJS Kesehatan', 'Piutang Usaha');
        $this->createCoa('103000024-B', ' piutang pasien bpjs kesehatan ', 'Piutang Usaha');
        $this->createCoa('440410001', 'Pendapatan Poli', 'Pendapatan');

        $this->expectCandidates([$this->candidate()]);
        $this->expectRevenueDetails('ext-1');
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame(
            'Nama COA piutang tidak unik: Piutang Pasien BPJS Kesehatan.',
            $result[0]['alasan_gagal'],
        );
        $this->assertNoImportWrites();
    }

    public function test_missing_revenue_coa_only_fails_that_visit(): void
    {
        $this->createRequiredCoas();
        $this->expectCandidates([
            $this->candidate('ext-ok', 'RJ-OK'),
            $this->candidate('ext-fail', 'RJ-FAIL'),
        ]);
        $this->expectRevenueDetails('ext-ok');
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-fail')->andReturn([
            ['akun' => 'MISSING', 'biaya' => 100_000, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-ok', 'ext-fail']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertFalse($result[1]['berhasil']);
        $this->assertDatabaseHas('faktur_penjualan', ['nomor_faktur' => 'RJ-OK']);
        $this->assertDatabaseMissing('faktur_penjualan', ['nomor_faktur' => 'RJ-FAIL']);
    }

    public function test_duplicate_revenue_code_is_rejected_as_ambiguous(): void
    {
        $this->createRequiredCoas();
        $this->createCoa('440410001', 'Pendapatan Poli Duplikat', 'Pendapatan');

        $this->expectCandidates([$this->candidate()]);
        $this->expectRevenueDetails('ext-1');
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame(
            'Kode COA pendapatan tidak unik untuk akun: 440410001.',
            $result[0]['alasan_gagal'],
        );
        $this->assertNoImportWrites();
    }

    public function test_duplicate_visit_is_rejected_without_second_invoice(): void
    {
        $this->createRequiredCoas();
        DB::table('simrs_import_pendapatan')->insert([
            'nomer_billing' => 'RJ-001',
            'tanggal_reg' => '2026-08-15',
        ]);

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldNotReceive('getAkun');
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('sudah pernah diimport', $result[0]['alasan_gagal']);
        $this->assertSame(0, DB::table('faktur_penjualan')->count());
    }

    public function test_delete_removes_invoice_import_without_touching_journal_with_same_number(): void
    {
        $invoiceId = DB::table('faktur_penjualan')->insertGetId([
            'pelanggan_id' => 1,
            'akun_piutang_id' => 1,
            'nomor_faktur' => 'RJ-DELETE',
            'tanggal_faktur' => '2026-08-15',
            'grandtotal' => 100_000,
            'sudah_terbayar' => 0,
            'status_proses' => 0,
            'nama_pasien' => 'Pasien',
            'nomer_rawat' => 'RJ-DELETE',
            'tanggal_registrasi' => '2026-08-15',
        ]);
        DB::table('faktur_penjualan_rinci')->insert([
            'faktur_penjualan_id' => $invoiceId,
            'harga' => 100_000,
            'kuantitas' => 1,
            'subtotal' => 100_000,
        ]);
        DB::table('bukubesar')->insert([
            [
                'coa_id' => 1,
                'sumber_id' => $invoiceId,
                'tanggal' => '2026-08-15',
                'periode_tahun' => 2026,
                'periode_bulan' => 8,
                'nomer' => 'RJ-DELETE',
                'sumber_transaksi' => 'Invoice Pendapatan',
                'nominal' => 100_000,
                'tipe_mutasi' => 'D',
            ],
            [
                'coa_id' => 2,
                'sumber_id' => 99,
                'tanggal' => '2026-08-15',
                'periode_tahun' => 2026,
                'periode_bulan' => 8,
                'nomer' => 'RJ-DELETE',
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => 100_000,
                'tipe_mutasi' => 'D',
            ],
        ]);
        DB::table('jurnal_umum')->insert([
            'id' => 99,
            'nomer' => 'RJ-DELETE',
            'tanggal' => '2026-08-15',
            'debit' => 100_000,
            'kredit' => 100_000,
        ]);
        DB::table('simrs_import_pendapatan')->insert([
            'nomer_billing' => 'RJ-DELETE',
            'tanggal_reg' => '2026-08-15',
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $logService = Mockery::mock(LogAktifitasService::class);
        $logService->shouldReceive('log')->once();
        $service = new BridgingPendapatanService(new BukuBesarService, $logService);

        $result = $service->hapusBanyak(['RJ-DELETE'], 'Tester');

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseMissing('faktur_penjualan', ['id' => $invoiceId]);
        $this->assertDatabaseMissing('faktur_penjualan_rinci', ['faktur_penjualan_id' => $invoiceId]);
        $this->assertDatabaseMissing('bukubesar', [
            'sumber_transaksi' => 'Invoice Pendapatan',
            'sumber_id' => $invoiceId,
        ]);
        $this->assertDatabaseMissing('simrs_import_pendapatan', ['nomer_billing' => 'RJ-DELETE']);
        $this->assertDatabaseHas('jurnal_umum', ['id' => 99, 'nomer' => 'RJ-DELETE']);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_transaksi' => 'Jurnal Umum',
            'sumber_id' => 99,
        ]);
    }

    private function createRequiredCoas(): void
    {
        $this->createCoa('103000021', 'Piutang Pasien Umum', 'Piutang Usaha');
        $this->createCoa('103000024', 'Piutang Pasien BPJS Kesehatan', 'Piutang Usaha');
        $this->createCoa('103000022', 'Piutang Asuransi (Non BPJS Kesehatan)', 'Piutang Usaha');
        $this->createCoa('440410001', 'Pendapatan Poli', 'Pendapatan');
    }

    private function expectCandidates(array $candidates): void
    {
        $this->candidateService
            ->shouldReceive('getKandidatUntukImpor')
            ->once()
            ->with('rawat_jalan', '2026-08-15', '2026-08-15', null, null)
            ->andReturn(collect($candidates));
    }

    private function expectRevenueDetails(string $externalId): void
    {
        $this->apiClient->shouldReceive('getAkun')->once()->with($externalId)->andReturn([
            ['akun' => '440410001', 'biaya' => 100_000, 'jml' => 1, 'job' => null],
        ]);
    }

    private function import(array $externalIds): array
    {
        return $this->service->imporBanyak(
            $externalIds,
            'rawat_jalan',
            '2026-08-15',
            '2026-08-15',
            null,
            null,
            'Tester',
        );
    }

    private function candidate(
        string $externalId = 'ext-1',
        string $noRawat = 'RJ-001',
        string $penjamin = 'BPJS',
        string $namaPasien = 'Pasien API',
    ): array {
        return [
            'external_id' => $externalId,
            'no_rawat' => $noRawat,
            'tanggal_registrasi' => '2026-08-15 09:30:45',
            'nama_pasien' => $namaPasien,
            'nama_dokter' => 'Dokter API',
            'nama_poli' => 'Poli API',
            'status_lanjut' => 'Rawat Jalan',
            'penjamin' => $penjamin,
        ];
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipe,
        bool $isPostable = true,
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => $tipe,
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => $isPostable,
        ]);
    }

    private function assertNoImportWrites(): void
    {
        $this->assertSame(0, DB::table('pelanggan')->count());
        $this->assertSame(0, DB::table('faktur_penjualan')->count());
        $this->assertSame(0, DB::table('faktur_penjualan_rinci')->count());
        $this->assertSame(0, DB::table('bukubesar')->count());
        $this->assertSame(0, DB::table('simrs_import_pendapatan')->count());
    }

    private function prepareTables(): void
    {
        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('pelanggan', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_pelanggan');
            $table->string('nama_pelanggan');
            $table->timestamps();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pelanggan_id');
            $table->unsignedInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur');
            $table->text('keterangan')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->unsignedTinyInteger('status_proses')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('nama_poli')->nullable();
            $table->string('nama_dokter')->nullable();
            $table->string('nama_pasien')->default('');
            $table->string('nomer_rawat')->default('');
            $table->date('tanggal_registrasi');
            $table->string('kode_penjamin')->nullable();
            $table->string('nama_penjamin')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id');
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun');
            $table->unsignedTinyInteger('periode_bulan');
            $table->string('nomer');
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('simrs_import_pendapatan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer_billing');
            $table->date('tanggal_reg')->nullable();
            $table->string('user_importer')->nullable();
            $table->dateTime('import_time')->nullable();
            $table->string('dokter')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->string('penjamin')->nullable();
            $table->string('poli')->nullable();
            $table->string('status_layanan')->nullable();
            $table->decimal('total_tagihan', 15, 2)->default(0);
            $table->string('kode_penjamin')->nullable();
            $table->string('import_ke')->nullable();
        });

        Schema::create('jurnal_umum', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('log_hapus_import_pendapatan', function (Blueprint $table): void {
            $table->increments('log_hapus_import_pendapatan_id');
            $table->string('nomer');
            $table->string('dihapus_oleh');
            $table->dateTime('created_at');
            $table->string('sumber_transaksi')->nullable();
        });
    }
}
