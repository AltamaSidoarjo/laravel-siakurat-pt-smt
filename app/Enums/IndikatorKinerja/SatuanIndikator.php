<?php

namespace App\Enums\IndikatorKinerja;

enum SatuanIndikator: string
{
    // Rasio & Persentase
    case PERSEN = '%';
    case KALI = 'Kali';
    case POIN = 'Poin';

    // Waktu & Durasi (EWS, Turnover, Respon Time, ALOS/TOI)
    case HARI = 'Hari';
    case JAM = 'Jam';
    case MENIT = 'Menit';

    // Finansial / Moneter
    case RUPIAH = 'Rp';
    case JUTA_RUPIAH = 'Juta Rp';

    // Kuantitas Entitas & Operasional RS (SDM, Pasien, Kapasitas Fasilitas, Klaim)
    case ORANG = 'Orang';
    case PASIEN = 'Pasien';
    case TEMPAT_TIDUR = 'TT';
    case DOKUMEN = 'Dokumen';

    // Satuan Majemuk Khusus Eksekutif
    case JUTA_PER_ORG_BLN = 'Juta/org/bln';

    public function label(): string
    {
        return match ($this) {
            self::PERSEN => 'Persentase (%)',
            self::KALI => 'Kali (Perputaran/Rasio)',
            self::POIN => 'Poin / Skor Indeks',
            self::HARI => 'Hari',
            self::JAM => 'Jam',
            self::MENIT => 'Menit',
            self::RUPIAH => 'Rupiah (Rp)',
            self::JUTA_RUPIAH => 'Juta Rupiah (Juta Rp)',
            self::ORANG => 'Orang (SDM / Karyawan)',
            self::PASIEN => 'Pasien / Kunjungan',
            self::TEMPAT_TIDUR => 'Tempat Tidur (TT / Kapasitas)',
            self::DOKUMEN => 'Dokumen / Berkas Klaim',
            self::JUTA_PER_ORG_BLN => 'Juta / Orang / Bulan',
        };
    }
}
