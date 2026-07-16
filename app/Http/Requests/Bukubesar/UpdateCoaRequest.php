<?php

namespace App\Http\Requests\Bukubesar;

use App\Models\Coa;
use App\Services\Bukubesar\CoaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Coa $coa */
        $coa = $this->route('coa');

        return [
            'status_aktif' => ['required', 'integer', 'in:0,1'],
            'parent_id' => ['nullable', 'integer', 'exists:coa,id', Rule::notIn([$coa->id])],
            'tipe_coa' => ['required', 'string', 'max:255'],
            'arus_kas_aktivitas' => ['nullable', Rule::in(['operasi', 'investasi', 'pendanaan'])],
            'arus_kas_kelompok' => ['nullable', 'required_with:arus_kas_aktivitas', 'string', 'max:150'],
            'kode' => ['required', 'string', 'max:20', Rule::unique('coa', 'kode')->ignore($coa->id)],
            'nama' => ['required', 'string', 'max:100', Rule::unique('coa', 'nama')->ignore($coa->id)],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status_aktif' => 'Status Aktif',
            'parent_id' => 'Parent Coa',
            'tipe_coa' => 'Tipe Coa',
            'arus_kas_aktivitas' => 'Aktivitas Arus Kas',
            'arus_kas_kelompok' => 'Kelompok Arus Kas',
            'kode' => 'Kode',
            'nama' => 'Nama',
            'deskripsi' => 'Deskripsi',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                /** @var Coa $coa */
                $coa = $this->route('coa');
                $parentId = $this->integer('parent_id');

                if (! $parentId) {
                    return;
                }

                $coaService = app(CoaService::class);

                if ($coaService->wouldCreateHierarchyCycle((int) $coa->id, $parentId)) {
                    $validator->errors()->add(
                        'parent_id',
                        'Parent COA tidak boleh menggunakan akun turunan dari COA ini.'
                    );
                }

                if ($coaService->cannotBeSelectedAsParent($parentId)) {
                    $validator->errors()->add(
                        'parent_id',
                        'COA child level paling bawah yang sudah memiliki transaksi bukubesar tidak dapat dijadikan parent.'
                    );
                }
            },
        ];
    }
}
