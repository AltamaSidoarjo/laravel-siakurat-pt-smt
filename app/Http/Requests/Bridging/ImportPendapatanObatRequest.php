<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class ImportPendapatanObatRequest extends FormRequest
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
            'jenisProses' => ['required', 'in:JurnalUmum'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedNoTransaksi.required' => 'Pilih minimal satu transaksi penjualan obat.',
            'jenisProses.required' => 'Pilih tujuan import terlebih dahulu.',
        ];
    }
}
