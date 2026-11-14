<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\JurnalUmum;
use App\Services\LogAktifitasService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class JurnalUmumService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logAktivitasService,
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
            ->betweenDates($startDate, $endDate);
    }

    /**
     * @return array{total_count: int, total_nominal: float}
     */
    public function getSummary(string $startDate, string $endDate): array
    {
        $aggregate = JurnalUmum::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(debit), 0) as total_nominal')
            ->first();

        return [
            'total_count' => (int) ($aggregate?->total_count ?? 0),
            'total_nominal' => (float) ($aggregate?->total_nominal ?? 0),
        ];
    }

    public function create(array $data): JurnalUmum
    {
        return DB::transaction(function () use ($data) {
            $rincianPayload = $this->mapRincianPayload($data['rincian']);
            $this->validateBalance((float) $data['debit'], (float) $data['kredit'], $rincianPayload);

            $jurnal = JurnalUmum::query()->create([
                'nomer' => $data['nomer'],
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'debit' => $data['debit'],
                'kredit' => $data['kredit'],
            ]);

            $jurnal->rincian()->createMany($rincianPayload);
            $this->bukuBesarService->syncFromJurnalUmum(
                (int) $jurnal->id,
                $jurnal->nomer,
                $jurnal->tanggal->format('Y-m-d'),
                $jurnal->keterangan,
                $rincianPayload,
            );

            $this->logAktivitasService->log('Jurnal Umum', 'create', null, [
                'nomer' => $jurnal->nomer,
                'tanggal' => $jurnal->tanggal->format('Y-m-d'),
                'keterangan' => $jurnal->keterangan,
                'debit' => $jurnal->debit,
                'kredit' => $jurnal->kredit,
                'rincian' => $rincianPayload,
            ]);

            return $jurnal->load('rincian.coa');
        });
    }

    public function update(JurnalUmum $jurnalUmum, array $data): JurnalUmum
    {
        return DB::transaction(function () use ($jurnalUmum, $data) {
            $rincianPayload = $this->mapRincianPayload($data['rincian']);
            $this->validateBalance((float) $data['debit'], (float) $data['kredit'], $rincianPayload);

            $oldData = [
                'nomer' => $jurnalUmum->nomer,
                'tanggal' => $jurnalUmum->tanggal->format('Y-m-d'),
                'keterangan' => $jurnalUmum->keterangan,
                'debit' => $jurnalUmum->debit,
                'kredit' => $jurnalUmum->kredit,
                'rincian' => $jurnalUmum->rincian->map->only(['coa_id', 'debit', 'kredit', 'catatan'])->all(),
            ];

            $jurnalUmum->update([
                'nomer' => $data['nomer'],
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'debit' => $data['debit'],
                'kredit' => $data['kredit'],
            ]);

            $jurnalUmum->rincian()->delete();
            $jurnalUmum->rincian()->createMany($rincianPayload);
            $this->bukuBesarService->syncFromJurnalUmum(
                (int) $jurnalUmum->id,
                $jurnalUmum->nomer,
                $jurnalUmum->tanggal->format('Y-m-d'),
                $jurnalUmum->keterangan,
                $rincianPayload,
            );

            $this->logAktivitasService->log('Jurnal Umum', 'update', $oldData, [
                'nomer' => $jurnalUmum->nomer,
                'tanggal' => $jurnalUmum->tanggal->format('Y-m-d'),
                'keterangan' => $jurnalUmum->keterangan,
                'debit' => $jurnalUmum->debit,
                'kredit' => $jurnalUmum->kredit,
                'rincian' => $rincianPayload,
            ]);

            return $jurnalUmum->load('rincian.coa');
        });
    }

    public function delete(JurnalUmum $jurnalUmum): void
    {
        DB::transaction(function () use ($jurnalUmum) {
            $this->logAktivitasService->log('Jurnal Umum', 'delete', [
                'nomer' => $jurnalUmum->nomer,
                'tanggal' => $jurnalUmum->tanggal->format('Y-m-d'),
                'keterangan' => $jurnalUmum->keterangan,
                'debit' => $jurnalUmum->debit,
                'kredit' => $jurnalUmum->kredit,
            ]);

            $this->bukuBesarService->deleteBySource('Jurnal Umum', (int) $jurnalUmum->id);
            $jurnalUmum->rincian()->delete();
            $jurnalUmum->delete();
        });
    }

    private function validateBalance(float $debit, float $kredit, array $rincianPayload): void
    {
        if (count($rincianPayload) < 2) {
            throw new DomainException('Jurnal umum minimal harus memiliki dua baris rincian.');
        }

        if (abs($debit - $kredit) > 0.0001) {
            throw new DomainException('Total debit dan kredit header tidak seimbang.');
        }

        $detailDebit = (float) collect($rincianPayload)->sum('debit');
        $detailKredit = (float) collect($rincianPayload)->sum('kredit');

        if (abs($detailDebit - $detailKredit) > 0.0001) {
            throw new DomainException('Total debit dan kredit rincian jurnal umum tidak seimbang.');
        }

        if (abs($debit - $detailDebit) > 0.0001 || abs($kredit - $detailKredit) > 0.0001) {
            throw new DomainException('Total rincian jurnal umum tidak cocok dengan nilai total header.');
        }
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
