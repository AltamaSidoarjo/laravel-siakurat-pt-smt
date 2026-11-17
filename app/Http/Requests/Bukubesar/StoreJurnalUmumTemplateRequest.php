<?php

namespace App\Http\Requests\Bukubesar;

use Illuminate\Foundation\Http\FormRequest;

class StoreJurnalUmumTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.debit' => ['required', 'numeric', 'min:0'],
            'rincian.*.kredit' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
