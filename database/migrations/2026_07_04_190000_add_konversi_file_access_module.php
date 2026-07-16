<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULE_CODE = 'pengaturan.konversi-file';

    public function up(): void
    {
        if (! Schema::hasTable('access_modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $timestamp = now();

        DB::table('access_modules')->updateOrInsert(
            ['kode' => self::MODULE_CODE],
            [
                'nama' => 'Konversi File',
                'group_nama' => 'Pengaturan',
                'urutan' => 210,
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]
        );

        $moduleId = DB::table('access_modules')
            ->where('kode', self::MODULE_CODE)
            ->value('id');

        if ($moduleId === null) {
            return;
        }

        $existingRoleIds = DB::table('role_permissions')
            ->where('access_module_id', $moduleId)
            ->pluck('role_id')
            ->all();

        $payload = DB::table('roles')
            ->select('id', 'is_system')
            ->get()
            ->reject(fn ($role) => in_array($role->id, $existingRoleIds, true))
            ->map(fn ($role) => [
                'role_id' => $role->id,
                'access_module_id' => $moduleId,
                'can_view' => (bool) $role->is_system,
                'can_create' => (bool) $role->is_system,
                'can_update' => (bool) $role->is_system,
                'can_delete' => (bool) $role->is_system,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if ($payload === []) {
            return;
        }

        DB::table('role_permissions')->insert($payload);
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_modules')) {
            return;
        }

        $moduleId = DB::table('access_modules')
            ->where('kode', self::MODULE_CODE)
            ->value('id');

        if ($moduleId === null) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->where('access_module_id', $moduleId)
                ->delete();
        }

        DB::table('access_modules')
            ->where('id', $moduleId)
            ->delete();
    }
};
