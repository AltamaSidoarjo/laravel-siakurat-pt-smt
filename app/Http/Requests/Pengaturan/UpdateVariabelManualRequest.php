<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Models\IndikatorKinerjaVariabelManual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariabelManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $variabel = $this->route('variabelManual');
        $variabelId = $variabel instanceof IndikatorKinerjaVariabelManual ? $variabel->id : (int) $variabel;

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('indikator_kinerja_variabel_manual', 'kode')->ignore($variabelId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'satuan' => ['required', Rule::enum(SatuanIndikator::class)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode variabel manual wajib diisi.',
            'kode.unique' => 'Kode variabel manual sudah digunakan.',
            'nama.required' => 'Nama variabel manual wajib diisi.',
            'satuan.required' => 'Satuan indikator wajib dipilih.',
        ];
    }
}
