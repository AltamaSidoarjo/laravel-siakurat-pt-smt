<?php

namespace App\Services;

use App\Models\LogAktifitas;
use Illuminate\Support\Facades\Auth;

class LogAktifitasService
{
    public function log(string $modul, string $tipe, ?array $old = null, ?array $new = null): void
    {
        $payload = array_filter(['old' => $old, 'new' => $new], fn ($v) => $v !== null);

        LogAktifitas::query()->create([
            'nama_user' => Auth::user()?->name ?? 'system',
            'modul' => $modul,
            'tipe' => $tipe,
            'payload' => json_encode($payload),
        ]);
    }
}
