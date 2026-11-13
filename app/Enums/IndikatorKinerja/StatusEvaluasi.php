<?php

namespace App\Enums\IndikatorKinerja;

enum StatusEvaluasi: string
{
    case HIJAU = 'hijau';
    case KUNING = 'kuning';
    case MERAH = 'merah';
    case NETRAL = 'netral';

    public function label(): string
    {
        return match ($this) {
            self::HIJAU => 'Hijau (Baik / Efisien)',
            self::KUNING => 'Kuning (Waspada)',
            self::MERAH => 'Merah (Kritis)',
            self::NETRAL => 'Netral (N/A)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::HIJAU => 'success',
            self::KUNING => 'warning',
            self::MERAH => 'danger',
            self::NETRAL => 'secondary',
        };
    }
}
