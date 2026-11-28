<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\SettingArusKas;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSettingArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kelompok_bagian' => ['required', 'string', Rule::in(SettingArusKas::KELOMPOK_BAGIAN)],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct', 'exists:setting_arus_kas,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kelompok_bagian.required' => 'Kelompok bagian wajib dipilih.',
            'kelompok_bagian.in' => 'Kelompok bagian tidak valid.',
            'ordered_ids.required' => 'Daftar urutan pos wajib diisi.',
            'ordered_ids.array' => 'Daftar urutan pos harus berupa array.',
            'ordered_ids.min' => 'Daftar urutan pos minimal berisi satu pos.',
            'ordered_ids.*.required' => 'ID pos wajib diisi.',
            'ordered_ids.*.integer' => 'ID pos harus berupa angka bulat.',
            'ordered_ids.*.distinct' => 'Daftar urutan pos tidak boleh mengandung duplikat.',
            'ordered_ids.*.exists' => 'Pos arus kas tidak ditemukan.',
        ];
    }

    /**
     * @return array<int, callable(ValidatorContract): void>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                $kelompokBagian = $this->input('kelompok_bagian');
                $orderedIds = $this->input('ordered_ids');

                if (
                    ! is_string($kelompokBagian)
                    || ! in_array($kelompokBagian, SettingArusKas::KELOMPOK_BAGIAN, true)
                    || ! is_array($orderedIds)
                    || $orderedIds === []
                ) {
                    return;
                }

                $ids = collect($orderedIds)
                    ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if ($ids === []) {
                    return;
                }

                $hasMismatch = SettingArusKas::query()
                    ->whereIn('id', $ids)
                    ->where('kelompok_bagian', '!=', $kelompokBagian)
                    ->exists();

                if ($hasMismatch) {
                    $validator->errors()->add(
                        'ordered_ids',
                        'Semua pos yang diurutkan harus berasal dari kelompok bagian yang sama.'
                    );
                }
            },
        ];
    }
}
