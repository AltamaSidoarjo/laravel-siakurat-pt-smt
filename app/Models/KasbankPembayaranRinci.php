<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasbankPembayaranRinci extends Model
{
    protected $table = 'kasbank_pembayaran_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kasbank_pembayaran_id',
        'coa_id',
        'nominal',
        'catatan',
    ];

    protected $casts = [
        'kasbank_pembayaran_id' => 'integer',
        'coa_id' => 'integer',
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kasbankPembayaran()
    {
        return $this->belongsTo(KasbankPembayaran::class, 'kasbank_pembayaran_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
