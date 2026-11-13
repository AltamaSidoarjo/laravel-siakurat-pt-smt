<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndikatorKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status_aktif')) {
            $this->merge(['status_aktif' => $this->boolean('status_aktif')]);
        } else {
            $this->merge(['status_aktif' => true]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:indikator_kinerja,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'satuan' => ['required', Rule::enum(SatuanIndikator::class)],
            'tipe_kalkulasi' => ['required', Rule::enum(TipeKalkulasi::class)],
            'basis_pembilang' => ['nullable', Rule::enum(BasisPembilang::class)],
            'basis_penyebut' => ['nullable', Rule::enum(BasisPenyebut::class)],
            'variabel_manual_id' => ['nullable', 'integer', 'exists:indikator_kinerja_variabel_manual,id'],
            'definisi_operasional' => ['nullable', 'string'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'status_aktif' => ['nullable', 'boolean'],
            'pola_evaluasi' => ['required', Rule::enum(PolaEvaluasi::class)],
            'target_hijau_min' => ['nullable', 'numeric'],
            'target_hijau_max' => ['nullable', 'numeric'],
            'target_kuning_min' => ['nullable', 'numeric'],
            'target_kuning_max' => ['nullable', 'numeric'],
            'target_merah_min' => ['nullable', 'numeric'],
            'target_merah_max' => ['nullable', 'numeric'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.coa_id' => ['required_with:mappings', 'integer', 'exists:coa,id'],
            'mappings.*.peran' => ['required_with:mappings', Rule::enum(PeranCoa::class)],
            'mappings.*.is_excluded' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode indikator wajib diisi.',
            'kode.unique' => 'Kode indikator sudah digunakan.',
            'nama.required' => 'Nama indikator wajib diisi.',
            'satuan.required' => 'Satuan indikator wajib dipilih.',
            'tipe_kalkulasi.required' => 'Tipe kalkulasi wajib dipilih.',
            'pola_evaluasi.required' => 'Pola evaluasi target wajib dipilih.',
            'mappings.*.coa_id.exists' => 'Akun COA yang dipilih tidak valid.',
            'mappings.*.peran.enum' => 'Peran COA yang dipilih tidak valid.',
        ];
    }
}
