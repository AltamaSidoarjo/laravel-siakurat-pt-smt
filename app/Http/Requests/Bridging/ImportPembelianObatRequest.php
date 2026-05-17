<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class ImportPembelianObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedNoTransaksi' => ['required', 'array', 'min:1'],
            'selectedNoTransaksi.*' => ['required', 'string', 'max:100'],
            'jenisProses' => ['required', 'in:InvoicePembelian'],
            'metodeTanggalPengakuan' => ['required', 'in:TanggalInvoice,TanggalBarangDatang'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedNoTransaksi.required' => 'Pilih minimal satu tagihan pembelian obat & BHP.',
            'jenisProses.required' => 'Pilih tujuan import terlebih dahulu.',
            'metodeTanggalPengakuan.required' => 'Pilih metode tanggal pengakuan terlebih dahulu.',
        ];
    }
}
