<?php

namespace App\Http\Requests\Bridging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadBillingAccountDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'externalId' => [
                'nullable',
                'string',
                'max:100',
                'required_without:importId',
                Rule::prohibitedIf($this->filled('importId')),
            ],
            'importId' => [
                'nullable',
                'integer',
                'min:1',
                'required_without:externalId',
                Rule::prohibitedIf($this->filled('externalId')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'externalId.required_without' => 'ID Billing API atau ID data import wajib tersedia.',
            'externalId.max' => 'ID Billing API maksimal 100 karakter.',
            'externalId.prohibited' => 'Gunakan salah satu sumber detail billing.',
            'importId.required_without' => 'ID Billing API atau ID data import wajib tersedia.',
            'importId.prohibited' => 'Gunakan salah satu sumber detail billing.',
        ];
    }
}
