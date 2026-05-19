<?php

namespace App\Services\Auth;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ModuleAccessService
{
    public function userCanAccess(?User $user, string $moduleKey, string $action): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasModuleAccess($moduleKey, $action);
    }

    public function authorize(?User $user, string $moduleKey, string $action): void
    {
        if (! $this->userCanAccess($user, $moduleKey, $action)) {
            throw new HttpException(403, 'Anda tidak memiliki akses ke modul ini.');
        }
    }
}
