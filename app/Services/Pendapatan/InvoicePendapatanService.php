<?php

namespace App\Services\Pendapatan;

use App\Models\FakturPenjualan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoicePendapatanService
{
    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return FakturPenjualan::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id');
    }

    public function findById(int $id): ?FakturPenjualan
    {
        return FakturPenjualan::query()
            ->with('rincian')
            ->find($id);
    }

    public function increaseSudahTerbayar(Collection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPenjualan::query()
                ->whereKey((int) $row['faktur_penjualan_id'])
                ->increment('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }

    public function decreaseSudahTerbayar(Collection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPenjualan::query()
                ->whereKey((int) $row['faktur_penjualan_id'])
                ->decrement('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }
}
