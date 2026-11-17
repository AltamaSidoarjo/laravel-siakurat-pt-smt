<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreLayananInformasiRequest;
use App\Http\Requests\Pengaturan\UpdateLayananInformasiRequest;
use App\Models\LayananInformasi;
use App\Services\Pengaturan\LayananInformasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LayananInformasiController extends Controller
{
    public function __construct(
        private readonly LayananInformasiService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('pengaturan.layanan-informasi.index', [
            'page' => 'app',
            'search' => $request->string('search')->toString(),
            'informasiList' => $this->service->paginate($request->string('search')->toString()),
        ]);
    }

    public function create(): View
    {
        return view('pengaturan.layanan-informasi.create', ['page' => 'app']);
    }

    public function store(StoreLayananInformasiRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('pengaturan.layanan-informasi.index')
            ->with('success', 'Layanan informasi berhasil disimpan.');
    }

    public function edit(LayananInformasi $layananInformasi): View
    {
        return view('pengaturan.layanan-informasi.edit', [
            'page' => 'app',
            'informasi' => $layananInformasi,
        ]);
    }

    public function update(UpdateLayananInformasiRequest $request, LayananInformasi $layananInformasi): RedirectResponse
    {
        $this->service->update($layananInformasi, $request->validated());

        return redirect()->route('pengaturan.layanan-informasi.index')
            ->with('success', 'Layanan informasi berhasil diperbarui.');
    }

    public function destroy(LayananInformasi $layananInformasi): RedirectResponse
    {
        $this->service->delete($layananInformasi);

        return redirect()->route('pengaturan.layanan-informasi.index')
            ->with('success', 'Layanan informasi berhasil dihapus.');
    }
}
