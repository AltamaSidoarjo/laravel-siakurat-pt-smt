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
            'selectedExternalIds' => ['required', 'array', 'min:1', 'max:100'],
            'selectedExternalIds.*' => ['required', 'string', 'max:100', 'distinct'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'jenisLayanan' => ['required', 'in:rawat_jalan,igd'],
            'spesialisId' => ['nullable', 'string', 'max:100'],
            'dokterId' => ['nullable', 'string', 'max:100'],
            'jenisProses' => ['required', 'in:JurnalUmum'],
            'basisTanggalPengakuan' => ['required', 'in:TanggalRegistrasi'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedExternalIds.required' => 'Pilih minimal satu data billing untuk diproses.',
            'selectedExternalIds.max' => 'Maksimal 100 data billing dapat diproses sekaligus.',
            'jenisProses.required' => 'Pilih tujuan import terlebih dahulu.',
            'basisTanggalPengakuan.required' => 'Pilih basis tanggal pengakuan terlebih dahulu.',
        ];
    }
}
