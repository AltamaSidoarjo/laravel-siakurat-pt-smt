<?php

namespace App\Http\Requests\Bukubesar;

use App\Models\JurnalUmum;
use App\Models\Pelaksana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreJurnalUmumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->journalRules([
            Rule::unique('jurnal_umum', 'nomer'),
        ], includeAction: true);
    }

    protected function journalRules(array $uniqueRule, bool $includeAction = false): array
    {
        $rules = [
            'nomer' => ['required', 'string', 'max:255', ...$uniqueRule],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'debit' => ['required', 'numeric', 'min:0'],
            'kredit' => ['required', 'numeric', 'min:0'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.pelaksana_id' => ['nullable', 'integer', 'exists:pelaksana,id'],
            'rincian.*.debit' => ['required', 'numeric', 'min:0'],
            'rincian.*.kredit' => ['required', 'numeric', 'min:0'],
            'rincian.*.catatan' => ['nullable', 'string'],
        ];

        if ($includeAction) {
            $rules['action'] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $debit = (float) $this->input('debit', 0);
                $kredit = (float) $this->input('kredit', 0);

                if ($debit !== $kredit) {
                    $validator->errors()->add('debit', 'Total debit dan kredit harus sama.');
                }
            },
            $this->validateActivePelaksana(...),
        ];
    }

    protected function validateActivePelaksana($validator): void
    {
        /** @var JurnalUmum|null $jurnalUmum */
        $jurnalUmum = $this->route('jurnalUmum');
        $existingPelaksanaIds = ($jurnalUmum instanceof JurnalUmum && Schema::hasColumn('jurnal_umum_rinci', 'pelaksana_id'))
            ? $jurnalUmum->rincian()
                ->whereNotNull('pelaksana_id')
                ->pluck('pelaksana_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        foreach ($this->input('rincian', []) as $index => $row) {
            $pelaksanaId = (int) ($row['pelaksana_id'] ?? 0);
            if ($pelaksanaId === 0 || in_array($pelaksanaId, $existingPelaksanaIds, true)) {
                continue;
            }

            if (! Pelaksana::query()->active()->whereKey($pelaksanaId)->exists()) {
                $validator->errors()->add("rincian.$index.pelaksana_id", 'Pelaksana yang dipilih tidak aktif.');
            }
        }
    }
}
