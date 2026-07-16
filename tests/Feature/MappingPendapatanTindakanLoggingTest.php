<?php

namespace Tests\Feature;

use App\Services\LogAktifitasService;
use App\Services\Pengaturan\MappingPendapatanTindakanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MappingPendapatanTindakanLoggingTest extends TestCase
{
    private MappingPendapatanTindakanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.simrs', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('simrs');

        $this->createApplicationTables();
        $this->createSimrsTables();

        $logActivityService = Mockery::mock(LogAktifitasService::class);
        $logActivityService->shouldIgnoreMissing();
        $this->service = new MappingPendapatanTindakanService($logActivityService);

        DB::table('coa')->insert([
            'id' => 1,
            'kode' => '41602',
            'nama' => 'Pendapatan Laboratorium',
        ]);
    }

    public function test_referensi_yang_tidak_ditemukan_dicatat_ke_log(): void
    {
        Log::spy();

        $result = $this->service->createMappings('lab', [[
            'tindakan_key' => 'LB 64||UMU',
            'coa_id' => 1,
        ]], 'operator.lab');

        $this->assertSame(['success' => 0, 'failed' => 1], $result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Mapping pendapatan tindakan gagal', Mockery::on(fn (array $context) =>
                $context['alasan'] === 'referensi_tindakan_tidak_ditemukan'
                && $context['jenis_tindakan'] === 'lab'
                && $context['sumber_tindakan'] === 'Laborat'
                && $context['tindakan_key'] === 'LB 64||UMU'
                && $context['kode_tindakan'] === null
                && $context['coa_id'] === 1
                && $context['kode_coa'] === '41602'
                && $context['pengguna'] === 'operator.lab'
            ));
    }

    public function test_mapping_duplikat_dicatat_dengan_detail_tindakan(): void
    {
        $this->insertLabReference('LB 64', 'Pemeriksaan Laboratorium', 'UMU');
        DB::table('mapping_pendapatan')->insert([
            'kode_jenis_perawatan' => 'LB 64',
            'kode_penjamin' => 'UMU',
            'coa_id' => 1,
            'sumber_tindakan' => 'Laborat',
            'nm_perawatan' => 'Pemeriksaan Laboratorium',
        ]);
        Log::spy();

        $result = $this->service->createMappings('lab', [[
            'tindakan_key' => 'LB 64||UMU',
            'coa_id' => 1,
        ]], 'operator.lab');

        $this->assertSame(['success' => 0, 'failed' => 1], $result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Mapping pendapatan tindakan gagal', Mockery::on(fn (array $context) =>
                $context['alasan'] === 'mapping_sudah_ada'
                && $context['kode_tindakan'] === 'LB 64'
                && $context['kode_penjamin'] === 'UMU'
                && $context['nama_tindakan'] === 'Pemeriksaan Laboratorium'
                && $context['kode_coa'] === '41602'
            ));
    }

    public function test_batch_campuran_mencatat_kegagalan_dan_tetap_menyimpan_mapping_valid(): void
    {
        $this->insertLabReference('LB 64', 'Tindakan Lama', 'UMU');
        $this->insertLabReference('LB 65', 'Tindakan Baru', 'UMU');
        DB::table('mapping_pendapatan')->insert([
            'kode_jenis_perawatan' => 'LB 64',
            'kode_penjamin' => 'UMU',
            'coa_id' => 1,
            'sumber_tindakan' => 'Laborat',
            'nm_perawatan' => 'Tindakan Lama',
        ]);
        Log::spy();

        $result = $this->service->createMappings('lab', [
            ['tindakan_key' => 'LB 64||UMU', 'coa_id' => 1],
            ['tindakan_key' => 'LB 65||UMU', 'coa_id' => 1],
            ['tindakan_key' => 'LB 66||UMU', 'coa_id' => 1],
        ], 'operator.lab');

        $this->assertSame(['success' => 1, 'failed' => 2], $result);
        $this->assertDatabaseHas('mapping_pendapatan', [
            'kode_jenis_perawatan' => 'LB 65',
            'kode_penjamin' => 'UMU',
            'coa_id' => 1,
        ]);
        Log::shouldHaveReceived('warning')->twice();
    }

    private function createApplicationTables(): void
    {
        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->string('kode');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('mapping_pendapatan', function (Blueprint $table): void {
            $table->id('mapping_pendapatan_id');
            $table->string('kode_jenis_perawatan');
            $table->string('kode_penjamin');
            $table->string('kode_poli')->nullable();
            $table->unsignedBigInteger('coa_id');
            $table->string('user_create')->nullable();
            $table->string('user_edit')->nullable();
            $table->string('sumber_tindakan');
            $table->string('nm_perawatan');
            $table->timestamps();
        });

        Schema::create('mapping_pendapatan_kamar', function (Blueprint $table): void {
            $table->id('mapping_pendapatan_kamar_id');
            $table->string('kode_kamar');
            $table->string('nama_kamar');
            $table->string('status_aktif');
            $table->unsignedBigInteger('pendapatan_kamar_coa_id');
            $table->timestamps();
        });
    }

    private function createSimrsTables(): void
    {
        Schema::connection('simrs')->create('penjab', function (Blueprint $table): void {
            $table->string('kd_pj')->primary();
            $table->string('png_jawab');
        });

        Schema::connection('simrs')->create('jns_perawatan_lab', function (Blueprint $table): void {
            $table->string('kd_jenis_prw');
            $table->string('nm_perawatan');
            $table->string('kd_pj');
            $table->string('status');
        });
    }

    private function insertLabReference(string $code, string $name, string $insurerCode): void
    {
        DB::connection('simrs')->table('penjab')->insertOrIgnore([
            'kd_pj' => $insurerCode,
            'png_jawab' => 'Umum',
        ]);
        DB::connection('simrs')->table('jns_perawatan_lab')->insert([
            'kd_jenis_prw' => $code,
            'nm_perawatan' => $name,
            'kd_pj' => $insurerCode,
            'status' => '1',
        ]);
    }
}
