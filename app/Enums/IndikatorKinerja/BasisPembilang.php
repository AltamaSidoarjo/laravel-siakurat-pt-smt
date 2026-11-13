<?php

namespace App\Enums\IndikatorKinerja;

enum BasisPembilang: string
{
    case COA = 'COA';
    case SURPLUS = 'SURPLUS';
    case NONE = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::COA => 'Akun COA (Mutasi / Saldo Akumulatif)',
            self::SURPLUS => 'Surplus (Laba Bersih Berjalan)',
            self::NONE => 'None (Tidak Ada / Manual)',
        };
    }
}
