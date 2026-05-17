<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingLawanPendapatanSimrs extends Model
{
    protected $table = 'mapping_lawan_pendapatan_simrs';

    protected $primaryKey = 'mapping_lawan_pendapatan_simrs_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kode_coa_simrs',
        'nama_coa_simrs',
        'coa_id',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
