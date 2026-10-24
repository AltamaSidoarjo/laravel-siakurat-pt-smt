<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingBridgingPembelian extends Model
{
    protected $table = 'setting_bridging_pembelians';

    protected $fillable = [
        'persediaan_obat_coa_id',
        'ppn_masukan_coa_id',
        'hutang_obat_coa_id',
        'persediaan_non_medis_coa_id',
        'hutang_non_medis_coa_id',
    ];

    protected $casts = [
        'persediaan_obat_coa_id' => 'integer',
        'ppn_masukan_coa_id' => 'integer',
        'hutang_obat_coa_id' => 'integer',
        'persediaan_non_medis_coa_id' => 'integer',
        'hutang_non_medis_coa_id' => 'integer',
    ];

    public function persediaanObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'persediaan_obat_coa_id');
    }

    public function ppnMasukanCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'ppn_masukan_coa_id');
    }

    public function hutangObatCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'hutang_obat_coa_id');
    }

    public function persediaanNonMedisCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'persediaan_non_medis_coa_id');
    }

    public function hutangNonMedisCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'hutang_non_medis_coa_id');
    }
}
