<?php

namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengaturanArusKasCrudTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $module = AccessModule::create([
            'kode' => 'pengaturan.arus-kas',
            'nama' => 'Pengaturan Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 206,
        ]);

        RolePermission::create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);
    }

    private function prepareSchema(): void
    {
        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'bukubesar',
            'coa',
            'role_permissions',
            'access_modules',
            'roles',
            'users',
            'log_aktifitas',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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
            $table->string('kode')->unique();
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
    }

    public function test_index_renders_pos_coa_badges_and_unmapped_mutated_neraca_banner(): void
    {
        $piutang = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Pasien', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $unmappedNeraca = Coa::create(['kode' => '119.01', 'nama' => 'Uang Muka Pembelian', 'tipe_coa' => 'Aset Lancar Lainnya', 'status_aktif' => 1]);
        $kasCoa = Coa::create(['kode' => '111.01', 'nama' => 'Kas Utama', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);

        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $piutang->id]);

        BukuBesar::create([
            'coa_id' => $unmappedNeraca->id,
            'tanggal' => now()->toDateString(),
            'nominal' => 250000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::create([
            'coa_id' => $kasCoa->id,
            'tanggal' => now()->toDateString(),
            'nominal' => 250000,
            'tipe_mutasi' => 'K',
        ]);

        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pengaturan.arus-kas.index');
        $response->assertSee('Metode Tidak Langsung');
        $response->assertSee('Piutang Usaha');
        $response->assertSee('112.01 - Piutang Pasien');
        $response->assertSee('119.01 - Uang Muka Pembelian');
        $response->assertDontSee('111.01 - Kas Utama');
    }

    public function test_index_safely_renders_when_bukubesar_empty_or_table_missing(): void
    {
        $responseEmpty = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertViewIs('pengaturan.arus-kas.index');

        Schema::dropIfExists('bukubesar');

        $responseNoTable = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));
        $responseNoTable->assertStatus(200);
        $responseNoTable->assertViewIs('pengaturan.arus-kas.index');
    }

    public function test_parent_coa_leaves_returns_filtered_eligible_and_skipped_json(): void
    {
        $parent = Coa::create(['kode' => '112', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $leafAvailable = Coa::create(['parent_coa' => $parent->id, 'kode' => '112.01', 'nama' => 'Piutang Rawat Jalan', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $leafMappedHere = Coa::create(['parent_coa' => $parent->id, 'kode' => '112.02', 'nama' => 'Piutang Rawat Inap', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $leafMappedOther = Coa::create(['parent_coa' => $parent->id, 'kode' => '112.03', 'nama' => 'Piutang Lain', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $leafKasbank = Coa::create(['parent_coa' => $parent->id, 'kode' => '112.04', 'nama' => 'Kas Salah Parent', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);

        $currentPos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG_USAHA',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $currentPos->id, 'coa_id' => $leafMappedHere->id]);

        $otherPos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG_LAIN',
            'nama_pos' => 'Piutang Lain-lain',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $otherPos->id, 'coa_id' => $leafMappedOther->id]);

        $response = $this->actingAs($this->admin)->getJson(route('pengaturan.arus-kas.parent-coa-leaves', [
            'parent_id' => $parent->id,
            'sifat_pos' => 'aset',
            'ignore_pos_id' => $currentPos->id,
        ]));

        $response->assertStatus(200);
        $eligibleIds = collect($response->json('eligible'))->pluck('id')->all();
        $skippedIds = collect($response->json('skipped'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$leafAvailable->id, $leafMappedHere->id], $eligibleIds);
        $this->assertEqualsCanonicalizing([$leafMappedOther->id, $leafKasbank->id], $skippedIds);
    }

    public function test_store_persists_pos_and_rejects_kasbank_or_duplicate_coa(): void
    {
        $asetLeaf = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan Obat', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);
        $kasbankLeaf = Coa::create(['kode' => '111.01', 'nama' => 'Kas Tunai', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);

        $response = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.store'), [
            'nama_pos' => '  Persediaan   Farmasi ',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => '1',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => [$asetLeaf->id],
        ]);

        $response->assertRedirect(route('pengaturan.arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('setting_arus_kas', [
            'nama_pos' => 'Persediaan Farmasi',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('setting_arus_kas_coa', ['coa_id' => $asetLeaf->id]);

        // Reject Kasbank
        $kasbankResponse = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.store'), [
            'nama_pos' => 'Pos Kasbank',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'coa_ids' => [$kasbankLeaf->id],
        ]);
        $kasbankResponse->assertSessionHasErrors('coa_ids.0');

        // Reject duplicate COA already mapped to another pos
        $duplicateResponse = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.store'), [
            'nama_pos' => 'Pos Duplikat',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 3,
            'coa_ids' => [$asetLeaf->id],
        ]);
        $duplicateResponse->assertSessionHasErrors('coa_ids.0');
    }

    public function test_update_updates_pos_and_replaces_coa_mappings(): void
    {
        $coa1 = Coa::create(['kode' => '112.01', 'nama' => 'Piutang A', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $coa2 = Coa::create(['kode' => '112.02', 'nama' => 'Piutang B', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);

        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Lama',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa1->id]);

        $response = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.update', $pos), [
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Baru',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => '0',
            'urutan' => 2,
            'is_active' => '1',
            'coa_ids' => [$coa2->id],
        ]);

        $response->assertRedirect(route('pengaturan.arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('setting_arus_kas', [
            'id' => $pos->id,
            'nama_pos' => 'Piutang Baru',
            'pakai_prefix_perubahan' => false,
            'urutan' => 2,
        ]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa1->id]);
        $this->assertDatabaseHas('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa2->id]);
    }

    public function test_toggle_status_and_destroy_pos(): void
    {
        $coa = Coa::create(['kode' => '216.01', 'nama' => 'Hutang Supplier', 'tipe_coa' => 'Hutang Usaha', 'status_aktif' => 1]);
        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_HUTANG',
            'nama_pos' => 'Hutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'liabilitas',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa->id]);

        $toggleRes = $this->actingAs($this->admin)->patch(route('pengaturan.arus-kas.toggle', $pos));
        $toggleRes->assertRedirect();
        $toggleRes->assertSessionHas('success');
        $this->assertFalse($pos->fresh()->is_active);

        $deleteRes = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.destroy', $pos));
        $deleteRes->assertRedirect(route('pengaturan.arus-kas.index'));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('setting_arus_kas', ['id' => $pos->id]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id]);
    }

    public function test_reorder_works_for_both_json_and_http_and_rejects_cross_section_ids(): void
    {
        $pos1 = SettingArusKas::create(['kode_pos' => 'INV_1', 'nama_pos' => 'Investasi 1', 'kelompok_bagian' => 'investasi', 'sifat_pos' => 'aset', 'urutan' => 1]);
        $pos2 = SettingArusKas::create(['kode_pos' => 'INV_2', 'nama_pos' => 'Investasi 2', 'kelompok_bagian' => 'investasi', 'sifat_pos' => 'aset', 'urutan' => 2]);
        $other = SettingArusKas::create(['kode_pos' => 'PEND_1', 'nama_pos' => 'Pendanaan 1', 'kelompok_bagian' => 'pendanaan', 'sifat_pos' => 'ekuitas', 'urutan' => 1]);

        $jsonRes = $this->actingAs($this->admin)->patchJson(route('pengaturan.arus-kas.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos2->id, $pos1->id],
        ]);
        $jsonRes->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(1, $pos2->fresh()->urutan);
        $this->assertSame(2, $pos1->fresh()->urutan);

        $httpRes = $this->actingAs($this->admin)->patch(route('pengaturan.arus-kas.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos1->id, $pos2->id],
        ]);
        $httpRes->assertRedirect();
        $httpRes->assertSessionHas('success');
        $this->assertSame(1, $pos1->fresh()->urutan);
        $this->assertSame(2, $pos2->fresh()->urutan);

        $invalidRes = $this->actingAs($this->admin)->patchJson(route('pengaturan.arus-kas.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos1->id, $other->id],
        ]);
        $invalidRes->assertStatus(422);
    }

    public function test_store_and_destroy_pengecualian(): void
    {
        $akum = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);

        $invalidRes = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.pengecualian.store'), [
            'coa_ids' => [$kasbank->id],
            'alasan' => 'Invalid',
        ]);
        $invalidRes->assertSessionHasErrors('coa_ids.0');

        $storeRes = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.pengecualian.store'), [
            'coa_ids' => [$akum->id],
            'alasan' => 'Akun kontra penyusutan',
        ]);
        $storeRes->assertRedirect(route('pengaturan.arus-kas.index'));
        $storeRes->assertSessionHas('success');
        $this->assertDatabaseHas('setting_arus_kas_pengecualian', [
            'coa_id' => $akum->id,
            'alasan' => 'Akun kontra penyusutan',
        ]);

        $pengecualian = SettingArusKasPengecualian::where('coa_id', $akum->id)->firstOrFail();

        $deleteRes = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.pengecualian.destroy', $pengecualian));
        $deleteRes->assertRedirect(route('pengaturan.arus-kas.index'));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('setting_arus_kas_pengecualian', ['id' => $pengecualian->id]);
    }

    public function test_index_filters_by_tab_and_search_and_renders_struktur_sections(): void
    {
        $posOp = SettingArusKas::create([
            'kode_pos' => 'PL_PENYUSUTAN',
            'nama_pos' => 'Penyusutan Aset Tetap',
            'kelompok_bagian' => 'penyesuaian_laba',
            'sifat_pos' => 'penambah_laba',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $posInv = SettingArusKas::create([
            'kode_pos' => 'INV_ASET',
            'nama_pos' => 'Pembelian Aset Tetap',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);

        // Without referensi-kelompok-arus-kas permission, shortcut link is omitted
        $resNoShortcut = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));
        $resNoShortcut->assertStatus(200);
        $resNoShortcut->assertSee('Mode Tab');
        $resNoShortcut->assertSee('Mode Struktur Laporan');
        $resNoShortcut->assertSee('data-section-bagian="penyesuaian_laba"', false);
        $resNoShortcut->assertSee('data-section-bagian="modal_kerja"', false);
        $resNoShortcut->assertSee('data-section-bagian="investasi"', false);
        $resNoShortcut->assertSee('data-section-bagian="pendanaan"', false);
        $resNoShortcut->assertDontSee('Referensi Kelompok Arus Kas (Langsung)');

        // Grant referensi-kelompok-arus-kas view permission and verify shortcut appears
        $refModule = AccessModule::create([
            'kode' => 'pengaturan.referensi-kelompok-arus-kas',
            'nama' => 'Referensi Kelompok Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 205,
        ]);
        RolePermission::create([
            'role_id' => $this->admin->role_id,
            'access_module_id' => $refModule->id,
            'can_view' => true,
        ]);
        $this->admin->unsetRelation('role');

        $resWithShortcut = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'tab' => 'investasi',
            'search' => 'Pembelian',
        ]));
        $resWithShortcut->assertStatus(200);
        $resWithShortcut->assertSee('Referensi Kelompok Arus Kas (Langsung)');
        $resWithShortcut->assertSee($posInv->nama_pos);
        $resWithShortcut->assertViewHas('activeTab', 'investasi');
        $resWithShortcut->assertViewHas('search', 'Pembelian');
        $this->assertSame(1, $resWithShortcut->viewData('tabCounts')['penyesuaian_laba']);
        $this->assertSame(1, $resWithShortcut->viewData('tabCounts')['investasi']);
        $this->assertSame(2, $resWithShortcut->viewData('tabCounts')['semua']);
        $this->assertNotNull($posOp);
    }

    public function test_index_renders_dual_mode_switcher_modals_and_coa_compatibility_controls(): void
    {
        $parent = Coa::create(['kode' => '112', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $mappedCoa = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '112.01',
            'nama' => 'Piutang Pasien',
            'tipe_coa' => 'Piutang Usaha',
            'status_aktif' => 1,
        ]);
        $inactiveNeraca = Coa::create([
            'parent_coa' => $parent->id,
            'kode' => '112.02',
            'nama' => 'Piutang Lama',
            'tipe_coa' => 'Piutang Usaha',
            'status_aktif' => 0,
        ]);
        $excludedCoa = Coa::create([
            'kode' => '142.01',
            'nama' => 'Akumulasi Penyusutan',
            'tipe_coa' => 'Aset Tetap',
            'status_aktif' => 1,
        ]);
        $bebanCoa = Coa::create([
            'kode' => '511.01',
            'nama' => 'Beban Penyusutan',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
        ]);

        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $mappedCoa->id]);
        SettingArusKasPengecualian::create(['coa_id' => $excludedCoa->id, 'alasan' => 'Akun kontra']);

        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));

        $response->assertStatus(200);

        // Dual-mode switcher & localStorage contract
        $response->assertSee('data-view-mode="tab"', false);
        $response->assertSee('data-view-mode="struktur"', false);
        $response->assertSee('siakurat.pengaturan_arus_kas.view_mode', false);

        // Modal Pos Arus Kas & fields
        $response->assertSee('id="modalPosArusKas"', false);
        $response->assertSee('name="kelompok_bagian"', false);
        $response->assertSee('name="sifat_pos"', false);
        $response->assertSee('name="nama_pos"', false);
        $response->assertSee('name="kode_pos"', false);
        $response->assertSee('name="urutan"', false);
        $response->assertSee('name="pakai_prefix_perubahan"', false);
        $response->assertSee('name="is_active"', false);
        $response->assertSee('name="coa_ids[]"', false);
        $response->assertSee('id="coaIdsSelect"', false);

        // Parent COA quick-select & AJAX endpoints
        $response->assertSee('id="parentCoaSelect"', false);
        $response->assertSee('id="btnAddParentLeaves"', false);
        $response->assertSee('id="parentLeavesFeedback"', false);
        $response->assertSee(route('pengaturan.arus-kas.parent-coa-leaves'), false);
        $response->assertSee(route('pengaturan.arus-kas.reorder'), false);

        // Modal Pengecualian Arus Kas & controls
        $response->assertSee('id="modalPengecualianArusKas"', false);
        $response->assertSee('id="pengecualianParentCoaSelect"', false);
        $response->assertSee('id="btnAddPengecualianParentLeaves"', false);
        $response->assertSee('id="pengecualianCoaIdsSelect"', false);
        $response->assertSee('name="alasan"', false);

        // Serialized COA metadata & availability/status markers
        $response->assertSee('[Nonaktif]', false);
        $response->assertSee('(Dipakai: Piutang Usaha)', false);
        $response->assertSee('(Dikecualikan)', false);
        $response->assertSee($inactiveNeraca->kode, false);
        $response->assertSee($bebanCoa->kode, false);
    }

    public function test_validation_error_with_modal_target_reopens_intended_modal_with_old_input(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index'))
            ->followingRedirects()
            ->post(route('pengaturan.arus-kas.store'), [
                '_modal_target' => 'modalPosArusKas',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'nama_pos' => '',
                'kode_pos' => 'MK_TEST_ERR',
                'urutan' => 3,
            ]);

        $response->assertStatus(200);
        $response->assertSee('value="MK_TEST_ERR"', false);
        $response->assertSee('"hasErrors":true', false);
        $response->assertSee('"target":"modalPosArusKas"', false);

        // Clearing coa_ids (empty string -> null via ConvertEmptyStringsToNull) clears mappings on update
        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_CLEAR',
            'nama_pos' => 'Pos Clear',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $coa = Coa::create(['kode' => '114.01', 'nama' => 'Biaya Dibayar Dimuka', 'tipe_coa' => 'Aset Lancar Lainnya', 'status_aktif' => 1]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa->id]);

        $clearResponse = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.update', $pos), [
            'kode_pos' => 'MK_CLEAR',
            'nama_pos' => 'Pos Clear',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => '',
        ]);
        $clearResponse->assertRedirect(route('pengaturan.arus-kas.index'));
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa->id]);

        // Validation error on update reopens modal in edit mode
        $editErrResponse = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index'))
            ->followingRedirects()
            ->put(route('pengaturan.arus-kas.update', $pos), [
                '_modal_target' => 'modalPosArusKas',
                '_pos_id' => $pos->id,
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'nama_pos' => '',
                'kode_pos' => 'MK_CLEAR',
                'urutan' => 1,
            ]);
        $editErrResponse->assertStatus(200);
        $editErrResponse->assertSee('"posId":'.$pos->id, false);
        $editErrResponse->assertSee('Perbarui Pos Arus Kas', false);
    }
}
