<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePelaksanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'no_proyek' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pelaksana', 'no_proyek')->ignore($this->route('pelaksana')),
            ],
            'nama_pelaksana' => ['required', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }
}
