<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\MappingPendapatanUmum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
                $nama = trim((string) $this->input('nama'));
                $kodePenjamin = trim((string) $this->input('kode_penjamin'));

                $exists = MappingPendapatanUmum::query()
                    ->whereRaw('LOWER(TRIM(nama)) = ?', [Str::lower($nama)])
                    ->whereRaw('LOWER(TRIM(kode_penjamin)) = ?', [Str::lower($kodePenjamin)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nama', 'Mapping umum untuk nama dan penjamin tersebut sudah ada.');
                }
            },
        ];
    }
}
