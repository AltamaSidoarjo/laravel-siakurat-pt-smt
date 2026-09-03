<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;

class LoadBillingPendapatanApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'jenisLayanan' => ['required', 'in:rawat_jalan,igd'],
            'spesialisId' => ['nullable', 'string', 'max:100'],
            'dokterId' => ['nullable', 'string', 'max:100'],
        ];
    }
}
