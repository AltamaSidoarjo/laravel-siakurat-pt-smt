<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Validation\Rule;

class UpdateInvoicePembelianRequest extends StoreInvoicePembelianRequest
{
    public function rules(): array
    {
        $invoice = $this->route('fakturPembelian');

        return $this->invoiceRules([
            Rule::unique('faktur_pembelian', 'nomer_faktur')->ignore($invoice?->id),
        ]);
    }
}
