<?php

namespace App\Services\Pengaturan;

use App\Models\Pelaksana;
use App\Services\LogAktifitasService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PelaksanaManagementService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {}

    public function paginate(?string $search): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return Pelaksana::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_proyek', 'like', '%'.$search.'%')
                        ->orWhere('nama_pelaksana', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('no_proyek')
            ->paginate(25)
            ->withQueryString();
    }

    public function create(array $data): Pelaksana
    {
        $pelaksana = Pelaksana::query()->create($this->payload($data));
        $this->logService->log('Master Pelaksana', 'create', null, $pelaksana->only(['no_proyek', 'nama_pelaksana', 'status_aktif']));

        return $pelaksana;
    }

    public function update(Pelaksana $pelaksana, array $data): Pelaksana
    {
        $oldData = $pelaksana->only(['no_proyek', 'nama_pelaksana', 'status_aktif']);
        $pelaksana->update($this->payload($data));
        $this->logService->log('Master Pelaksana', 'update', $oldData, $pelaksana->only(['no_proyek', 'nama_pelaksana', 'status_aktif']));

        return $pelaksana->refresh();
    }

    public function delete(Pelaksana $pelaksana): bool
    {
        if ($pelaksana->rincianFakturPenjualan()->exists()) {
            return false;
        }

        $oldData = $pelaksana->only(['no_proyek', 'nama_pelaksana', 'status_aktif']);
        $pelaksana->deleteOrFail();
        $this->logService->log('Master Pelaksana', 'delete', $oldData);

        return true;
    }

    private function payload(array $data): array
    {
        return [
            'no_proyek' => $data['no_proyek'],
            'nama_pelaksana' => $data['nama_pelaksana'],
            'status_aktif' => (bool) ($data['status_aktif'] ?? false),
        ];
    }
}
