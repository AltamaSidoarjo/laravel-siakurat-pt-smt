<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Services\Bridging\BillingApiClient;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Bridging\BillingPendapatanJournalImportService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BillingPendapatanJournalImportServiceTest extends TestCase
{
    private BillingPendapatanApiService $candidateService;

    private BillingApiClient $apiClient;

    private LogAktifitasService $logService;

    private BillingPendapatanJournalImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->candidateService = Mockery::mock(BillingPendapatanApiService::class);
        $this->apiClient = Mockery::mock(BillingApiClient::class);
        $this->logService = Mockery::mock(LogAktifitasService::class);
        $this->service = new BillingPendapatanJournalImportService(
            $this->candidateService,
            $this->apiClient,
            new BukuBesarService,
            $this->logService,
        );
    }

    public function test_import_creates_balanced_journal_ledger_and_import_log_using_trimmed_coa_codes(): void
    {
        $cash = $this->createCoa(' 110.01 ', 'Kasbank');
        $revenue = $this->createCoa('410.01', 'Pendapatan lain');

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-1')->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 2, 'job' => 'Pembayaran'],
            ['akun' => '410.01', 'biaya' => 200, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseHas('jurnal_umum', [
            'nomer' => 'RJ-001',
            'tanggal' => '2026-08-15 00:00:00',
            'debit' => 200,
            'kredit' => 200,
        ]);
        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'coa_id' => $cash->id,
            'debit' => 200,
            'kredit' => 0,
            'catatan' => 'Pembayaran',
        ]);
        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'coa_id' => $revenue->id,
            'debit' => 0,
            'kredit' => 200,
            'catatan' => 'Billing API - 410.01',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $cash->id,
            'nomer' => 'RJ-001',
            'tipe_mutasi' => 'D',
            'nominal' => 200,
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $revenue->id,
            'nomer' => 'RJ-001',
            'tipe_mutasi' => 'K',
            'nominal' => 200,
        ]);
        $this->assertDatabaseHas('simrs_import_pendapatan', [
            'nomer_billing' => 'RJ-001',
            'nama_pasien' => 'Pasien API',
            'import_ke' => 'Jurnal Umum',
            'total_tagihan' => 200,
        ]);
    }

    public function test_import_accepts_registration_datetime_from_billing_api(): void
    {
        $this->createCoa('110.01', 'Kasbank');
        $this->createCoa('410.01', 'Pendapatan');

        $candidate = $this->candidate();
        $candidate['tanggal_registrasi'] = '2026-08-15 09:30:45';

        $this->expectCandidates([$candidate]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseHas('jurnal_umum', [
            'nomer' => 'RJ-001',
            'tanggal' => '2026-08-15 00:00:00',
        ]);
    }

    public function test_negative_amount_is_posted_to_the_opposite_side_of_the_normal_balance(): void
    {
        $expense = $this->createCoa('510.01', 'Beban');
        $revenue = $this->createCoa('410.01', 'Pendapatan');
        $asset = $this->createCoa('110.01', 'Aktiva Lancar lainnya');
        $liability = $this->createCoa('210.01', 'Kewajiban');

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '510.01', 'biaya' => 10, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 10, 'jml' => 1, 'job' => null],
            ['akun' => '110.01', 'biaya' => -5, 'jml' => 1, 'job' => null],
            ['akun' => '210.01', 'biaya' => -5, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertDatabaseHas('jurnal_umum_rinci', ['coa_id' => $expense->id, 'debit' => 10, 'kredit' => 0]);
        $this->assertDatabaseHas('jurnal_umum_rinci', ['coa_id' => $revenue->id, 'debit' => 0, 'kredit' => 10]);
        $this->assertDatabaseHas('jurnal_umum_rinci', ['coa_id' => $asset->id, 'debit' => 0, 'kredit' => 5]);
        $this->assertDatabaseHas('jurnal_umum_rinci', ['coa_id' => $liability->id, 'debit' => 5, 'kredit' => 0]);
    }

    public function test_missing_coa_returns_codes_and_does_not_write_any_transaction(): void
    {
        $this->createCoa('110.01', 'Kasbank');

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => 'MISSING-01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame('COA tidak ditemukan untuk akun: MISSING-01.', $result[0]['alasan_gagal']);
        $this->assertNoImportWrites();
    }

    public function test_duplicate_coa_code_is_rejected_as_ambiguous(): void
    {
        $this->createCoa('110.01', 'Kasbank');
        $this->createCoa('110.01', 'Kasbank');
        $this->createCoa('410.01', 'Pendapatan');

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame('Kode COA tidak unik untuk akun: 110.01.', $result[0]['alasan_gagal']);
        $this->assertNoImportWrites();
    }

    public function test_non_postable_and_unknown_coa_types_are_rejected(): void
    {
        $this->createCoa('INVALID-01', 'Kasbank', false);
        $this->createCoa('UNKNOWN-01', 'Tipe Khusus');
        $this->createCoa('410.01', 'Pendapatan');

        $this->expectCandidates([
            $this->candidate('ext-1', 'RJ-001'),
            $this->candidate('ext-2', 'RJ-002'),
        ]);
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-1')->andReturn([
            ['akun' => 'INVALID-01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-2')->andReturn([
            ['akun' => 'UNKNOWN-01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1', 'ext-2']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('tidak aktif, tidak postable, atau bukan akun leaf', $result[0]['alasan_gagal']);
        $this->assertFalse($result[1]['berhasil']);
        $this->assertStringContainsString('tidak dikenali', $result[1]['alasan_gagal']);
        $this->assertNoImportWrites();
    }

    public function test_unbalanced_patient_is_rejected_without_writes(): void
    {
        $this->createCoa('110.01', 'Kasbank');

        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldReceive('getAkun')->once()->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['ext-1']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame(
            'Jurnal tidak balance. Total debit 100.00 dan kredit 0.00.',
            $result[0]['alasan_gagal'],
        );
        $this->assertNoImportWrites();
    }

    public function test_one_failed_patient_does_not_cancel_another_patient(): void
    {
        $this->createCoa('110.01', 'Kasbank');
        $this->createCoa('410.01', 'Pendapatan');

        $this->expectCandidates([
            $this->candidate('ext-1', 'RJ-001'),
            $this->candidate('ext-2', 'RJ-002'),
        ]);
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-1')->andReturn([
            ['akun' => '110.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
            ['akun' => '410.01', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->apiClient->shouldReceive('getAkun')->once()->with('ext-2')->andReturn([
            ['akun' => 'MISSING-02', 'biaya' => 100, 'jml' => 1, 'job' => null],
        ]);
        $this->logService->shouldReceive('log')->once();

        $result = $this->import(['ext-1', 'ext-2']);

        $this->assertTrue($result[0]['berhasil']);
        $this->assertFalse($result[1]['berhasil']);
        $this->assertSame(1, DB::table('jurnal_umum')->count());
        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'RJ-001']);
        $this->assertDatabaseMissing('jurnal_umum', ['nomer' => 'RJ-002']);
    }

    public function test_selected_external_id_outside_current_filter_is_rejected_without_detail_call(): void
    {
        $this->expectCandidates([$this->candidate()]);
        $this->apiClient->shouldNotReceive('getAkun');
        $this->logService->shouldNotReceive('log');

        $result = $this->import(['outside-filter']);

        $this->assertFalse($result[0]['berhasil']);
        $this->assertSame('outside-filter', $result[0]['no_rawat']);
        $this->assertSame(
            'Data terpilih tidak ditemukan pada hasil Billing API untuk filter ini.',
            $result[0]['alasan_gagal'],
        );
        $this->assertNoImportWrites();
    }

    private function expectCandidates(array $candidates): void
    {
        $this->candidateService
            ->shouldReceive('getKandidatUntukImpor')
            ->once()
            ->with('rawat_jalan', '2026-08-15', '2026-08-15', null, null)
            ->andReturn(collect($candidates));
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

    private function candidate(string $externalId = 'ext-1', string $noRawat = 'RJ-001'): array
    {
        return [
            'external_id' => $externalId,
            'no_rawat' => $noRawat,
            'tanggal_registrasi' => '2026-08-15',
            'nama_pasien' => 'Pasien API',
            'nama_dokter' => 'Dokter API',
            'nama_poli' => 'Poli API',
            'status_lanjut' => 'Rawat Jalan',
        ];
    }

    private function createCoa(string $kode, string $tipe, bool $isPostable = true): Coa
    {
        return Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => $tipe,
            'kode' => $kode,
            'nama' => $kode,
            'is_postable' => $isPostable,
        ]);
    }

    private function assertNoImportWrites(): void
    {
        $this->assertSame(0, DB::table('jurnal_umum')->count());
        $this->assertSame(0, DB::table('jurnal_umum_rinci')->count());
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

        Schema::create('jurnal_umum', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
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
            $table->text('alamat')->nullable();
            $table->string('jam_reg')->nullable();
            $table->string('kode_dokter')->nullable();
            $table->string('kode_penjamin')->nullable();
            $table->string('kode_poli')->nullable();
            $table->string('nama_kabupaten')->nullable();
            $table->string('nama_kecamatan')->nullable();
            $table->string('nama_kelurahan')->nullable();
            $table->string('no_rekam_medis')->nullable();
            $table->string('import_ke')->nullable();
        });
    }
}
