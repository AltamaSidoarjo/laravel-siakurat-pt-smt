<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoAwalRinci extends Model
{
    protected $table = 'saldo_awal_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'saldo_awal_id',
        'coa_id',
        'debit',
        'kredit',
        'catatan',
    ];

    protected $casts = [
        'saldo_awal_id' => 'integer',
        'coa_id' => 'integer',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function saldoAwal(): BelongsTo
    {
        return $this->belongsTo(SaldoAwal::class, 'saldo_awal_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}

