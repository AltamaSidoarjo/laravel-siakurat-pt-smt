<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingBridgingPembelian;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingBridgingPembelianTest extends TestCase
{
    private User $adminUser;
    private User $unauthorizedUser;
    private Coa $coaPersediaanObat;
    private Coa $coaPpnMasukan;
    private Coa $coaHutangObat;
    private Coa $coaPersediaanNonMedis;
    private Coa $coaHutangNonMedis;

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
            'kode' => 'pengaturan.setting-pembelian',
            'nama' => 'Setting Akun Pembelian',
            'group_nama' => 'Pengaturan',
            'urutan' => 168,
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

        $this->coaPersediaanObat = Coa::create([
            'kode' => '115010',
            'nama' => 'Persediaan Obat & BHP',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPpnMasukan = Coa::create([
            'kode' => '116011',
            'nama' => 'PPN Masukan',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaHutangObat = Coa::create([
            'kode' => '211010',
            'nama' => 'Hutang Pembelian Obat & BHP',
            'tipe_coa' => 'Kewajiban Jangka Pendek',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPersediaanNonMedis = Coa::create([
            'kode' => '115020',
            'nama' => 'Persediaan / Beban Non Medis',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaHutangNonMedis = Coa::create([
            'kode' => '211210',
            'nama' => 'Hutang Pembelian Non Medis',
            'tipe_coa' => 'Kewajiban Jangka Pendek',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('setting_bridging_pembelians');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('log_aktifitas');

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('tipe_coa')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('access_module_id')->constrained('access_modules')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('setting_bridging_pembelians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persediaan_obat_coa_id');
            $table->unsignedBigInteger('ppn_masukan_coa_id');
            $table->unsignedBigInteger('hutang_obat_coa_id');
            $table->unsignedBigInteger('persediaan_non_medis_coa_id');
            $table->unsignedBigInteger('hutang_non_medis_coa_id');
            $table->timestamps();

            $table->foreign('persediaan_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('ppn_masukan_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('hutang_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('persediaan_non_medis_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('hutang_non_medis_coa_id')->references('id')->on('coa')->restrictOnDelete();
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

    public function test_unauthorized_user_cannot_access_setting_pembelian(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('pengaturan.setting-pembelian.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_setting_pembelian(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-pembelian.index'));

        $response->assertOk()
            ->assertViewIs('pengaturan.setting-pembelian.index')
            ->assertViewHas('coaOptions')
            ->assertSee('Setting Akun Pembelian')
            ->assertSee('115010')
            ->assertSee('116011')
            ->assertSee('211010');
    }

    public function test_authorized_user_can_save_setting_pembelian(): void
    {
        $payload = [
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            'ppn_masukan_coa_id' => $this->coaPpnMasukan->id,
            'hutang_obat_coa_id' => $this->coaHutangObat->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('pengaturan.setting-pembelian.update'), $payload);

        $response->assertRedirect(route('pengaturan.setting-pembelian.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('setting_bridging_pembelians', [
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            'ppn_masukan_coa_id' => $this->coaPpnMasukan->id,
            'hutang_obat_coa_id' => $this->coaHutangObat->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ]);
    }

    public function test_validation_fails_if_required_coa_is_missing(): void
    {
        $payload = [
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            // ppn_masukan_coa_id missing
            'hutang_obat_coa_id' => $this->coaHutangObat->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('pengaturan.setting-pembelian.update'), $payload);

        $response->assertSessionHasErrors(['ppn_masukan_coa_id']);
    }

    public function test_can_update_existing_setting(): void
    {
        SettingBridgingPembelian::create([
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            'ppn_masukan_coa_id' => $this->coaPpnMasukan->id,
            'hutang_obat_coa_id' => $this->coaHutangObat->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ]);

        $newCoaHutang = Coa::create([
            'kode' => '211999',
            'nama' => 'Hutang Baru',
            'tipe_coa' => 'Kewajiban',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);

        $payload = [
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            'ppn_masukan_coa_id' => $this->coaPpnMasukan->id,
            'hutang_obat_coa_id' => $newCoaHutang->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('pengaturan.setting-pembelian.update'), $payload);

        $response->assertRedirect(route('pengaturan.setting-pembelian.index'));

        $this->assertDatabaseHas('setting_bridging_pembelians', [
            'hutang_obat_coa_id' => $newCoaHutang->id,
        ]);
        $this->assertSame(1, SettingBridgingPembelian::count());
    }
}
