@php
    $isEdit = isset($pembayaranPembelian);
    $currentSupplierId = old('supplier_id', $pembayaranPembelian->supplier_id ?? '');
    $currentPotonganAdmin = (float) old('potongan_admin', $pembayaranPembelian->potongan_admin ?? 0);
    $currentTotalBayar = (float) old('total_bayar', $pembayaranPembelian->total_bayar ?? 0);
    $currentNominalBank = max($currentTotalBayar - $currentPotonganAdmin, 0);
    $selectedRows = old('rincian');

    if ($selectedRows === null && $isEdit) {
        $selectedRows = $pembayaranPembelian->rincian->map(function ($item) {
            $grandTotal = (float) ($item->fakturPembelian?->grandtotal ?? 0);
            $sudahTerbayar = (float) ($item->fakturPembelian?->sudah_terbayar ?? 0);
            $nominalBayar = (float) $item->nominal_bayar;
            $sisaTagihan = max($grandTotal - $sudahTerbayar + $nominalBayar, 0);

            return [
                'faktur_pembelian_id' => $item->faktur_pembelian_id,
                'nomer_faktur' => $item->fakturPembelian?->nomer_faktur,
                'tanggal_faktur' => optional($item->fakturPembelian?->tanggal_faktur)->format('Y-m-d'),
                'grandtotal' => $grandTotal,
                'sudah_terbayar' => $sudahTerbayar,
                'sisa_tagihan' => $sisaTagihan,
                'nominal_bayar' => $nominalBayar,
                'check' => true,
            ];
        })->toArray();
    }

    $selectedRows = $selectedRows ?? [];
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">
            Header
        </div>
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="input_nomor_pembayaran" class="col-12 col-sm-2 col-form-label fw-bold">
                    Nomor<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <input type="text" name="nomer_pembayaran" id="input_nomor_pembayaran" class="form-control" value="{{ old('nomer_pembayaran', $pembayaranPembelian->nomer_pembayaran ?? '') }}" required>
                </div>
            </div>

            @if ($isEdit)
                <input type="hidden" name="supplier_id" value="{{ $currentSupplierId }}">
                <div class="row align-items-center mb-2">
                    <label class="col-12 col-sm-2 col-form-label fw-bold">
                        Supplier<span class="text-danger">*</span>
                    </label>
                    <div class="col">
                        <input type="text" class="form-control" value="{{ $pembayaranPembelian->supplier?->kode_supplier }} - {{ $pembayaranPembelian->supplier?->nama_supplier }}" readonly>
                    </div>
                </div>
            @else
                <div class="row align-items-center mb-2">
                    <label for="select_supplier" class="col-12 col-sm-2 col-form-label fw-bold">
                        Supplier<span class="text-danger">*</span>
                    </label>
                    <div class="col">
                        <select name="supplier_id" id="select_supplier" class="form-select select2-supplier" required>
                            <option value="">Pilih opsi</option>
                            @foreach ($supplierOptions as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) $currentSupplierId === (string) $supplier->id)>
                                    {{ $supplier->kode_supplier }} - {{ $supplier->nama_supplier }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <div class="row align-items-center mb-2">
                <label for="input_tanggal" class="col-12 col-sm-2 col-form-label fw-bold">
                    Tanggal<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <input type="date" name="tanggal" id="input_tanggal" class="form-control" value="{{ old('tanggal', isset($pembayaranPembelian) ? optional($pembayaranPembelian->tanggal)->format('Y-m-d') : '') }}" required>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="select_akun_bank" class="col-12 col-sm-2 col-form-label fw-bold">
                    Akun Bank<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <select name="akun_bank_id" id="select_akun_bank" class="form-select select2-coa" required>
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_bank_id', $pembayaranPembelian->akun_bank_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_keterangan" class="col-12 col-sm-2 col-form-label fw-bold">
                    Keterangan
                </label>
                <div class="col">
                    <textarea name="keterangan" id="input_keterangan" class="form-control" rows="3">{{ old('keterangan', $pembayaranPembelian->keterangan ?? '') }}</textarea>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_potongan_admin" class="col-12 col-sm-2 col-form-label fw-bold">
                    Potongan Admin
                </label>
                <div class="col">
                    <input type="text" name="potongan_admin" id="input_potongan_admin" class="form-control text-end" value="{{ number_format($currentPotonganAdmin, 0, ',', '.') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">
            Detail
        </div>
        <div class="card-body">
            <div id="invoice_alert" class="alert alert-warning d-none mb-3"></div>
            <div class="table-responsive">
                <table id="table_data_detail" class="table table-sm table-bordered {{ count($selectedRows) === 0 ? 'd-none' : '' }}">
                    <thead>
                        <tr>
                            <th class="text-center align-middle" style="min-width: 220px;">Info Faktur</th>
                            <th class="text-center align-middle" style="min-width: 180px;">Nominal</th>
                            <th class="text-center align-middle" style="min-width: 180px;">Terhutang</th>
                            <th class="text-center align-middle" style="min-width: 180px;">Bayar<span class="text-danger">*</span></th>
                            <th class="text-center align-middle" style="min-width: 50px;">#</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($selectedRows as $index => $item)
                            @php
                                $grandTotal = (float) ($item['grandtotal'] ?? 0);
                                $sisaTagihan = (float) ($item['sisa_tagihan'] ?? max($grandTotal - (float) ($item['sudah_terbayar'] ?? 0), 0));
                                $nominalBayar = (float) ($item['nominal_bayar'] ?? 0);
                                $isChecked = filter_var($item['check'] ?? false, FILTER_VALIDATE_BOOLEAN);
                            @endphp
                            <tr>
                                <td class="align-middle">
                                    <div class="row">
                                        <div class="col-4 fw-bold">Nomor</div>
                                        <div class="col detail-nomor-faktur">: {{ $item['nomer_faktur'] ?? '-' }}</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-4 fw-bold">Tanggal</div>
                                        <div class="col detail-tanggal-faktur">: {{ $item['tanggal_faktur'] ?? '-' }}</div>
                                    </div>
                                    <input type="hidden" name="rincian[{{ $index }}][faktur_pembelian_id]" class="input-faktur-pembelian-id" value="{{ $item['faktur_pembelian_id'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][nomer_faktur]" value="{{ $item['nomer_faktur'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][tanggal_faktur]" value="{{ $item['tanggal_faktur'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][grandtotal]" class="input-grandtotal" value="{{ $grandTotal }}">
                                    <input type="hidden" name="rincian[{{ $index }}][sudah_terbayar]" value="{{ (float) ($item['sudah_terbayar'] ?? 0) }}">
                                    <input type="hidden" name="rincian[{{ $index }}][sisa_tagihan]" class="input-sisa-tagihan-raw" value="{{ $sisaTagihan }}">
                                </td>
                                <td class="align-middle">
                                    <input type="text" class="form-control text-end input-grandtotal-display" value="{{ number_format($grandTotal, 0, ',', '.') }}" readonly>
                                </td>
                                <td class="align-middle">
                                    <input type="text" class="form-control text-end input-sisa-tagihan-display" value="{{ number_format($sisaTagihan, 0, ',', '.') }}" readonly>
                                </td>
                                <td class="align-middle">
                                    <input type="text" name="rincian[{{ $index }}][nominal_bayar]" class="form-control text-end input-nominal-bayar" value="{{ number_format($nominalBayar, 0, ',', '.') }}" required>
                                </td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" name="rincian[{{ $index }}][check]" value="1" class="form-check-input check" @checked($isChecked)>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-center align-middle">Total</th>
                            <td>
                                <input type="text" class="form-control text-end" id="input_total_hutang_awal" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control text-end" id="input_total_hutang_sisa" readonly>
                            </td>
                            <td>
                                <input type="text" name="total_bayar" id="input_total_pembayaran" class="form-control text-end" value="{{ number_format((float) old('total_bayar', $pembayaranPembelian->total_bayar ?? 0), 0, ',', '.') }}" readonly>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">
            Akun
        </div>
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="select_akun_hutang" class="col-12 col-sm-2 col-form-label fw-bold">
                    Akun Hutang<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <select name="akun_hutang_id" id="select_akun_hutang" class="form-select select2-coa" required>
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_hutang_id', $pembayaranPembelian->akun_hutang_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="select_akun_potongan_admin" class="col-12 col-sm-2 col-form-label fw-bold">
                    Akun Potongan
                </label>
                <div class="col">
                    <select name="akun_potongan_admin_id" id="select_akun_potongan_admin" class="form-select select2-coa">
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_potongan_admin_id', $pembayaranPembelian->akun_potongan_admin_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_nominal_bank_keluar" class="col-12 col-sm-2 col-form-label fw-bold">
                    Nominal Bank
                </label>
                <div class="col">
                    <input type="text" id="input_nominal_bank_keluar" class="form-control text-end" value="{{ number_format($currentNominalBank, 0, ',', '.') }}" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="d-flex {{ $isEdit ? 'justify-content-between' : 'justify-content-end' }} gap-3">
                @if ($isEdit)
                    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                        <i class="bi bi-trash3-fill"></i> Hapus
                    </button>
                @endif

                <div class="d-flex gap-3">
                    <a href="{{ route('pembelian.pembayaran.index') }}" class="btn btn-light fw-bold">
                        <i class="bi bi-x-circle-fill"></i> Batal
                    </a>

                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-check-circle-fill"></i> {{ $isEdit ? 'Update' : 'Simpan' }}
                    </button>

                    <button type="submit" name="submit_action" value="save-print" class="btn btn-primary fw-bold">
                        <i class="bi bi-printer-fill"></i> {{ $isEdit ? 'Update & Print' : 'Simpan & Print' }}
                    </button>

                    @if ($isEdit)
                        <a class="btn btn-outline-primary fw-bold" target="_blank" href="{{ route('pembelian.pembayaran.print', $pembayaranPembelian) }}">
                            <i class="bi bi-printer"></i> Print
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<template id="detail-row-template">
    <tr>
        <td class="align-middle">
            <div class="row">
                <div class="col-4 fw-bold">Nomor</div>
                <div class="col detail-nomor-faktur">-</div>
            </div>
            <div class="row">
                <div class="col-4 fw-bold">Tanggal</div>
                <div class="col detail-tanggal-faktur">-</div>
            </div>
            <input type="hidden" name="rincian[__index__][faktur_pembelian_id]" class="input-faktur-pembelian-id">
            <input type="hidden" name="rincian[__index__][nomer_faktur]">
            <input type="hidden" name="rincian[__index__][tanggal_faktur]">
            <input type="hidden" name="rincian[__index__][grandtotal]" class="input-grandtotal" value="0">
            <input type="hidden" name="rincian[__index__][sudah_terbayar]" value="0">
            <input type="hidden" name="rincian[__index__][sisa_tagihan]" class="input-sisa-tagihan-raw" value="0">
        </td>
        <td class="align-middle">
            <input type="text" class="form-control text-end input-grandtotal-display" value="0" readonly>
        </td>
        <td class="align-middle">
            <input type="text" class="form-control text-end input-sisa-tagihan-display" value="0" readonly>
        </td>
        <td class="align-middle">
            <input type="text" name="rincian[__index__][nominal_bayar]" class="form-control text-end input-nominal-bayar" value="0" required>
        </td>
        <td class="text-center align-middle">
            <input type="checkbox" name="rincian[__index__][check]" value="1" class="form-check-input check">
        </td>
    </tr>
</template>
