<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pelaksana extends Model
{
    protected $table = 'pelaksana';

    protected $fillable = [
        'no_proyek',
        'nama_pelaksana',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function rincianFakturPenjualan()
    {
        return $this->hasMany(FakturPenjualanRinci::class, 'pelaksana_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }
}
