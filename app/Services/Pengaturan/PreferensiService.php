<?php

namespace App\Services\Pengaturan;

use App\Models\PreferensiPerusahaan;
use App\Services\LogAktifitasService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PreferensiService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }
    public function getFormData(): PreferensiPerusahaan
    {
        return PreferensiPerusahaan::query()->first() ?? new PreferensiPerusahaan([
            'nama_perusahaan' => '',
            'logo_perusahaan' => '',
            'ttd_kabag' => '',
            'ttd_direktur' => '',
        ]);
    }

    public function save(array $data, ?UploadedFile $logoFile): PreferensiPerusahaan
    {
        $preferensi = empty($data['id'])
            ? (PreferensiPerusahaan::query()->first() ?? new PreferensiPerusahaan())
            : (PreferensiPerusahaan::query()->find($data['id']) ?? new PreferensiPerusahaan());

        $currentLogo = (string) ($preferensi->logo_perusahaan ?? $data['logo_perusahaan'] ?? '');

        if ($logoFile !== null) {
            $data['logo_perusahaan'] = $this->storeLogo($logoFile, $currentLogo);
        } else {
            $data['logo_perusahaan'] = $data['logo_perusahaan'] ?? $currentLogo;
        }

        $preferensi->fill([
            'nama_perusahaan' => $data['nama_perusahaan'] ?? null,
            'logo_perusahaan' => $data['logo_perusahaan'] ?? null,
            'ttd_kabag' => $data['ttd_kabag'] ?? null,
            'ttd_direktur' => $data['ttd_direktur'] ?? null,
        ]);

        $preferensi->save();

        $this->logService->log('Preferensi', 'update', null, [
            'nama_perusahaan' => $preferensi->nama_perusahaan,
        ]);

        return $preferensi->refresh();
    }

    private function storeLogo(UploadedFile $logoFile, string $currentLogo): string
    {
        $directory = public_path('uploads/logo');
        File::ensureDirectoryExists($directory);

        $extension = strtolower($logoFile->getClientOriginalExtension());
        $fileName = 'logo_'.Str::lower(Str::random(32)).'.'.$extension;
        $logoFile->move($directory, $fileName);

        $this->deleteOldLogo($currentLogo, $directory.DIRECTORY_SEPARATOR.$fileName);

        return '/uploads/logo/'.$fileName;
    }

    private function deleteOldLogo(string $currentLogo, string $newFilePath): void
    {
        if (! Str::startsWith($currentLogo, '/uploads/logo/')) {
            return;
        }

        $oldFilePath = public_path(ltrim($currentLogo, '/'));

        if ($oldFilePath === $newFilePath || ! File::exists($oldFilePath)) {
            return;
        }

        File::delete($oldFilePath);
    }
}
