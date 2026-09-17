<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Models\Pelaksana;
use App\Models\Pelanggan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use App\Services\Pendapatan\InvoicePendapatanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class InvoicePendapatanFormOptionsTest extends TestCase
{
    private InvoicePendapatanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pelanggan', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_pelanggan');
            $table->string('nama_pelanggan')->unique();
            $table->timestamps();
        });
        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->string('tipe_coa');
            $table->string('kode');
            $table->string('nama');
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });
        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('no_proyek');
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        $this->service = new InvoicePendapatanService(
            Mockery::mock(BukuBesarService::class),
            Mockery::mock(LogAktifitasService::class),
        );
    }

    public function test_form_options_use_the_expected_master_data_sources(): void
    {
        $activePelanggan = new Pelanggan;
        $activePelanggan->kode_pelanggan = 'PJ-001';
        $activePelanggan->nama_pelanggan = 'Penjamin Aktif';
        $activePelanggan->status_aktif = true;
        $activePelanggan->save();

        $inactivePelanggan = new Pelanggan;
        $inactivePelanggan->kode_pelanggan = 'PJ-002';
        $inactivePelanggan->nama_pelanggan = 'Penjamin Nonaktif';
        $inactivePelanggan->status_aktif = false;
        $inactivePelanggan->save();
        $akunPiutang = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Piutang Pasien',
            'tipe_coa' => 'Akun Piutang',
            'status_aktif' => true,
            'is_postable' => true,
        ]);
        $akunPiutangUsaha = Coa::query()->create([
            'kode' => '1102',
            'nama' => 'Piutang Usaha Lama',
            'tipe_coa' => 'Piutang Usaha',
            'status_aktif' => true,
            'is_postable' => true,
        ]);
        $akunPendapatan = Coa::query()->create([
            'kode' => '4101',
            'nama' => 'Pendapatan Rawat Jalan',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => true,
            'is_postable' => true,
        ]);
        Coa::query()->create([
            'kode' => '4199',
            'nama' => 'Pendapatan Lain',
            'tipe_coa' => 'Pendapatan Lain',
            'status_aktif' => true,
            'is_postable' => true,
        ]);
        $pelaksanaAktif = Pelaksana::query()->create([
            'no_proyek' => '01_PROYEK',
            'nama_pelaksana' => 'Pelaksana Aktif',
            'status_aktif' => true,
        ]);
        Pelaksana::query()->create([
            'no_proyek' => '02_PROYEK',
            'nama_pelaksana' => 'Pelaksana Nonaktif',
            'status_aktif' => false,
        ]);

        $this->assertSame([$activePelanggan->id], $this->service->getPelangganOptions()->pluck('id')->all());
        $this->assertSame([$akunPiutang->id, $akunPiutangUsaha->id], $this->service->getReceivableCoaOptions()->pluck('id')->all());
        $this->assertSame([$akunPendapatan->id], $this->service->getRevenueCoaOptions()->pluck('id')->all());
        $this->assertSame([$pelaksanaAktif->id], $this->service->getPelaksanaOptions()->pluck('id')->all());
    }

    public function test_sync_pelanggan_uses_name_as_identity_and_updates_existing_code(): void
    {
        $pelanggan = Pelanggan::query()->create([
            'kode_pelanggan' => 'OLD',
            'nama_pelanggan' => 'Keluarga Karyawan',
            'status_aktif' => false,
        ]);

        $options = collect([
            ['id' => '12', 'nama' => ' Keluarga Karyawan '],
            ['id' => '12', 'nama' => 'keluarga karyawan'],
        ]);

        $result = $this->service->syncPelangganOptionsFromApi($options);

        $this->assertCount(1, $result);
        $this->assertSame($pelanggan->id, $result->first()->id);
        $this->assertDatabaseCount('pelanggan', 1);
        $this->assertDatabaseHas('pelanggan', [
            'id' => $pelanggan->id,
            'kode_pelanggan' => '12',
            'nama_pelanggan' => 'Keluarga Karyawan',
            'status_aktif' => true,
        ]);
    }

    public function test_sync_pelanggan_is_idempotent_and_creates_new_customer_once(): void
    {
        $options = collect([
            ['id' => '20', 'nama' => 'Penjamin Baru'],
        ]);

        $firstResult = $this->service->syncPelangganOptionsFromApi($options);
        $secondResult = $this->service->syncPelangganOptionsFromApi($options);

        $this->assertSame($firstResult->first()->id, $secondResult->first()->id);
        $this->assertDatabaseCount('pelanggan', 1);
    }

    public function test_sync_pelanggan_skips_invalid_options_and_logs_warning(): void
    {
        Log::shouldReceive('warning')->times(3);

        $result = $this->service->syncPelangganOptionsFromApi(collect([
            'invalid',
            ['id' => '', 'nama' => 'Tanpa Kode'],
            ['id' => '21', 'nama' => ''],
        ]));

        $this->assertTrue($result->isEmpty());
        $this->assertDatabaseCount('pelanggan', 0);
    }
}
