<?php

namespace Tests\Feature;

use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserGuideAccessModuleMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');

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
    }

    public function test_registry_contains_panduan_user_guide(): void
    {
        $modules = collect(AccessModuleRegistry::all());
        $userGuide = $modules->firstWhere('kode', 'panduan.user-guide');

        $this->assertNotNull($userGuide);
        $this->assertEquals('User Guide', $userGuide['nama']);
        $this->assertEquals('Panduan', $userGuide['group_nama']);
        $this->assertEquals(250, $userGuide['urutan']);
    }

    public function test_migration_up_and_down_executes_cleanly(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'nama' => 'Superadmin', 'kode' => 'superadmin', 'is_system' => 1],
            ['id' => 2, 'nama' => 'Staff', 'kode' => 'staff', 'is_system' => 0],
        ]);

        $migration = require database_path('migrations/2026_11_16_000001_add_panduan_user_guide_access_module.php');
        $migration->up();

        $this->assertDatabaseHas('access_modules', ['kode' => 'panduan.user-guide']);
        $moduleId = DB::table('access_modules')->where('kode', 'panduan.user-guide')->value('id');

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => 1,
            'access_module_id' => $moduleId,
            'can_view' => 1,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => 2,
            'access_module_id' => $moduleId,
            'can_view' => 0,
        ]);

        $migration->down();
        $this->assertDatabaseMissing('access_modules', ['kode' => 'panduan.user-guide']);
        $this->assertDatabaseMissing('role_permissions', ['access_module_id' => $moduleId]);
    }
}
