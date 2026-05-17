<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'nama_perusahaan' => ['nullable', 'string', 'max:255'],
            'logo_perusahaan' => ['nullable', 'string', 'max:255'],
            'ttd_kabag' => ['nullable', 'string', 'max:255'],
            'ttd_direktur' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo_file.mimes' => 'Format logo harus PNG, JPG, JPEG, atau WEBP.',
            'logo_file.max' => 'Ukuran logo maksimal 2 MB.',
        ];
    }
}
