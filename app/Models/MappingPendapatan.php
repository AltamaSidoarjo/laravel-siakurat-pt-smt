<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MappingPendapatan extends Model
{
    protected $table = 'mapping_pendapatan';

    protected $primaryKey = 'mapping_pendapatan_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kode_jenis_perawatan',
        'kode_penjamin',
        'kelas',
        'kode_poli',
        'coa_id',
        'user_create',
        'user_edit',
        'sumber_tindakan',
        'nm_perawatan',
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
