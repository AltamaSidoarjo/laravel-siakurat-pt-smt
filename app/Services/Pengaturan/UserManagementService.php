<?php

namespace App\Services\Pengaturan;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserManagementService
{
    public function getAll(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): User
    {
        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);
    }

    public function update(User $user, array $data): User
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->fill($payload);
        $user->save();

        return $user->refresh();
    }
}
