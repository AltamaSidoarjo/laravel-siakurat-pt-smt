<?php

namespace App\Services\Pembelian;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\PembayaranPembelian;
use App\Models\Supplier;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class PembayaranPembelianService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly InvoicePembelianService $invoicePembelianService,
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return PembayaranPembelian::query()
            ->with([
                'supplier:id,kode_supplier,nama_supplier',
                'akunBank:id,kode,nama',
                'akunPotonganAdmin:id,kode,nama',
            ])
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getSupplierOptions(): Collection
    {
        return Supplier::query()
            ->where('status_aktif', true)
            ->whereHas('fakturPembelians', function ($query) {
                $query
                    ->whereRaw($this->outstandingInvoiceCondition())
                    ->where(function ($statusQuery) {
                        $statusQuery
                            ->whereNull('status_proses')
                            ->orWhere('status_proses', '!=', '1');
                    });
            })
            ->orderBy('kode_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function getAvailableInvoicesBySupplier(int $supplierId): Collection
    {
        return FakturPembelian::query()
            ->where('supplier_id', $supplierId)
            ->whereRaw($this->outstandingInvoiceCondition())
            ->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('status_proses')
                    ->orWhere('status_proses', '!=', '1');
            })
            ->orderBy('tanggal_faktur')
            ->orderBy('nomer_faktur')
            ->get([
                'id',
                'nomer_faktur',
                'tanggal_faktur',
                'grandtotal',
                'sudah_terbayar',
            ]);
    }

    public function create(array $data, string $actor = 'system'): PembayaranPembelian
    {
        $selectedRincian = $this->extractSelectedRincian($data['rincian']);

        $pembayaranPembelian = PembayaranPembelian::query()->create([
            'supplier_id' => $data['supplier_id'],
            'akun_bank_id' => $data['akun_bank_id'],
            'akun_hutang_id' => $data['akun_hutang_id'],
            'akun_potongan_admin_id' => $data['akun_potongan_admin_id'] ?? null,
            'nomer_pembayaran' => $data['nomer_pembayaran'],
            'tanggal' => $data['tanggal'],
            'total_bayar' => $data['total_bayar'],
            'potongan_admin' => $data['potongan_admin'] ?? 0,
            'keterangan' => $data['keterangan'] ?? null,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $pembayaranPembelian->rincian()->createMany($selectedRincian->map(fn ($row) => [
            'faktur_pembelian_id' => $row['faktur_pembelian_id'],
            'nominal_bayar' => $row['nominal_bayar'],
        ])->all());

        $this->invoicePembelianService->increaseSudahTerbayar($selectedRincian);
        $this->bukuBesarService->syncFromPembayaranPembelian(
            (int) $pembayaranPembelian->id,
            (int) $pembayaranPembelian->akun_bank_id,
            (int) $pembayaranPembelian->akun_hutang_id,
            $pembayaranPembelian->akun_potongan_admin_id ? (int) $pembayaranPembelian->akun_potongan_admin_id : null,
            $pembayaranPembelian->nomer_pembayaran,
            $pembayaranPembelian->tanggal->format('Y-m-d'),
            $pembayaranPembelian->keterangan,
            (float) $pembayaranPembelian->total_bayar,
            (float) $pembayaranPembelian->potongan_admin,
        );

        $this->logService->log('Pembayaran Pembelian', 'create', null, [
            'nomer_pembayaran' => $pembayaranPembelian->nomer_pembayaran,
            'tanggal' => $pembayaranPembelian->tanggal->format('Y-m-d'),
            'supplier_id' => $pembayaranPembelian->supplier_id,
            'total_bayar' => $pembayaranPembelian->total_bayar,
            'potongan_admin' => $pembayaranPembelian->potongan_admin,
            'keterangan' => $pembayaranPembelian->keterangan,
        ]);

        return $pembayaranPembelian->load([
            'supplier',
            'akunBank',
            'akunHutang',
            'akunPotonganAdmin',
            'rincian.fakturPembelian',
        ]);
    }

    public function update(PembayaranPembelian $pembayaranPembelian, array $data, string $actor = 'system'): PembayaranPembelian
    {
        $oldData = [
            'nomer_pembayaran' => $pembayaranPembelian->nomer_pembayaran,
            'tanggal' => $pembayaranPembelian->tanggal->format('Y-m-d'),
            'total_bayar' => $pembayaranPembelian->total_bayar,
            'potongan_admin' => $pembayaranPembelian->potongan_admin,
            'keterangan' => $pembayaranPembelian->keterangan,
        ];

        $oldRincian = $pembayaranPembelian->rincian()
            ->get(['faktur_pembelian_id', 'nominal_bayar'])
            ->map(fn ($item) => [
                'faktur_pembelian_id' => (int) $item->faktur_pembelian_id,
                'nominal_bayar' => (float) $item->nominal_bayar,
            ]);

        $this->invoicePembelianService->decreaseSudahTerbayar($oldRincian);

        $pembayaranPembelian->update([
            'akun_bank_id' => $data['akun_bank_id'],
            'akun_hutang_id' => $data['akun_hutang_id'],
            'akun_potongan_admin_id' => $data['akun_potongan_admin_id'] ?? null,
            'nomer_pembayaran' => $data['nomer_pembayaran'],
            'tanggal' => $data['tanggal'],
            'total_bayar' => $data['total_bayar'],
            'potongan_admin' => $data['potongan_admin'] ?? 0,
            'keterangan' => $data['keterangan'] ?? null,
            'updated_by' => $actor,
        ]);

        $pembayaranPembelian->rincian()->delete();
        $selectedRincian = $this->extractSelectedRincian($data['rincian']);
        $pembayaranPembelian->rincian()->createMany($selectedRincian->map(fn ($row) => [
            'faktur_pembelian_id' => $row['faktur_pembelian_id'],
            'nominal_bayar' => $row['nominal_bayar'],
        ])->all());

        $this->invoicePembelianService->increaseSudahTerbayar($selectedRincian);
        $this->bukuBesarService->syncFromPembayaranPembelian(
            (int) $pembayaranPembelian->id,
            (int) $pembayaranPembelian->akun_bank_id,
            (int) $pembayaranPembelian->akun_hutang_id,
            $pembayaranPembelian->akun_potongan_admin_id ? (int) $pembayaranPembelian->akun_potongan_admin_id : null,
            $pembayaranPembelian->nomer_pembayaran,
            $pembayaranPembelian->tanggal->format('Y-m-d'),
            $pembayaranPembelian->keterangan,
            (float) $pembayaranPembelian->total_bayar,
            (float) $pembayaranPembelian->potongan_admin,
        );

        $this->logService->log('Pembayaran Pembelian', 'update', $oldData, [
            'nomer_pembayaran' => $pembayaranPembelian->nomer_pembayaran,
            'tanggal' => $pembayaranPembelian->tanggal->format('Y-m-d'),
            'total_bayar' => $pembayaranPembelian->total_bayar,
            'potongan_admin' => $pembayaranPembelian->potongan_admin,
            'keterangan' => $pembayaranPembelian->keterangan,
        ]);

        return $pembayaranPembelian->load([
            'supplier',
            'akunBank',
            'akunHutang',
            'akunPotonganAdmin',
            'rincian.fakturPembelian',
        ]);
    }

    public function delete(PembayaranPembelian $pembayaranPembelian): void
    {
        $this->logService->log('Pembayaran Pembelian', 'delete', [
            'nomer_pembayaran' => $pembayaranPembelian->nomer_pembayaran,
            'tanggal' => $pembayaranPembelian->tanggal->format('Y-m-d'),
            'supplier_id' => $pembayaranPembelian->supplier_id,
            'total_bayar' => $pembayaranPembelian->total_bayar,
            'potongan_admin' => $pembayaranPembelian->potongan_admin,
            'keterangan' => $pembayaranPembelian->keterangan,
        ]);

        $rincian = $pembayaranPembelian->rincian()
            ->get(['faktur_pembelian_id', 'nominal_bayar'])
            ->map(fn ($item) => [
                'faktur_pembelian_id' => (int) $item->faktur_pembelian_id,
                'nominal_bayar' => (float) $item->nominal_bayar,
            ]);

        $this->bukuBesarService->deleteBySource('Pembayaran Pembelian', (int) $pembayaranPembelian->id);
        $this->invoicePembelianService->decreaseSudahTerbayar($rincian);
        $pembayaranPembelian->rincian()->delete();
        $pembayaranPembelian->delete();
    }

    private function extractSelectedRincian(array $rincian): SupportCollection
    {
        return collect($rincian)
            ->filter(fn ($row) => filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn ($row) => [
                'faktur_pembelian_id' => (int) $row['faktur_pembelian_id'],
                'nominal_bayar' => (float) ($row['nominal_bayar'] ?? 0),
            ])
            ->values();
    }

    private function outstandingInvoiceCondition(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'CAST(COALESCE(grandtotal, 0) AS INTEGER) > CAST(COALESCE(sudah_terbayar, 0) AS INTEGER)';
        }

        return 'FLOOR(COALESCE(grandtotal, 0)) > FLOOR(COALESCE(sudah_terbayar, 0))';
    }
}
