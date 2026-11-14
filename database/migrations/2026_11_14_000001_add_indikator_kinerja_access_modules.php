<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULES = [
        [
            'kode' => 'dashboard.indikator-kinerja',
            'nama' => 'Indikator Kinerja',
            'group_nama' => 'Dashboard',
            'urutan' => 15,
        ],
        [
            'kode' => 'pengaturan.setting-indikator-kinerja',
            'nama' => 'Setting Indikator Kinerja',
            'group_nama' => 'Pengaturan',
            'urutan' => 175,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('access_modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $timestamp = now();
        $roles = DB::table('roles')->select('id', 'is_system')->get();

        foreach (self::MODULES as $moduleData) {
            DB::table('access_modules')->updateOrInsert(
                ['kode' => $moduleData['kode']],
                [
                    'nama' => $moduleData['nama'],
                    'group_nama' => $moduleData['group_nama'],
                    'urutan' => $moduleData['urutan'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );

            $moduleId = DB::table('access_modules')->where('kode', $moduleData['kode'])->value('id');
            if ($moduleId === null) {
                continue;
            }

            $existingRoleIds = DB::table('role_permissions')
                ->where('access_module_id', $moduleId)
                ->pluck('role_id')
                ->all();

            $permissions = $roles
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

            if ($permissions !== []) {
                DB::table('role_permissions')->insert($permissions);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_modules')) {
            return;
        }

        $moduleCodes = array_column(self::MODULES, 'kode');
        $moduleIds = DB::table('access_modules')->whereIn('kode', $moduleCodes)->pluck('id')->all();

        if ($moduleIds !== []) {
            if (Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('access_module_id', $moduleIds)->delete();
            }

            DB::table('access_modules')->whereIn('id', $moduleIds)->delete();
        }
    }
};
