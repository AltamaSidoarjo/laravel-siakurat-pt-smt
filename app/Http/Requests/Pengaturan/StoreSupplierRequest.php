<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_supplier' => ['required', 'string', 'max:100', 'unique:supplier,kode_supplier'],
            'nama_supplier' => ['required', 'string', 'max:200'],
            'kategori_supplier' => ['nullable', 'string', 'max:200'],
            'email_supplier' => ['nullable', 'email', 'max:255'],
            'alamat_supplier' => ['nullable', 'string'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }
}
