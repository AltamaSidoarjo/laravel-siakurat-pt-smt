<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\Pengaturan\PengaturanArusKasService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class StorePengecualianArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('alasan') && is_string($this->input('alasan'))) {
            $trimmed = trim($this->input('alasan'));
            $this->merge([
                'alasan' => $trimmed !== '' ? $trimmed : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coa_ids' => ['required', 'array', 'min:1'],
            'coa_ids.*' => ['required', 'integer', 'distinct', 'exists:coa,id'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'coa_ids.required' => 'Pilih minimal satu akun COA untuk dikecualikan.',
            'coa_ids.array' => 'Daftar akun COA harus berupa array.',
            'coa_ids.min' => 'Pilih minimal satu akun COA untuk dikecualikan.',
            'coa_ids.*.required' => 'ID akun COA wajib diisi.',
            'coa_ids.*.integer' => 'ID akun COA tidak valid.',
            'coa_ids.*.distinct' => 'Akun COA tidak boleh dipilih lebih dari satu kali.',
            'coa_ids.*.exists' => 'Akun COA tidak ditemukan.',
            'alasan.max' => 'Alasan pengecualian maksimal 255 karakter.',
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
                    'pengecualian',
                );
            },
        ];
    }
}
