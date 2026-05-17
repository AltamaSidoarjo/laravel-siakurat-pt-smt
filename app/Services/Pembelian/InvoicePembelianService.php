<?php

namespace App\Services\Pembelian;

use App\Models\FakturPembelian;
use Illuminate\Database\Eloquent\Builder;

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
}
