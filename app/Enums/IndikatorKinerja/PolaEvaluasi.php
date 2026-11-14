<?php

namespace App\Enums\IndikatorKinerja;

enum PolaEvaluasi: string
{
    case HIGHER_IS_BETTER = 'HIGHER_IS_BETTER';
    case LOWER_IS_BETTER = 'LOWER_IS_BETTER';
    case TARGET_BAND = 'TARGET_BAND';

    public function label(): string
    {
        return match ($this) {
            self::HIGHER_IS_BETTER => 'Higher is Better (Makin Tinggi Makin Baik)',
            self::LOWER_IS_BETTER => 'Lower is Better (Makin Rendah Makin Baik)',
            self::TARGET_BAND => 'Target Band (Rentang Target Optimal)',
        };
    }
}
