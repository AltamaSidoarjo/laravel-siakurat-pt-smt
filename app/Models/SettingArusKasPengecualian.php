<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingArusKasPengecualian extends Model
{
    protected $table = 'setting_arus_kas_pengecualian';

    protected $fillable = [
        'coa_id',
        'alasan',
    ];

    protected $casts = [
        'coa_id' => 'integer',
    ];

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
