<?php

namespace Tests\Unit\Models;

use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingArusKasMigrationAndRegistryTest extends TestCase
{
    public function test_registry_contains_pengaturan_arus_kas_module(): void
    {
        $module = collect(AccessModuleRegistry::all())->firstWhere('kode', 'pengaturan.arus-kas');

        $this->assertNotNull($module);
        $this->assertSame('Pengaturan Arus Kas', $module['nama']);
        $this->assertSame('Pengaturan', $module['group_nama']);
        $this->assertSame(206, $module['urutan']);
    }

    public function test_migrations_create_and_rollback_tables_and_permissions(): void
    {
        foreach (['setting_arus_kas_pengecualian', 'setting_arus_kas_coa', 'setting_arus_kas', 'coa', 'role_permissions', 'access_modules', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('group_nama')->nullable();
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
            $table->id();
            $table->string('kode', 50);
            $table->string('nama', 150);
            $table->timestamps();
        });

        $sysRoleId = DB::table('roles')->insertGetId(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $staffRoleId = DB::table('roles')->insertGetId(['nama' => 'Staff', 'kode' => 'staff', 'is_system' => false]);

        $schemaMigration = require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php');
        $accessMigration = require database_path('migrations/2026_11_25_000002_add_pengaturan_arus_kas_access_module.php');

        $schemaMigration->up();
        $accessMigration->up();

        $this->assertTrue(Schema::hasTable('setting_arus_kas'));
        $this->assertTrue(Schema::hasTable('setting_arus_kas_coa'));
        $this->assertTrue(Schema::hasTable('setting_arus_kas_pengecualian'));
        $this->assertTrue(Schema::hasColumns('setting_arus_kas', ['id', 'kode_pos', 'nama_pos', 'kelompok_bagian', 'sifat_pos', 'pakai_prefix_perubahan', 'urutan', 'is_active']));
        $this->assertTrue(Schema::hasColumns('setting_arus_kas_coa', ['id', 'setting_arus_kas_id', 'coa_id']));
        $this->assertTrue(Schema::hasColumns('setting_arus_kas_pengecualian', ['id', 'coa_id', 'alasan']));

        $moduleId = DB::table('access_modules')->where('kode', 'pengaturan.arus-kas')->value('id');
        $this->assertNotNull($moduleId);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $sysRoleId,
            'access_module_id' => $moduleId,
            'can_view' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1,
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $staffRoleId,
            'access_module_id' => $moduleId,
            'can_view' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0,
        ]);

        $accessMigration->down();
        $schemaMigration->down();

        $this->assertFalse(Schema::hasTable('setting_arus_kas_pengecualian'));
        $this->assertFalse(Schema::hasTable('setting_arus_kas_coa'));
        $this->assertFalse(Schema::hasTable('setting_arus_kas'));
        $this->assertDatabaseMissing('access_modules', ['kode' => 'pengaturan.arus-kas']);
    }

    public function test_schema_migration_succeeds_without_coa_table(): void
    {
        foreach (['setting_arus_kas_pengecualian', 'setting_arus_kas_coa', 'setting_arus_kas', 'coa'] as $table) {
            Schema::dropIfExists($table);
        }

        $schemaMigration = require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php');
        $schemaMigration->up();

        $this->assertTrue(Schema::hasTable('setting_arus_kas'));
        $this->assertTrue(Schema::hasTable('setting_arus_kas_coa'));
        $this->assertTrue(Schema::hasTable('setting_arus_kas_pengecualian'));

        $schemaMigration->down();

        $this->assertFalse(Schema::hasTable('setting_arus_kas_pengecualian'));
        $this->assertFalse(Schema::hasTable('setting_arus_kas_coa'));
        $this->assertFalse(Schema::hasTable('setting_arus_kas'));
    }
}
