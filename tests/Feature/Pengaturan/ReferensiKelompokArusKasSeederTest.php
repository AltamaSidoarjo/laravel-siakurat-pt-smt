<?php

namespace Tests\Feature\Pengaturan;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use Database\Seeders\ReferensiKelompokArusKasSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('tipe_coa', 50);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->timestamps();
        });

        // Insert legacy unstandardized data
        Coa::create([
            'kode' => '1.1.01',
            'nama' => 'Kas Operasional',
            'tipe_coa' => 'Kas',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan dari pasien dan pelanggan',
        ]);

        // Insert legacy data that already matches a standard pos
        Coa::create([
            'kode' => '1.1.02',
            'nama' => 'Bank Mandiri',
            'tipe_coa' => 'Bank',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan klaim BPJS Kesehatan',
        ]);
    }

    public function test_seeder_populates_14_standard_active_and_legacy_inactive(): void
    {
        $this->seed(ReferensiKelompokArusKasSeeder::class);

        // 14 standard items should be active
        $this->assertEquals(14, ReferensiKelompokArusKas::where('status_aktif', true)->count());
        $this->assertEquals(10, ReferensiKelompokArusKas::where('status_aktif', true)->where('aktivitas', 'operasi')->count());
        $this->assertEquals(2, ReferensiKelompokArusKas::where('status_aktif', true)->where('aktivitas', 'investasi')->count());
        $this->assertEquals(2, ReferensiKelompokArusKas::where('status_aktif', true)->where('aktivitas', 'pendanaan')->count());

        // Legacy pos not in standard list should be seeded as inactive
        $legacy = ReferensiKelompokArusKas::where('nama', 'Penerimaan dari pasien dan pelanggan')->first();
        $this->assertNotNull($legacy);
        $this->assertFalse($legacy->status_aktif);
        $this->assertEquals('operasi', $legacy->aktivitas);

        // Standard pos that also existed in legacy coa should remain active
        $standardExistingInLegacy = ReferensiKelompokArusKas::where('nama', 'Penerimaan klaim BPJS Kesehatan')->first();
        $this->assertNotNull($standardExistingInLegacy);
        $this->assertTrue($standardExistingInLegacy->status_aktif);

        // Verify idempotency (re-running does not duplicate or alter status)
        $this->seed(ReferensiKelompokArusKasSeeder::class);
        $this->assertEquals(14, ReferensiKelompokArusKas::where('status_aktif', true)->count());
        $this->assertEquals(1, ReferensiKelompokArusKas::where('status_aktif', false)->count());
        $this->assertEquals(15, ReferensiKelompokArusKas::count());
    }
}
