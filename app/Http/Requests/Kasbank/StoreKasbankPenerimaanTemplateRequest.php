<?php

namespace App\Http\Requests\Kasbank;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasbankPenerimaanTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'coa_id' => ['nullable', 'integer', 'exists:coa,id'],
            'keterangan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.nominal' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
