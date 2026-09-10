<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class StorePelaksanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'no_proyek' => ['required', 'string', 'max:255', 'unique:pelaksana,no_proyek'],
            'nama_pelaksana' => ['required', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }
}
