<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingPendapatanUmum extends Model
{
    protected $table = 'mapping_pendapatan_umum';

    protected $primaryKey = 'mapping_pendapatan_umum_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'nama',
        'kode_penjamin',
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
