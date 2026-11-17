<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;

class BukuPembantuHutangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportDate' => ['nullable', 'date_format:Y-m-d'],
            'supplierIds' => ['nullable', 'array'],
            'supplierIds.*' => ['integer', 'distinct', 'exists:supplier,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reportDate' => 'tanggal laporan',
            'supplierIds' => 'supplier',
        ];
    }
}
