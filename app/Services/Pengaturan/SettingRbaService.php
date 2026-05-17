<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\SettingRba;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SettingRbaService
{
    public function getIndexQuery(?int $yearFrom, ?int $yearTo): Builder
    {
        return SettingRba::query()
            ->with('coa:id,kode,nama')
            ->betweenYears($yearFrom, $yearTo)
            ->orderByDesc('tahun')
            ->orderByDesc('id');
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function createMany(array $data): Collection
    {
        $rows = collect($data['items'])->map(function (array $item) use ($data) {
            return SettingRba::query()->create([
                'coa_id' => (int) $item['coa_id'],
                'tahun' => (int) $data['tahun'],
                'total_nominal' => (float) $item['total_nominal'],
                'catatan' => $item['catatan'] ?? null,
                'is_rinci' => false,
            ]);
        });

        return SettingRba::query()
            ->with('coa:id,kode,nama')
            ->whereIn('id', $rows->pluck('id'))
            ->get();
    }

    public function delete(SettingRba $settingRba): void
    {
        $settingRba->rincian()->delete();
        $settingRba->delete();
    }
}
