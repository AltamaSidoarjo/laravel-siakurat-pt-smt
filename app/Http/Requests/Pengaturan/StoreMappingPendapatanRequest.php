<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMappingPendapatanRequest extends FormRequest
{
    private const JENIS_TINDAKAN = [
        'rawat_jalan',
        'rawat_inap',
        'lab',
        'radiologi',
        'utd',
        'operasi',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_tindakan' => ['required', 'string', Rule::in(self::JENIS_TINDAKAN)],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.tindakan_key' => ['required', 'string', 'distinct'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'rincian.*.tindakan_key.distinct' => 'Tindakan yang sama tidak boleh dipilih lebih dari satu kali.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $rows = collect($this->input('rincian', []))
                    ->filter(fn ($row) => filled($row['tindakan_key'] ?? null) || filled($row['coa_id'] ?? null));

                if ($rows->isEmpty()) {
                    $validator->errors()->add('rincian', 'Minimal satu rincian mapping harus diisi.');
                }
            },
        ];
    }
}
