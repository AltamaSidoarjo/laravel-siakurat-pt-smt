<?php

namespace App\Models;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndikatorKinerjaVariabelManual extends Model
{
    use HasFactory;

    protected $table = 'indikator_kinerja_variabel_manual';

    protected $fillable = [
        'kode',
        'nama',
        'satuan',
        'deskripsi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'satuan' => SatuanIndikator::class,
        ];
    }

    public function indikatorKinerja(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'variabel_manual_id');
    }

    public function manualData(): HasMany
    {
        return $this->hasMany(IndikatorKinerjaManualData::class, 'variabel_id');
    }
}
