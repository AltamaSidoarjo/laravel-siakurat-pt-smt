<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLayananInformasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'tipe' => ['required', 'string', 'in:info,success,warning,danger'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tampilkan_popup' => ['nullable', 'boolean'],
            'status_aktif' => ['nullable', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul informasi wajib diisi.',
            'judul.max' => 'Judul informasi maksimal 255 karakter.',
            'isi.required' => 'Isi informasi wajib diisi.',
            'tipe.required' => 'Tipe informasi wajib dipilih.',
            'tipe.in' => 'Tipe informasi harus salah satu dari: info, success, warning, danger.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }
}
