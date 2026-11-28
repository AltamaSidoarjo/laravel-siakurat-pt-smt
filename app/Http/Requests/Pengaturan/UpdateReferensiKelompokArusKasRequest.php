<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\ReferensiKelompokArusKas;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReferensiKelompokArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('nama') && is_string($this->input('nama'))) {
            $merge['nama'] = trim(preg_replace('/\s+/', ' ', $this->input('nama')));
        }
        if ($this->has('coa_ids') && ($this->input('coa_ids') === '' || $this->input('coa_ids') === null)) {
            $merge['coa_ids'] = [];
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'status_aktif' => ['required', 'boolean'],
            'coa_ids' => ['nullable', 'array'],
            'coa_ids.*' => ['integer', 'distinct', 'exists:coa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kelompok arus kas wajib diisi.',
            'nama.max' => 'Nama kelompok arus kas maksimal 150 karakter.',
            'status_aktif.required' => 'Status aktif wajib ditentukan.',
            'status_aktif.boolean' => 'Status aktif harus berupa boolean.',
            'coa_ids.array' => 'Daftar akun COA harus berupa array.',
            'coa_ids.*.integer' => 'ID akun COA harus berupa bilangan bulat.',
            'coa_ids.*.distinct' => 'Terdapat duplikasi akun COA yang dipilih.',
            'coa_ids.*.exists' => 'Akun COA yang dipilih tidak valid atau tidak ditemukan.',
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
            function ($validator): void {
                app(ReferensiKelompokArusKasService::class)->validateCoaIdsForValidator($validator, $this->input('coa_ids'));
            },
        ];
    }
}
