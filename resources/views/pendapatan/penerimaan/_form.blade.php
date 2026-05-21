@php
    $isEdit = isset($penerimaanPendapatan);
    $currentPelangganId = old('pelanggan_id', $penerimaanPendapatan->pelanggan_id ?? '');
    $currentSelisihTarif = (float) old('selisih_tarif', $penerimaanPendapatan->selisih_tarif ?? 0);
    $currentJumlahPembayaran = (float) old('jumlah_pembayaran', $penerimaanPendapatan->jumlah_pembayaran ?? 0);
    $currentNominalBank = $currentJumlahPembayaran;
    $selectedRows = old('rincian');

    if ($selectedRows === null && $isEdit) {
        $selectedRows = $penerimaanPendapatan->rincian->map(function ($item) {
            $grandTotal = (float) ($item->fakturPenjualan?->grandtotal ?? 0);
            $sudahTerbayar = (float) ($item->fakturPenjualan?->sudah_terbayar ?? 0);
            $nominalBayar = (float) $item->nominal_bayar;
            $sisaTagihan = max($grandTotal - $sudahTerbayar + $nominalBayar, 0);

            return [
                'faktur_penjualan_id' => $item->faktur_penjualan_id,
                'nomor_faktur' => $item->fakturPenjualan?->nomor_faktur,
                'tanggal_faktur' => optional($item->fakturPenjualan?->tanggal_faktur)->format('Y-m-d'),
                'nama_pasien' => $item->fakturPenjualan?->nama_pasien,
                'nomer_rekam_medis' => $item->fakturPenjualan?->nomer_rekam_medis,
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
                <label for="input_nomor" class="col-12 col-sm-2 col-form-label fw-bold">
                    Nomor<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <input type="text" name="nomer" id="input_nomor" class="form-control" value="{{ old('nomer', $penerimaanPendapatan->nomer ?? '') }}" required>
                </div>
            </div>

            @if ($isEdit)
                <input type="hidden" name="pelanggan_id" value="{{ $currentPelangganId }}">
                <div class="row align-items-center mb-2">
                    <label class="col-12 col-sm-2 col-form-label fw-bold">
                        Penjamin<span class="text-danger">*</span>
                    </label>
                    <div class="col">
                        <input type="text" class="form-control" value="{{ $penerimaanPendapatan->pelanggan?->kode_pelanggan }} - {{ $penerimaanPendapatan->pelanggan?->nama_pelanggan }}" readonly>
                    </div>
                </div>
            @else
                <div class="row align-items-center mb-2">
                    <label for="select_pelanggan" class="col-12 col-sm-2 col-form-label fw-bold">
                        Penjamin<span class="text-danger">*</span>
                    </label>
                    <div class="col">
                        <select name="pelanggan_id" id="select_pelanggan" class="form-select select2-pelanggan" required>
                            <option value="">Pilih opsi</option>
                            @foreach ($pelangganOptions as $pelanggan)
                                <option value="{{ $pelanggan->id }}" @selected((string) $currentPelangganId === (string) $pelanggan->id)>
                                    {{ $pelanggan->kode_pelanggan }} - {{ $pelanggan->nama_pelanggan }}
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
                    <input type="date" name="tanggal" id="input_tanggal" class="form-control" value="{{ old('tanggal', isset($penerimaanPendapatan) ? optional($penerimaanPendapatan->tanggal)->format('Y-m-d') : '') }}" required>
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
                            <option value="{{ $coa->id }}" @selected((string) old('akun_bank_id', $penerimaanPendapatan->akun_bank_id ?? '') === (string) $coa->id)>
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
                    <textarea name="keterangan" id="input_keterangan" class="form-control" rows="3">{{ old('keterangan', $penerimaanPendapatan->keterangan ?? '') }}</textarea>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_selisih_tarif" class="col-12 col-sm-2 col-form-label fw-bold">
                    Selisih Tarif
                </label>
                <div class="col">
                    <input type="text" name="selisih_tarif" id="input_selisih_tarif" class="form-control text-end" value="{{ number_format($currentSelisihTarif, 0, ',', '.') }}">
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
                            <th class="text-center align-middle" style="min-width: 250px;">Info Faktur</th>
                            <th class="text-center align-middle" style="min-width: 170px;">Nominal</th>
                            <th class="text-center align-middle" style="min-width: 170px;">Terhutang</th>
                            <th class="text-center align-middle" style="min-width: 170px;">Bayar<span class="text-danger">*</span></th>
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
                                    <div><span class="fw-bold">Nomor:</span> <span class="detail-nomor-faktur">{{ $item['nomor_faktur'] ?? '-' }}</span></div>
                                    <div><span class="fw-bold">Tanggal:</span> <span class="detail-tanggal-faktur">{{ $item['tanggal_faktur'] ?? '-' }}</span></div>
                                    <div><span class="fw-bold">Pasien:</span> <span class="detail-nama-pasien">{{ $item['nama_pasien'] ?? '-' }}</span></div>
                                    <div><span class="fw-bold">No. RM:</span> <span class="detail-norm">{{ $item['nomer_rekam_medis'] ?? '-' }}</span></div>
                                    <input type="hidden" name="rincian[{{ $index }}][faktur_penjualan_id]" class="input-faktur-penjualan-id" value="{{ $item['faktur_penjualan_id'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][nomor_faktur]" value="{{ $item['nomor_faktur'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][tanggal_faktur]" value="{{ $item['tanggal_faktur'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][nama_pasien]" value="{{ $item['nama_pasien'] ?? '' }}">
                                    <input type="hidden" name="rincian[{{ $index }}][nomer_rekam_medis]" value="{{ $item['nomer_rekam_medis'] ?? '' }}">
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
                                <input type="text" class="form-control text-end" id="input_total_piutang_awal" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control text-end" id="input_total_piutang_sisa" readonly>
                            </td>
                            <td>
                                <input type="text" id="input_total_rincian_piutang" class="form-control text-end" value="{{ number_format((float) old('jumlah_pembayaran', $penerimaanPendapatan->jumlah_pembayaran ?? 0), 0, ',', '.') }}" readonly>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="select_akun_piutang" class="col-12 col-sm-2 col-form-label fw-bold">
                    Akun Piutang<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <select name="akun_piutang_id" id="select_akun_piutang" class="form-select select2-coa" required>
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_piutang_id', $penerimaanPendapatan->akun_piutang_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="select_akun_selisih_tarif" class="col-12 col-sm-2 col-form-label fw-bold">
                    Akun Selisih Tarif
                </label>
                <div class="col">
                    <select name="akun_selisih_tarif_id" id="select_akun_selisih_tarif" class="form-select select2-coa">
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('akun_selisih_tarif_id', $penerimaanPendapatan->akun_selisih_tarif_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-3">
                <label for="input_nominal_bank" class="col-12 col-sm-2 col-form-label fw-bold">
                    Nominal Bank
                </label>
                <div class="col">
                    <input type="text" id="input_nominal_bank" class="form-control text-end" value="{{ number_format($currentNominalBank, 0, ',', '.') }}" readonly>
                </div>
            </div>

            <input type="hidden" name="jumlah_pembayaran" id="hidden_jumlah_pembayaran" value="{{ number_format($currentJumlahPembayaran, 0, ',', '.') }}">

            <div class="d-flex {{ $isEdit ? 'justify-content-between' : 'justify-content-end' }} gap-3">
                @if ($isEdit)
                    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                        <i class="bi bi-trash3-fill"></i> Hapus
                    </button>
                @endif

                <div class="d-flex gap-3">
                    <a href="{{ route('pendapatan.penerimaan.index') }}" class="btn btn-light fw-bold">
                        <i class="bi bi-x-circle-fill"></i> Batal
                    </a>

                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-check-circle-fill"></i> {{ $isEdit ? 'Update' : 'Simpan' }}
                    </button>

                    <button type="submit" name="submit_action" value="save-print" class="btn btn-primary fw-bold">
                        <i class="bi bi-printer-fill"></i> {{ $isEdit ? 'Update & Print' : 'Simpan & Print' }}
                    </button>

                    @if ($isEdit)
                        <a class="btn btn-outline-primary fw-bold" target="_blank" href="{{ route('pendapatan.penerimaan.print', $penerimaanPendapatan) }}">
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
            <div><span class="fw-bold">Nomor:</span> <span class="detail-nomor-faktur">-</span></div>
            <div><span class="fw-bold">Tanggal:</span> <span class="detail-tanggal-faktur">-</span></div>
            <div><span class="fw-bold">Pasien:</span> <span class="detail-nama-pasien">-</span></div>
            <div><span class="fw-bold">No. RM:</span> <span class="detail-norm">-</span></div>
            <input type="hidden" name="rincian[__index__][faktur_penjualan_id]" class="input-faktur-penjualan-id">
            <input type="hidden" name="rincian[__index__][nomor_faktur]">
            <input type="hidden" name="rincian[__index__][tanggal_faktur]">
            <input type="hidden" name="rincian[__index__][nama_pasien]">
            <input type="hidden" name="rincian[__index__][nomer_rekam_medis]">
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
