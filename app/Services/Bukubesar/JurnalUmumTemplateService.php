<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\JurnalUmumTemplate;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class JurnalUmumTemplateService
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
        return JurnalUmumTemplate::query()
            ->withCount('rincian')
            ->with('rincian.coa')
            ->orderBy('nama');
    }

    public function getTemplateOptions(): Collection
    {
        return JurnalUmumTemplate::query()
            ->orderBy('nama')
            ->get(['id', 'nama', 'keterangan']);
    }

    public function getTemplateWithRincian(int|string $id): JurnalUmumTemplate
    {
        return JurnalUmumTemplate::query()
            ->with(['rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'), 'rincian.coa'])
            ->findOrFail($id);
    }

    public function create(array $data): JurnalUmumTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = JurnalUmumTemplate::query()->create([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
            if (! empty($rincianPayload)) {
                $template->rincian()->createMany($rincianPayload);
            }

            $this->logAktivitasService->log('Template Jurnal Umum', 'create', null, [
                'nama' => $template->nama,
                'keterangan' => $template->keterangan,
                'rincian' => $rincianPayload,
            ]);

            return $template->load('rincian.coa');
        });
    }

    public function update(JurnalUmumTemplate $template, array $data): JurnalUmumTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $oldData = [
                'nama' => $template->nama,
                'keterangan' => $template->keterangan,
                'rincian' => $template->rincian->map->only(['coa_id', 'debit', 'kredit', 'catatan', 'urutan'])->all(),
            ];

            $template->update([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $template->rincian()->delete();
            $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
            if (! empty($rincianPayload)) {
                $template->rincian()->createMany($rincianPayload);
            }

            $this->logAktivitasService->log('Template Jurnal Umum', 'update', $oldData, [
                'nama' => $template->nama,
                'keterangan' => $template->keterangan,
                'rincian' => $rincianPayload,
            ]);

            return $template->load('rincian.coa');
        });
    }

    public function delete(JurnalUmumTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $this->logAktivitasService->log('Template Jurnal Umum', 'delete', [
                'nama' => $template->nama,
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
                'debit' => (float) ($row['debit'] ?? 0),
                'kredit' => (float) ($row['kredit'] ?? 0),
                'catatan' => $row['catatan'] ?? null,
                'urutan' => $index + 1,
            ])
            ->all();
    }
}
