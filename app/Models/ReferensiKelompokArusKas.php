<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReferensiKelompokArusKas extends Model
{
    protected $table = 'referensi_kelompok_arus_kas';

    protected $fillable = [
        'aktivitas',
        'nama',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }

    public function scopeAktivitas(Builder $query, string $aktivitas): Builder
    {
        return $query->where('aktivitas', $aktivitas);
    }
}
