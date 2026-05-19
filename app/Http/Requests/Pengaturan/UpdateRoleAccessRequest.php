<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('roles', 'nama')->ignore($role->id)],
            'kode' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'kode')->ignore($role->id)],
            'deskripsi' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.can_view' => ['nullable', 'boolean'],
            'permissions.*.can_create' => ['nullable', 'boolean'],
            'permissions.*.can_update' => ['nullable', 'boolean'],
            'permissions.*.can_delete' => ['nullable', 'boolean'],
        ];
    }
}
