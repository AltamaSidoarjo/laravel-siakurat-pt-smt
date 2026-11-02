<?php

namespace App\Http\Requests\Bukubesar;

use App\Models\JurnalUmum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJurnalUmumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var JurnalUmum $jurnalUmum */
        $jurnalUmum = $this->route('jurnalUmum');

        return [
            'nomer' => ['required', 'string', 'max:255', Rule::unique('jurnal_umum', 'nomer')->ignore($jurnalUmum->id)],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'debit' => ['required', 'numeric', 'min:0'],
            'kredit' => ['required', 'numeric', 'min:0'],
            'rincian' => ['required', 'array', 'min:2'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.debit' => ['required', 'numeric', 'min:0'],
            'rincian.*.kredit' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $headerDebit = (float) $this->input('debit', 0);
                $headerKredit = (float) $this->input('kredit', 0);

                if (abs($headerDebit - $headerKredit) > 0.0001) {
                    $validator->errors()->add('debit', 'Total debit dan kredit header harus sama.');
                }

                $rincian = $this->input('rincian', []);
                if (! is_array($rincian) || count($rincian) < 2) {
                    $validator->errors()->add('rincian', 'Rincian jurnal umum minimal harus terdiri dari dua baris.');
                    return;
                }

                $detailDebit = 0.0;
                $detailKredit = 0.0;

                foreach ($rincian as $index => $row) {
                    $rowDebit = (float) ($row['debit'] ?? 0);
                    $rowKredit = (float) ($row['kredit'] ?? 0);

                    if ($rowDebit > 0 && $rowKredit > 0) {
                        $validator->errors()->add("rincian.{$index}.debit", 'Baris rincian tidak boleh mengisi debit dan kredit sekaligus.');
                    } elseif ($rowDebit <= 0 && $rowKredit <= 0) {
                        $validator->errors()->add("rincian.{$index}.debit", 'Baris rincian harus memiliki nilai nominal pada salah satu sisi debit atau kredit.');
                    }

                    $detailDebit += $rowDebit;
                    $detailKredit += $rowKredit;
                }

                if (abs($detailDebit - $detailKredit) > 0.0001) {
                    $validator->errors()->add('rincian', 'Total debit dan kredit pada rincian jurnal tidak seimbang.');
                }

                if (abs($headerDebit - $detailDebit) > 0.0001) {
                    $validator->errors()->add('debit', 'Total debit header tidak cocok dengan total rincian debit.');
                }

                if (abs($headerKredit - $detailKredit) > 0.0001) {
                    $validator->errors()->add('kredit', 'Total kredit header tidak cocok dengan total rincian kredit.');
                }
            },
        ];
    }
}
