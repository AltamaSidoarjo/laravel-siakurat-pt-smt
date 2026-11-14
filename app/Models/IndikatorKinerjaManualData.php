<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorKinerjaManualData extends Model
{
    use HasFactory;

    protected $table = 'indikator_kinerja_manual_data';

    protected $fillable = [
        'variabel_id',
        'tahun',
        'bulan',
        'nilai',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'nilai' => 'decimal:2',
        ];
    }

    public function variabel(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerjaVariabelManual::class, 'variabel_id');
    }
}
