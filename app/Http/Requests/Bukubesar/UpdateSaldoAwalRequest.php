<?php

namespace App\Http\Requests\Bukubesar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaldoAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rincian') && is_array($this->rincian)) {
            $cleaned = [];
            foreach ($this->rincian as $index => $row) {
                if (empty($row['coa_id'])) {
                    continue;
                }
                $debit = isset($row['debit']) ? str_replace(['.', ','], ['', '.'], (string) $row['debit']) : 0;
                $kredit = isset($row['kredit']) ? str_replace(['.', ','], ['', '.'], (string) $row['kredit']) : 0;

                $cleaned[$index] = [
                    'coa_id' => $row['coa_id'],
                    'debit' => is_numeric($debit) ? (float) $debit : 0,
                    'kredit' => is_numeric($kredit) ? (float) $kredit : 0,
                    'catatan' => $row['catatan'] ?? null,
                ];
            }
            $this->merge(['rincian' => $cleaned]);
        }
    }

    public function rules(): array
    {
        $saldoAwalId = $this->route('saldoAwal')?->id ?? $this->route('saldo_awal')?->id ?? $this->route('saldoAwal');

        return [
            'nomer' => ['nullable', 'string', 'max:50', Rule::unique('saldo_awal', 'nomer')->ignore($saldoAwalId)],
            'tanggal_cutoff' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'action' => ['nullable', 'string', 'in:save_draft,save_lock'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.debit' => ['required', 'numeric', 'min:0'],
            'rincian.*.kredit' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $rincian = $this->input('rincian', []);
                $hasActiveRow = collect($rincian)->contains(fn ($row) => ((float) ($row['debit'] ?? 0) > 0) || ((float) ($row['kredit'] ?? 0) > 0));

                if (! $hasActiveRow) {
                    $validator->errors()->add('rincian', 'Minimal harus mengisi 1 akun dengan nominal debit atau kredit.');
                }

                if ($this->input('action') === 'save_lock') {
                    $totalDebit = collect($rincian)->sum('debit');
                    $totalKredit = collect($rincian)->sum('kredit');

                    if (abs($totalDebit - $totalKredit) > 0.01) {
                        $validator->errors()->add('balance', 'Untuk mengunci dan memposting Saldo Awal, Total Debit (Rp ' . number_format($totalDebit, 0, ',', '.') . ') harus sama dengan Total Kredit (Rp ' . number_format($totalKredit, 0, ',', '.') . '). Selisih: Rp ' . number_format(abs($totalDebit - $totalKredit), 0, ',', '.'));
                    }
                }
            },
        ];
    }
}

