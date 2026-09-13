<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BukuPembantuHutangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExport = $this->routeIs('laporan.pembelian.buku-pembantu-hutang.export-csv');

        return [
            'startDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'endDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'supplierIds' => [$isExport ? 'required' : 'nullable', 'array', 'min:1'],
            'supplierIds.*' => ['integer', 'distinct', 'exists:supplier,id'],
            'statusSaldo' => ['nullable', Rule::in(['semua', 'masih-hutang', 'lunas'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'startDate' => 'dari tanggal',
            'endDate' => 'sampai tanggal',
            'supplierIds' => 'supplier',
            'statusSaldo' => 'status saldo akhir',
        ];
    }
}
