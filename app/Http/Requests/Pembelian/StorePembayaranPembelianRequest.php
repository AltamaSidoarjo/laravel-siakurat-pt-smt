<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePembayaranPembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'akun_bank_id' => ['required', 'integer', 'exists:coa,id'],
            'akun_hutang_id' => ['required', 'integer', 'exists:coa,id'],
            'nomer_pembayaran' => ['required', 'string', 'max:50', Rule::unique('pembayaran_pembelian', 'nomer_pembayaran')],
            'tanggal' => ['required', 'date'],
            'total_bayar' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.faktur_pembelian_id' => ['required', 'integer', 'exists:faktur_pembelian,id'],
            'rincian.*.nominal_bayar' => ['required', 'numeric', 'min:0'],
            'rincian.*.check' => ['nullable'],
            'submit_action' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $checkedRows = collect($this->input('rincian', []))
                    ->filter(fn ($row) => filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN));

                if ($checkedRows->isEmpty()) {
                    $validator->errors()->add('rincian', 'Minimal satu faktur harus dicentang.');
                    return;
                }

                $calculated = (float) $checkedRows->sum(fn ($row) => (float) ($row['nominal_bayar'] ?? 0));
                $total = (float) $this->input('total_bayar', 0);

                if (abs($calculated - $total) > 0.00001) {
                    $validator->errors()->add('total_bayar', 'Jumlah pembayaran harus sama dengan total rincian terpilih.');
                }
            },
        ];
    }
}
