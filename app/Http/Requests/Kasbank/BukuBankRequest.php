<?php

namespace App\Http\Requests\Kasbank;

use Illuminate\Foundation\Http\FormRequest;

class BukuBankRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'startDate' => $this->input('startDate') ?: now()->startOfMonth()->toDateString(),
            'endDate' => $this->input('endDate') ?: now()->toDateString(),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'coaIds' => ['nullable', 'array'],
            'coaIds.*' => ['integer', 'distinct', 'exists:coa,id'],
        ];
    }
}
