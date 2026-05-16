<?php

namespace App\Services;

use App\Models\PreferensiPerusahaan;

class PreferensiPerusahaanService
{
    public function getPrintIdentity(): array
    {
        $preferensi = PreferensiPerusahaan::query()->first();

        return [
            'namaRumahSakit' => $preferensi?->nama_perusahaan ?: config('siakurat.rs_name'),
            'ttdDirektur' => blank($preferensi?->ttd_direktur)
                ? '.................................'
                : $preferensi->ttd_direktur,
            'ttdKabag' => blank($preferensi?->ttd_kabag)
                ? '.................................'
                : $preferensi->ttd_kabag,
        ];
    }
}
