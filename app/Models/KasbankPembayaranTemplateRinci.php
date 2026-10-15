<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasbankPembayaranTemplateRinci extends Model
{
    protected $table = 'kasbank_pembayaran_template_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'kasbank_pembayaran_template_id',
        'coa_id',
        'nominal',
        'catatan',
        'urutan',
    ];

    protected $casts = [
        'kasbank_pembayaran_template_id' => 'integer',
        'coa_id' => 'integer',
        'nominal' => 'decimal:2',
        'urutan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KasbankPembayaranTemplate::class, 'kasbank_pembayaran_template_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
