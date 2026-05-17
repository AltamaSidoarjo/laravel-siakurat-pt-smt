<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasbankPenerimaanRinci extends Model
{
    protected $table = 'kasbank_penerimaan_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kasbank_penerimaan_id',
        'coa_id',
        'nominal',
        'catatan',
    ];

    protected $casts = [
        'kasbank_penerimaan_id' => 'integer',
        'coa_id' => 'integer',
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kasbankPenerimaan()
    {
        return $this->belongsTo(KasbankPenerimaan::class, 'kasbank_penerimaan_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
