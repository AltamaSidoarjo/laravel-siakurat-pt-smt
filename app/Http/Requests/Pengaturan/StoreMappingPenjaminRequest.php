<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Coa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMappingPenjaminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'penjamin_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mapping_penjamin_piutang', 'penjamin_id'),
            ],
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->has('coa_id')) {
                    return;
                }

                $coa = Coa::query()
                    ->withCount('children')
                    ->find($this->integer('coa_id'));

                if ($coa === null
                    || (int) $coa->status_aktif !== 1
                    || ! (bool) $coa->is_postable
                    || (int) $coa->children_count > 0
                    || ! str_contains(Str::lower((string) $coa->tipe_coa), 'piutang')) {
                    $validator->errors()->add(
                        'coa_id',
                        'Akun harus merupakan COA piutang yang aktif, postable, dan tidak memiliki akun turunan.',
                    );
                }
            },
        ];
    }
}
