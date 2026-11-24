<?php

namespace Tests\Feature\Bukubesar;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TipeCoa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaArusKasTaggingTest extends TestCase
{
    private User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $role = Role::create(['nama' => 'Accounting', 'kode' => 'akunting', 'is_system' => false]);
        $module = AccessModule::create([
            'kode' => 'bukubesar.coa',
            'nama' => 'COA',
            'group_nama' => 'Bukubesar',
            'urutan' => 30,
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->accountingUser = User::create([
            'name' => 'Akuntan',
            'email' => 'akuntan@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        TipeCoa::create(['nama' => 'Beban', 'status_aktif' => 1]);
        TipeCoa::create(['nama' => 'Kasbank', 'status_aktif' => 1]);
    }

    private function prepareSchema(): void
    {
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::dropIfExists('tipe_coa');
        Schema::dropIfExists('log_aktifitas');
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

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
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
            $table->string('nama', 100)->unique();
            $table->string('tipe_coa', 50);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->integer('status_aktif')->default(1);
            $table->boolean('is_postable')->default(false);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });
    }

    public function test_saving_coa_with_new_tag_auto_creates_master_reference(): void
    {
        $response = $this->actingAs($this->accountingUser)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Biaya Riset & Pengembangan',
            'kode' => '5.9.01',
            'nama' => 'Biaya Litbang',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coa', [
            'kode' => '5.9.01',
            'arus_kas_kelompok' => 'Pembayaran Biaya Riset & Pengembangan',
        ]);

        // Auto-insert to master referensi
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Biaya Riset & Pengembangan',
            'status_aktif' => true,
        ]);
    }

    public function test_updating_coa_with_new_tag_auto_creates_master_reference(): void
    {
        $coa = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'investasi',
            'arus_kas_kelompok' => 'Perolehan Aset Tetap',
            'kode' => '1.3.01',
            'nama' => 'Aset Bangunan',
        ]);

        $response = $this->actingAs($this->accountingUser)->put(route('bukubesar.coa.update', $coa), [
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'investasi',
            'arus_kas_kelompok' => 'Perolehan Properti Investasi Baru',
            'kode' => '1.3.01',
            'nama' => 'Aset Bangunan',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coa', [
            'id' => $coa->id,
            'arus_kas_kelompok' => 'Perolehan Properti Investasi Baru',
        ]);

        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'investasi',
            'nama' => 'Perolehan Properti Investasi Baru',
            'status_aktif' => true,
        ]);
    }

    public function test_create_and_edit_forms_pass_referensi_kelompok(): void
    {
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Kas dari Pelanggan',
            'status_aktif' => true,
        ]);

        $createResponse = $this->actingAs($this->accountingUser)->get(route('bukubesar.coa.create'));
        $createResponse->assertOk();
        $createResponse->assertViewHas('referensiKelompok');

        $coa = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan Kas dari Pelanggan',
            'kode' => '5.1.01',
            'nama' => 'Beban Pokok',
        ]);

        $editResponse = $this->actingAs($this->accountingUser)->get(route('bukubesar.coa.edit', $coa));
        $editResponse->assertOk();
        $editResponse->assertViewHas('referensiKelompok');
    }

    public function test_kasbank_coa_does_not_create_master_reference(): void
    {
        $response = $this->actingAs($this->accountingUser)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Kasbank',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Kas Operasional',
            'kode' => '1.1.01',
            'nama' => 'Kas Kecil',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coa', [
            'kode' => '1.1.01',
            'tipe_coa' => 'Kasbank',
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);

        $this->assertDatabaseMissing('referensi_kelompok_arus_kas', [
            'nama' => 'Kas Operasional',
        ]);
    }

    public function test_saving_with_existing_tag_does_not_duplicate_master_reference(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran kepada Pemasok',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->accountingUser)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'pembayaran kepada pemasok', // case-insensitive match
            'kode' => '5.2.01',
            'nama' => 'Beban Vendor',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, ReferensiKelompokArusKas::where('aktivitas', 'operasi')->where('nama', 'Pembayaran kepada Pemasok')->count());
    }
}
