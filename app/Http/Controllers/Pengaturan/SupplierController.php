<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreSupplierRequest;
use App\Http\Requests\Pengaturan\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\Pengaturan\SupplierManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierManagementService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('pengaturan.supplier.index', [
            'page' => 'app',
            'search' => $request->string('search')->toString(),
            'suppliers' => $this->service->paginate($request->string('search')->toString()),
        ]);
    }

    public function create(): View
    {
        return view('pengaturan.supplier.create', ['page' => 'app']);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('pengaturan.supplier.index')->with('success', 'Supplier berhasil disimpan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('pengaturan.supplier.edit', ['page' => 'app', 'supplier' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return redirect()->route('pengaturan.supplier.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if (! $this->service->delete($supplier)) {
            return redirect()->route('pengaturan.supplier.index')
                ->with('error', 'Supplier sudah digunakan pada transaksi pembelian. Nonaktifkan supplier sebagai gantinya.');
        }

        return redirect()->route('pengaturan.supplier.index')->with('success', 'Supplier berhasil dihapus.');
    }
}
