<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingCoaSimrs extends Model
{
    protected $table = 'mapping_coa_simrs';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'kode_rekening',
        'coa_id',
        'kode_coa',
        'nama_coa',
        'nama_rekening',
    ];

    protected $casts = [
        'coa_id' => 'integer',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
