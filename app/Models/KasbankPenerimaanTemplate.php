<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KasbankPenerimaanTemplate extends Model
{
    protected $table = 'kasbank_penerimaan_template';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nama',
        'coa_id',
        'keterangan',
    ];

    protected $casts = [
        'coa_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(KasbankPenerimaanTemplateRinci::class, 'kasbank_penerimaan_template_id')->orderBy('urutan')->orderBy('id');
    }
}
