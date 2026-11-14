<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariabelManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:indikator_kinerja_variabel_manual,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'satuan' => ['required', Rule::enum(SatuanIndikator::class)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode variabel manual wajib diisi.',
            'kode.unique' => 'Kode variabel manual sudah digunakan.',
            'nama.required' => 'Nama variabel manual wajib diisi.',
            'satuan.required' => 'Satuan indikator wajib dipilih.',
        ];
    }
}
