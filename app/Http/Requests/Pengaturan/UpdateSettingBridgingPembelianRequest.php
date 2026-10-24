<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingBridgingPembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'persediaan_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'ppn_masukan_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'hutang_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'persediaan_non_medis_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'hutang_non_medis_coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'persediaan_obat_coa_id.required' => 'Akun Persediaan Obat & BHP wajib dipilih.',
            'persediaan_obat_coa_id.exists' => 'Akun Persediaan Obat & BHP tidak valid.',
            'ppn_masukan_coa_id.required' => 'Akun PPN Masukan wajib dipilih.',
            'ppn_masukan_coa_id.exists' => 'Akun PPN Masukan tidak valid.',
            'hutang_obat_coa_id.required' => 'Akun Hutang Pembelian Obat & BHP wajib dipilih.',
            'hutang_obat_coa_id.exists' => 'Akun Hutang Pembelian Obat & BHP tidak valid.',
            'persediaan_non_medis_coa_id.required' => 'Akun Persediaan / Beban Non Medis wajib dipilih.',
            'persediaan_non_medis_coa_id.exists' => 'Akun Persediaan / Beban Non Medis tidak valid.',
            'hutang_non_medis_coa_id.required' => 'Akun Hutang Pembelian Non Medis wajib dipilih.',
            'hutang_non_medis_coa_id.exists' => 'Akun Hutang Pembelian Non Medis tidak valid.',
        ];
    }
}
