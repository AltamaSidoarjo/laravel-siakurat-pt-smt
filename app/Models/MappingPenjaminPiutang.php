<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingPenjaminPiutang extends Model
{
    protected $table = 'mapping_penjamin_piutang';

    protected $fillable = [
        'penjamin_id',
        'nama_penjamin',
        'coa_id',
    ];

    protected $casts = [
        'coa_id' => 'integer',
    ];

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
