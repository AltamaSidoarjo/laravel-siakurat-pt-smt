<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateSettingBridgingPembelianRequest;
use App\Services\Pengaturan\SettingBridgingPembelianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingBridgingPembelianController extends Controller
{
    public function __construct(
        private readonly SettingBridgingPembelianService $settingService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.setting-pembelian.index', [
            'page' => 'app',
            'setting' => $this->settingService->getSetting(),
            'coaOptions' => $this->settingService->getCoaOptions(),
        ]);
    }

    public function update(UpdateSettingBridgingPembelianRequest $request): RedirectResponse
    {
        $this->settingService->save($request->validated());

        return redirect()
            ->route('pengaturan.setting-pembelian.index')
            ->with('success', 'Pengaturan Akun Pembelian berhasil disimpan.');
    }
}
