<?php

namespace App\Services\Pengaturan;

use App\Models\Role;
use App\Models\User;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {}

    public function getAll(): Collection
    {
        return User::query()
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    public function getRoleOptions(?User $actor = null): Collection
    {
        $query = Role::query()
            ->orderByDesc('is_system')
            ->orderBy('nama');

        if ($actor && ! (bool) $actor->role?->is_system) {
            $query->where('is_system', false);
        }

        return $query->get(['id', 'nama', 'kode']);
    }

    public function create(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'nama_lengkap' => $data['nama_lengkap'] ?: null,
            'jabatan' => $data['jabatan'] ?: null,
            'email' => $data['email'],
            'role_id' => (int) $data['role_id'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $this->logService->log('User Management', 'create', null, [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
        ]);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $oldData = ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id];

        $payload = [
            'name' => $data['name'],
            'nama_lengkap' => $data['nama_lengkap'] ?: null,
            'jabatan' => $data['jabatan'] ?: null,
            'email' => $data['email'],
            'role_id' => (int) $data['role_id'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->fill($payload);
        $user->save();

        $this->logService->log('User Management', 'update', $oldData, [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
        ]);

        return $user->refresh();
    }

    public function delete(User $user, User $actor): bool
    {
        if ($user->is($actor) || (bool) $user->role?->is_system) {
            return false;
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
        ];

        DB::transaction(function () use ($user, $oldData): void {
            $user->deleteOrFail();
            $this->logService->log('User Management', 'delete', $oldData);
        });

        return true;
    }
}
