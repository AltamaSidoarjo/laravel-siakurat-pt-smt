<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;

class BukuPembantuPiutangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportDate' => ['nullable', 'date_format:Y-m-d'],
            'pelangganIds' => ['nullable', 'array'],
            'pelangganIds.*' => ['integer', 'distinct', 'exists:pelanggan,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reportDate' => 'tanggal laporan',
            'pelangganIds' => 'pelanggan',
        ];
    }
}
