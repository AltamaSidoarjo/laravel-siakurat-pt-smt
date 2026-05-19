<?php

namespace App\Services\Pengaturan;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserManagementService
{
    public function getAll(): Collection
    {
        return User::query()
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    public function getRoleOptions(): Collection
    {
        return Role::query()
            ->orderByDesc('is_system')
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode']);
    }

    public function create(array $data): User
    {
        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => (int) $data['role_id'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);
    }

    public function update(User $user, array $data): User
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => (int) $data['role_id'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->fill($payload);
        $user->save();

        return $user->refresh();
    }
}
