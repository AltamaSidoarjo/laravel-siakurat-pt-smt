<?php

namespace App\Models;

use App\Enums\IndikatorKinerja\PeranCoa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorKinerjaMappingCoa extends Model
{
    use HasFactory;

    protected $table = 'indikator_kinerja_mapping_coa';

    protected $fillable = [
        'indikator_id',
        'coa_id',
        'peran',
        'is_excluded',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peran' => PeranCoa::class,
            'is_excluded' => 'boolean',
        ];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
