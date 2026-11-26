<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_MODULE_CODE = 'pengaturan.referensi-kelompok-arus-kas';

    private const NEW_MODULE_CODE = 'pengaturan.arus-kas';

    public function up(): void
    {
        if (! Schema::hasTable('access_modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $timestamp = now();

        DB::transaction(function () use ($timestamp): void {
            $oldModuleId = DB::table('access_modules')
                ->where('kode', self::OLD_MODULE_CODE)
                ->value('id');

            $existingNewModule = DB::table('access_modules')
                ->where('kode', self::NEW_MODULE_CODE)
                ->first();

            DB::table('access_modules')->updateOrInsert(
                ['kode' => self::NEW_MODULE_CODE],
                [
                    'nama' => 'Pengaturan Arus Kas',
                    'group_nama' => 'Pengaturan',
                    'urutan' => 206,
                    'created_at' => $existingNewModule->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ],
            );

            $newModuleId = DB::table('access_modules')
                ->where('kode', self::NEW_MODULE_CODE)
                ->value('id');

            if ($newModuleId === null) {
                return;
            }

            $oldPermissions = $oldModuleId !== null
                ? DB::table('role_permissions')->where('access_module_id', $oldModuleId)->get()->keyBy('role_id')
                : collect();

            $newPermissions = DB::table('role_permissions')
                ->where('access_module_id', $newModuleId)
                ->get()
                ->keyBy('role_id');

            foreach (DB::table('roles')->select('id', 'is_system')->get() as $role) {
                $oldPerm = $oldPermissions->get($role->id);
                $newPerm = $newPermissions->get($role->id);
                $isSystem = (bool) $role->is_system;

                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'access_module_id' => $newModuleId,
                    ],
                    [
                        'can_view' => $isSystem || (bool) ($oldPerm->can_view ?? false) || (bool) ($newPerm->can_view ?? false),
                        'can_create' => $isSystem || (bool) ($oldPerm->can_create ?? false) || (bool) ($newPerm->can_create ?? false),
                        'can_update' => $isSystem || (bool) ($oldPerm->can_update ?? false) || (bool) ($newPerm->can_update ?? false),
                        'can_delete' => $isSystem || (bool) ($oldPerm->can_delete ?? false) || (bool) ($newPerm->can_delete ?? false),
                        'created_at' => $newPerm->created_at ?? $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            }

            if ($oldModuleId !== null) {
                DB::table('role_permissions')->where('access_module_id', $oldModuleId)->delete();
                DB::table('access_modules')->where('id', $oldModuleId)->delete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_modules')) {
            return;
        }

        $timestamp = now();

        DB::transaction(function () use ($timestamp): void {
            $existingOldModule = DB::table('access_modules')
                ->where('kode', self::OLD_MODULE_CODE)
                ->first();

            DB::table('access_modules')->updateOrInsert(
                ['kode' => self::OLD_MODULE_CODE],
                [
                    'nama' => 'Referensi Kelompok Arus Kas',
                    'group_nama' => 'Pengaturan',
                    'urutan' => 205,
                    'created_at' => $existingOldModule->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ],
            );

            if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
                return;
            }

            $oldModuleId = DB::table('access_modules')
                ->where('kode', self::OLD_MODULE_CODE)
                ->value('id');

            if ($oldModuleId === null) {
                return;
            }

            $existingRoleIds = DB::table('role_permissions')
                ->where('access_module_id', $oldModuleId)
                ->pluck('role_id')
                ->all();

            $permissions = DB::table('roles')->select('id', 'is_system')->get()
                ->reject(fn ($role) => in_array($role->id, $existingRoleIds, true))
                ->map(fn ($role) => [
                    'role_id' => $role->id,
                    'access_module_id' => $oldModuleId,
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
        });
    }
};
