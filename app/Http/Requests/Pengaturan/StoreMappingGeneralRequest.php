<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\MappingCoaSimrs;
use Illuminate\Foundation\Http\FormRequest;

class StoreMappingGeneralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_rekening' => ['required', 'string', 'max:100'],
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $exists = MappingCoaSimrs::query()
                    ->where('kode_rekening', $this->input('kode_rekening'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('kode_rekening', 'Mapping general untuk rekening SIMRS tersebut sudah ada.');
                }
            },
        ];
    }
}
