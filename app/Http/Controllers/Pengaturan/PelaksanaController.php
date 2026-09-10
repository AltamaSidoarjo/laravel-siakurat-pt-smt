<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StorePelaksanaRequest;
use App\Http\Requests\Pengaturan\UpdatePelaksanaRequest;
use App\Models\Pelaksana;
use App\Services\Pengaturan\PelaksanaManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelaksanaController extends Controller
{
    public function __construct(
        private readonly PelaksanaManagementService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('pengaturan.pelaksana.index', [
            'page' => 'app',
            'search' => $request->string('search')->toString(),
            'pelaksanas' => $this->service->paginate($request->string('search')->toString()),
        ]);
    }

    public function create(): View
    {
        return view('pengaturan.pelaksana.create', ['page' => 'app']);
    }

    public function store(StorePelaksanaRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('pengaturan.pelaksana.index')->with('success', 'Pelaksana berhasil disimpan.');
    }

    public function edit(Pelaksana $pelaksana): View
    {
        return view('pengaturan.pelaksana.edit', ['page' => 'app', 'pelaksana' => $pelaksana]);
    }

    public function update(UpdatePelaksanaRequest $request, Pelaksana $pelaksana): RedirectResponse
    {
        $this->service->update($pelaksana, $request->validated());

        return redirect()->route('pengaturan.pelaksana.index')->with('success', 'Pelaksana berhasil diperbarui.');
    }

    public function destroy(Pelaksana $pelaksana): RedirectResponse
    {
        if (! $this->service->delete($pelaksana)) {
            return redirect()->route('pengaturan.pelaksana.index')
                ->with('error', 'Pelaksana sudah digunakan pada rincian invoice. Nonaktifkan pelaksana sebagai gantinya.');
        }

        return redirect()->route('pengaturan.pelaksana.index')->with('success', 'Pelaksana berhasil dihapus.');
    }
}
