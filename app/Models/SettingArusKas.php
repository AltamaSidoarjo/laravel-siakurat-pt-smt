<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettingArusKas extends Model
{
    public const KELOMPOK_BAGIAN = [
        'penyesuaian_laba',
        'modal_kerja',
        'investasi',
        'pendanaan',
    ];

    public const KELOMPOK_BAGIAN_LABELS = [
        'penyesuaian_laba' => 'Penyesuaian Laba',
        'modal_kerja' => 'Modal Kerja Operasi',
        'investasi' => 'Aktivitas Investasi',
        'pendanaan' => 'Aktivitas Pendanaan',
    ];

    public const SIFAT_POS = [
        'aset',
        'liabilitas',
        'ekuitas',
        'penambah_laba',
        'pengurang_laba',
    ];

    public const SIFAT_LABA_RUGI = [
        'penambah_laba',
        'pengurang_laba',
    ];

    public const SIFAT_NERACA = [
        'aset',
        'liabilitas',
        'ekuitas',
    ];

    public const TIPE_LABA_RUGI = [
        'Pendapatan',
        'Pendapatan lain',
        'Beban',
        'Beban lain',
        'Beban Pokok Penjualan',
    ];

    protected $table = 'setting_arus_kas';

    protected $fillable = [
        'kode_pos',
        'nama_pos',
        'kelompok_bagian',
        'sifat_pos',
        'pakai_prefix_perubahan',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'pakai_prefix_perubahan' => 'boolean',
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mappingCoa(): HasMany
    {
        return $this->hasMany(SettingArusKasCoa::class, 'setting_arus_kas_id');
    }

    public function coas(): BelongsToMany
    {
        return $this->belongsToMany(
            Coa::class,
            'setting_arus_kas_coa',
            'setting_arus_kas_id',
            'coa_id'
        )->withTimestamps();
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE kelompok_bagian 
                WHEN 'penyesuaian_laba' THEN 1 
                WHEN 'modal_kerja' THEN 2 
                WHEN 'investasi' THEN 3 
                WHEN 'pendanaan' THEN 4 
                ELSE 5 END")
            ->orderBy('urutan')
            ->orderBy('id');
    }

    /**
     * Format cash-flow line label with dynamic increase/decrease prefix for balance-sheet items.
     */
    public function formatLabelPos(float $perubahan): string
    {
        $namaPos = (string) $this->nama_pos;

        if (! $this->pakai_prefix_perubahan || in_array($this->sifat_pos, self::SIFAT_LABA_RUGI, true)) {
            return $namaPos;
        }

        $prefixes = match ($this->sifat_pos) {
            'aset' => ['(Kenaikan) ', 'Penurunan ', 'Penurunan (kenaikan) '],
            'liabilitas', 'ekuitas' => ['Kenaikan ', '(Penurunan) ', 'Kenaikan (penurunan) '],
            default => null,
        };

        if ($prefixes === null) {
            return $namaPos;
        }

        $prefix = match (true) {
            $perubahan > 0.005 => $prefixes[0],
            $perubahan < -0.005 => $prefixes[1],
            default => $prefixes[2],
        };

        return $prefix.$namaPos;
    }
}
