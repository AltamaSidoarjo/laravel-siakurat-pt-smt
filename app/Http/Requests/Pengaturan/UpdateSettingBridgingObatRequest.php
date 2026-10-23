<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingBridgingObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pendapatan_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'hpp_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'persediaan_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'piutang_obat_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'kas_uang_muka_coa_id' => ['nullable', 'integer', 'exists:coa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pendapatan_obat_coa_id.required' => 'Akun Pendapatan Obat wajib dipilih.',
            'pendapatan_obat_coa_id.exists' => 'Akun Pendapatan Obat tidak valid.',
            'hpp_obat_coa_id.required' => 'Akun HPP Obat wajib dipilih.',
            'hpp_obat_coa_id.exists' => 'Akun HPP Obat tidak valid.',
            'persediaan_obat_coa_id.required' => 'Akun Persediaan Obat wajib dipilih.',
            'persediaan_obat_coa_id.exists' => 'Akun Persediaan Obat tidak valid.',
            'piutang_obat_coa_id.required' => 'Akun Piutang Obat & BHP wajib dipilih.',
            'piutang_obat_coa_id.exists' => 'Akun Piutang Obat & BHP tidak valid.',
            'kas_uang_muka_coa_id.exists' => 'Akun Kas Uang Muka tidak valid.',
        ];
    }
}
