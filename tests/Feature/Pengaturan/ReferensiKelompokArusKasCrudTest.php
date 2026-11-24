<?php

namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasCrudTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $module = AccessModule::create([
            'kode' => 'pengaturan.referensi-kelompok-arus-kas',
            'nama' => 'Referensi Kelompok Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 205,
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
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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
    }

    public function test_index_renders_view_with_data_and_coa_count(): void
    {
        $ref1 = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Pasien',
            'status_aktif' => true,
        ]);

        $ref2 = ReferensiKelompokArusKas::create([
            'aktivitas' => 'investasi',
            'nama' => 'Pembelian Alat Medis',
            'status_aktif' => true,
        ]);

        Coa::create([
            'kode' => '1.1.01',
            'nama' => 'Kas Penerimaan',
            'tipe_coa' => 'Kas & Bank',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Pasien',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pengaturan.referensi-kelompok-arus-kas.index');
        $response->assertSee('Penerimaan Pasien');
        $response->assertSee('Pembelian Alat Medis');
        $response->assertSee('Operasi');
        $response->assertSee('Investasi');
    }

    public function test_index_filters_by_aktivitas_and_search(): void
    {
        ReferensiKelompokArusKas::create(['aktivitas' => 'operasi', 'nama' => 'Penerimaan Pasien', 'status_aktif' => true]);
        ReferensiKelompokArusKas::create(['aktivitas' => 'investasi', 'nama' => 'Pembelian Mesin', 'status_aktif' => true]);
        ReferensiKelompokArusKas::create(['aktivitas' => 'operasi', 'nama' => 'Beban Operasional', 'status_aktif' => true]);

        // Filter by aktivitas
        $responseAktivitas = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index', ['aktivitas' => 'operasi']));
        $responseAktivitas->assertSee('Penerimaan Pasien');
        $responseAktivitas->assertSee('Beban Operasional');
        $responseAktivitas->assertDontSee('Pembelian Mesin');

        // Filter by search
        $responseSearch = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index', ['search' => 'Mesin']));
        $responseSearch->assertSee('Pembelian Mesin');
        $responseSearch->assertDontSee('Penerimaan Pasien');
    }

    public function test_store_creates_sanitized_reference(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pengaturan.referensi-kelompok-arus-kas.store'), [
            'aktivitas' => 'operasi',
            'nama' => '  Penerimaan   Klaim BPJS  ',
        ]);

        $response->assertRedirect(route('pengaturan.referensi-kelompok-arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Klaim BPJS',
            'status_aktif' => true,
        ]);
    }

    public function test_store_creates_inactive_reference_when_status_is_zero(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pengaturan.referensi-kelompok-arus-kas.store'), [
            'aktivitas' => 'investasi',
            'nama' => 'Peralatan Nonaktif Baru',
            'status_aktif' => '0',
        ]);

        $response->assertRedirect(route('pengaturan.referensi-kelompok-arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'investasi',
            'nama' => 'Peralatan Nonaktif Baru',
            'status_aktif' => false,
        ]);
    }

    public function test_update_updates_name_and_status_with_coa_cascade(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Lama',
            'status_aktif' => true,
        ]);

        $coa = Coa::create([
            'kode' => '5.1.01',
            'nama' => 'Akun Beban',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Lama',
        ]);

        $response = $this->actingAs($this->admin)->put(route('pengaturan.referensi-kelompok-arus-kas.update', $ref), [
            'nama' => 'Beban Baru Diperbarui',
            'status_aktif' => '0',
        ]);

        $response->assertRedirect(route('pengaturan.referensi-kelompok-arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'id' => $ref->id,
            'nama' => 'Beban Baru Diperbarui',
            'status_aktif' => false,
        ]);
        $this->assertEquals('Beban Baru Diperbarui', $coa->fresh()->arus_kas_kelompok);
    }

    public function test_delete_blocked_when_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Operasional',
            'status_aktif' => true,
        ]);

        Coa::create([
            'kode' => '5.2.01',
            'nama' => 'Operasional Kantor',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('pengaturan.referensi-kelompok-arus-kas.destroy', $ref));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', ['id' => $ref->id]);
    }

    public function test_delete_succeeds_when_not_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'pendanaan',
            'nama' => 'Penerimaan Modal',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('pengaturan.referensi-kelompok-arus-kas.destroy', $ref));
        $response->assertRedirect(route('pengaturan.referensi-kelompok-arus-kas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('referensi_kelompok_arus_kas', ['id' => $ref->id]);
    }

    public function test_toggle_status_flips_active_state(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Konsumsi',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('pengaturan.referensi-kelompok-arus-kas.toggle', $ref));
        $response->assertSessionHas('success');
        $this->assertFalse($ref->fresh()->status_aktif);

        $response2 = $this->actingAs($this->admin)->patch(route('pengaturan.referensi-kelompok-arus-kas.toggle', $ref));
        $response2->assertSessionHas('success');
        $this->assertTrue($ref->fresh()->status_aktif);
    }

    public function test_index_renders_modern_ui_layout_with_tabs_and_toolbar(): void
    {
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Pasien',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(200);
        $response->assertSee('card-header-tabs');
        $response->assertSee('Semua Pos');
        $response->assertSee('Aktivitas Operasi');
        $response->assertSee('Aktivitas Investasi');
        $response->assertSee('Aktivitas Pendanaan');
        $response->assertSee('rounded-circle');
        $response->assertSee('table-hover');
    }
}
