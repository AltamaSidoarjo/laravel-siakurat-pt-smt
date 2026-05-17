<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingPendapatanKamar extends Model
{
    protected $table = 'mapping_pendapatan_kamar';

    protected $primaryKey = 'mapping_pendapatan_kamar_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'kode_kamar',
        'nama_kamar',
        'status_aktif',
        'pendapatan_kamar_coa_id',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'pendapatan_kamar_coa_id' => 'integer',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'pendapatan_kamar_coa_id');
    }
}
