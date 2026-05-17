<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KasbankPenerimaan extends Model
{
    protected $table = 'kasbank_penerimaan';

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
        return $this->hasMany(KasbankPenerimaanRinci::class, 'kasbank_penerimaan_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}
