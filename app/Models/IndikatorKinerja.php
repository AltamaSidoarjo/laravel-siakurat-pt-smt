<?php

namespace App\Models;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndikatorKinerja extends Model
{
    use HasFactory;

    protected $table = 'indikator_kinerja';

    protected $fillable = [
        'kode',
        'nama',
        'satuan',
        'tipe_kalkulasi',
        'basis_pembilang',
        'basis_penyebut',
        'variabel_manual_id',
        'definisi_operasional',
        'urutan',
        'status_aktif',
        'pola_evaluasi',
        'target_hijau_min',
        'target_hijau_max',
        'target_kuning_min',
        'target_kuning_max',
        'target_merah_min',
        'target_merah_max',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'satuan' => SatuanIndikator::class,
            'tipe_kalkulasi' => TipeKalkulasi::class,
            'basis_pembilang' => BasisPembilang::class,
            'basis_penyebut' => BasisPenyebut::class,
            'pola_evaluasi' => PolaEvaluasi::class,
            'status_aktif' => 'boolean',
            'urutan' => 'integer',
            'target_hijau_min' => 'decimal:2',
            'target_hijau_max' => 'decimal:2',
            'target_kuning_min' => 'decimal:2',
            'target_kuning_max' => 'decimal:2',
            'target_merah_min' => 'decimal:2',
            'target_merah_max' => 'decimal:2',
        ];
    }

    public function variabelManual(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerjaVariabelManual::class, 'variabel_manual_id');
    }

    public function mappingCoa(): HasMany
    {
        return $this->hasMany(IndikatorKinerjaMappingCoa::class, 'indikator_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_aktif', true)->orderBy('urutan');
    }
}
