<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoAwal extends Model
{
    protected $table = 'saldo_awal';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nomer',
        'tanggal_cutoff',
        'keterangan',
        'total_debit',
        'total_kredit',
        'status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_cutoff' => 'date',
        'total_debit' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'locked_at' => 'datetime',
        'locked_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rincian(): HasMany
    {
        return $this->hasMany(SaldoAwalRinci::class, 'saldo_awal_id');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->total_debit - (float) $this->total_kredit) < 0.01;
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('status', 'locked');
    }
}

