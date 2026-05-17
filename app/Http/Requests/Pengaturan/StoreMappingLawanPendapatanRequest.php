<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\MappingLawanPendapatanSimrs;
use Illuminate\Foundation\Http\FormRequest;

class StoreMappingLawanPendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_coa_simrs' => ['required', 'string', 'max:100'],
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $exists = MappingLawanPendapatanSimrs::query()
                    ->where('kode_coa_simrs', $this->input('kode_coa_simrs'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('kode_coa_simrs', 'Mapping lawan pendapatan untuk COA SIMRS tersebut sudah ada.');
                }
            },
        ];
    }
}
