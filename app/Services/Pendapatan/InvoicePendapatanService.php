<?php

namespace App\Services\Pendapatan;

use App\Models\FakturPenjualan;
use Illuminate\Database\Eloquent\Builder;

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
}
