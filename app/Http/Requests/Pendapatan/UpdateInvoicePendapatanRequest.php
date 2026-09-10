<?php

namespace App\Http\Requests\Pendapatan;

use App\Models\FakturPenjualan;
use App\Models\SimrsImportPendapatan;
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

    public function after(): array
    {
        return [
            ...parent::after(),
            $this->validateLockedSimrsIdentity(...),
        ];
    }

    private function validateLockedSimrsIdentity($validator): void
    {
        $invoice = $this->route('fakturPenjualan');
        if (! $invoice instanceof FakturPenjualan) {
            return;
        }

        $imported = SimrsImportPendapatan::query()
            ->where('import_ke', 'Invoice Pendapatan')
            ->where('nomer_billing', $invoice->nomer_rawat ?: $invoice->nomor_faktur)
            ->exists();

        if (! $imported) {
            return;
        }

        foreach (['nomor_faktur', 'pelanggan_id', 'nama_pasien', 'nomer_rekam_medis', 'nama_dokter', 'nama_poli'] as $field) {
            if ((string) $this->input($field) !== (string) $invoice->{$field}) {
                $validator->errors()->add($field, 'Identitas SIMRS pada invoice bridging tidak dapat diubah.');
            }
        }
    }
}
