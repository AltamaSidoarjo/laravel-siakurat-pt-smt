<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function hasModuleAccess(string $moduleKey, string $action): bool
    {
        $column = RolePermission::resolveActionColumn($action);

        if ($this->relationLoaded('permissions')) {
            return $this->permissions
                ->first(fn (RolePermission $permission) => $permission->accessModule?->kode === $moduleKey)?->{$column} === true;
        }

        return $this->permissions()
            ->where($column, true)
            ->whereHas('accessModule', fn ($query) => $query->where('kode', $moduleKey))
            ->exists();
    }
}
