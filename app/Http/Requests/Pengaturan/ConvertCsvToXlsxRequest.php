<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class ConvertCsvToXlsxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_file.required' => 'File CSV wajib dipilih.',
            'source_file.uploaded' => sprintf(
                'Upload file gagal. Batas upload PHP saat ini %s dan batas post %s. Gunakan file yang lebih kecil atau naikkan limit PHP.',
                ini_get('upload_max_filesize') ?: 'tidak diketahui',
                ini_get('post_max_size') ?: 'tidak diketahui'
            ),
            'source_file.file' => 'File sumber tidak valid.',
            'source_file.mimes' => 'Format file harus CSV atau TXT.',
            'source_file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
