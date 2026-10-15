<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JurnalUmumTemplate extends Model
{
    protected $table = 'jurnal_umum_template';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nama',
        'keterangan',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rincian(): HasMany
    {
        return $this->hasMany(JurnalUmumTemplateRinci::class, 'jurnal_umum_template_id')->orderBy('urutan')->orderBy('id');
    }
}
