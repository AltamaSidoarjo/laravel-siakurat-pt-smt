<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SettingRba extends Model
{
    protected $table = 'setting_rba';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'coa_id',
        'tahun',
        'total_nominal',
        'catatan',
        'is_rinci',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'tahun' => 'integer',
        'total_nominal' => 'decimal:2',
        'is_rinci' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function rincian()
    {
        return $this->hasMany(SettingRbaRinci::class, 'setting_rba_id');
    }

    public function scopeBetweenYears(Builder $query, ?int $yearFrom, ?int $yearTo): Builder
    {
        if ($yearFrom !== null) {
            $query->where('tahun', '>=', $yearFrom);
        }

        if ($yearTo !== null) {
            $query->where('tahun', '<=', $yearTo);
        }

        return $query;
    }
}
