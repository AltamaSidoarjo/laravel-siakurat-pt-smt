<?php

namespace App\Services\Pengaturan;

use App\Models\AccessModule;
use App\Models\Role;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleAccessManagementService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }
    public function getAll(): Collection
    {
        return Role::query()
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('nama')
            ->get();
    }

    public function getModulesGrouped(): Collection
    {
        return AccessModule::query()
            ->orderBy('urutan')
            ->get()
            ->groupBy('group_nama');
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'nama' => $data['nama'],
                'kode' => $data['kode'],
                'deskripsi' => $data['deskripsi'] ?: null,
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            $this->logService->log('Role Access', 'create', null, [
                'nama' => $role->nama,
                'kode' => $role->kode,
            ]);

            return $role->load('permissions.accessModule');
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $oldData = ['nama' => $role->nama, 'kode' => $role->kode];

            $role->fill([
                'nama' => $data['nama'],
                'kode' => $data['kode'],
                'deskripsi' => $data['deskripsi'] ?: null,
            ]);
            $role->save();

            $this->syncPermissions($role, $data['permissions'] ?? []);

            $this->logService->log('Role Access', 'update', $oldData, [
                'nama' => $role->nama,
                'kode' => $role->kode,
            ]);

            return $role->load('permissions.accessModule');
        });
    }

    public function getPermissionMatrix(Role $role): array
    {
        $role->loadMissing('permissions.accessModule');

        return $role->permissions
            ->keyBy('access_module_id')
            ->map(fn ($permission) => [
                'can_view' => (bool) $permission->can_view,
                'can_create' => (bool) $permission->can_create,
                'can_update' => (bool) $permission->can_update,
                'can_delete' => (bool) $permission->can_delete,
            ])
            ->all();
    }

    private function syncPermissions(Role $role, array $permissions): void
    {
        $modules = AccessModule::query()->orderBy('urutan')->get(['id']);
        $payload = [];
        $timestamp = now();

        foreach ($modules as $module) {
            $selected = $permissions[$module->id] ?? [];

            $payload[] = [
                'role_id' => $role->id,
                'access_module_id' => $module->id,
                'can_view' => (bool) ($selected['can_view'] ?? false),
                'can_create' => (bool) ($selected['can_create'] ?? false),
                'can_update' => (bool) ($selected['can_update'] ?? false),
                'can_delete' => (bool) ($selected['can_delete'] ?? false),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $role->permissions()->delete();
        $role->permissions()->insert($payload);
    }
}
