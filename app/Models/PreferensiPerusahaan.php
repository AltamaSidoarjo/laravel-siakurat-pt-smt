<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferensiPerusahaan extends Model
{
    protected $table = 'preferensi_perusahaan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'coa_id',
        'nama_perusahaan',
        'shortname',
        'npwp_perusahaan',
        'no_telp_perusahaan',
        'email_perusahaan',
        'nama_penandatangan',
        'alamat_perusahaan',
        'logo_perusahaan',
        'ttd_kabag',
        'ttd_direktur',
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
