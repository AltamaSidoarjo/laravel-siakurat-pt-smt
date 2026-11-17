<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayananInformasi extends Model
{
    use HasFactory;

    protected $table = 'layanan_informasi';

    protected $fillable = [
        'judul',
        'isi',
        'tipe',
        'tanggal_mulai',
        'tanggal_selesai',
        'tampilkan_popup',
        'status_aktif',
        'urutan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tampilkan_popup' => 'boolean',
        'status_aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status_aktif', true)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('tanggal_mulai')
                    ->orWhere('tanggal_mulai', '<=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $today);
            });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $clean = trim((string) $term);
        if ($clean === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($clean): void {
            $q->where('judul', 'like', "%{$clean}%")
                ->orWhere('isi', 'like', "%{$clean}%");
        });
    }

    public function getAlertTypeAttribute(): string
    {
        return match ($this->tipe) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            default => 'info',
        };
    }

    public function getIconClassAttribute(): string
    {
        return match ($this->tipe) {
            'success' => 'bi-check-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'danger' => 'bi-exclamation-octagon-fill',
            default => 'bi-info-circle-fill',
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return match ($this->tipe) {
            'success' => 'text-bg-success',
            'warning' => 'text-bg-warning text-dark',
            'danger' => 'text-bg-danger',
            default => 'text-bg-info text-dark',
        };
    }

    public function getTipeLabelAttribute(): string
    {
        return match ($this->tipe) {
            'success' => 'Sukses',
            'warning' => 'Peringatan',
            'danger' => 'Penting / Bahaya',
            default => 'Informasi',
        };
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
