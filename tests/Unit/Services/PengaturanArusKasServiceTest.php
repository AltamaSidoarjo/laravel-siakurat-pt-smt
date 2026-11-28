<?php

namespace Tests\Unit\Services;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\LogAktifitas;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use App\Services\Pengaturan\PengaturanArusKasService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengaturanArusKasServiceTest extends TestCase
{
    private PengaturanArusKasService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'bukubesar',
            'coa',
            'log_aktifitas',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->default('Jurnal Umum');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php'))->up();

        $this->service = app(PengaturanArusKasService::class);
    }

    public function test_store_auto_generates_unique_kode_pos_syncs_coas_and_writes_audit_log(): void
    {
        $piutangA = Coa::create(['kode' => '112.01', 'nama' => 'Piutang BPJS', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $piutangNonaktif = Coa::create(['kode' => '112.02', 'nama' => 'Piutang Lama', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 0]);

        $first = $this->service->store([
            'nama_pos' => '  Piutang   Usaha  ',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'is_active' => true,
            'coa_ids' => [$piutangA->id, $piutangNonaktif->id, $piutangA->id],
        ]);

        $this->assertSame('MK_PIUTANG_USAHA', $first->kode_pos);
        $this->assertSame('Piutang Usaha', $first->nama_pos);
        $this->assertTrue($first->pakai_prefix_perubahan);
        $this->assertTrue($first->is_active);
        $this->assertSame([$piutangA->id, $piutangNonaktif->id], $first->coas->pluck('id')->all());

        // Second store with identical nama_pos and blank urutan should auto-generate distinct kode_pos and next urutan
        $second = $this->service->store([
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => '',
        ]);

        $this->assertSame('MK_PIUTANG_USAHA_2', $second->kode_pos);
        $this->assertSame(2, $second->urutan);

        $third = $this->service->store([
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => null,
        ]);

        $this->assertSame('MK_PIUTANG_USAHA_3', $third->kode_pos);
        $this->assertSame(3, $third->urutan);

        $log = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'create')->first();
        $this->assertNotNull($log);
        $payload = json_decode((string) $log->payload, true);
        $this->assertSame('MK_PIUTANG_USAHA', $payload['new']['kode_pos']);
        $this->assertSame([$piutangA->id, $piutangNonaktif->id], $payload['new']['coa_ids']);
    }

    public function test_store_and_update_reject_conflicting_or_incompatible_coas_atomically(): void
    {
        $parent = Coa::create(['kode' => '112', 'nama' => 'Piutang Induk', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $child = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Anak', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $parent->id, 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas Utama', 'tipe_coa' => 'kasbank', 'status_aktif' => 1]);
        $bebanAktif = Coa::create(['kode' => '706.01', 'nama' => 'Beban Penyusutan', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $bebanNonaktif = Coa::create(['kode' => '706.02', 'nama' => 'Beban Nonaktif', 'tipe_coa' => 'Beban', 'status_aktif' => 0]);
        $excluded = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $alreadyMapped = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan Obat', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);

        SettingArusKasPengecualian::create(['coa_id' => $excluded->id, 'alasan' => 'Akun kontra']);
        $inactiveHolder = SettingArusKas::create([
            'kode_pos' => 'MK_PERSEDIAAN_OFF',
            'nama_pos' => 'Persediaan Nonaktif',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => false,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $inactiveHolder->id, 'coa_id' => $alreadyMapped->id]);

        $cases = [
            ['sifat' => 'aset', 'coa_ids' => [$parent->id], 'msg' => 'induk'],
            ['sifat' => 'aset', 'coa_ids' => [$kasbank->id], 'msg' => 'Kasbank'],
            ['sifat' => 'penambah_laba', 'coa_ids' => [$bebanNonaktif->id], 'msg' => 'nonaktif'],
            ['sifat' => 'penambah_laba', 'coa_ids' => [$child->id], 'msg' => 'Laba Rugi'],
            ['sifat' => 'aset', 'coa_ids' => [$bebanAktif->id], 'msg' => 'Laba Rugi'],
            ['sifat' => 'aset', 'coa_ids' => [$excluded->id], 'msg' => 'pengecualian'],
            ['sifat' => 'aset', 'coa_ids' => [$alreadyMapped->id], 'msg' => 'Persediaan Nonaktif'],
        ];

        $initialPosCount = SettingArusKas::count();
        foreach ($cases as $idx => $case) {
            try {
                $this->service->store([
                    'kode_pos' => 'TEST_ERR_'.$idx,
                    'nama_pos' => 'Pos Error '.$idx,
                    'kelompok_bagian' => 'modal_kerja',
                    'sifat_pos' => $case['sifat'],
                    'urutan' => 10 + $idx,
                    'coa_ids' => $case['coa_ids'],
                ]);
                $this->fail("Expected DomainException for case index {$idx}");
            } catch (DomainException $e) {
                $this->assertStringContainsString($case['msg'], $e->getMessage());
            }
        }
        // Transaction rollback must ensure no orphan pos rows were committed
        $this->assertSame($initialPosCount, SettingArusKas::count());

        // Update must also throw DomainException and leave original pos intact
        $validPos = $this->service->store([
            'kode_pos' => 'MK_VALID',
            'nama_pos' => 'Pos Valid',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'coa_ids' => [$child->id],
        ]);

        try {
            $this->service->update($validPos, [
                'nama_pos' => 'Pos Valid Diubah',
                'coa_ids' => [$alreadyMapped->id],
            ]);
            $this->fail('Expected DomainException on update conflict');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Persediaan Nonaktif', $e->getMessage());
        }

        $this->assertSame('Pos Valid', $validPos->fresh()->nama_pos);
        $this->assertSame([$child->id], $validPos->fresh()->coas->pluck('id')->all());

        // Changing sifat_pos without passing coa_ids must still validate existing mapped COAs
        try {
            $this->service->update($validPos, ['sifat_pos' => 'penambah_laba']);
            $this->fail('Expected DomainException when changing sifat_pos with incompatible existing COAs');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Laba Rugi', $e->getMessage());
        }
        $this->assertSame('aset', $validPos->fresh()->sifat_pos);
    }

    public function test_update_replaces_coas_transactionally_preserves_kode_pos_when_omitted_and_logs(): void
    {
        $coa1 = Coa::create(['kode' => '112.01', 'nama' => 'Piutang 1', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $coa2 = Coa::create(['kode' => '112.02', 'nama' => 'Piutang 2', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);

        $pos = $this->service->store([
            'kode_pos' => 'MK_PIUTANG_CUSTOM',
            'nama_pos' => 'Piutang Awal',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'coa_ids' => [$coa1->id],
        ]);

        $updated = $this->service->update($pos, [
            'kode_pos' => '',
            'nama_pos' => 'Piutang Revisi',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => false,
            'urutan' => 3,
            'coa_ids' => [$coa2->id],
        ]);

        $this->assertSame('MK_PIUTANG_CUSTOM', $updated->kode_pos);
        $this->assertSame('Piutang Revisi', $updated->nama_pos);
        $this->assertFalse($updated->pakai_prefix_perubahan);
        $this->assertSame(3, $updated->urutan);
        $this->assertSame([$coa2->id], $updated->coas->pluck('id')->all());
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $coa1->id]);

        $log = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'update')->latest('id')->first();
        $this->assertNotNull($log);
        $payload = json_decode((string) $log->payload, true);
        $this->assertSame('Piutang Awal', $payload['old']['nama_pos']);
        $this->assertSame([$coa1->id], $payload['old']['coa_ids']);
        $this->assertSame('Piutang Revisi', $payload['new']['nama_pos']);
        $this->assertSame([$coa2->id], $payload['new']['coa_ids']);

        // Passing null/empty optional values on update must preserve existing urutan and flags
        $preserved = $this->service->update($updated, [
            'kode_pos' => null,
            'urutan' => null,
            'pakai_prefix_perubahan' => null,
            'is_active' => null,
        ]);
        $this->assertSame('MK_PIUTANG_CUSTOM', $preserved->kode_pos);
        $this->assertSame(3, $preserved->urutan);
        $this->assertFalse($preserved->pakai_prefix_perubahan);
        $this->assertTrue($preserved->is_active);

        $flipped = $this->service->update($preserved, [
            'urutan' => '',
            'pakai_prefix_perubahan' => true,
            'is_active' => false,
        ]);
        $this->assertSame(3, $flipped->urutan);
        $this->assertTrue($flipped->pakai_prefix_perubahan);
        $this->assertFalse($flipped->is_active);

        $preservedFlipped = $this->service->update($flipped, [
            'urutan' => '',
            'pakai_prefix_perubahan' => null,
            'is_active' => null,
        ]);
        $this->assertSame(3, $preservedFlipped->urutan);
        $this->assertTrue($preservedFlipped->pakai_prefix_perubahan);
        $this->assertFalse($preservedFlipped->is_active);
    }

    public function test_toggle_status_flips_is_active_and_logs(): void
    {
        $pos = $this->service->store([
            'nama_pos' => 'Pos Toggle',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $newState = $this->service->toggleStatus($pos);
        $this->assertFalse($newState);
        $this->assertFalse($pos->fresh()->is_active);

        $newState2 = $this->service->toggleStatus($pos->fresh());
        $this->assertTrue($newState2);
        $this->assertTrue($pos->fresh()->is_active);

        $this->assertSame(2, LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'toggle')->count());
    }

    public function test_reorder_renumbers_within_kelompok_bagian_rejects_cross_section_and_logs(): void
    {
        $p1 = $this->service->store(['nama_pos' => 'Pos 1', 'kelompok_bagian' => 'modal_kerja', 'sifat_pos' => 'aset', 'urutan' => 1]);
        $p2 = $this->service->store(['nama_pos' => 'Pos 2', 'kelompok_bagian' => 'modal_kerja', 'sifat_pos' => 'aset', 'urutan' => 2]);
        $p3 = $this->service->store(['nama_pos' => 'Pos 3', 'kelompok_bagian' => 'modal_kerja', 'sifat_pos' => 'aset', 'urutan' => 3]);
        $other = $this->service->store(['nama_pos' => 'Pos Investasi', 'kelompok_bagian' => 'investasi', 'sifat_pos' => 'aset', 'urutan' => 1]);

        $this->service->reorder('modal_kerja', [$p3->id, $p1->id, $p2->id]);

        $this->assertSame(1, $p3->fresh()->urutan);
        $this->assertSame(2, $p1->fresh()->urutan);
        $this->assertSame(3, $p2->fresh()->urutan);

        $log = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'reorder')->first();
        $this->assertNotNull($log);

        $this->expectException(DomainException::class);
        $this->service->reorder('modal_kerja', [$p1->id, $other->id]);
    }

    public function test_delete_cascades_coa_mappings_and_logs(): void
    {
        $coa = Coa::create(['kode' => '1502', 'nama' => 'Aset Lain', 'tipe_coa' => 'Aktiva Lainnya', 'status_aktif' => 1]);
        $pos = $this->service->store([
            'nama_pos' => 'Aset Lain-Lain',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'coa_ids' => [$coa->id],
        ]);

        $posId = $pos->id;
        $this->service->delete($pos);

        $this->assertDatabaseMissing('setting_arus_kas', ['id' => $posId]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $coa->id]);

        $log = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'delete')->first();
        $this->assertNotNull($log);
        $payload = json_decode((string) $log->payload, true);
        $this->assertSame([$coa->id], $payload['old']['coa_ids']);
    }

    public function test_get_leaf_descendants_by_parent_handles_hierarchy_skips_and_cycle_guard(): void
    {
        $root = Coa::create(['kode' => '112', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $sub = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Sub', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $root->id, 'status_aktif' => 1]);

        $eligibleAktif = Coa::create(['kode' => '112.01.01', 'nama' => 'Piutang Umum', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $sub->id, 'status_aktif' => 1]);
        $eligibleNonaktif = Coa::create(['kode' => '112.01.02', 'nama' => 'Piutang Lama', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $sub->id, 'status_aktif' => 0]);
        $kasbankLeaf = Coa::create(['kode' => '112.01.03', 'nama' => 'Kas Kecil Salah Tipe', 'tipe_coa' => 'Kasbank', 'parent_coa' => $sub->id, 'status_aktif' => 1]);
        $excludedLeaf = Coa::create(['kode' => '112.09', 'nama' => 'Cadangan Piutang', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $root->id, 'status_aktif' => 1]);
        $mappedOtherLeaf = Coa::create(['kode' => '112.03', 'nama' => 'Piutang Karyawan', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $root->id, 'status_aktif' => 1]);
        $mappedSelfLeaf = Coa::create(['kode' => '112.04', 'nama' => 'Piutang Sendiri', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $root->id, 'status_aktif' => 1]);
        $wrongGroupLeaf = Coa::create(['kode' => '112.99', 'nama' => 'Beban Piutang', 'tipe_coa' => 'Beban', 'parent_coa' => $root->id, 'status_aktif' => 1]);

        SettingArusKasPengecualian::create(['coa_id' => $excludedLeaf->id, 'alasan' => 'Kontra']);

        $otherPos = SettingArusKas::create([
            'kode_pos' => 'MK_LAIN',
            'nama_pos' => 'Piutang Lain-lain',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'is_active' => false,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $otherPos->id, 'coa_id' => $mappedOtherLeaf->id]);

        $selfPos = SettingArusKas::create([
            'kode_pos' => 'MK_SENDIRI',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $selfPos->id, 'coa_id' => $mappedSelfLeaf->id]);

        $result = $this->service->getLeafDescendantsByParent($root->id, 'aset', $selfPos->id);

        $eligibleIds = collect($result['eligible'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [$eligibleAktif->id, $eligibleNonaktif->id, $mappedSelfLeaf->id],
            $eligibleIds
        );

        $skippedById = collect($result['skipped'])->keyBy('id');
        $this->assertCount(4, $skippedById);
        $this->assertStringContainsString('Kasbank', $skippedById[$kasbankLeaf->id]['reason']);
        $this->assertStringContainsString('pengecualian', $skippedById[$excludedLeaf->id]['reason']);
        $this->assertStringContainsString('Piutang Lain-lain', $skippedById[$mappedOtherLeaf->id]['reason']);
        $this->assertStringContainsString('Laba Rugi', $skippedById[$wrongGroupLeaf->id]['reason']);

        // Leaf COA as parentId should return empty eligible and empty skipped
        $leafResult = $this->service->getLeafDescendantsByParent($eligibleAktif->id, 'aset');
        $this->assertSame(['eligible' => [], 'skipped' => []], $leafResult);

        // Laba Rugi group check rejects inactive Laba Rugi
        $lrParent = Coa::create(['kode' => '706', 'nama' => 'Beban Penyusutan Induk', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $lrAktif = Coa::create(['kode' => '706.01', 'nama' => 'Penyusutan Gedung', 'tipe_coa' => 'Beban', 'parent_coa' => $lrParent->id, 'status_aktif' => 1]);
        $lrNonaktif = Coa::create(['kode' => '706.02', 'nama' => 'Penyusutan Lama', 'tipe_coa' => 'Beban', 'parent_coa' => $lrParent->id, 'status_aktif' => 0]);

        $lrResult = $this->service->getLeafDescendantsByParent($lrParent->id, 'penambah_laba');
        $this->assertSame([$lrAktif->id], collect($lrResult['eligible'])->pluck('id')->all());
        $this->assertSame([$lrNonaktif->id], collect($lrResult['skipped'])->pluck('id')->all());
        $this->assertStringContainsString('nonaktif', $lrResult['skipped'][0]['reason']);

        // Cycle guard: cyclic parent_coa must not infinite-loop
        $cycA = Coa::create(['kode' => '990', 'nama' => 'Cycle A', 'tipe_coa' => 'Aktiva Lainnya', 'status_aktif' => 1]);
        $cycB = Coa::create(['kode' => '991', 'nama' => 'Cycle B', 'tipe_coa' => 'Aktiva Lainnya', 'parent_coa' => $cycA->id, 'status_aktif' => 1]);
        $cycLeaf = Coa::create(['kode' => '991.01', 'nama' => 'Cycle Leaf', 'tipe_coa' => 'Aktiva Lainnya', 'parent_coa' => $cycB->id, 'status_aktif' => 1]);
        $cycA->update(['parent_coa' => $cycB->id]);

        $cycResult = $this->service->getLeafDescendantsByParent($cycA->id, 'aset');
        $this->assertSame([$cycLeaf->id], collect($cycResult['eligible'])->pluck('id')->all());
    }

    public function test_pengecualian_store_and_delete_validates_exclusivity_and_logs(): void
    {
        $akum = Coa::create(['kode' => '142.01', 'nama' => 'Akum Gedung', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $akumNonaktif = Coa::create(['kode' => '143.01', 'nama' => 'Akum Lama', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 0]);
        $beban = Coa::create(['kode' => '706.01', 'nama' => 'Beban Penyusutan', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);
        $mappedCoa = Coa::create(['kode' => '112.01', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);

        $pos = $this->service->store([
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'coa_ids' => [$mappedCoa->id],
        ]);
        $this->assertNotNull($pos->id);

        foreach ([$beban->id, $kasbank->id, $mappedCoa->id] as $badId) {
            try {
                $this->service->storePengecualian([$badId], 'Invalid');
                $this->fail("Expected DomainException for COA ID {$badId}");
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }

        $created = $this->service->storePengecualian([$akum->id, $akumNonaktif->id], 'Akun kontra penyusutan');
        $this->assertCount(2, $created);
        $this->assertDatabaseHas('setting_arus_kas_pengecualian', ['coa_id' => $akum->id, 'alasan' => 'Akun kontra penyusutan']);
        $this->assertDatabaseHas('setting_arus_kas_pengecualian', ['coa_id' => $akumNonaktif->id]);

        $createLog = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'create_pengecualian')->first();
        $this->assertNotNull($createLog);

        // Duplicate pengecualian rejected
        try {
            $this->service->storePengecualian([$akum->id], 'Duplikat');
            $this->fail('Expected DomainException on duplicate pengecualian');
        } catch (DomainException $e) {
            $this->assertStringContainsString('pengecualian', $e->getMessage());
        }

        $firstRecord = $created->firstWhere('coa_id', $akum->id);
        $this->service->deletePengecualian($firstRecord);
        $this->assertDatabaseMissing('setting_arus_kas_pengecualian', ['coa_id' => $akum->id]);

        $deleteLog = LogAktifitas::query()->where('modul', 'Pengaturan Arus Kas')->where('tipe', 'delete_pengecualian')->first();
        $this->assertNotNull($deleteLog);
    }

    public function test_get_unmapped_mutated_neraca_accounts_filters_correctly_and_handles_missing_table(): void
    {
        // Empty bukubesar table should safely return empty collection
        $this->assertCount(0, $this->service->getUnmappedMutatedNeracaAccounts('2026-01-01', '2026-12-31'));

        $mappedAktif = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Terpetakan', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $mappedPosNonaktif = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan Pos Off', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);
        $excluded = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kasbank', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);
        $labaRugi = Coa::create(['kode' => '701.01', 'nama' => 'Beban Gaji', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $unmappedAktif = Coa::create(['kode' => '119.01', 'nama' => 'Uang Muka Baru', 'tipe_coa' => 'Aktiva Lancar lainnya', 'status_aktif' => 1]);
        $unmappedNonaktif = Coa::create(['kode' => '119.02', 'nama' => 'Aset Historis Nonaktif', 'tipe_coa' => 'Aktiva Lainnya', 'status_aktif' => 0]);

        $this->service->store([
            'nama_pos' => 'Piutang Aktif',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
            'coa_ids' => [$mappedAktif->id],
        ]);
        $this->service->store([
            'nama_pos' => 'Persediaan Nonaktif',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'is_active' => false,
            'coa_ids' => [$mappedPosNonaktif->id],
        ]);
        $this->service->storePengecualian([$excluded->id], 'Akun kontra');

        $rows = [
            [$mappedAktif->id, '2026-03-10', 100000, 'D'],
            [$mappedPosNonaktif->id, '2026-03-10', 250000, 'D'],
            [$excluded->id, '2026-03-10', 50000, 'K'],
            [$kasbank->id, '2026-03-10', 500000, 'D'],
            [$labaRugi->id, '2026-03-10', 120000, 'D'],
            [$unmappedAktif->id, '2026-03-15', 300000, 'D'],
            [$unmappedAktif->id, '2026-03-20', 50000, 'K'],
            [$unmappedNonaktif->id, '2026-12-31', 75000, 'K'],
            // Out-of-range transaction should not be counted
            [$unmappedNonaktif->id, '2025-12-31', 999999, 'D'],
        ];

        foreach ($rows as [$coaId, $tgl, $nominal, $tipe]) {
            BukuBesar::create([
                'coa_id' => $coaId,
                'tanggal' => $tgl,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipe,
            ]);
        }

        $unmapped = $this->service->getUnmappedMutatedNeracaAccounts('2026-01-01', '2026-12-31')->keyBy('coa_id');

        $this->assertCount(3, $unmapped);
        $this->assertTrue($unmapped->has($mappedPosNonaktif->id));
        $this->assertTrue($unmapped->has($unmappedAktif->id));
        $this->assertTrue($unmapped->has($unmappedNonaktif->id));
        $this->assertFalse($unmapped->has($mappedAktif->id));
        $this->assertFalse($unmapped->has($excluded->id));
        $this->assertFalse($unmapped->has($kasbank->id));
        $this->assertFalse($unmapped->has($labaRugi->id));

        $this->assertSame(300000.0, $unmapped[$unmappedAktif->id]['debit']);
        $this->assertSame(50000.0, $unmapped[$unmappedAktif->id]['kredit']);
        $this->assertSame(-250000.0, $unmapped[$unmappedAktif->id]['dampak_kas']);
        $this->assertSame(0, $unmapped[$unmappedNonaktif->id]['status_aktif']);
        $this->assertSame(75000.0, $unmapped[$unmappedNonaktif->id]['dampak_kas']);

        // Missing bukubesar table should safely return empty collection
        Schema::dropIfExists('bukubesar');
        $this->assertCount(0, $this->service->getUnmappedMutatedNeracaAccounts('2026-01-01', '2026-12-31'));
    }

    public function test_validate_coa_ids_and_get_coa_options_data(): void
    {
        $parent = Coa::create(['kode' => '112', 'nama' => 'Induk', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $childMapped = Coa::create(['kode' => '112.01', 'nama' => 'Piutang 1', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $parent->id, 'status_aktif' => 1]);
        $childExcluded = Coa::create(['kode' => '112.09', 'nama' => 'Cadangan', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $parent->id, 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas', 'tipe_coa' => 'KASBANK', 'status_aktif' => 1]);
        $bebanNonaktif = Coa::create(['kode' => '706.02', 'nama' => 'Beban Nonaktif', 'tipe_coa' => 'Beban', 'status_aktif' => 0]);
        $neracaValid = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);

        $pos = $this->service->store([
            'nama_pos' => 'Pos Piutang',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => false,
            'coa_ids' => [$childMapped->id],
        ]);
        $this->service->storePengecualian([$childExcluded->id], 'Kontra');

        // Pos validation (keyed by array position)
        $errors = $this->service->validateCoaIds([
            0 => $parent->id,
            1 => $kasbank->id,
            2 => $bebanNonaktif->id,
            3 => $childExcluded->id,
            4 => $childMapped->id,
            5 => $neracaValid->id,
        ], 'aset');

        $this->assertArrayHasKey(0, $errors);
        $this->assertArrayHasKey(1, $errors);
        $this->assertArrayHasKey(2, $errors);
        $this->assertArrayHasKey(3, $errors);
        $this->assertArrayHasKey(4, $errors);
        $this->assertArrayNotHasKey(5, $errors);
        $this->assertStringContainsString('Pos Piutang', $errors[4]);

        // Ignoring holding pos allows keeping its own COA
        $errorsIgnoreSelf = $this->service->validateCoaIds([0 => $childMapped->id], 'aset', $pos->id);
        $this->assertSame([], $errorsIgnoreSelf);

        // Pengecualian mode validation
        $excErrors = $this->service->validateCoaIds([
            0 => $parent->id,
            1 => $kasbank->id,
            2 => $bebanNonaktif->id,
            3 => $childExcluded->id,
            4 => $childMapped->id,
            5 => $neracaValid->id,
        ], 'pengecualian');

        $this->assertArrayHasKey(0, $excErrors);
        $this->assertArrayHasKey(1, $excErrors);
        $this->assertArrayHasKey(2, $excErrors);
        $this->assertArrayHasKey(3, $excErrors);
        $this->assertArrayHasKey(4, $excErrors);
        $this->assertArrayNotHasKey(5, $excErrors);

        $this->assertSame('laba_rugi_aktif', $this->service->getCoaCompatibilityGroup('penambah_laba'));
        $this->assertSame('laba_rugi_aktif', $this->service->getCoaCompatibilityGroup('pengurang_laba'));
        $this->assertSame('neraca_non_kasbank', $this->service->getCoaCompatibilityGroup('aset'));
        $this->assertSame('neraca_non_kasbank', $this->service->getCoaCompatibilityGroup('liabilitas'));
        $this->assertSame('neraca_non_kasbank', $this->service->getCoaCompatibilityGroup('ekuitas'));
        $this->assertSame('neraca_non_kasbank', $this->service->getCoaCompatibilityGroup(null));

        // Options data shape check
        $options = $this->service->getCoaOptionsData();
        $this->assertSame([$parent->id], $options['parentCoas']->pluck('id')->all());
        $this->assertFalse($options['leafCoas']->pluck('id')->contains($parent->id));
        $this->assertFalse($options['leafCoas']->pluck('id')->contains($kasbank->id));
        $this->assertSame([
            'pos_id' => $pos->id,
            'nama_pos' => 'Pos Piutang',
            'is_active' => false,
        ], $options['mappedByPos'][$childMapped->id]);
        $this->assertSame([$childExcluded->id], $options['excludedCoaIds']);
    }
}
