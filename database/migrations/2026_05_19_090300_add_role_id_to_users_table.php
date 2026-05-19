<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->nullOnDelete();
            });
        }

        $timestamp = now();

        $adminRoleId = DB::table('roles')->insertGetId([
            'nama' => 'Administrator',
            'kode' => 'admin',
            'deskripsi' => 'Role sistem dengan akses penuh ke seluruh modul.',
            'is_system' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $moduleIds = DB::table('access_modules')->pluck('id');

        DB::table('role_permissions')->insert(
            $moduleIds->map(fn ($moduleId) => [
                'role_id' => $adminRoleId,
                'access_module_id' => $moduleId,
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all()
        );

        DB::table('users')->update([
            'role_id' => $adminRoleId,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('role_id');
            });
        }
    }
};
