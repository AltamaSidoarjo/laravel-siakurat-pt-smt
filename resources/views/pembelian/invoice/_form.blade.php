@php
    $isEdit = isset($invoicePembelian);
    $details = old('rincian', $isEdit ? $invoicePembelian->rincian->map(fn ($item) => [
        'id' => $item->id,
        'coa_id' => $item->coa_id,
        'kuantitas' => (float) $item->kuantitas,
        'harga' => (float) $item->harga_barang,
        'catatan' => $item->catatan,
    ])->toArray() : [[
        'coa_id' => '',
        'kuantitas' => 1,
        'harga' => 0,
        'catatan' => '',
    ]]);
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">Header</div>
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="nomer_faktur" class="col-12 col-sm-2 col-form-label fw-bold">Nomor<span class="text-danger">*</span></label>
                <div class="col">
                    <input type="text" id="nomer_faktur" name="nomer_faktur" class="form-control" value="{{ old('nomer_faktur', $invoicePembelian->nomer_faktur ?? '') }}" required>
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="tanggal_faktur" class="col-12 col-sm-2 col-form-label fw-bold">Tanggal<span class="text-danger">*</span></label>
                <div class="col">
                    <input type="date" id="tanggal_faktur" name="tanggal_faktur" class="form-control" value="{{ old('tanggal_faktur', isset($invoicePembelian) ? optional($invoicePembelian->tanggal_faktur)->format('Y-m-d') : now()->toDateString()) }}" required>
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="tanggal_jatuh_tempo" class="col-12 col-sm-2 col-form-label fw-bold">Tgl Jatuh Tempo</label>
                <div class="col">
                    <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" class="form-control" value="{{ old('tanggal_jatuh_tempo', isset($invoicePembelian) ? optional($invoicePembelian->tanggal_jatuh_tempo)->format('Y-m-d') : '') }}">
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="supplier_id" class="col-12 col-sm-2 col-form-label fw-bold">Supplier<span class="text-danger">*</span></label>
                <div class="col">
                    <select id="supplier_id" name="supplier_id" class="form-select select2 select2-header" required>
                        <option value="">Pilih Supplier</option>
                        @foreach ($supplierOptions as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $invoicePembelian->supplier_id ?? '') === (string) $supplier->id)>
                                {{ $supplier->kode_supplier ? $supplier->kode_supplier . ' - ' : '' }}{{ $supplier->nama_supplier }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="akun_hutang_id" class="col-12 col-sm-2 col-form-label fw-bold">Akun Hutang<span class="text-danger">*</span></label>
                <div class="col">
                    <select id="akun_hutang_id" name="akun_hutang_id" class="form-select select2 select2-header" required>
                        <option value="">Pilih Akun Hutang</option>
                        @foreach ($payableCoaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_hutang_id', $invoicePembelian->akun_hutang_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row align-items-center">
                <label for="keterangan" class="col-12 col-sm-2 col-form-label fw-bold">Keterangan</label>
                <div class="col">
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="3">{{ old('keterangan', $invoicePembelian->keterangan ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">Rincian</div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="invoice-detail-table" class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th style="min-width:300px">Akun Pembelian / Biaya / Persediaan<span class="text-danger">*</span></th>
                            <th style="min-width:100px">Kuantitas<span class="text-danger">*</span></th>
                            <th style="min-width:150px">Harga<span class="text-danger">*</span></th>
                            <th style="min-width:150px">Subtotal</th>
                            <th style="min-width:180px">Catatan</th>
                            <th>#</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($details as $index => $detail)
                        <tr>
                            <td>
                                <input type="hidden" name="rincian[{{ $index }}][id]" value="{{ $detail['id'] ?? '' }}">
                                <select name="rincian[{{ $index }}][coa_id]" class="form-select select2 select2-detail" required>
                                    <option value="">Pilih Akun</option>
                                    @foreach ($purchasingCoaOptions as $coa)
                                        <option value="{{ $coa->id }}" @selected((string) ($detail['coa_id'] ?? '') === (string) $coa->id)>
                                            {{ $coa->kode }} - {{ $coa->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="rincian[{{ $index }}][kuantitas]" class="form-control text-end detail-quantity" min="0.01" step="0.01" value="{{ $detail['kuantitas'] ?? 1 }}" required></td>
                            <td><input type="number" name="rincian[{{ $index }}][harga]" class="form-control text-end detail-price" min="0.01" step="0.01" value="{{ $detail['harga'] ?? 0 }}" required></td>
                            <td><input type="text" class="form-control text-end detail-subtotal" readonly></td>
                            <td><input type="text" name="rincian[{{ $index }}][catatan]" class="form-control" value="{{ $detail['catatan'] ?? '' }}"></td>
                            <td><button type="button" class="btn btn-sm btn-light text-danger remove-detail"><i class="bi bi-trash3-fill"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Grand Total</th>
                            <th><input type="text" id="invoice-grandtotal" class="form-control text-end fw-bold" readonly></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="text-end">
                <button type="button" id="add-invoice-detail" class="btn btn-outline-primary"><i class="bi bi-plus-circle-fill"></i> Tambah Rincian</button>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body d-flex justify-end gap-3 justify-content-end">
            <a href="{{ route('pembelian.invoice.index') }}" class="btn btn-light fw-bold">Batal</a>
            <button type="submit" class="btn btn-success fw-bold">Simpan</button>
        </div>
    </div>
</div>

<template id="invoice-detail-template">
    <tr>
        <td>
            <select data-name="coa_id" class="form-select select2 select2-detail" required>
                <option value="">Pilih Akun</option>
                @foreach ($purchasingCoaOptions as $coa)
                    <option value="{{ $coa->id }}">{{ $coa->kode }} - {{ $coa->nama }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" data-name="kuantitas" class="form-control text-end detail-quantity" min="0.01" step="0.01" value="1" required></td>
        <td><input type="number" data-name="harga" class="form-control text-end detail-price" min="0.01" step="0.01" value="0" required></td>
        <td><input type="text" class="form-control text-end detail-subtotal" readonly></td>
        <td><input type="text" data-name="catatan" class="form-control"></td>
        <td><button type="button" class="btn btn-sm btn-light text-danger remove-detail"><i class="bi bi-trash3-fill"></i></button></td>
    </tr>
</template>
