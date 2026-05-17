<?php

namespace App\Services\Pembelian;

use App\Models\FakturPembelian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoicePembelianService
{
    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return FakturPembelian::query()
            ->with('supplier:id,nama_supplier')
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id');
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
