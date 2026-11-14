<?php

namespace App\Enums\IndikatorKinerja;

enum BasisPenyebut: string
{
    case COA = 'COA';
    case RBA = 'RBA';
    case MANUAL = 'MANUAL';
    case NONE = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::COA => 'Akun COA (Mutasi Transaksi)',
            self::RBA => 'Target RBA (Rencana Bisnis dan Anggaran)',
            self::MANUAL => 'Variabel Manual Non-Akuntansi',
            self::NONE => 'None (Tidak Ada / Manual)',
        };
    }
}
