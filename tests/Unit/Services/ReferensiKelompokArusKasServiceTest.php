<?php

namespace Tests\Unit\Services;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasServiceTest extends TestCase
{
    private ReferensiKelompokArusKasService $service;

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

        $this->service = app(ReferensiKelompokArusKasService::class);
    }

    public function test_sanitization_and_case_insensitive_ensure_exists(): void
    {
        $first = $this->service->ensureExists('operasi', '  Pembayaran   listrik, air,  internet  ');
        $this->assertEquals('Pembayaran listrik, air, internet', $first->nama);

        // Case-insensitive match should reuse existing without creating duplicate
        $second = $this->service->ensureExists('operasi', 'pembayaran listrik, air, internet');
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, ReferensiKelompokArusKas::count());
    }

    public function test_update_cascade_updates_associated_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Listrk',
            'status_aktif' => true,
        ]);

        $coa = Coa::create([
            'kode' => '5.1.01',
            'nama' => 'Beban Listrik',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Listrk',
        ]);

        $this->service->update($ref, [
            'nama' => 'Pembayaran listrik, air, internet',
            'status_aktif' => true,
        ]);

        $this->assertEquals('Pembayaran listrik, air, internet', $ref->fresh()->nama);
        $this->assertEquals('Pembayaran listrik, air, internet', $coa->fresh()->arus_kas_kelompok);
    }

    public function test_update_without_name_change_does_not_modify_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Operasional',
            'status_aktif' => true,
        ]);

        $coa = Coa::create([
            'kode' => '5.1.03',
            'nama' => 'Operasional Kantor',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Operasional',
        ]);

        $this->service->update($ref, [
            'nama' => 'Beban Operasional',
            'status_aktif' => false,
        ]);

        $this->assertFalse($ref->fresh()->status_aktif);
        $this->assertEquals('Beban Operasional', $coa->fresh()->arus_kas_kelompok);
    }

    public function test_restricted_delete_when_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Gaji',
            'status_aktif' => true,
        ]);

        Coa::create([
            'kode' => '5.1.02',
            'nama' => 'Gaji Dokter',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Gaji',
        ]);

        $check = $this->service->canDelete($ref);
        $this->assertFalse($check['allowed']);
        $this->assertEquals(1, $check['coa_count']);
        $this->assertStringContainsString('Pos ini sedang digunakan oleh 1 akun COA.', $check['reason']);

        $this->expectException(DomainException::class);
        $this->service->delete($ref);
    }

    public function test_delete_succeeds_when_not_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'investasi',
            'nama' => 'Penjualan Aset Tetap',
            'status_aktif' => true,
        ]);

        $check = $this->service->canDelete($ref);
        $this->assertTrue($check['allowed']);
        $this->assertEquals(0, $check['coa_count']);
        $this->assertNull($check['reason']);

        $this->service->delete($ref);
        $this->assertDatabaseMissing('referensi_kelompok_arus_kas', ['id' => $ref->id]);
    }

    public function test_get_grouped_active_returns_active_ordered_and_grouped(): void
    {
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Pasien',
            'status_aktif' => true,
        ]);
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Bahan Medis',
            'status_aktif' => true,
        ]);
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Nonaktif',
            'status_aktif' => false,
        ]);
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'investasi',
            'nama' => 'Pembelian Alat Medis',
            'status_aktif' => true,
        ]);

        $grouped = $this->service->getGroupedActive();

        $this->assertTrue($grouped->has('operasi'));
        $this->assertTrue($grouped->has('investasi'));
        $this->assertFalse($grouped->has('pendanaan'));

        $operasiList = $grouped->get('operasi');
        $this->assertCount(2, $operasiList);
        $this->assertEquals('Beban Bahan Medis', $operasiList->first()->nama);
        $this->assertEquals('Penerimaan Pasien', $operasiList->last()->nama);
    }

    public function test_store_creates_record_with_sanitized_name(): void
    {
        $record = $this->service->store([
            'aktivitas' => 'pendanaan',
            'nama' => '   Penerimaan   Pinjaman   Bank   ',
            'status_aktif' => true,
        ]);

        $this->assertEquals('Penerimaan Pinjaman Bank', $record->nama);
        $this->assertEquals('pendanaan', $record->aktivitas);
        $this->assertTrue($record->status_aktif);
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'id' => $record->id,
            'nama' => 'Penerimaan Pinjaman Bank',
        ]);
    }

    public function test_toggle_status_flips_boolean_and_persists(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Promosi',
            'status_aktif' => true,
        ]);

        $newStatus = $this->service->toggleStatus($ref);
        $this->assertFalse($newStatus);
        $this->assertFalse($ref->fresh()->status_aktif);

        $newStatus2 = $this->service->toggleStatus($ref);
        $this->assertTrue($newStatus2);
        $this->assertTrue($ref->fresh()->status_aktif);
    }
}
