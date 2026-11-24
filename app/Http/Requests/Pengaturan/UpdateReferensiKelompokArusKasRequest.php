<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\ReferensiKelompokArusKas;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReferensiKelompokArusKasRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:150'],
            'status_aktif' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kelompok arus kas wajib diisi.',
            'nama.max' => 'Nama kelompok arus kas maksimal 150 karakter.',
            'status_aktif.required' => 'Status aktif wajib ditentukan.',
            'status_aktif.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $referensi = $this->route('referensi') ?? $this->route('referensi_kelompok_arus_kas') ?? $this->route('id');
                $id = $referensi instanceof ReferensiKelompokArusKas ? $referensi->id : $referensi;

                $aktivitas = $this->input('aktivitas');
                if (! $aktivitas && $referensi instanceof ReferensiKelompokArusKas) {
                    $aktivitas = $referensi->aktivitas;
                } elseif (! $aktivitas && $id) {
                    $aktivitas = ReferensiKelompokArusKas::where('id', $id)->value('aktivitas');
                }

                $nama = $this->input('nama');
                if (! $aktivitas || ! is_string($nama) || trim($nama) === '') {
                    return;
                }

                $sanitized = trim(preg_replace('/\s+/', ' ', $nama));

                $query = ReferensiKelompokArusKas::query()
                    ->where('aktivitas', $aktivitas)
                    ->whereRaw('LOWER(nama) = ?', [mb_strtolower($sanitized)]);

                if ($id) {
                    $query->where('id', '!=', $id);
                }

                if ($query->exists()) {
                    $validator->errors()->add('nama', 'Nama kelompok arus kas sudah terdaftar pada aktivitas ini.');
                }
            },
        ];
    }
}
