<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingBridgingObat extends Model
{
    protected $table = 'setting_bridging_obats';

    protected $fillable = [
        'pendapatan_obat_coa_id',
        'hpp_obat_coa_id',
        'persediaan_obat_coa_id',
        'piutang_obat_coa_id',
        'kas_uang_muka_coa_id',
    ];

    protected $casts = [
        'pendapatan_obat_coa_id' => 'integer',
        'hpp_obat_coa_id' => 'integer',
        'persediaan_obat_coa_id' => 'integer',
        'piutang_obat_coa_id' => 'integer',
        'kas_uang_muka_coa_id' => 'integer',
    ];

    public function pendapatanObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'pendapatan_obat_coa_id');
    }

    public function hppObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'hpp_obat_coa_id');
    }

    public function persediaanObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'persediaan_obat_coa_id');
    }

    public function piutangObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'piutang_obat_coa_id');
    }

    public function kasUangMukaCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'kas_uang_muka_coa_id');
    }
}
