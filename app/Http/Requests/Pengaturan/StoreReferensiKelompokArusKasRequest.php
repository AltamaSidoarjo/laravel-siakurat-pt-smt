<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\ReferensiKelompokArusKas;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferensiKelompokArusKasRequest extends FormRequest
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
        if ($this->has('status_aktif')) {
            $merge['status_aktif'] = $this->boolean('status_aktif');
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
            'aktivitas' => ['required', 'string', 'in:operasi,investasi,pendanaan'],
            'nama' => ['required', 'string', 'max:150'],
            'status_aktif' => ['nullable', 'boolean'],
            'coa_ids' => ['nullable', 'array'],
            'coa_ids.*' => ['integer', 'distinct', 'exists:coa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'aktivitas.required' => 'Aktivitas arus kas wajib dipilih.',
            'aktivitas.in' => 'Aktivitas arus kas tidak valid.',
            'nama.required' => 'Nama kelompok arus kas wajib diisi.',
            'nama.max' => 'Nama kelompok arus kas maksimal 150 karakter.',
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
            function ($validator): void {
                app(ReferensiKelompokArusKasService::class)->validateCoaIdsForValidator($validator, $this->input('coa_ids'));
            },
        ];
    }
}
