<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULE_CODE = 'kasbank.buku-bank';

    public function up(): void
    {
        if (! Schema::hasTable('access_modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $timestamp = now();

        DB::table('access_modules')->updateOrInsert(
            ['kode' => self::MODULE_CODE],
            [
                'nama' => 'Buku Bank',
                'group_nama' => 'Kasbank',
                'urutan' => 55,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        $moduleId = DB::table('access_modules')->where('kode', self::MODULE_CODE)->value('id');
        if ($moduleId === null) {
            return;
        }

        $sourceModuleIds = DB::table('access_modules')
            ->whereIn('kode', ['kasbank.penerimaan', 'kasbank.pembayaran'])
            ->pluck('id');

        $viewableRoleIds = DB::table('role_permissions')
            ->whereIn('access_module_id', $sourceModuleIds)
            ->where('can_view', true)
            ->pluck('role_id')
            ->unique();

        $existingRoleIds = DB::table('role_permissions')
            ->where('access_module_id', $moduleId)
            ->pluck('role_id');

        $permissions = DB::table('roles')
            ->pluck('id')
            ->reject(fn ($roleId) => $existingRoleIds->contains($roleId))
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'access_module_id' => $moduleId,
                'can_view' => $viewableRoleIds->contains($roleId),
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all();

        if ($permissions !== []) {
            DB::table('role_permissions')->insert($permissions);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_modules')) {
            return;
        }

        $moduleId = DB::table('access_modules')->where('kode', self::MODULE_CODE)->value('id');
        if ($moduleId === null) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('access_module_id', $moduleId)->delete();
        }

        DB::table('access_modules')->where('id', $moduleId)->delete();
    }
};
