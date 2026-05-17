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
            'member' => $data['member'] ?? null,
            'password' => $data['password'],
            'email_verified_at' => now(),
            'jumlah_ganti_password' => 0,
        ]);
    }

    public function update(User $user, array $data): User
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'member' => $data['member'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['jumlah_ganti_password'] = (int) $user->jumlah_ganti_password + 1;
        }

        $user->fill($payload);
        $user->save();

        return $user->refresh();
    }
}
