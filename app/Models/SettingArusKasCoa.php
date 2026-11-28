<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingArusKasCoa extends Model
{
    protected $table = 'setting_arus_kas_coa';

    protected $fillable = [
        'setting_arus_kas_id',
        'coa_id',
    ];

    protected $casts = [
        'setting_arus_kas_id' => 'integer',
        'coa_id' => 'integer',
    ];

    public function settingArusKas(): BelongsTo
    {
        return $this->belongsTo(SettingArusKas::class, 'setting_arus_kas_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
