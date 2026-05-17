<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pelanggan extends Model
{
    protected $table = 'pelanggan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'status_aktif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fakturPenjualans()
    {
        return $this->hasMany(FakturPenjualan::class, 'pelanggan_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }
}
