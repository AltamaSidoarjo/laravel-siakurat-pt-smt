<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\SettingArusKas;
use App\Services\Pengaturan\PengaturanArusKasService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function settingArusKasId(): ?int
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('nama_pos') && is_string($this->input('nama_pos'))) {
            $merge['nama_pos'] = trim(preg_replace('/\s+/', ' ', $this->input('nama_pos')) ?? '');
        }

        if ($this->has('kode_pos') && is_string($this->input('kode_pos'))) {
            $trimmedKode = trim($this->input('kode_pos'));
            $merge['kode_pos'] = $trimmedKode !== '' ? $trimmedKode : null;
        }

        foreach (['pakai_prefix_perubahan', 'is_active'] as $booleanField) {
            if ($this->has($booleanField) && $this->input($booleanField) !== null) {
                $merge[$booleanField] = $this->boolean($booleanField);
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $uniqueKodePos = Rule::unique('setting_arus_kas', 'kode_pos');
        $posId = $this->settingArusKasId();
        if ($posId !== null) {
            $uniqueKodePos->ignore($posId);
        }

        return [
            'kode_pos' => ['nullable', 'string', 'max:80', $uniqueKodePos],
            'nama_pos' => ['required', 'string', 'max:150'],
            'kelompok_bagian' => ['required', 'string', Rule::in(SettingArusKas::KELOMPOK_BAGIAN)],
            'sifat_pos' => ['required', 'string', Rule::in(SettingArusKas::SIFAT_POS)],
            'pakai_prefix_perubahan' => ['nullable', 'boolean'],
            'urutan' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'coa_ids' => ['nullable', 'array'],
            'coa_ids.*' => ['integer', 'distinct', 'exists:coa,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_pos.max' => 'Kode pos maksimal 80 karakter.',
            'kode_pos.unique' => 'Kode pos sudah digunakan.',
            'nama_pos.required' => 'Nama pos arus kas wajib diisi.',
            'nama_pos.max' => 'Nama pos arus kas maksimal 150 karakter.',
            'kelompok_bagian.required' => 'Kelompok bagian wajib dipilih.',
            'kelompok_bagian.in' => 'Kelompok bagian tidak valid.',
            'sifat_pos.required' => 'Sifat pos wajib dipilih.',
            'sifat_pos.in' => 'Sifat pos tidak valid.',
            'urutan.required' => 'Urutan pos wajib diisi.',
            'urutan.integer' => 'Urutan pos harus berupa angka bulat.',
            'urutan.min' => 'Urutan pos minimal 1.',
            'coa_ids.array' => 'Daftar akun COA harus berupa array.',
            'coa_ids.*.integer' => 'ID akun COA tidak valid.',
            'coa_ids.*.distinct' => 'Akun COA tidak boleh dipilih lebih dari satu kali.',
            'coa_ids.*.exists' => 'Akun COA tidak ditemukan.',
        ];
    }

    /**
     * @return array<int, callable(ValidatorContract): void>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                app(PengaturanArusKasService::class)->validateCoaIdsForValidator(
                    $validator,
                    $this->input('coa_ids'),
                    $this->input('sifat_pos'),
                    $this->settingArusKasId(),
                );
            },
        ];
    }
}
