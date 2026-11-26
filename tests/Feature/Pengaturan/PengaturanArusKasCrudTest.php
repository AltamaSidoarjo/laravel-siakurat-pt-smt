<?php

namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
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
            'referensi_kelompok_arus_kas',
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

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table): void {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
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

    public function test_default_index_renders_langsung_header_card_panduan_and_contextual_report_link(): void
    {
        $ref1 = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Pasien',
            'status_aktif' => true,
        ]);
        Coa::create([
            'kode' => '1.1.01',
            'nama' => 'Kas Penerimaan',
            'tipe_coa' => 'Kas & Bank',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Pasien',
            'status_aktif' => 1,
        ]);

        // Tanpa izin modul laporan.keuangan
        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pengaturan.arus-kas.index');
        $this->assertSame('langsung', $response->viewData('activeMetode'));

        // Header Card, Bantuan & Panduan
        $response->assertSee('Pengaturan Arus Kas');
        $response->assertSee('Bantuan & Panduan');
        $response->assertSee('id="btnStartTour"', false);
        $response->assertSee('id="modalPanduanArusKas"', false);
        $response->assertSee('Panduan & Glosarium belum tersedia.');

        // 2 Tab Utama
        $response->assertSee('Pengaturan Arus Kas Metode Langsung');
        $response->assertSee('Pengaturan Arus Kas Metode Tidak Langsung');

        // Tidak ada strip 4 kartu ringkasan
        $response->assertDontSee('Total Saldo Kas & Bank');
        $response->assertDontSee('Kategori Aktif');

        // Unmapped banner tidak muncul pada tab Langsung
        $response->assertDontSee('Akun Neraca Belum Terpetakan');

        // Dropdown aktivitas dan data tabel
        $response->assertSee('id="selectAktivitasLangsung"', false);
        $response->assertSee('Penerimaan Pasien');
        $response->assertSee('1 Akun');

        // Tombol laporan tidak muncul saat belum ada izin laporan.keuangan
        $response->assertDontSee(route('laporan.keuangan.arus-kas'));

        // Beri akses modul laporan.keuangan
        $laporanModule = AccessModule::create([
            'kode' => 'laporan.keuangan',
            'nama' => 'Laporan Keuangan',
            'group_nama' => 'Laporan',
            'urutan' => 100,
        ]);
        RolePermission::create([
            'role_id' => $this->admin->role_id,
            'access_module_id' => $laporanModule->id,
            'can_view' => true,
        ]);
        $this->admin->unsetRelation('role');

        // Pada metode langsung, muncul link laporan arus kas langsung
        $resWithLaporanLangsung = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $resWithLaporanLangsung->assertSee(route('laporan.keuangan.arus-kas'));
        $resWithLaporanLangsung->assertDontSee(route('laporan.keuangan.arus-kas-tidak-langsung'));

        // Pada metode tidak langsung, muncul link laporan arus kas tidak langsung
        $resWithLaporanTidakLangsung = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $resWithLaporanTidakLangsung->assertSee(route('laporan.keuangan.arus-kas-tidak-langsung'));
        $resWithLaporanTidakLangsung->assertDontSee('href="'.route('laporan.keuangan.arus-kas').'"', false);
    }

    public function test_langsung_filters_by_aktivitas_dropdown_and_search(): void
    {
        ReferensiKelompokArusKas::create(['aktivitas' => 'operasi', 'nama' => 'Penerimaan Pasien', 'status_aktif' => true]);
        ReferensiKelompokArusKas::create(['aktivitas' => 'investasi', 'nama' => 'Pembelian Mesin', 'status_aktif' => true]);
        ReferensiKelompokArusKas::create(['aktivitas' => 'operasi', 'nama' => 'Beban Operasional', 'status_aktif' => true]);

        // Filter by aktivitas
        $responseAktivitas = $this->actingAs($this->admin)
            ->get(route('pengaturan.arus-kas.index', ['metode' => 'langsung', 'aktivitas' => 'operasi']));
        $responseAktivitas->assertSee('Penerimaan Pasien');
        $responseAktivitas->assertSee('Beban Operasional');
        $responseAktivitas->assertDontSee('Pembelian Mesin');

        // Filter by search
        $responseSearch = $this->actingAs($this->admin)
            ->get(route('pengaturan.arus-kas.index', ['metode' => 'langsung', 'aktivitas' => 'operasi', 'search' => 'Operasional']));
        $responseSearch->assertSee('Beban Operasional');
        $responseSearch->assertDontSee('Penerimaan Pasien');

        // Reset link mempertahankan metode=langsung dan aktivitas=operasi
        $responseSearch->assertSee(route('pengaturan.arus-kas.index', ['metode' => 'langsung', 'aktivitas' => 'operasi']));
    }

    public function test_langsung_store_update_cascade_restricted_delete_and_toggle(): void
    {
        // 1. Store dengan sanitasi spasi
        $storeResponse = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.langsung.store'), [
            'aktivitas' => 'operasi',
            'nama' => '  Penerimaan   Klaim BPJS  ',
            'status_aktif' => '1',
        ]);
        $storeResponse->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $storeResponse->assertSessionHas('success');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Klaim BPJS',
            'status_aktif' => true,
        ]);

        // 2. Store status_aktif = 0
        $storeInactive = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.langsung.store'), [
            'aktivitas' => 'investasi',
            'nama' => 'Peralatan Nonaktif Baru',
            'status_aktif' => '0',
        ]);
        $storeInactive->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'nama' => 'Peralatan Nonaktif Baru',
            'status_aktif' => false,
        ]);

        // 3. Update dengan cascade ke COA
        $ref = ReferensiKelompokArusKas::where('nama', 'Penerimaan Klaim BPJS')->firstOrFail();
        $coa = Coa::create([
            'kode' => '112.99',
            'nama' => 'Piutang Klaim',
            'tipe_coa' => 'Piutang Usaha',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Klaim BPJS',
            'status_aktif' => 1,
        ]);

        $updateResponse = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.langsung.update', $ref), [
            'nama' => 'Klaim BPJS Kesehatan',
            'status_aktif' => '0',
        ]);
        $updateResponse->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $updateResponse->assertSessionHas('success');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'id' => $ref->id,
            'nama' => 'Klaim BPJS Kesehatan',
            'status_aktif' => false,
        ]);
        $this->assertEquals('Klaim BPJS Kesehatan', $coa->fresh()->arus_kas_kelompok);

        // 4. Restricted delete saat sedang dipakai COA
        $deleteBlocked = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.langsung.destroy', $ref));
        $deleteBlocked->assertSessionHas('error');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', ['id' => $ref->id]);

        // 5. Delete sukses saat bebas dari COA
        $coa->update(['arus_kas_kelompok' => null]);
        $deleteSuccess = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.langsung.destroy', $ref));
        $deleteSuccess->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $deleteSuccess->assertSessionHas('success');
        $this->assertDatabaseMissing('referensi_kelompok_arus_kas', ['id' => $ref->id]);

        // 6. Toggle status
        $refInactive = ReferensiKelompokArusKas::where('nama', 'Peralatan Nonaktif Baru')->firstOrFail();
        $toggleRes = $this->actingAs($this->admin)->patch(route('pengaturan.arus-kas.langsung.toggle', $refInactive));
        $toggleRes->assertSessionHas('success');
        $this->assertTrue($refInactive->fresh()->status_aktif);
    }

    public function test_index_falls_back_to_safe_defaults_for_invalid_query_parameters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'ngawur',
            'aktivitas' => 'invalid',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('pengaturan.arus-kas.index');
        $this->assertSame('langsung', $response->viewData('activeMetode'));
        $this->assertSame('semua', $response->viewData('aktivitas'));
    }

    public function test_validation_errors_reopen_intended_modal_for_both_langsung_and_tidak_langsung(): void
    {
        // 1. Invalid store langsung -> #modalTambahReferensi
        $resStoreLangsung = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'langsung']))
            ->followingRedirects()
            ->post(route('pengaturan.arus-kas.langsung.store'), [
                '_modal_target' => 'modalTambahReferensi',
                'aktivitas' => '',
                'nama' => '',
            ]);
        $resStoreLangsung->assertStatus(200);
        $resStoreLangsung->assertSee('id="modalTambahReferensi"', false);
        $resStoreLangsung->assertSee('id="modalTambahReferensiValidationAlert"', false);

        // 2. Invalid update langsung -> #modalEditReferensi
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Operasional Awal',
            'status_aktif' => true,
        ]);
        $resUpdateLangsung = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'langsung']))
            ->followingRedirects()
            ->put(route('pengaturan.arus-kas.langsung.update', $ref), [
                '_modal_target' => 'modalEditReferensi',
                '_referensi_id' => $ref->id,
                '_referensi_aktivitas' => $ref->aktivitas,
                'nama' => '',
                'status_aktif' => '1',
            ]);
        $resUpdateLangsung->assertStatus(200);
        $resUpdateLangsung->assertSee('id="modalEditReferensi"', false);
        $resUpdateLangsung->assertSee('id="modalEditReferensiValidationAlert"', false);
        $resUpdateLangsung->assertSee(route('pengaturan.arus-kas.langsung.update', $ref), false);

        // 3. Invalid store tidak langsung -> #modalPosArusKas
        $resStoreTidakLangsung = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']))
            ->followingRedirects()
            ->post(route('pengaturan.arus-kas.tidak-langsung.store'), [
                '_modal_target' => 'modalPosArusKas',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'nama_pos' => '',
                'kode_pos' => 'MK_ERR',
                'urutan' => 1,
            ]);
        $resStoreTidakLangsung->assertStatus(200);
        $resStoreTidakLangsung->assertSee('id="modalPosArusKas"', false);
        $resStoreTidakLangsung->assertSee('id="modalPosValidationAlert"', false);

        // 4. Invalid store pengecualian -> #modalPengecualianArusKas
        $resStorePengecualian = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']))
            ->followingRedirects()
            ->post(route('pengaturan.arus-kas.tidak-langsung.pengecualian.store'), [
                '_modal_target' => 'modalPengecualianArusKas',
                'coa_ids' => [],
                'alasan' => '',
            ]);
        $resStorePengecualian->assertStatus(200);
        $resStorePengecualian->assertSee('id="modalPengecualianArusKas"', false);
        $resStorePengecualian->assertSee('id="modalPengecualianValidationAlert"', false);
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

        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));

        $response->assertStatus(200);
        $response->assertViewIs('pengaturan.arus-kas.index');
        $response->assertSee('Pengaturan Arus Kas Metode Tidak Langsung');
        $response->assertSee('Piutang Usaha');
        $response->assertSee('112.01 - Piutang Pasien');
        $response->assertSee('119.01 - Uang Muka Pembelian');
        $response->assertDontSee('111.01 - Kas Utama');
    }

    public function test_index_safely_renders_when_bukubesar_empty_or_table_missing(): void
    {
        $responseEmpty = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertViewIs('pengaturan.arus-kas.index');

        Schema::dropIfExists('bukubesar');

        $responseNoTable = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
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

        $response = $this->actingAs($this->admin)->getJson(route('pengaturan.arus-kas.tidak-langsung.parent-coa-leaves', [
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

        $response = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.tidak-langsung.store'), [
            'nama_pos' => '  Persediaan   Farmasi ',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => '1',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => [$asetLeaf->id],
        ]);

        $response->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
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
        $kasbankResponse = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.tidak-langsung.store'), [
            'nama_pos' => 'Pos Kasbank',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'coa_ids' => [$kasbankLeaf->id],
        ]);
        $kasbankResponse->assertSessionHasErrors('coa_ids.0');

        // Reject duplicate COA already mapped to another pos
        $duplicateResponse = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.tidak-langsung.store'), [
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

        $response = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.tidak-langsung.update', $pos), [
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Baru',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => '0',
            'urutan' => 2,
            'is_active' => '1',
            'coa_ids' => [$coa2->id],
        ]);

        $response->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
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

        $toggleRes = $this->actingAs($this->admin)->patch(route('pengaturan.arus-kas.tidak-langsung.toggle', $pos));
        $toggleRes->assertRedirect();
        $toggleRes->assertSessionHas('success');
        $this->assertFalse($pos->fresh()->is_active);

        $deleteRes = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.tidak-langsung.destroy', $pos));
        $deleteRes->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('setting_arus_kas', ['id' => $pos->id]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id]);
    }

    public function test_reorder_works_for_both_json_and_http_and_rejects_cross_section_ids(): void
    {
        $pos1 = SettingArusKas::create(['kode_pos' => 'INV_1', 'nama_pos' => 'Investasi 1', 'kelompok_bagian' => 'investasi', 'sifat_pos' => 'aset', 'urutan' => 1]);
        $pos2 = SettingArusKas::create(['kode_pos' => 'INV_2', 'nama_pos' => 'Investasi 2', 'kelompok_bagian' => 'investasi', 'sifat_pos' => 'aset', 'urutan' => 2]);
        $other = SettingArusKas::create(['kode_pos' => 'PEND_1', 'nama_pos' => 'Pendanaan 1', 'kelompok_bagian' => 'pendanaan', 'sifat_pos' => 'ekuitas', 'urutan' => 1]);

        $jsonRes = $this->actingAs($this->admin)->patchJson(route('pengaturan.arus-kas.tidak-langsung.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos2->id, $pos1->id],
        ]);
        $jsonRes->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(1, $pos2->fresh()->urutan);
        $this->assertSame(2, $pos1->fresh()->urutan);

        $httpRes = $this->actingAs($this->admin)->patch(route('pengaturan.arus-kas.tidak-langsung.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos1->id, $pos2->id],
        ]);
        $httpRes->assertRedirect();
        $httpRes->assertSessionHas('success');
        $this->assertSame(1, $pos1->fresh()->urutan);
        $this->assertSame(2, $pos2->fresh()->urutan);

        $invalidRes = $this->actingAs($this->admin)->patchJson(route('pengaturan.arus-kas.tidak-langsung.reorder'), [
            'kelompok_bagian' => 'investasi',
            'ordered_ids' => [$pos1->id, $other->id],
        ]);
        $invalidRes->assertStatus(422);
    }

    public function test_store_and_destroy_pengecualian(): void
    {
        $akum = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);

        $invalidRes = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.tidak-langsung.pengecualian.store'), [
            'coa_ids' => [$kasbank->id],
            'alasan' => 'Invalid',
        ]);
        $invalidRes->assertSessionHasErrors('coa_ids.0');

        $storeRes = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.tidak-langsung.pengecualian.store'), [
            'coa_ids' => [$akum->id],
            'alasan' => 'Akun kontra penyusutan',
        ]);
        $storeRes->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung', 'bagian' => 'pengecualian']));
        $storeRes->assertSessionHas('success');
        $this->assertDatabaseHas('setting_arus_kas_pengecualian', [
            'coa_id' => $akum->id,
            'alasan' => 'Akun kontra penyusutan',
        ]);

        $pengecualian = SettingArusKasPengecualian::where('coa_id', $akum->id)->firstOrFail();

        $deleteRes = $this->actingAs($this->admin)->delete(route('pengaturan.arus-kas.tidak-langsung.pengecualian.destroy', $pengecualian));
        $deleteRes->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung', 'bagian' => 'pengecualian']));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('setting_arus_kas_pengecualian', ['id' => $pengecualian->id]);
    }

    public function test_tidak_langsung_uses_bagian_dropdown_and_tab_mode_switcher_without_nav_tabs(): void
    {
        SettingArusKas::create([
            'kode_pos' => 'PL_PENYUSUTAN',
            'nama_pos' => 'Penyusutan Aset Tetap',
            'kelompok_bagian' => 'penyesuaian_laba',
            'sifat_pos' => 'penambah_laba',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKas::create([
            'kode_pos' => 'INV_ASET',
            'nama_pos' => 'Pembelian Aset Tetap',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'bagian' => 'investasi',
            'search' => 'Pembelian',
        ]));

        $response->assertStatus(200);
        $this->assertSame('investasi', $response->viewData('activeBagian'));

        // Tidak ada inner nav-tabs lama #arusKasTab
        $response->assertDontSee('id="arusKasTab"', false);

        // Dropdown bagian beserta 6 opsi ber-counter
        $response->assertSee('id="filterToolbarTidakLangsung"', false);
        $response->assertSee('id="formFilterTidakLangsung"', false);
        $response->assertSee('<select name="bagian"', false);
        $response->assertSee('id="selectBagianTidakLangsung"', false);
        $response->assertSee('Semua Pos (');
        $response->assertSee('Penyesuaian Laba (');
        $response->assertSee('Modal Kerja Operasi (');
        $response->assertSee('Aktivitas Investasi (');
        $response->assertSee('Aktivitas Pendanaan (');
        $response->assertSee('Akun Dikecualikan (');

        // Top control bar & mode switcher dirender pada metode tidak langsung
        $response->assertSee('id="tidakLangsungControlBar"', false);
        $response->assertSee('id="subBarModeTab"', false);
        $response->assertSee('id="subBarModeStruktur"', false);
        $response->assertSee('id="btnModeTab"', false);
        $response->assertSee('id="btnModeStruktur"', false);
        $response->assertSee('id="btnExpandAllSections"', false);
        $response->assertSee('id="btnCollapseAllSections"', false);

        // Link reset pencarian mempertahankan metode & bagian
        $response->assertSee('id="btnResetSearchTidakLangsung"', false);
        $response->assertSee(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung', 'bagian' => 'investasi']));

        // JS memeriksa prioritas parameter bagian || search
        $response->assertSee("urlParams.has('bagian') || urlParams.has('search')", false);

        // Switcher & kontrol struktur TIDAK dirender pada metode langsung
        $langsungResponse = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'langsung',
        ]));
        $langsungResponse->assertStatus(200);
        $langsungResponse->assertDontSee('id="btnModeTab"', false);
        $langsungResponse->assertDontSee('id="btnModeStruktur"', false);
        $langsungResponse->assertDontSee('id="btnExpandAllSections"', false);
        $langsungResponse->assertDontSee('id="btnCollapseAllSections"', false);
    }

    public function test_tidak_langsung_bagian_dropdown_and_invalid_bagian_fallback(): void
    {
        $coa = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        SettingArusKasPengecualian::create([
            'coa_id' => $coa->id,
            'alasan' => 'Akun kontra penyusutan',
        ]);

        $pengecualianRes = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'bagian' => 'pengecualian',
        ]));

        $pengecualianRes->assertStatus(200);
        $this->assertSame('pengecualian', $pengecualianRes->viewData('activeBagian'));

        $html = $pengecualianRes->getContent();
        $tabStart = strpos($html, 'id="viewModeTab"');
        $strukturStart = strpos($html, 'id="viewModeStruktur"');
        $this->assertNotFalse($tabStart);
        $this->assertNotFalse($strukturStart);
        $viewModeTabHtml = substr($html, $tabStart, $strukturStart - $tabStart);

        $this->assertStringContainsString('Tambah Pengecualian', $viewModeTabHtml);
        $this->assertStringContainsString('Alasan Pengecualian', $viewModeTabHtml);
        $this->assertStringContainsString('142.01 - Akumulasi Penyusutan', $viewModeTabHtml);
        $this->assertStringNotContainsString('Tambah Pos', $viewModeTabHtml);

        $fallbackRes = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'bagian' => 'ngawur',
        ]));

        $fallbackRes->assertStatus(200);
        $this->assertSame('semua', $fallbackRes->viewData('activeBagian'));
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

        $res = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'tab' => 'investasi',
            'search' => 'Pembelian',
        ]));

        $res->assertStatus(200);
        $res->assertSee('Mode Tab');
        $res->assertSee('Mode Struktur Laporan');
        $res->assertSee('data-section-bagian="penyesuaian_laba"', false);
        $res->assertSee('data-section-bagian="modal_kerja"', false);
        $res->assertSee('data-section-bagian="investasi"', false);
        $res->assertSee('data-section-bagian="pendanaan"', false);

        // Memastikan 2 tab utama ada dan tidak ada shortcut lama
        $res->assertSee('Pengaturan Arus Kas Metode Langsung');
        $res->assertSee('Pengaturan Arus Kas Metode Tidak Langsung');
        $res->assertDontSee('Referensi Kelompok Arus Kas (Langsung)');

        $res->assertSee($posInv->nama_pos);
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

        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));

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
        $response->assertSee(route('pengaturan.arus-kas.tidak-langsung.parent-coa-leaves'), false);
        $response->assertSee(route('pengaturan.arus-kas.tidak-langsung.reorder'), false);

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
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']))
            ->followingRedirects()
            ->post(route('pengaturan.arus-kas.tidak-langsung.store'), [
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

        // Clearing coa_ids clears mappings on update
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

        $clearResponse = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.tidak-langsung.update', $pos), [
            'kode_pos' => 'MK_CLEAR',
            'nama_pos' => 'Pos Clear',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => '',
        ]);
        $clearResponse->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa->id]);

        // Validation error on update reopens modal in edit mode
        $editErrResponse = $this->actingAs($this->admin)
            ->from(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']))
            ->followingRedirects()
            ->put(route('pengaturan.arus-kas.tidak-langsung.update', $pos), [
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

    public function test_modal_pos_renders_coa_ids_hidden_sentinel_and_clearing_all_coas_unmaps_them(): void
    {
        $coa1 = Coa::create(['kode' => '112.11', 'nama' => 'Piutang Rawat Inap', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $coa2 = Coa::create(['kode' => '112.12', 'nama' => 'Piutang Rawat Jalan', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);

        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_SENTINEL_TEST',
            'nama_pos' => 'Piutang Sentinel',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa1->id]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $coa2->id]);

        $indexResponse = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $indexResponse->assertStatus(200);

        $html = $indexResponse->getContent();
        $modalPosPos = strpos($html, 'id="modalPosArusKas"');
        $modalPengecualianPos = strpos($html, 'id="modalPengecualianArusKas"');
        $this->assertNotFalse($modalPosPos);
        $this->assertNotFalse($modalPengecualianPos);

        $modalPosHtml = substr($html, $modalPosPos, $modalPengecualianPos - $modalPosPos);
        $this->assertMatchesRegularExpression(
            '/<input\s+type="hidden"\s+name="coa_ids"\s+value=""\s*>\s*<select\s+name="coa_ids\[\]"[^>]*id="coaIdsSelect"/s',
            $modalPosHtml
        );
        $this->assertStringContainsString('id="posKodePos"', $modalPosHtml);
        $this->assertStringContainsString('maxlength="80"', $modalPosHtml);
        $this->assertStringContainsString('id="coaCounterBadge"', $modalPosHtml);
        $this->assertStringContainsString('id="filterCoaOptionsInput"', $modalPosHtml);

        $modalDetailPos = strpos($html, 'id="modalDetailCoaPos"');
        $this->assertNotFalse($modalDetailPos);
        $modalPengecualianHtml = substr($html, $modalPengecualianPos, $modalDetailPos - $modalPengecualianPos);
        $this->assertMatchesRegularExpression(
            '/<input\s+type="hidden"\s+name="coa_ids"\s+value=""\s*>\s*<select\s+name="coa_ids\[\]"[^>]*id="pengecualianCoaIdsSelect"/s',
            $modalPengecualianHtml
        );
        $this->assertStringContainsString('id="pengecualianParentLeavesFeedback"', $modalPengecualianHtml);
        $this->assertStringContainsString('id="filterPengecualianCoaInput"', $modalPengecualianHtml);

        // Simulate browser PUT when all options in #coaIdsSelect are deselected (hidden sentinel submits coa_ids="")
        $clearResponse = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.tidak-langsung.update', $pos), [
            'kode_pos' => 'MK_SENTINEL_TEST',
            'nama_pos' => 'Piutang Sentinel Dikosongkan',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => '',
        ]);

        $clearResponse->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $clearResponse->assertSessionHas('success');
        $this->assertSame('Piutang Sentinel Dikosongkan', $pos->fresh()->nama_pos);
        $this->assertCount(0, $pos->fresh()->coas);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['setting_arus_kas_id' => $pos->id]);

        // Re-mapping after clearing also succeeds
        $remapResponse = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.tidak-langsung.update', $pos), [
            'kode_pos' => 'MK_SENTINEL_TEST',
            'nama_pos' => 'Piutang Sentinel Dikosongkan',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => '1',
            'coa_ids' => [$coa1->id],
        ]);
        $remapResponse->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
        $this->assertSame([$coa1->id], $pos->fresh()->coas->pluck('id')->all());
    }

    public function test_pengecualian_empty_state_copy_distinguishes_default_vs_search(): void
    {
        $emptyDefaultRes = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'bagian' => 'pengecualian',
        ]));
        $emptyDefaultRes->assertStatus(200);
        $emptyDefaultRes->assertSee('Belum ada akun yang dikecualikan.');

        $emptySearchRes = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'tidak_langsung',
            'bagian' => 'pengecualian',
            'search' => 'TidakMungkinAda',
        ]));
        $emptySearchRes->assertStatus(200);
        $emptySearchRes->assertSee('Tidak ada akun dikecualikan yang cocok dengan pencarian.');
    }

    public function test_langsung_endpoints_view_data_and_coa_search(): void
    {
        $refOperasi = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Rawat Inap',
            'status_aktif' => true,
        ]);

        $parentCoa = Coa::create([
            'kode' => '410',
            'nama' => 'Pendapatan Rawat Inap',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Rawat Inap',
        ]);

        $leafCoa1 = Coa::create([
            'parent_coa' => $parentCoa->id,
            'kode' => '410.01',
            'nama' => 'Tarif Kamar VIP',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Rawat Inap',
        ]);

        $leafCoa2 = Coa::create([
            'parent_coa' => $parentCoa->id,
            'kode' => '410.02',
            'nama' => 'Tarif Kamar Kelas 1',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);

        $kasbankCoa = Coa::create([
            'kode' => '111.05',
            'nama' => 'Bank BCA',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
        ]);

        // 1. Index langsung view data verification
        $resIndex = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $resIndex->assertStatus(200);
        $resIndex->assertViewHas('unmappedLangsungAccounts');
        $resIndex->assertViewHas('langsungCoaOptionsData');
        $resIndex->assertViewHas('activeReferensiOptions');

        $referensiList = $resIndex->viewData('referensiList');
        $this->assertNotNull($referensiList);
        $firstRef = $referensiList->firstWhere('id', $refOperasi->id);
        $this->assertNotNull($firstRef);
        $this->assertSame(1, $firstRef->coa_count); // leafCoa1
        $this->assertSame(1, $firstRef->parent_coa_count); // parentCoa
        $this->assertCount(1, $firstRef->mapped_leaf_coas);
        $this->assertCount(1, $firstRef->mapped_parent_coas);

        // 2. Search matches leaf COA nama or kode
        $resSearch = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'langsung',
            'search' => 'Kamar VIP',
        ]));
        $resSearch->assertStatus(200);
        $resSearch->assertSee('Penerimaan Rawat Inap');

        $resSearchNotFound = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index', [
            'metode' => 'langsung',
            'search' => 'TidakAdaAkunSamaSekali',
        ]));
        $resSearchNotFound->assertStatus(200);
        $resSearchNotFound->assertDontSee('Penerimaan Rawat Inap');

        // 3. parentCoaLeavesLangsung returns JSON structure
        $resLeaves = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.langsung.parent-coa-leaves', [
            'parent_id' => $parentCoa->id,
            'ignore_referensi_id' => $refOperasi->id,
        ]));
        $resLeaves->assertStatus(200);
        $resLeaves->assertJsonStructure(['eligible', 'reassignable', 'skipped']);

        // 4. quickMapLangsung maps leaf COA directly
        $resQuickMap = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.langsung.quick-map'), [
            'coa_id' => $leafCoa2->id,
            'referensi_id' => $refOperasi->id,
        ]);
        $resQuickMap->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $resQuickMap->assertSessionHas('success');
        $this->assertSame('operasi', $leafCoa2->fresh()->arus_kas_aktivitas);
        $this->assertSame('Penerimaan Rawat Inap', $leafCoa2->fresh()->arus_kas_kelompok);

        // 5. quickMapLangsung fails on Kasbank COA
        $resQuickMapKasbank = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.langsung.quick-map'), [
            'coa_id' => $kasbankCoa->id,
            'referensi_id' => $refOperasi->id,
        ]);
        $resQuickMapKasbank->assertSessionHasErrors('coa_id');

        // 6. storeLangsung with coa_ids syncs mappings
        $leafCoa3 = Coa::create([
            'kode' => '520.01',
            'nama' => 'Beban Perjalanan Dinas',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
        ]);

        $resStoreWithCoas = $this->actingAs($this->admin)->post(route('pengaturan.arus-kas.langsung.store'), [
            'aktivitas' => 'operasi',
            'nama' => 'Beban Perjalanan',
            'status_aktif' => '1',
            'coa_ids' => [$leafCoa3->id],
        ]);
        $resStoreWithCoas->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $this->assertSame('Beban Perjalanan', $leafCoa3->fresh()->arus_kas_kelompok);

        // 7. updateLangsung with empty sentinel clears mappings
        $refCreated = ReferensiKelompokArusKas::where('nama', 'Beban Perjalanan')->firstOrFail();
        $resUpdateClear = $this->actingAs($this->admin)->put(route('pengaturan.arus-kas.langsung.update', $refCreated), [
            'nama' => 'Beban Perjalanan Update',
            'status_aktif' => '1',
            'coa_ids' => '',
        ]);
        $resUpdateClear->assertRedirect(route('pengaturan.arus-kas.index', ['metode' => 'langsung']));
        $this->assertNull($leafCoa3->fresh()->arus_kas_kelompok);
    }
}
