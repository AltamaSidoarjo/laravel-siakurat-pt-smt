<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmumRinci extends Model
{
    protected $table = 'jurnal_umum_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'jurnal_umum_id',
        'coa_id',
        'debit',
        'kredit',
        'catatan',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function jurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }
}
