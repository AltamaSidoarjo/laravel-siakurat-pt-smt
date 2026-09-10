@php
    $isEdit = isset($invoicePendapatan);
    $isImported = $isImported ?? false;
    $details = old('rincian', $isEdit ? $invoicePendapatan->rincian->map(fn ($item) => [
        'id' => $item->id,
        'coa_id' => $item->coa_id,
        'pelaksana_id' => $item->pelaksana_id,
        'kode_proyek' => $item->kode_proyek,
        'kuantitas' => (float) $item->kuantitas,
        'harga' => (float) $item->harga,
        'catatan' => $item->catatan,
    ])->toArray() : [[
        'coa_id' => '', 'pelaksana_id' => '', 'kode_proyek' => null,
        'kuantitas' => 1, 'harga' => 0, 'catatan' => '',
    ]]);
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">Header</div>
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="nomor_faktur" class="col-12 col-sm-2 col-form-label fw-bold">Nomor<span class="text-danger">*</span></label>
                <div class="col"><input type="text" id="nomor_faktur" name="nomor_faktur" class="form-control" value="{{ old('nomor_faktur', $invoicePendapatan->nomor_faktur ?? '') }}" required @readonly($isImported)></div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="tanggal_faktur" class="col-12 col-sm-2 col-form-label fw-bold">Tanggal<span class="text-danger">*</span></label>
                <div class="col"><input type="date" id="tanggal_faktur" name="tanggal_faktur" class="form-control" value="{{ old('tanggal_faktur', isset($invoicePendapatan) ? optional($invoicePendapatan->tanggal_faktur)->format('Y-m-d') : now()->toDateString()) }}" required></div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="pelanggan_id" class="col-12 col-sm-2 col-form-label fw-bold">Penjamin<span class="text-danger">*</span></label>
                <div class="col">
                    <select id="pelanggan_id" name="pelanggan_id" class="form-select select2 select2-header" required @disabled($isImported)>
                        <option value="">Pilih Penjamin</option>
                        @foreach ($pelangganOptions as $pelanggan)
                            <option value="{{ $pelanggan->id }}" @selected((string) old('pelanggan_id', $invoicePendapatan->pelanggan_id ?? '') === (string) $pelanggan->id)>{{ $pelanggan->kode_pelanggan }} - {{ $pelanggan->nama_pelanggan }}</option>
                        @endforeach
                    </select>
                    @if ($isImported)<input type="hidden" name="pelanggan_id" value="{{ $invoicePendapatan->pelanggan_id }}">@endif
                </div>
            </div>
            <div class="row align-items-center mb-2">
                <label for="akun_piutang_id" class="col-12 col-sm-2 col-form-label fw-bold">Akun Piutang<span class="text-danger">*</span></label>
                <div class="col"><select id="akun_piutang_id" name="akun_piutang_id" class="form-select select2 select2-header" required><option value="">Pilih Akun Piutang</option>@foreach ($receivableCoaOptions as $coa)<option value="{{ $coa->id }}" @selected((string) old('akun_piutang_id', $invoicePendapatan->akun_piutang_id ?? '') === (string) $coa->id)>{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach</select></div>
            </div>
            @if ($apiOptionsError)
                <div class="alert alert-warning mb-2">{{ $apiOptionsError }}</div>
            @endif
            @foreach ([['nama_pasien', 'Nama Pasien', true], ['nomer_rekam_medis', 'No. RM', false]] as [$field, $label, $required])
                <div class="row align-items-center mb-2">
                    <label for="{{ $field }}" class="col-12 col-sm-2 col-form-label fw-bold">{{ $label }}@if($required)<span class="text-danger">*</span>@endif</label>
                    <div class="col"><input type="text" id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field, $invoicePendapatan->{$field} ?? '') }}" @required($required) @readonly($isImported)></div>
                </div>
            @endforeach
            @foreach ([['nama_dokter', 'Dokter', $dokterOptions], ['nama_poli', 'Poli', $poliOptions]] as [$field, $label, $options])
                @php($selectedValue = old($field, $invoicePendapatan->{$field} ?? ''))
                <div class="row align-items-center mb-2">
                    <label for="{{ $field }}" class="col-12 col-sm-2 col-form-label fw-bold">{{ $label }}</label>
                    <div class="col">
                        <select id="{{ $field }}" name="{{ $field }}" class="form-select select2" @disabled($isImported)>
                            <option value="">Pilih {{ $label }}</option>
                            @if (filled($selectedValue) && ! $options->contains('nama', $selectedValue))
                                <option value="{{ $selectedValue }}" selected>{{ $selectedValue }}</option>
                            @endif
                            @foreach ($options as $option)
                                <option value="{{ $option['nama'] }}" @selected($selectedValue === $option['nama'])>{{ $option['nama'] }}</option>
                            @endforeach
                        </select>
                        @if ($isImported)<input type="hidden" name="{{ $field }}" value="{{ $selectedValue }}">@endif
                    </div>
                </div>
            @endforeach
            <div class="row align-items-center">
                <label for="keterangan" class="col-12 col-sm-2 col-form-label fw-bold">Keterangan</label>
                <div class="col"><textarea id="keterangan" name="keterangan" class="form-control" rows="3">{{ old('keterangan', $invoicePendapatan->keterangan ?? '') }}</textarea></div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">Rincian</div>
        <div class="card-body">
            <div class="table-responsive"><table id="invoice-detail-table" class="table table-sm table-bordered">
                <thead><tr><th style="min-width:260px">Akun Pendapatan<span class="text-danger">*</span></th><th style="min-width:240px">Pelaksana</th><th style="min-width:100px">Kuantitas<span class="text-danger">*</span></th><th style="min-width:150px">Harga<span class="text-danger">*</span></th><th style="min-width:150px">Subtotal</th><th style="min-width:180px">Catatan</th><th>#</th></tr></thead>
                <tbody>
                @foreach ($details as $index => $detail)
                    <tr>
                        <td><input type="hidden" name="rincian[{{ $index }}][id]" value="{{ $detail['id'] ?? '' }}"><select name="rincian[{{ $index }}][coa_id]" class="form-select select2 select2-detail" required><option value="">Pilih Akun</option>@foreach ($revenueCoaOptions as $coa)<option value="{{ $coa->id }}" @selected((string) ($detail['coa_id'] ?? '') === (string) $coa->id)>{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach</select></td>
                        <td><select name="rincian[{{ $index }}][pelaksana_id]" class="form-select select2 select2-detail"><option value="">Tanpa Pelaksana</option>@foreach ($pelaksanaOptions as $pelaksana)<option value="{{ $pelaksana->id }}" @selected((string) ($detail['pelaksana_id'] ?? '') === (string) $pelaksana->id)>{{ $pelaksana->nama_pelaksana }}@if(!$pelaksana->status_aktif) (Nonaktif)@endif</option>@endforeach</select><input type="hidden" name="rincian[{{ $index }}][kode_proyek]" value="{{ $detail['kode_proyek'] ?? '' }}"></td>
                        <td><input type="number" name="rincian[{{ $index }}][kuantitas]" class="form-control text-end detail-quantity" min="0.01" step="0.01" value="{{ $detail['kuantitas'] ?? 1 }}" required></td>
                        <td><input type="number" name="rincian[{{ $index }}][harga]" class="form-control text-end detail-price" min="0.01" step="0.01" value="{{ $detail['harga'] ?? 0 }}" required></td>
                        <td><input type="text" class="form-control text-end detail-subtotal" readonly></td>
                        <td><input type="text" name="rincian[{{ $index }}][catatan]" class="form-control" value="{{ $detail['catatan'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-light text-danger remove-detail"><i class="bi bi-trash3-fill"></i></button></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="4" class="text-end">Grand Total</th><th><input type="text" id="invoice-grandtotal" class="form-control text-end fw-bold" readonly></th><th colspan="2"></th></tr></tfoot>
            </table></div>
            <div class="text-end"><button type="button" id="add-invoice-detail" class="btn btn-outline-primary"><i class="bi bi-plus-circle-fill"></i> Tambah Rincian</button></div>
        </div>
    </div>

    <div class="card border-light shadow-sm"><div class="card-body d-flex justify-content-end gap-3"><a href="{{ route('pendapatan.invoice.index') }}" class="btn btn-light fw-bold">Batal</a><button type="submit" class="btn btn-success fw-bold">Simpan</button></div></div>
</div>

<template id="invoice-detail-template">
    <tr>
        <td><select data-name="coa_id" class="form-select select2 select2-detail" required><option value="">Pilih Akun</option>@foreach ($revenueCoaOptions as $coa)<option value="{{ $coa->id }}">{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach</select></td>
        <td><select data-name="pelaksana_id" class="form-select select2 select2-detail"><option value="">Tanpa Pelaksana</option>@foreach ($pelaksanaOptions->where('status_aktif', true) as $pelaksana)<option value="{{ $pelaksana->id }}">{{ $pelaksana->nama_pelaksana }}</option>@endforeach</select><input type="hidden" data-name="kode_proyek"></td>
        <td><input type="number" data-name="kuantitas" class="form-control text-end detail-quantity" min="0.01" step="0.01" value="1" required></td>
        <td><input type="number" data-name="harga" class="form-control text-end detail-price" min="0.01" step="0.01" value="0" required></td>
        <td><input type="text" class="form-control text-end detail-subtotal" readonly></td>
        <td><input type="text" data-name="catatan" class="form-control"></td>
        <td><button type="button" class="btn btn-sm btn-light text-danger remove-detail"><i class="bi bi-trash3-fill"></i></button></td>
    </tr>
</template>
