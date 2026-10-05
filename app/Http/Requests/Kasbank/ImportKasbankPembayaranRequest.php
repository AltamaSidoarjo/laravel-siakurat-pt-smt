<?php

namespace App\Http\Requests\Kasbank;

use Illuminate\Foundation\Http\FormRequest;

class ImportKasbankPembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File XLSX wajib dipilih.',
            'file.uploaded' => sprintf(
                'Upload file gagal. Batas upload PHP saat ini %s dan batas post %s. Gunakan file yang lebih kecil atau naikkan limit PHP.',
                ini_get('upload_max_filesize') ?: 'tidak diketahui',
                ini_get('post_max_size') ?: 'tidak diketahui'
            ),
            'file.file' => 'File sumber tidak valid.',
            'file.mimes' => 'Format file harus XLSX atau XLS.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
