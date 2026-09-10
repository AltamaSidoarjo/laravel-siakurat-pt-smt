<?php

namespace App\Http\Requests\Laporan;

use App\Models\Coa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BukuPembantuPiutangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExport = $this->routeIs('laporan.pendapatan.buku-pembantu-piutang.export-csv');

        return [
            'startDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'endDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'pelangganIds' => [$isExport ? 'required' : 'nullable', 'array', 'min:1'],
            'pelangganIds.*' => ['integer', 'distinct', 'exists:pelanggan,id'],
            'akunPiutang' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'tanpa-akun') {
                        return;
                    }

                    if (! ctype_digit((string) $value)) {
                        $fail('Akun piutang yang dipilih tidak valid.');

                        return;
                    }

                    $exists = Coa::query()
                        ->whereKey((int) $value)
                        ->whereRaw('LOWER(COALESCE(tipe_coa, ?)) LIKE ?', ['', '%piutang%'])
                        ->exists();

                    if (! $exists) {
                        $fail('Akun piutang yang dipilih tidak valid.');
                    }
                },
            ],
            'statusSaldo' => ['nullable', Rule::in(['semua', 'masih-piutang', 'lunas'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'startDate' => 'dari tanggal',
            'endDate' => 'sampai tanggal',
            'pelangganIds' => 'pelanggan',
            'akunPiutang' => 'akun piutang',
            'statusSaldo' => 'status saldo akhir',
        ];
    }
}
