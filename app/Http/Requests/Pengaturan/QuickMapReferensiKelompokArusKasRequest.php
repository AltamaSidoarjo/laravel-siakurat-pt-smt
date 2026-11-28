<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use Illuminate\Foundation\Http\FormRequest;

class QuickMapReferensiKelompokArusKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
            'referensi_id' => ['required', 'integer', 'exists:referensi_kelompok_arus_kas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'coa_id.required' => 'Akun COA wajib dipilih.',
            'coa_id.integer' => 'ID akun COA harus berupa bilangan bulat.',
            'coa_id.exists' => 'Akun COA yang dipilih tidak ditemukan.',
            'referensi_id.required' => 'Pos referensi wajib dipilih.',
            'referensi_id.integer' => 'ID pos referensi harus berupa bilangan bulat.',
            'referensi_id.exists' => 'Pos referensi yang dipilih tidak ditemukan.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (is_numeric($this->input('coa_id'))) {
                    app(ReferensiKelompokArusKasService::class)->validateCoaIdsForValidator(
                        $validator,
                        [(int) $this->input('coa_id')],
                        'coa_id',
                        false,
                    );
                }
            },
        ];
    }
}
