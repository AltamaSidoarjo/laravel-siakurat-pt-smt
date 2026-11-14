<?php

namespace App\Enums\IndikatorKinerja;

enum TipeKalkulasi: string
{
    case RASIO = 'RASIO';
    case TURNOVER = 'TURNOVER';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::RASIO => 'Rasio (Pembagian Standar)',
            self::TURNOVER => 'Turnover (Perputaran Hari)',
            self::MANUAL => 'Manual (Entri Non-Akuntansi)',
        };
    }
}
