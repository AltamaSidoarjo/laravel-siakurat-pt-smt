<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\SettingBridgingObat;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingBridgingObatService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getSetting(): SettingBridgingObat
    {
        return SettingBridgingObat::query()
            ->with(['pendapatanObatCoa', 'hppObatCoa', 'persediaanObatCoa', 'piutangObatCoa', 'kasUangMukaCoa'])
            ->first() ?? new SettingBridgingObat();
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->where('status_aktif', 1)
            ->where('is_postable', true)
            ->orderBy('kode')
            ->get();
    }

    public function save(array $data): SettingBridgingObat
    {
        return DB::transaction(function () use ($data) {
            $setting = SettingBridgingObat::query()->first();
            $oldData = $setting ? $setting->toArray() : null;

            if ($setting === null) {
                $setting = SettingBridgingObat::query()->create($data);
                $this->logService->log('Setting Akun Obat', 'create', null, $setting->toArray());
            } else {
                $setting->update($data);
                $this->logService->log('Setting Akun Obat', 'update', $oldData, $setting->fresh()->toArray());
            }

            return $setting;
        });
    }
}
