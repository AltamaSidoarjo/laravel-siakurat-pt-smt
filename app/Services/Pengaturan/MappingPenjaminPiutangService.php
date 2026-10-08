<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\MappingPenjaminPiutang;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class MappingPenjaminPiutangService
{
    public function __construct(
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
        private readonly LogAktifitasService $logService,
    ) {}

    public function getIndexQuery(): Builder
    {
        return MappingPenjaminPiutang::query()
            ->leftJoin('coa', 'coa.id', '=', 'mapping_penjamin_piutang.coa_id')
            ->select([
                'mapping_penjamin_piutang.*',
                'coa.kode as coa_kode',
                'coa.nama as coa_nama',
            ])
            ->orderBy('nama_penjamin')
            ->orderBy('jenis_layanan')
            ->orderBy('mapping_penjamin_piutang.penjamin_id');
    }

    public function getAvailablePenjaminOptions(): Collection
    {
        $mappedPairs = MappingPenjaminPiutang::query()
            ->get(['penjamin_id', 'jenis_layanan'])
            ->mapWithKeys(fn (MappingPenjaminPiutang $mapping) => [
                $mapping->jenis_layanan.'|'.$mapping->penjamin_id => true,
            ]);

        return $this->billingPendapatanApiService
            ->getPenjaminOptions()
            ->flatMap(fn (array $penjamin) => collect([
                ['jenis_layanan' => 'rawat_jalan', 'label' => 'Rawat Jalan'],
                ['jenis_layanan' => 'rawat_inap', 'label' => 'Rawat Inap'],
                ['jenis_layanan' => 'igd', 'label' => 'IGD'],
            ])->map(fn (array $layanan) => [
                ...$penjamin,
                'jenis_layanan' => $layanan['jenis_layanan'],
                'label' => $layanan['label'],
            ]))
            ->reject(fn (array $option) => $mappedPairs->has(
                $option['jenis_layanan'].'|'.(string) $option['id'],
            ))
            ->values();
    }

    public function getCoaOptions(): EloquentCollection
    {
        return Coa::query()
            ->selectableTransaction()
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
        $this->ensureValidActiveLeafCoa($coa);

        $mapping = MappingPenjaminPiutang::query()->create([
            'penjamin_id' => $penjaminId,
            'nama_penjamin' => (string) $penjamin['nama'],
            'jenis_layanan' => (string) $data['jenis_layanan'],
            'coa_id' => (int) $coa->id,
        ]);

        $this->logService->log('Mapping Penjamin', 'create', null, [
            'penjamin_id' => $mapping->penjamin_id,
            'nama_penjamin' => $mapping->nama_penjamin,
            'jenis_layanan' => $mapping->jenis_layanan,
            'coa_id' => $mapping->coa_id,
        ]);

        return $mapping;
    }

    public function delete(MappingPenjaminPiutang $mapping): void
    {
        $this->logService->log('Mapping Penjamin', 'delete', [
            'penjamin_id' => $mapping->penjamin_id,
            'nama_penjamin' => $mapping->nama_penjamin,
            'jenis_layanan' => $mapping->jenis_layanan,
            'coa_id' => $mapping->coa_id,
        ]);

        $mapping->delete();
    }

    private function ensureValidActiveLeafCoa(?Coa $coa): void
    {
        if ($coa === null
            || (int) $coa->status_aktif !== 1
            || (int) $coa->children_count > 0) {
            throw new RuntimeException(
                'Akun harus merupakan COA aktif yang tidak memiliki akun turunan.',
            );
        }
    }
}
