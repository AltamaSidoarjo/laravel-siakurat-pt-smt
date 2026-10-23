<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingBridgingObat;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingBridgingObatTest extends TestCase
{
    private User $adminUser;
    private User $unauthorizedUser;
    private Coa $coaPendapatan;
    private Coa $coaHpp;
    private Coa $coaPersediaan;
    private Coa $coaPiutang;
    private Coa $coaKas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $roleAdmin = Role::create([
            'nama' => 'Admin Test',
            'kode' => 'admin_test',
            'is_system' => false,
        ]);

        $module = AccessModule::create([
            'kode' => 'pengaturan.setting-obat',
            'nama' => 'Setting Akun Obat & BHP',
            'group_nama' => 'Pengaturan',
            'urutan' => 165,
        ]);

        RolePermission::create([
            'role_id' => $roleAdmin->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $roleAdmin->id,
        ]);

        $rolePlain = Role::create([
            'nama' => 'Plain User',
            'kode' => 'plain_user',
            'is_system' => false,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized User',
            'email' => 'plain@test.com',
            'password' => bcrypt('password'),
            'role_id' => $rolePlain->id,
        ]);

        $this->coaPendapatan = Coa::create([
            'kode' => '410001',
            'nama' => 'Pendapatan Obat Test',
            'tipe_coa' => 'Pendapatan',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaHpp = Coa::create([
            'kode' => '510001',
            'nama' => 'HPP Obat Test',
            'tipe_coa' => 'Beban',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPersediaan = Coa::create([
            'kode' => '115001',
            'nama' => 'Persediaan Obat Test',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPiutang = Coa::create([
            'kode' => '117001',
            'nama' => 'Piutang Obat Test',
            'tipe_coa' => 'Piutang',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaKas = Coa::create([
            'kode' => '111001',
            'nama' => 'Kas Kasir Test',
            'tipe_coa' => 'KasBank',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('setting_bridging_obats');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('log_aktifitas');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('nama_lengkap')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->string('profile_picture')->nullable();
            $table->rememberToken();
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

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->integer('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('setting_bridging_obats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pendapatan_obat_coa_id')->nullable();
            $table->unsignedInteger('hpp_obat_coa_id')->nullable();
            $table->unsignedInteger('persediaan_obat_coa_id')->nullable();
            $table->unsignedInteger('piutang_obat_coa_id')->nullable();
            $table->unsignedInteger('kas_uang_muka_coa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_unauthorized_user_cannot_access_setting_obat(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('pengaturan.setting-obat.index'));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_setting_obat_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-obat.index'));

        $response->assertStatus(200);
        $response->assertSee('Setting Akun Obat');
        $response->assertSee('Akun Pendapatan Obat');
        $response->assertSee('Akun Persediaan Obat');
    }

    public function test_user_can_save_and_update_setting_obat(): void
    {
        $payload = [
            'pendapatan_obat_coa_id' => $this->coaPendapatan->id,
            'hpp_obat_coa_id' => $this->coaHpp->id,
            'persediaan_obat_coa_id' => $this->coaPersediaan->id,
            'piutang_obat_coa_id' => $this->coaPiutang->id,
            'kas_uang_muka_coa_id' => $this->coaKas->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('pengaturan.setting-obat.update'), $payload);

        $response->assertRedirect(route('pengaturan.setting-obat.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('setting_bridging_obats', [
            'pendapatan_obat_coa_id' => $this->coaPendapatan->id,
            'hpp_obat_coa_id' => $this->coaHpp->id,
            'persediaan_obat_coa_id' => $this->coaPersediaan->id,
            'piutang_obat_coa_id' => $this->coaPiutang->id,
            'kas_uang_muka_coa_id' => $this->coaKas->id,
        ]);
    }

    public function test_validation_fails_when_coa_is_invalid(): void
    {
        $payload = [
            'pendapatan_obat_coa_id' => 999999,
            'hpp_obat_coa_id' => $this->coaHpp->id,
            'persediaan_obat_coa_id' => $this->coaPersediaan->id,
            'piutang_obat_coa_id' => $this->coaPiutang->id,
            'kas_uang_muka_coa_id' => $this->coaKas->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('pengaturan.setting-obat.update'), $payload);

        $response->assertSessionHasErrors(['pendapatan_obat_coa_id']);
    }
}
