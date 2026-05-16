<?php

namespace App\Http\Requests\Bukubesar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJurnalUmumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomer' => ['required', 'string', 'max:255', Rule::unique('jurnal_umum', 'nomer')],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'debit' => ['required', 'numeric', 'min:0'],
            'kredit' => ['required', 'numeric', 'min:0'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.debit' => ['required', 'numeric', 'min:0'],
            'rincian.*.kredit' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $debit = (float) $this->input('debit', 0);
                $kredit = (float) $this->input('kredit', 0);

                if ($debit !== $kredit) {
                    $validator->errors()->add('debit', 'Total debit dan kredit harus sama.');
                }
            },
        ];
    }
}
