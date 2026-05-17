<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class ImportPendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedNoRawat' => ['required', 'array', 'min:1'],
            'selectedNoRawat.*' => ['required', 'string', 'max:100'],
            'jenisProses' => ['required', 'in:JurnalUmum,InvoicePendapatan'],
            'basisTanggalPengakuan' => ['required', 'in:TanggalRegistrasi,TanggalKeluarRanap'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedNoRawat.required' => 'Pilih minimal satu data billing untuk diproses.',
            'jenisProses.required' => 'Pilih tujuan import terlebih dahulu.',
            'basisTanggalPengakuan.required' => 'Pilih basis tanggal pengakuan terlebih dahulu.',
        ];
    }
}
