<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\SettingRba;
use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRbaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'min:1900', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.coa_id' => ['required', 'integer', 'exists:coa,id', 'distinct'],
            'items.*.total_nominal' => ['required', 'numeric', 'min:0'],
            'items.*.catatan' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.coa_id.distinct' => 'COA tidak boleh dipilih lebih dari satu kali.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $tahun = (int) $this->input('tahun');
                $coaIds = collect($this->input('items', []))
                    ->pluck('coa_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values();

                if ($coaIds->isEmpty()) {
                    return;
                }

                $existing = SettingRba::query()
                    ->with('coa:id,kode,nama')
                    ->where('tahun', $tahun)
                    ->whereIn('coa_id', $coaIds)
                    ->get();

                foreach ($existing as $item) {
                    $validator->errors()->add(
                        'items',
                        sprintf(
                            'COA %s - %s sudah memiliki Setting RBA pada tahun %d.',
                            $item->coa?->kode,
                            $item->coa?->nama,
                            $tahun,
                        )
                    );
                }
            },
        ];
    }
}
