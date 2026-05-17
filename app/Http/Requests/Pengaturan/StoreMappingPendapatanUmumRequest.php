<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class StoreMappingPendapatanUmumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'kode_penjamin' => ['required', 'string', 'max:50'],
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $exists = \App\Models\MappingPendapatanUmum::query()
                    ->where('nama', $this->input('nama'))
                    ->where('kode_penjamin', $this->input('kode_penjamin'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nama', 'Mapping umum untuk nama dan penjamin tersebut sudah ada.');
                }
            },
        ];
    }
}
