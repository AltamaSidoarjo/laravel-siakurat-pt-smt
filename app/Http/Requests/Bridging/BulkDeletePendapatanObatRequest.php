<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeletePendapatanObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedNoTransaksi' => ['required', 'array', 'min:1'],
            'selectedNoTransaksi.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'selectedNoTransaksi.required' => 'Pilih minimal satu data untuk dihapus.',
        ];
    }
}
