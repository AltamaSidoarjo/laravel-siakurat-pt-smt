<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KasbankPembayaran extends Model
{
    protected $table = 'kasbank_pembayaran';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'coa_id',
        'nomer',
        'tanggal',
        'keterangan',
        'total',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'tanggal' => 'date',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function rincian()
    {
        return $this->hasMany(KasbankPembayaranRinci::class, 'kasbank_pembayaran_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}
