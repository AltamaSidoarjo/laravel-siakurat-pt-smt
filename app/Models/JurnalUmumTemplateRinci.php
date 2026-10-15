<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalUmumTemplateRinci extends Model
{
    protected $table = 'jurnal_umum_template_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'jurnal_umum_template_id',
        'coa_id',
        'debit',
        'kredit',
        'catatan',
        'urutan',
    ];

    protected $casts = [
        'jurnal_umum_template_id' => 'integer',
        'coa_id' => 'integer',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
        'urutan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(JurnalUmumTemplate::class, 'jurnal_umum_template_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
