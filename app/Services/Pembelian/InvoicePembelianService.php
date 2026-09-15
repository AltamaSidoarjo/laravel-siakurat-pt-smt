<?php

namespace App\Services\Pembelian;

use App\Models\FakturPembelian;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoicePembelianService
{
    public function getIndexQuery(string $startDate, string $endDate, array $supplierIds = []): Builder
    {
        return FakturPembelian::query()
            ->with('supplier:id,nama_supplier')
            ->betweenDates($startDate, $endDate)
            ->when(
                $supplierIds !== [],
                fn (Builder $query) => $query->whereIn('supplier_id', $supplierIds),
            )
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id');
    }

    public function getSupplierOptions(): Collection
    {
        return Supplier::query()
            ->orderBy('kode_supplier')
            ->orderBy('nama_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function findById(int $id): ?FakturPembelian
    {
        return FakturPembelian::query()
            ->with(['supplier', 'rincian'])
            ->find($id);
    }

    public function increaseSudahTerbayar(Collection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPembelian::query()
                ->whereKey((int) $row['faktur_pembelian_id'])
                ->increment('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }

    public function decreaseSudahTerbayar(Collection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPembelian::query()
                ->whereKey((int) $row['faktur_pembelian_id'])
                ->decrement('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }
}
