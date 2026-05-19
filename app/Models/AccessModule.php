<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessModule extends Model
{
    protected $table = 'access_modules';

    protected $fillable = [
        'kode',
        'nama',
        'group_nama',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'access_module_id');
    }
}
