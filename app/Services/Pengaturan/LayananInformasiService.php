<?php

namespace App\Services\Pengaturan;

use App\Models\LayananInformasi;
use App\Services\LogAktifitasService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LayananInformasiService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {}

    public function paginate(?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return LayananInformasi::query()
            ->search($search)
            ->orderBy('urutan')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getActiveInformasi(): Collection
    {
        return LayananInformasi::query()
            ->active()
            ->orderBy('urutan')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): LayananInformasi
    {
        $payload = $this->payload($data);
        $payload['created_by'] = auth()->id();

        $informasi = LayananInformasi::query()->create($payload);

        $this->logService->log(
            'Layanan Informasi',
            'create',
            null,
            $informasi->only(['judul', 'tipe', 'status_aktif', 'tampilkan_popup', 'urutan'])
        );

        return $informasi;
    }

    public function update(LayananInformasi $layananInformasi, array $data): LayananInformasi
    {
        $oldData = $layananInformasi->only(['judul', 'tipe', 'status_aktif', 'tampilkan_popup', 'urutan']);
        $layananInformasi->update($this->payload($data));

        $this->logService->log(
            'Layanan Informasi',
            'update',
            $oldData,
            $layananInformasi->only(['judul', 'tipe', 'status_aktif', 'tampilkan_popup', 'urutan'])
        );

        return $layananInformasi->refresh();
    }

    public function delete(LayananInformasi $layananInformasi): bool
    {
        $oldData = $layananInformasi->only(['judul', 'tipe', 'status_aktif', 'tampilkan_popup', 'urutan']);
        $layananInformasi->deleteOrFail();

        $this->logService->log('Layanan Informasi', 'delete', $oldData);

        return true;
    }

    private function payload(array $data): array
    {
        return [
            'judul' => trim((string) ($data['judul'] ?? '')),
            'isi' => trim((string) ($data['isi'] ?? '')),
            'tipe' => (string) ($data['tipe'] ?? 'info'),
            'tanggal_mulai' => ! empty($data['tanggal_mulai']) ? $data['tanggal_mulai'] : null,
            'tanggal_selesai' => ! empty($data['tanggal_selesai']) ? $data['tanggal_selesai'] : null,
            'tampilkan_popup' => (bool) ($data['tampilkan_popup'] ?? false),
            'status_aktif' => (bool) ($data['status_aktif'] ?? true),
            'urutan' => (int) ($data['urutan'] ?? 0),
        ];
    }
}
