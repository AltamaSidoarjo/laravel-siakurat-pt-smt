<?php

namespace App\Http\Requests\Bukubesar;

use App\Models\JurnalUmum;
use Illuminate\Validation\Rule;

class UpdateJurnalUmumRequest extends StoreJurnalUmumRequest
{
    public function rules(): array
    {
        /** @var JurnalUmum $jurnalUmum */
        $jurnalUmum = $this->route('jurnalUmum');

        return $this->journalRules([
            Rule::unique('jurnal_umum', 'nomer')->ignore($jurnalUmum->id),
        ]);
    }
}
