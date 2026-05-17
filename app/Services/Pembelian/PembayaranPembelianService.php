<?php

namespace App\Services\Pembelian;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\PembayaranPembelian;
use App\Models\Supplier;
use App\Services\Bukubesar\BukuBesarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PembayaranPembelianService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly InvoicePembelianService $invoicePembelianService,
    ) {
    }

    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return PembayaranPembelian::query()
            ->with([
                'supplier:id,kode_supplier,nama_supplier',
                'akunBank:id,kode,nama',
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
                    ->whereRaw('COALESCE(grandtotal, 0) <> COALESCE(sudah_terbayar, 0)')
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
            ->whereRaw('COALESCE(grandtotal, 0) <> COALESCE(sudah_terbayar, 0)')
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
            'nomer_pembayaran' => $data['nomer_pembayaran'],
            'tanggal' => $data['tanggal'],
            'total_bayar' => $data['total_bayar'],
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
            $pembayaranPembelian->nomer_pembayaran,
            $pembayaranPembelian->tanggal->format('Y-m-d'),
            $pembayaranPembelian->keterangan,
            (float) $pembayaranPembelian->total_bayar,
        );

        return $pembayaranPembelian->load([
            'supplier',
            'akunBank',
            'akunHutang',
            'rincian.fakturPembelian',
        ]);
    }

    public function update(PembayaranPembelian $pembayaranPembelian, array $data, string $actor = 'system'): PembayaranPembelian
    {
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
            'nomer_pembayaran' => $data['nomer_pembayaran'],
            'tanggal' => $data['tanggal'],
            'total_bayar' => $data['total_bayar'],
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
            $pembayaranPembelian->nomer_pembayaran,
            $pembayaranPembelian->tanggal->format('Y-m-d'),
            $pembayaranPembelian->keterangan,
            (float) $pembayaranPembelian->total_bayar,
        );

        return $pembayaranPembelian->load([
            'supplier',
            'akunBank',
            'akunHutang',
            'rincian.fakturPembelian',
        ]);
    }

    public function delete(PembayaranPembelian $pembayaranPembelian): void
    {
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
}
