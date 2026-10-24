<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\SettingBridgingPembelian;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingBridgingPembelianService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getSetting(): SettingBridgingPembelian
    {
        return SettingBridgingPembelian::query()
            ->with(['persediaanObatCoa', 'ppnMasukanCoa', 'hutangObatCoa', 'persediaanNonMedisCoa', 'hutangNonMedisCoa'])
            ->first() ?? new SettingBridgingPembelian();
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->active()
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama']);
    }

    public function save(array $data): SettingBridgingPembelian
    {
        return DB::transaction(function () use ($data) {
            $setting = SettingBridgingPembelian::query()->first();
            $oldData = $setting ? $setting->toArray() : null;

            if ($setting === null) {
                $setting = SettingBridgingPembelian::query()->create($data);
                $this->logService->log('Setting Akun Pembelian', 'create', null, $setting->toArray());
            } else {
                $setting->update($data);
                $this->logService->log('Setting Akun Pembelian', 'update', $oldData, $setting->fresh()->toArray());
            }

            return $setting;
        });
    }
}
