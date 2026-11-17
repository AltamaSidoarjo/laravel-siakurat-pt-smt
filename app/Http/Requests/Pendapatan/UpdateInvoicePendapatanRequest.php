<?php

namespace App\Http\Requests\Pendapatan;

use Illuminate\Validation\Rule;

class UpdateInvoicePendapatanRequest extends StoreInvoicePendapatanRequest
{
    public function rules(): array
    {
        $invoice = $this->route('fakturPenjualan');

        return $this->invoiceRules([
            Rule::unique('faktur_penjualan', 'nomor_faktur')->ignore($invoice?->id),
        ]);
    }
}
