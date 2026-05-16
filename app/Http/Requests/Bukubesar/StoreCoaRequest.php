<?php

namespace App\Http\Requests\Bukubesar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_aktif' => ['required', 'integer', 'in:0,1'],
            'parent_id' => ['nullable', 'integer', 'exists:coa,id'],
            'tipe_coa' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'max:20', Rule::unique('coa', 'kode')],
            'nama' => ['required', 'string', 'max:100', Rule::unique('coa', 'nama')],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status_aktif' => 'Status Aktif',
            'parent_id' => 'Parent Coa',
            'tipe_coa' => 'Tipe Coa',
            'kode' => 'Kode',
            'nama' => 'Nama',
            'deskripsi' => 'Deskripsi',
        ];
    }
}
