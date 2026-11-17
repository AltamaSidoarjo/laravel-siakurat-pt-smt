<?php

namespace App\Services\Pengaturan;

use App\Models\Supplier;
use App\Services\LogAktifitasService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierManagementService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {}

    public function paginate(?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Supplier::query()
            ->search($search)
            ->orderBy('kode_supplier')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Supplier
    {
        $supplier = Supplier::query()->create($this->payload($data));
        $this->logService->log(
            'Master Supplier',
            'create',
            null,
            $supplier->only(['kode_supplier', 'nama_supplier', 'kategori_supplier', 'status_aktif'])
        );

        return $supplier;
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $oldData = $supplier->only(['kode_supplier', 'nama_supplier', 'kategori_supplier', 'status_aktif']);
        $supplier->update($this->payload($data));
        $this->logService->log(
            'Master Supplier',
            'update',
            $oldData,
            $supplier->only(['kode_supplier', 'nama_supplier', 'kategori_supplier', 'status_aktif'])
        );

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): bool
    {
        if ($supplier->fakturPembelians()->exists() || $supplier->pembayaranPembelians()->exists()) {
            return false;
        }

        $oldData = $supplier->only(['kode_supplier', 'nama_supplier', 'kategori_supplier', 'status_aktif']);
        $supplier->deleteOrFail();
        $this->logService->log('Master Supplier', 'delete', $oldData);

        return true;
    }

    private function payload(array $data): array
    {
        return [
            'kode_supplier' => trim((string) $data['kode_supplier']),
            'nama_supplier' => trim((string) $data['nama_supplier']),
            'kategori_supplier' => isset($data['kategori_supplier']) && trim((string) $data['kategori_supplier']) !== ''
                ? trim((string) $data['kategori_supplier'])
                : null,
            'email_supplier' => isset($data['email_supplier']) && trim((string) $data['email_supplier']) !== ''
                ? trim((string) $data['email_supplier'])
                : null,
            'alamat_supplier' => isset($data['alamat_supplier']) && trim((string) $data['alamat_supplier']) !== ''
                ? trim((string) $data['alamat_supplier'])
                : null,
            'status_aktif' => (bool) ($data['status_aktif'] ?? false),
        ];
    }
}
