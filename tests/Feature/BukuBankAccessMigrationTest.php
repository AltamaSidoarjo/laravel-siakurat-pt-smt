<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BukuBankAccessMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
        });
        Schema::create('access_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan');
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'access_module_id']);
        });
    }

    public function test_migration_inherits_view_access_from_either_existing_kasbank_module(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'nama' => 'Penerimaan'],
            ['id' => 2, 'nama' => 'Pembayaran'],
            ['id' => 3, 'nama' => 'Tanpa Akses'],
        ]);
        DB::table('access_modules')->insert([
            ['id' => 1, 'kode' => 'kasbank.penerimaan', 'nama' => 'Penerimaan', 'group_nama' => 'Kasbank', 'urutan' => 40],
            ['id' => 2, 'kode' => 'kasbank.pembayaran', 'nama' => 'Pembayaran', 'group_nama' => 'Kasbank', 'urutan' => 50],
        ]);
        DB::table('role_permissions')->insert([
            ['role_id' => 1, 'access_module_id' => 1, 'can_view' => true],
            ['role_id' => 2, 'access_module_id' => 2, 'can_view' => true],
        ]);

        $migration = require database_path('migrations/2026_09_23_000000_add_buku_bank_access_module.php');
        $migration->up();
        $migration->up();

        $moduleId = DB::table('access_modules')->where('kode', 'kasbank.buku-bank')->value('id');
        $permissions = DB::table('role_permissions')->where('access_module_id', $moduleId)->orderBy('role_id')->get();

        $this->assertCount(3, $permissions);
        $this->assertSame([1, 1, 0], $permissions->pluck('can_view')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([0, 0, 0], $permissions->pluck('can_create')->map(fn ($value) => (int) $value)->all());
    }
}
