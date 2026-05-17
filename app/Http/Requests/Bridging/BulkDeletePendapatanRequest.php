<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeletePendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedNoRawat' => ['required', 'array', 'min:1'],
            'selectedNoRawat.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedNoRawat.required' => 'Pilih minimal satu data untuk dihapus.',
        ];
    }
}
