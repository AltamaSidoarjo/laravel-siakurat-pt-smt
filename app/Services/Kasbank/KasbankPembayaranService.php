<?php

namespace App\Services\Kasbank;

use App\Models\Coa;
use App\Models\KasbankPembayaran;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class KasbankPembayaranService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logService,
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
        return KasbankPembayaran::query()
            ->with('coa:id,kode,nama')
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function create(array $data): KasbankPembayaran
    {
        $kasbankPembayaran = KasbankPembayaran::query()->create([
            'coa_id' => $data['coa_id'],
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'total' => $data['total'],
        ]);

        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $kasbankPembayaran->rincian()->createMany($rincianPayload);

        $this->bukuBesarService->syncFromKasbankPembayaran(
            (int) $kasbankPembayaran->id,
            (int) $kasbankPembayaran->coa_id,
            $kasbankPembayaran->nomer,
            $kasbankPembayaran->tanggal->format('Y-m-d'),
            $kasbankPembayaran->keterangan,
            (float) $kasbankPembayaran->total,
            $rincianPayload,
        );

        $this->logService->log('Kasbank Pembayaran', 'create', null, [
            'nomer' => $kasbankPembayaran->nomer,
            'tanggal' => $kasbankPembayaran->tanggal->format('Y-m-d'),
            'coa_id' => $kasbankPembayaran->coa_id,
            'keterangan' => $kasbankPembayaran->keterangan,
            'total' => $kasbankPembayaran->total,
            'rincian' => $rincianPayload,
        ]);

        return $kasbankPembayaran->load(['coa', 'rincian.coa']);
    }

    public function update(KasbankPembayaran $kasbankPembayaran, array $data): KasbankPembayaran
    {
        $oldData = [
            'nomer' => $kasbankPembayaran->nomer,
            'tanggal' => $kasbankPembayaran->tanggal->format('Y-m-d'),
            'coa_id' => $kasbankPembayaran->coa_id,
            'keterangan' => $kasbankPembayaran->keterangan,
            'total' => $kasbankPembayaran->total,
            'rincian' => $kasbankPembayaran->rincian->map->only(['coa_id', 'nominal', 'catatan'])->all(),
        ];

        $kasbankPembayaran->update([
            'coa_id' => $data['coa_id'],
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'] ?? null,
            'total' => $data['total'],
        ]);

        $kasbankPembayaran->rincian()->delete();
        $rincianPayload = $this->mapRincianPayload($data['rincian']);
        $kasbankPembayaran->rincian()->createMany($rincianPayload);

        $this->bukuBesarService->syncFromKasbankPembayaran(
            (int) $kasbankPembayaran->id,
            (int) $kasbankPembayaran->coa_id,
            $kasbankPembayaran->nomer,
            $kasbankPembayaran->tanggal->format('Y-m-d'),
            $kasbankPembayaran->keterangan,
            (float) $kasbankPembayaran->total,
            $rincianPayload,
        );

        $this->logService->log('Kasbank Pembayaran', 'update', $oldData, [
            'nomer' => $kasbankPembayaran->nomer,
            'tanggal' => $kasbankPembayaran->tanggal->format('Y-m-d'),
            'coa_id' => $kasbankPembayaran->coa_id,
            'keterangan' => $kasbankPembayaran->keterangan,
            'total' => $kasbankPembayaran->total,
            'rincian' => $rincianPayload,
        ]);

        return $kasbankPembayaran->load(['coa', 'rincian.coa']);
    }

    public function delete(KasbankPembayaran $kasbankPembayaran): void
    {
        $this->logService->log('Kasbank Pembayaran', 'delete', [
            'nomer' => $kasbankPembayaran->nomer,
            'tanggal' => $kasbankPembayaran->tanggal->format('Y-m-d'),
            'coa_id' => $kasbankPembayaran->coa_id,
            'keterangan' => $kasbankPembayaran->keterangan,
            'total' => $kasbankPembayaran->total,
        ]);

        $this->bukuBesarService->deleteBySource('Kasbank Pembayaran', (int) $kasbankPembayaran->id);
        $kasbankPembayaran->rincian()->delete();
        $kasbankPembayaran->delete();
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
