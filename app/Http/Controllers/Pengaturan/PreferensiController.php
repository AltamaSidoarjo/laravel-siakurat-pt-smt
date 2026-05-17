<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdatePreferensiRequest;
use App\Services\Pengaturan\PreferensiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PreferensiController extends Controller
{
    public function __construct(
        private readonly PreferensiService $preferensiService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.preferensi.index', [
            'page' => 'app',
            'preferensi' => $this->preferensiService->getFormData(),
        ]);
    }

    public function update(UpdatePreferensiRequest $request): RedirectResponse
    {
        $this->preferensiService->save(
            $request->validated(),
            $request->file('logo_file'),
        );

        return redirect()
            ->route('pengaturan.preferensi.index')
            ->with('success', 'Preferensi berhasil disimpan.');
    }
}
