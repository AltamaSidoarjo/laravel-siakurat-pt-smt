<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class SimpanManualDataRequest extends FormRequest
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
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'data' => ['required', 'array'],
            'data.*' => ['array'],
            'data.*.*' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tahun.required' => 'Tahun wajib diisi.',
            'tahun.integer' => 'Tahun harus berupa bilangan bulat.',
            'data.required' => 'Data bulanan tidak boleh kosong.',
            'data.*.*.numeric' => 'Nilai bulanan harus berupa angka yang valid.',
        ];
    }
}
