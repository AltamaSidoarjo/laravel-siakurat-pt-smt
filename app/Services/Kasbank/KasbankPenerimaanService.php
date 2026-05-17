<?php

namespace App\Services\Kasbank;

use App\Models\Coa;
use App\Models\KasbankPenerimaan;
use App\Services\Bukubesar\BukuBesarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class KasbankPenerimaanService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
    ) {
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return KasbankPenerimaan::query()
            ->with('coa:id,kode,nama')
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function create(array $data): KasbankPenerimaan
    {
        $kasbankPenerimaan = KasbankPenerimaan::query()->create([
            'coa_id' => $data['coa_id'],
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'total' => $data['total'],
        ]);

        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $kasbankPenerimaan->rincian()->createMany($rincianPayload);

        $this->bukuBesarService->syncFromKasbankPenerimaan(
            (int) $kasbankPenerimaan->id,
            (int) $kasbankPenerimaan->coa_id,
            $kasbankPenerimaan->nomer,
            $kasbankPenerimaan->tanggal->format('Y-m-d'),
            $kasbankPenerimaan->keterangan,
            (float) $kasbankPenerimaan->total,
            $rincianPayload,
        );

        return $kasbankPenerimaan->load(['coa', 'rincian.coa']);
    }

    public function update(KasbankPenerimaan $kasbankPenerimaan, array $data): KasbankPenerimaan
    {
        $kasbankPenerimaan->update([
            'coa_id' => $data['coa_id'],
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'total' => $data['total'],
        ]);

        $kasbankPenerimaan->rincian()->delete();
        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $kasbankPenerimaan->rincian()->createMany($rincianPayload);

        $this->bukuBesarService->syncFromKasbankPenerimaan(
            (int) $kasbankPenerimaan->id,
            (int) $kasbankPenerimaan->coa_id,
            $kasbankPenerimaan->nomer,
            $kasbankPenerimaan->tanggal->format('Y-m-d'),
            $kasbankPenerimaan->keterangan,
            (float) $kasbankPenerimaan->total,
            $rincianPayload,
        );

        return $kasbankPenerimaan->load(['coa', 'rincian.coa']);
    }

    public function delete(KasbankPenerimaan $kasbankPenerimaan): void
    {
        $this->bukuBesarService->deleteBySource('Kasbank Penerimaan', (int) $kasbankPenerimaan->id);
        $kasbankPenerimaan->rincian()->delete();
        $kasbankPenerimaan->delete();
    }

    public function mapRincianPayload(array $rincian): array
    {
        return collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(fn ($row) => [
                'coa_id' => $row['coa_id'],
                'nominal' => $row['nominal'] ?? 0,
                'catatan' => $row['catatan'] ?? null,
            ])
            ->values()
            ->all();
    }
}
