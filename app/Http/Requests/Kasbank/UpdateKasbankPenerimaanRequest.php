<?php

namespace App\Http\Requests\Kasbank;

use App\Models\KasbankPenerimaan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKasbankPenerimaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var KasbankPenerimaan $kasbankPenerimaan */
        $kasbankPenerimaan = $this->route('kasbankPenerimaan');

        return [
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
            'nomer' => ['required', 'string', 'max:255', Rule::unique('kasbank_penerimaan', 'nomer')->ignore($kasbankPenerimaan->id)],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'total' => ['required', 'numeric', 'min:0'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.nominal' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $total = (float) $this->input('total', 0);
                $detailTotal = collect($this->input('rincian', []))
                    ->sum(fn ($row) => (float) ($row['nominal'] ?? 0));

                if ($total <= 0) {
                    $validator->errors()->add('total', 'Total harus lebih besar dari nol.');
                }

                if (abs($total - $detailTotal) > 0.00001) {
                    $validator->errors()->add('total', 'Total harus sama dengan jumlah nominal rincian.');
                }
            },
        ];
    }
}
