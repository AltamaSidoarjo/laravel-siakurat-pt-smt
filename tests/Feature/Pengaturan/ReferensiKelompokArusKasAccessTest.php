<?php

namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasAccessTest extends TestCase
{
    private User $admin;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $staffRole = Role::create(['nama' => 'Staff', 'kode' => 'staff', 'is_system' => false]);

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

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role_id' => $adminRole->id]);
        $this->unauthorizedUser = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => bcrypt('password'), 'role_id' => $staffRole->id]);
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

    public function test_unauthorized_user_gets_403(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(403);
    }

    public function test_admin_user_can_access(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(200);
    }
}
