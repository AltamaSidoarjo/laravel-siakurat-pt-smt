<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BukuBesar extends Model
{
    protected $table = 'bukubesar';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'coa_id',
        'sumber_id',
        'tanggal',
        'nomer',
        'sumber_transaksi',
        'nominal',
        'tipe_mutasi',
        'keterangan',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'sumber_id' => 'integer',
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function scopeForSource(Builder $query, string $sourceTransaction, int $sourceId): Builder
    {
        return $query
            ->where('sumber_transaksi', $sourceTransaction)
            ->where('sumber_id', $sourceId);
    }
}
