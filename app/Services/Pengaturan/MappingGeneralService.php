<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\MappingCoaSimrs;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MappingGeneralService
{
    public function getIndexData(): EloquentCollection
    {
        return MappingCoaSimrs::query()
            ->orderBy('kode_rekening')
            ->get();
    }

    public function getCoaOptions(): EloquentCollection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getRekeningOptions(): Collection
    {
        $existingMapping = MappingCoaSimrs::query()
            ->pluck('kode_rekening');

        return collect(DB::connection('simrs')->select(
            'SELECT kd_rek AS kode_rekening, nm_rek AS nama_rekening FROM rekening ORDER BY kd_rek ASC'
        ))
            ->map(fn (object $row) => [
                'kode_rekening' => (string) $row->kode_rekening,
                'nama_rekening' => blank($row->nama_rekening ?? null) ? null : (string) $row->nama_rekening,
            ])
            ->reject(fn (array $row) => $existingMapping->contains($row['kode_rekening']))
            ->values();
    }

    public function create(array $data): MappingCoaSimrs
    {
        $coa = Coa::query()
            ->findOrFail($data['coa_id'], ['id', 'kode', 'nama']);

        $rekening = collect(DB::connection('simrs')->select(
            'SELECT kd_rek AS kode_rekening, nm_rek AS nama_rekening FROM rekening WHERE kd_rek = ? LIMIT 1',
            [$data['kode_rekening']]
        ))->first();

        return MappingCoaSimrs::query()->create([
            'kode_rekening' => $data['kode_rekening'],
            'coa_id' => (int) $data['coa_id'],
            'kode_coa' => $coa->kode,
            'nama_coa' => $coa->nama,
            'nama_rekening' => (string) ($rekening->nama_rekening ?? ''),
        ]);
    }

    public function delete(MappingCoaSimrs $mappingCoaSimrs): void
    {
        $mappingCoaSimrs->delete();
    }
}
