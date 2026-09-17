<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BukuPembantuPiutangMutasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExport = $this->routeIs('laporan.pendapatan.buku-pembantu-piutang-mutasi.export-csv');

        return [
            'startDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'endDate' => [$isExport ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'pelangganIds' => [$isExport ? 'required' : 'nullable', 'array', 'min:1'],
            'pelangganIds.*' => ['integer', 'distinct', 'exists:pelanggan,id'],
            'statusSaldo' => ['nullable', Rule::in(['semua', 'masih-piutang', 'lunas'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'startDate' => 'dari tanggal',
            'endDate' => 'sampai tanggal',
            'pelangganIds' => 'pelanggan',
            'statusSaldo' => 'status saldo akhir',
        ];
    }
}
