<?php

namespace Tests\Unit\Services;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\LogAktifitas;
use App\Models\ReferensiKelompokArusKas;
use App\Services\LogAktifitasService;
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
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::dropIfExists('log_aktifitas');

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('tipe_coa', 50);
            $table->boolean('status_aktif')->default(true);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('coa_id');
            $table->string('sumber_id', 50)->nullable();
            $table->string('tanggal', 25);
            $table->string('nomer', 50)->nullable();
            $table->string('sumber_transaksi', 50)->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('tipe_mutasi', 5);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_user', 100)->nullable();
            $table->string('modul', 100);
            $table->string('tipe', 50);
            $table->text('payload')->nullable();
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

    public function test_update_ignores_aktivitas_and_preserves_immutability(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Operasional',
            'status_aktif' => true,
        ]);

        $this->service->update($ref, [
            'nama' => 'Beban Operasional Baru',
            'aktivitas' => 'investasi',
            'status_aktif' => true,
        ]);

        $this->assertEquals('operasi', $ref->fresh()->aktivitas);
        $this->assertEquals('Beban Operasional Baru', $ref->fresh()->nama);
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

    public function test_get_coa_options_data_and_leaf_descendants_classify_eligible_reassignable_and_skipped(): void
    {
        $selfPos = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Sendiri',
            'status_aktif' => true,
        ]);
        $otherPos = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Lain',
            'status_aktif' => true,
        ]);

        $root = Coa::create([
            'kode' => '5',
            'nama' => 'Beban Induk',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $sub = Coa::create([
            'parent_coa' => $root->id,
            'kode' => '5.1',
            'nama' => 'Beban Operasi',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);

        $leafUnmapped = Coa::create([
            'parent_coa' => $sub->id,
            'kode' => '5.1.01',
            'nama' => 'Beban Listrik',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $leafSelf = Coa::create([
            'parent_coa' => $sub->id,
            'kode' => '5.1.02',
            'nama' => 'Beban Air',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'Operasi',
            'arus_kas_kelompok' => 'pos sendiri',
        ]);
        $leafOther = Coa::create([
            'parent_coa' => $sub->id,
            'kode' => '5.1.03',
            'nama' => 'Beban Telepon',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pos Lain',
        ]);
        $leafInactive = Coa::create([
            'parent_coa' => $sub->id,
            'kode' => '5.1.04',
            'nama' => 'Beban Lama Nonaktif',
            'tipe_coa' => 'Beban',
            'status_aktif' => false,
        ]);
        $leafKasbank = Coa::create([
            'parent_coa' => $sub->id,
            'kode' => '5.1.05',
            'nama' => 'Kas Kecil Cabang',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => true,
        ]);

        $optionsData = $this->service->getCoaOptionsData();

        $this->assertEqualsCanonicalizing(
            [$leafUnmapped->id, $leafSelf->id, $leafOther->id, $leafInactive->id],
            $optionsData['leafCoas']->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$root->id, $sub->id],
            $optionsData['parentCoas']->pluck('id')->all()
        );

        $mappedByCoaId = $optionsData['mappedByCoaId'];
        $this->assertArrayHasKey($leafSelf->id, $mappedByCoaId);
        $this->assertEquals('Operasi', $mappedByCoaId[$leafSelf->id]['aktivitas']);
        $this->assertEquals('pos sendiri', $mappedByCoaId[$leafSelf->id]['kelompok']);
        $this->assertEquals($selfPos->id, $mappedByCoaId[$leafSelf->id]['referensi_id']);

        $this->assertArrayHasKey($leafOther->id, $mappedByCoaId);
        $this->assertEquals('operasi', $mappedByCoaId[$leafOther->id]['aktivitas']);
        $this->assertEquals('Pos Lain', $mappedByCoaId[$leafOther->id]['kelompok']);
        $this->assertEquals($otherPos->id, $mappedByCoaId[$leafOther->id]['referensi_id']);

        $descendants = $this->service->getLeafDescendantsByParent($root->id, $selfPos->id);

        $this->assertArrayHasKey('eligible', $descendants);
        $this->assertArrayHasKey('reassignable', $descendants);
        $this->assertArrayHasKey('skipped', $descendants);

        $eligibleIds = array_column($descendants['eligible'], 'id');
        $this->assertEqualsCanonicalizing([$leafUnmapped->id, $leafSelf->id, $leafInactive->id], $eligibleIds);

        $reassignableIds = array_column($descendants['reassignable'], 'id');
        $this->assertSame([$leafOther->id], $reassignableIds);
        $this->assertEquals('operasi', $descendants['reassignable'][0]['current_aktivitas']);
        $this->assertEquals('Pos Lain', $descendants['reassignable'][0]['current_kelompok']);
        $this->assertEquals($otherPos->id, $descendants['reassignable'][0]['current_referensi_id']);

        $skippedIds = array_column($descendants['skipped'], 'id');
        $this->assertSame([$leafKasbank->id], $skippedIds);
        $this->assertStringContainsString('Kasbank', $descendants['skipped'][0]['reason']);
    }

    public function test_store_update_sync_coa_mappings_and_protects_parent_coa_tags(): void
    {
        $leaf1 = Coa::create([
            'kode' => '5.1.01',
            'nama' => 'Beban Listrik',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $leaf2 = Coa::create([
            'kode' => '5.1.02',
            'nama' => 'Beban Air',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $leafFromPosB = Coa::create([
            'kode' => '5.1.03',
            'nama' => 'Beban Telepon',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pos B',
        ]);

        $parentCoa = Coa::create([
            'kode' => '5',
            'nama' => 'Induk Beban',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pos A',
        ]);
        $leaf1->update(['parent_coa' => $parentCoa->id]);
        $leaf2->update(['parent_coa' => $parentCoa->id]);
        $leafFromPosB->update(['parent_coa' => $parentCoa->id]);

        $posA = $this->service->store([
            'aktivitas' => 'operasi',
            'nama' => 'Pos A',
            'status_aktif' => true,
            'coa_ids' => [$leaf1->id, $leaf2->id],
        ]);

        $this->assertEquals('operasi', $leaf1->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Pos A', $leaf1->fresh()->arus_kas_kelompok);
        $this->assertEquals('operasi', $leaf2->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Pos A', $leaf2->fresh()->arus_kas_kelompok);

        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Pengaturan Arus Kas',
            'tipe' => 'create_langsung',
        ]);

        $this->service->update($posA, [
            'nama' => 'Pos A Revisi',
            'status_aktif' => true,
            'coa_ids' => [$leafFromPosB->id],
        ]);

        $this->assertNull($leaf1->fresh()->arus_kas_aktivitas);
        $this->assertNull($leaf1->fresh()->arus_kas_kelompok);
        $this->assertNull($leaf2->fresh()->arus_kas_aktivitas);
        $this->assertNull($leaf2->fresh()->arus_kas_kelompok);

        $this->assertEquals('operasi', $leafFromPosB->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Pos A Revisi', $leafFromPosB->fresh()->arus_kas_kelompok);

        // Parent COA must NOT be unmapped by syncCoaMappings and must be cascaded to new name
        $this->assertEquals('operasi', $parentCoa->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Pos A Revisi', $parentCoa->fresh()->arus_kas_kelompok);

        $updateLog = LogAktifitas::where('tipe', 'update_langsung')->latest('id')->first();
        $this->assertNotNull($updateLog);
        $payload = json_decode($updateLog->payload, true);
        $this->assertEqualsCanonicalizing([$leafFromPosB->id], $payload['new']['added_coa_ids']);
        $this->assertEqualsCanonicalizing([$leaf1->id, $leaf2->id], $payload['new']['removed_coa_ids']);
        $this->assertArrayHasKey((string) $leafFromPosB->id, $payload['new']['moved_from']);

        // Update without coa_ids key preserves existing leaf mapping and cascades name
        $this->service->update($posA, [
            'nama' => 'Pos A Revisi 2',
            'status_aktif' => true,
        ]);
        $this->assertEquals('operasi', $leafFromPosB->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Pos A Revisi 2', $leafFromPosB->fresh()->arus_kas_kelompok);
        $this->assertEquals('Pos A Revisi 2', $parentCoa->fresh()->arus_kas_kelompok);

        // Update with empty coa_ids clears all leaves, preserves parent
        $this->service->update($posA, [
            'nama' => 'Pos A Revisi 2',
            'status_aktif' => true,
            'coa_ids' => [],
        ]);
        $this->assertNull($leafFromPosB->fresh()->arus_kas_aktivitas);
        $this->assertNull($leafFromPosB->fresh()->arus_kas_kelompok);
        $this->assertEquals('Pos A Revisi 2', $parentCoa->fresh()->arus_kas_kelompok);
    }

    public function test_can_delete_distinguishes_leaf_and_parent_usage_case_insensitively(): void
    {
        $pos = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Pengujian Hapus',
            'status_aktif' => true,
        ]);

        $parent = Coa::create([
            'kode' => '1.1',
            'nama' => 'Induk Aset',
            'tipe_coa' => 'Aset',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'OPERASI',
            'arus_kas_kelompok' => 'pos pengujian hapus',
        ]);
        $leaf1 = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '1.1.01',
            'nama' => 'Aset Daun 1',
            'tipe_coa' => 'Aset',
            'status_aktif' => true,
        ]);
        $leaf2 = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '1.1.02',
            'nama' => 'Aset Daun 2',
            'tipe_coa' => 'Aset',
            'status_aktif' => true,
        ]);

        // Case 1: only parent is mapped
        $check = $this->service->canDelete($pos);
        $this->assertFalse($check['allowed']);
        $this->assertEquals(0, $check['leaf_count']);
        $this->assertEquals(1, $check['parent_count']);
        $this->assertEquals(1, $check['coa_count']);
        $this->assertStringContainsString('induk', strtolower($check['reason']));

        // Case 2: 2 leaves + 1 parent
        $leaf1->update([
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pos Pengujian Hapus',
        ]);
        $leaf2->update([
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pos Pengujian Hapus',
        ]);

        $check2 = $this->service->canDelete($pos);
        $this->assertFalse($check2['allowed']);
        $this->assertEquals(2, $check2['leaf_count']);
        $this->assertEquals(1, $check2['parent_count']);
        $this->assertEquals(3, $check2['coa_count']);
        $this->assertStringContainsString('2 akun COA', $check2['reason']);
        $this->assertStringContainsString('1 akun induk', $check2['reason']);
    }

    public function test_quick_map_coa_validates_and_logs_and_safe_when_log_table_missing(): void
    {
        $pos = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Pasien',
            'status_aktif' => true,
        ]);

        $parent = Coa::create([
            'kode' => '4',
            'nama' => 'Pendapatan',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => true,
        ]);
        $leaf = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '4.1.01',
            'nama' => 'Pendapatan Rawat Inap',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => true,
        ]);
        $kasbank = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '4.1.99',
            'nama' => 'Kas Operasional',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => true,
        ]);

        $result = $this->service->quickMapCoa($leaf->id, $pos->id);
        $this->assertSame($leaf->id, $result['coa']->id);
        $this->assertSame($pos->id, $result['referensi']->id);
        $this->assertEquals('operasi', $leaf->fresh()->arus_kas_aktivitas);
        $this->assertEquals('Penerimaan Pasien', $leaf->fresh()->arus_kas_kelompok);

        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Pengaturan Arus Kas',
            'tipe' => 'quick_map_langsung',
        ]);

        // Rejects Kasbank
        try {
            $this->service->quickMapCoa($kasbank->id, $pos->id);
            $this->fail('Should reject Kasbank');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Kasbank', $e->getMessage());
        }

        // Rejects parent COA
        try {
            $this->service->quickMapCoa($parent->id, $pos->id);
            $this->fail('Should reject parent COA');
        } catch (DomainException $e) {
            $this->assertStringContainsString('induk', $e->getMessage());
        }

        // Safe when log_aktifitas table is missing
        Schema::dropIfExists('log_aktifitas');

        $newRef = $this->service->store([
            'aktivitas' => 'investasi',
            'nama' => 'Peralatan Medis',
            'status_aktif' => true,
        ]);
        $this->service->update($newRef, ['nama' => 'Peralatan Medis Revisi']);
        $this->service->toggleStatus($newRef);
        $this->service->quickMapCoa($leaf->id, $newRef->id);
        $this->service->delete($pos);
        $this->assertTrue(true); // Reached without exception
    }

    public function test_get_unmapped_cash_counterpart_accounts_detects_only_cash_counterparts_and_ignores_accruals(): void
    {
        $kasUtama = Coa::create([
            'kode' => '1.1.01.01',
            'nama' => 'Kas Utama',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => true,
        ]);
        $bankBca = Coa::create([
            'kode' => '1.1.01.02',
            'nama' => 'Bank BCA',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => true,
        ]);
        $piutangUnmapped = Coa::create([
            'kode' => '1.1.03.01',
            'nama' => 'Piutang Belum Map',
            'tipe_coa' => 'Piutang',
            'status_aktif' => true,
        ]);
        $bebanUnmapped = Coa::create([
            'kode' => '5.1.01.99',
            'nama' => 'Beban Belum Map',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $pendapatanMapped = Coa::create([
            'kode' => '4.1.01.01',
            'nama' => 'Penerimaan Pasien Mapped',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => true,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Pasien',
        ]);
        $bebanPenyusutan = Coa::create([
            'kode' => '5.2.01.01',
            'nama' => 'Beban Penyusutan Unmapped',
            'tipe_coa' => 'Beban',
            'status_aktif' => true,
        ]);
        $akmLabaPenyusutan = Coa::create([
            'kode' => '1.3.01.99',
            'nama' => 'Akumulasi Penyusutan Unmapped',
            'tipe_coa' => 'Aset Tetap',
            'status_aktif' => true,
        ]);

        // 1. Kasbank transaction: Kas Utama D 500.000, Piutang Belum Map K 500.000 (2026-03-10)
        BukuBesar::create([
            'coa_id' => $kasUtama->id,
            'sumber_id' => '101',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2026-03-10',
            'nominal' => 500000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $piutangUnmapped->id,
            'sumber_id' => '101',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2026-03-10',
            'nominal' => 500000,
            'tipe_mutasi' => 'K',
        ]);

        // 2. Year-end Kasbank without sumber_id: Kas Utama K 150.000, Beban Belum Map D 150.000 (2026-12-31)
        BukuBesar::create([
            'coa_id' => $kasUtama->id,
            'sumber_id' => null,
            'sumber_transaksi' => 'BKK',
            'nomer' => 'BKK-001',
            'tanggal' => '2026-12-31',
            'nominal' => 150000,
            'tipe_mutasi' => 'K',
        ]);
        BukuBesar::create([
            'coa_id' => $bebanUnmapped->id,
            'sumber_id' => null,
            'sumber_transaksi' => 'BKK',
            'nomer' => 'BKK-001',
            'tanggal' => '2026-12-31',
            'nominal' => 150000,
            'tipe_mutasi' => 'D',
        ]);

        // 3. Already mapped counterpart
        BukuBesar::create([
            'coa_id' => $kasUtama->id,
            'sumber_id' => '102',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2026-05-01',
            'nominal' => 1000000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $pendapatanMapped->id,
            'sumber_id' => '102',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2026-05-01',
            'nominal' => 1000000,
            'tipe_mutasi' => 'K',
        ]);

        // 4. Pure accrual without Kasbank
        BukuBesar::create([
            'coa_id' => $bebanPenyusutan->id,
            'sumber_id' => '201',
            'sumber_transaksi' => 'JU',
            'tanggal' => '2026-06-01',
            'nominal' => 200000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $akmLabaPenyusutan->id,
            'sumber_id' => '201',
            'sumber_transaksi' => 'JU',
            'tanggal' => '2026-06-01',
            'nominal' => 200000,
            'tipe_mutasi' => 'K',
        ]);

        // 5. Internal cash transfer (cashNet = 0)
        BukuBesar::create([
            'coa_id' => $kasUtama->id,
            'sumber_id' => '301',
            'sumber_transaksi' => 'BK',
            'tanggal' => '2026-07-01',
            'nominal' => 300000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $bankBca->id,
            'sumber_id' => '301',
            'sumber_transaksi' => 'BK',
            'tanggal' => '2026-07-01',
            'nominal' => 300000,
            'tipe_mutasi' => 'K',
        ]);

        // 6. Previous year transaction (2025-12-31)
        BukuBesar::create([
            'coa_id' => $kasUtama->id,
            'sumber_id' => '999',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2025-12-31',
            'nominal' => 400000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $piutangUnmapped->id,
            'sumber_id' => '999',
            'sumber_transaksi' => 'BKM',
            'tanggal' => '2025-12-31',
            'nominal' => 400000,
            'tipe_mutasi' => 'K',
        ]);

        $unmapped = $this->service->getUnmappedCashCounterpartAccounts('2026-01-01', '2026-12-31');

        $this->assertCount(2, $unmapped);

        $piutangRow = $unmapped->firstWhere('coa_id', $piutangUnmapped->id);
        $this->assertNotNull($piutangRow);
        $this->assertEquals(0, $piutangRow['debit']);
        $this->assertEquals(500000, $piutangRow['kredit']);
        $this->assertEquals(500000, $piutangRow['dampak_kas']);
        $this->assertEquals(1, $piutangRow['trx_count']);

        $bebanRow = $unmapped->firstWhere('coa_id', $bebanUnmapped->id);
        $this->assertNotNull($bebanRow);
        $this->assertEquals(150000, $bebanRow['debit']);
        $this->assertEquals(0, $bebanRow['kredit']);
        $this->assertEquals(-150000, $bebanRow['dampak_kas']);
        $this->assertEquals(1, $bebanRow['trx_count']);
    }
}
