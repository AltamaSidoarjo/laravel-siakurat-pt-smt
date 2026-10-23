<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateSettingBridgingObatRequest;
use App\Services\Pengaturan\SettingBridgingObatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingBridgingObatController extends Controller
{
    public function __construct(
        private readonly SettingBridgingObatService $settingService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.setting-obat.index', [
            'page' => 'app',
            'setting' => $this->settingService->getSetting(),
            'coaOptions' => $this->settingService->getCoaOptions(),
        ]);
    }

    public function update(UpdateSettingBridgingObatRequest $request): RedirectResponse
    {
        $this->settingService->save($request->validated());

        return redirect()
            ->route('pengaturan.setting-obat.index')
            ->with('success', 'Pengaturan Akun Obat & BHP berhasil disimpan.');
    }
}
