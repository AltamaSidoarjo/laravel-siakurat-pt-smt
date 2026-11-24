<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\ReferensiKelompokArusKas;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferensiKelompokArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nama') && is_string($this->input('nama'))) {
            $this->merge([
                'nama' => trim(preg_replace('/\s+/', ' ', $this->input('nama'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'aktivitas' => ['required', 'string', 'in:operasi,investasi,pendanaan'],
            'nama' => ['required', 'string', 'max:150'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'aktivitas.required' => 'Aktivitas arus kas wajib dipilih.',
            'aktivitas.in' => 'Aktivitas arus kas tidak valid.',
            'nama.required' => 'Nama kelompok arus kas wajib diisi.',
            'nama.max' => 'Nama kelompok arus kas maksimal 150 karakter.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $aktivitas = $this->input('aktivitas');
                $nama = $this->input('nama');

                if (! $aktivitas || ! is_string($nama) || trim($nama) === '') {
                    return;
                }

                $sanitized = trim(preg_replace('/\s+/', ' ', $nama));

                $exists = ReferensiKelompokArusKas::query()
                    ->where('aktivitas', $aktivitas)
                    ->whereRaw('LOWER(nama) = ?', [mb_strtolower($sanitized)])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nama', 'Nama kelompok arus kas sudah terdaftar pada aktivitas ini.');
                }
            },
        ];
    }
}
