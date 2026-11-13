<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasbankPembayaranRinci extends Model
{
    protected $table = 'kasbank_pembayaran_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kasbank_pembayaran_id',
        'coa_id',
        'pelaksana_id',
        'nominal',
        'catatan',
    ];

    protected $casts = [
        'kasbank_pembayaran_id' => 'integer',
        'coa_id' => 'integer',
        'pelaksana_id' => 'integer',
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kasbankPembayaran(): BelongsTo
    {
        return $this->belongsTo(KasbankPembayaran::class, 'kasbank_pembayaran_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function pelaksana(): BelongsTo
    {
        return $this->belongsTo(Pelaksana::class, 'pelaksana_id');
    }
}
