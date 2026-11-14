<?php

namespace App\Enums\IndikatorKinerja;

enum PeranCoa: string
{
    case NUMERATOR = 'numerator';
    case DENOMINATOR = 'denominator';
    case ADDITION = 'addition';
    case DEDUCTION = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::NUMERATOR => 'Numerator (Pembilang)',
            self::DENOMINATOR => 'Denominator (Penyebut)',
            self::ADDITION => 'Addition (Penambah / Add-back)',
            self::DEDUCTION => 'Deduction (Pengurang)',
        };
    }
}
