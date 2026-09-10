<?php

namespace Tests\Unit;

use App\Models\Pelaksana;
use App\Services\LogAktifitasService;
use App\Services\Pengaturan\PelaksanaManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PelaksanaManagementServiceTest extends TestCase
{
    private PelaksanaManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pelaksana_id')->nullable();
        });

        $logger = Mockery::mock(LogAktifitasService::class);
        $logger->shouldReceive('log')->zeroOrMoreTimes();
        $this->service = new PelaksanaManagementService($logger);
    }

    public function test_create_update_search_and_delete_unused_pelaksana(): void
    {
        $pelaksana = $this->service->create([
            'no_proyek' => '1_TEST',
            'nama_pelaksana' => 'Pelaksana Test',
            'status_aktif' => true,
        ]);

        $this->assertSame(1, $this->service->paginate('Pelaksana Test')->total());
        $this->assertTrue(Pelaksana::query()->active()->whereKey($pelaksana)->exists());
        $this->service->update($pelaksana, [
            'no_proyek' => '1_TEST',
            'nama_pelaksana' => 'Pelaksana Diperbarui',
            'status_aktif' => false,
        ]);
        $this->assertDatabaseHas('pelaksana', ['id' => $pelaksana->id, 'status_aktif' => false]);
        $this->assertFalse(Pelaksana::query()->active()->whereKey($pelaksana)->exists());
        $this->assertTrue($this->service->delete($pelaksana));
        $this->assertDatabaseMissing('pelaksana', ['id' => $pelaksana->id]);
    }

    public function test_delete_is_rejected_when_pelaksana_is_referenced(): void
    {
        $pelaksana = Pelaksana::query()->create([
            'no_proyek' => '1_USED',
            'nama_pelaksana' => 'Pelaksana Terpakai',
            'status_aktif' => true,
        ]);
        $pelaksana->rincianFakturPenjualan()->getQuery()->insert(['pelaksana_id' => $pelaksana->id]);

        $this->assertFalse($this->service->delete($pelaksana));
        $this->assertDatabaseHas('pelaksana', ['id' => $pelaksana->id]);
    }
}
