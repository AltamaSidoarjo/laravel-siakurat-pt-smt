<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingRbaRinci extends Model
{
    protected $table = 'setting_rba_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'setting_rba_id',
        'bulan',
        'nominal',
    ];

    protected $casts = [
        'setting_rba_id' => 'integer',
        'bulan' => 'integer',
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function settingRba()
    {
        return $this->belongsTo(SettingRba::class, 'setting_rba_id');
    }
}
