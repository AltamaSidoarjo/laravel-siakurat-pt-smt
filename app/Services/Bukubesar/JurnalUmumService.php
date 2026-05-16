<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\JurnalUmum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class JurnalUmumService
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
        return JurnalUmum::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function create(array $data): JurnalUmum
    {
        $jurnal = JurnalUmum::query()->create([
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'debit' => $data['debit'],
            'kredit' => $data['kredit'],
        ]);

        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $jurnal->rincian()->createMany($rincianPayload);
        $this->bukuBesarService->syncFromJurnalUmum(
            (int) $jurnal->id,
            $jurnal->nomer,
            $jurnal->tanggal->format('Y-m-d'),
            $jurnal->keterangan,
            $rincianPayload,
        );

        return $jurnal->load('rincian.coa');
    }

    public function update(JurnalUmum $jurnalUmum, array $data): JurnalUmum
    {
        $jurnalUmum->update([
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'debit' => $data['debit'],
            'kredit' => $data['kredit'],
        ]);

        $jurnalUmum->rincian()->delete();
        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $jurnalUmum->rincian()->createMany($rincianPayload);
        $this->bukuBesarService->syncFromJurnalUmum(
            (int) $jurnalUmum->id,
            $jurnalUmum->nomer,
            $jurnalUmum->tanggal->format('Y-m-d'),
            $jurnalUmum->keterangan,
            $rincianPayload,
        );

        return $jurnalUmum->load('rincian.coa');
    }

    public function delete(JurnalUmum $jurnalUmum): void
    {
        $this->bukuBesarService->deleteBySource('Jurnal Umum', (int) $jurnalUmum->id);
        $jurnalUmum->rincian()->delete();
        $jurnalUmum->delete();
    }

    public function mapRincianPayload(array $rincian): array
    {
        return collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(fn ($row) => [
                'coa_id' => $row['coa_id'],
                'debit' => $row['debit'] ?? 0,
                'kredit' => $row['kredit'] ?? 0,
                'catatan' => $row['catatan'] ?? null,
            ])
            ->values()
            ->all();
    }
}
