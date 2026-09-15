<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;

class InvoicePembelianFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $datePresenceRule = $this->routeIs('pembelian.invoice.export-csv') ? 'required' : 'nullable';

        return [
            'startDate' => [$datePresenceRule, 'date_format:Y-m-d'],
            'endDate' => [$datePresenceRule, 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'supplierIds' => ['nullable', 'array'],
            'supplierIds.*' => ['integer', 'distinct', 'exists:supplier,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'startDate' => 'tanggal awal',
            'endDate' => 'tanggal akhir',
            'supplierIds' => 'supplier',
            'supplierIds.*' => 'supplier',
        ];
    }

    public function supplierIds(): array
    {
        return collect($this->validated('supplierIds', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
