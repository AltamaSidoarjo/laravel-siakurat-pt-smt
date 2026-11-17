<?php

namespace App\Services\Kasbank;

use App\Models\Coa;
use App\Models\KasbankPenerimaanTemplate;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KasbankPenerimaanTemplateService
{
    public function __construct(
        private readonly LogAktifitasService $logAktivitasService,
    ) {}

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getIndexQuery(): Builder
    {
        return KasbankPenerimaanTemplate::query()
            ->with('coa:id,kode,nama')
            ->withCount('rincian')
            ->with('rincian.coa')
            ->orderBy('nama');
    }

    public function getTemplateOptions(): Collection
    {
        return KasbankPenerimaanTemplate::query()
            ->with('coa:id,kode,nama')
            ->orderBy('nama')
            ->get(['id', 'nama', 'coa_id', 'keterangan']);
    }

    public function getTemplateWithRincian(int|string $id): KasbankPenerimaanTemplate
    {
        return KasbankPenerimaanTemplate::query()
            ->with([
                'coa:id,kode,nama',
                'rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
                'rincian.coa:id,kode,nama',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): KasbankPenerimaanTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = KasbankPenerimaanTemplate::query()->create([
                'nama' => $data['nama'],
                'coa_id' => ! empty($data['coa_id']) ? (int) $data['coa_id'] : null,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
            if (! empty($rincianPayload)) {
                $template->rincian()->createMany($rincianPayload);
            }

            $this->logAktivitasService->log('Template Kasbank Penerimaan', 'create', null, [
                'nama' => $template->nama,
                'coa_id' => $template->coa_id,
                'keterangan' => $template->keterangan,
                'rincian' => $rincianPayload,
            ]);

            return $template->load(['coa', 'rincian.coa']);
        });
    }

    public function update(KasbankPenerimaanTemplate $template, array $data): KasbankPenerimaanTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $oldData = [
                'nama' => $template->nama,
                'coa_id' => $template->coa_id,
                'keterangan' => $template->keterangan,
                'rincian' => $template->rincian->map->only(['coa_id', 'nominal', 'catatan', 'urutan'])->all(),
            ];

            $template->update([
                'nama' => $data['nama'],
                'coa_id' => ! empty($data['coa_id']) ? (int) $data['coa_id'] : null,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $template->rincian()->delete();
            $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
            if (! empty($rincianPayload)) {
                $template->rincian()->createMany($rincianPayload);
            }

            $this->logAktivitasService->log('Template Kasbank Penerimaan', 'update', $oldData, [
                'nama' => $template->nama,
                'coa_id' => $template->coa_id,
                'keterangan' => $template->keterangan,
                'rincian' => $rincianPayload,
            ]);

            return $template->load(['coa', 'rincian.coa']);
        });
    }

    public function delete(KasbankPenerimaanTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $this->logAktivitasService->log('Template Kasbank Penerimaan', 'delete', [
                'nama' => $template->nama,
                'coa_id' => $template->coa_id,
                'keterangan' => $template->keterangan,
                'rincian_count' => $template->rincian()->count(),
            ]);

            $template->rincian()->delete();
            $template->delete();
        });
    }

    public function mapRincianPayload(array $rincian): array
    {
        return collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->values()
            ->map(fn ($row, $index) => [
                'coa_id' => (int) $row['coa_id'],
                'nominal' => (float) ($row['nominal'] ?? 0),
                'catatan' => $row['catatan'] ?? null,
                'urutan' => $index + 1,
            ])
            ->all();
    }
}
