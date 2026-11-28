<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\SettingArusKas;

class UpdateSettingArusKasRequest extends StoreSettingArusKasRequest
{
    protected function settingArusKasId(): ?int
    {
        $routeParam = $this->route('settingArusKas')
            ?? $this->route('setting_arus_kas')
            ?? $this->route('id');

        if ($routeParam instanceof SettingArusKas) {
            return (int) $routeParam->getKey();
        }

        if (is_numeric($routeParam) && (int) $routeParam > 0) {
            return (int) $routeParam;
        }

        return null;
    }
}
