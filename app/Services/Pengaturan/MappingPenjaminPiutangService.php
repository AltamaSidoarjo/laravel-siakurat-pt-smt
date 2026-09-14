<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\MappingPenjaminPiutang;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class MappingPenjaminPiutangService
{
    public function __construct(
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
        private readonly LogAktifitasService $logService,
    ) {}

    public function getIndexData(): EloquentCollection
    {
        return MappingPenjaminPiutang::query()
            ->with('coa:id,kode,nama')
            ->orderBy('nama_penjamin')
            ->orderBy('penjamin_id')
            ->get();
    }

    public function getAvailablePenjaminOptions(): Collection
    {
        $mappedIds = MappingPenjaminPiutang::query()
            ->pluck('penjamin_id')
            ->map(fn (mixed $id) => (string) $id);

        return $this->billingPendapatanApiService
            ->getPenjaminOptions()
            ->reject(fn (array $penjamin) => $mappedIds->containsStrict((string) $penjamin['id']))
            ->values();
    }

    public function getCoaOptions(): EloquentCollection
    {
        return Coa::query()
            ->selectableTransaction()
            ->where('is_postable', true)
            ->whereRaw('LOWER(tipe_coa) LIKE ?', ['%piutang%'])
            ->get(['id', 'kode', 'nama']);
    }

    public function create(array $data): MappingPenjaminPiutang
    {
        $penjaminId = trim((string) $data['penjamin_id']);
        $penjamin = $this->billingPendapatanApiService
            ->getPenjaminOptions()
            ->first(fn (array $option) => (string) $option['id'] === $penjaminId);

        if ($penjamin === null) {
            throw new RuntimeException('Penjamin tidak ditemukan pada Billing API.');
        }

        $coa = Coa::query()->withCount('children')->find((int) $data['coa_id']);
        $this->ensureValidReceivableCoa($coa);

        $mapping = MappingPenjaminPiutang::query()->create([
            'penjamin_id' => $penjaminId,
            'nama_penjamin' => (string) $penjamin['nama'],
            'coa_id' => (int) $coa->id,
        ]);

        $this->logService->log('Mapping Penjamin', 'create', null, [
            'penjamin_id' => $mapping->penjamin_id,
            'nama_penjamin' => $mapping->nama_penjamin,
            'coa_id' => $mapping->coa_id,
        ]);

        return $mapping;
    }

    public function delete(MappingPenjaminPiutang $mapping): void
    {
        $this->logService->log('Mapping Penjamin', 'delete', [
            'penjamin_id' => $mapping->penjamin_id,
            'nama_penjamin' => $mapping->nama_penjamin,
            'coa_id' => $mapping->coa_id,
        ]);

        $mapping->delete();
    }

    private function ensureValidReceivableCoa(?Coa $coa): void
    {
        if ($coa === null
            || (int) $coa->status_aktif !== 1
            || ! (bool) $coa->is_postable
            || (int) $coa->children_count > 0
            || ! str_contains(Str::lower((string) $coa->tipe_coa), 'piutang')) {
            throw new RuntimeException(
                'Akun harus merupakan COA piutang yang aktif, postable, dan tidak memiliki akun turunan.',
            );
        }
    }
}
