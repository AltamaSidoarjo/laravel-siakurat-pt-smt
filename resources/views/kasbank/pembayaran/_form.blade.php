@php
    $isEdit = isset($kasbankPembayaran);
    $rincian = old('rincian', $isEdit ? $kasbankPembayaran->rincian->map(fn ($item) => [
        'coa_id' => $item->coa_id,
        'nominal' => (int) $item->nominal,
        'catatan' => $item->catatan,
    ])->toArray() : [[
        'coa_id' => '',
        'nominal' => 0,
        'catatan' => '',
    ]]);
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
                    <input type="text" name="nomer" id="input_nomor" class="form-control" value="{{ old('nomer', $kasbankPembayaran->nomer ?? '') }}" required>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="select_coa" class="col-12 col-sm-2 col-form-label fw-bold">
                    Dibayar dari<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <select name="coa_id" id="select_coa" class="form-select select2-coa" required>
                        <option value="">Pilih Akun</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('coa_id', $kasbankPembayaran->coa_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_tanggal" class="col-12 col-sm-2 col-form-label fw-bold">
                    Tanggal<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <input type="date" name="tanggal" id="input_tanggal" class="form-control" value="{{ old('tanggal', isset($kasbankPembayaran) ? optional($kasbankPembayaran->tanggal)->format('Y-m-d') : '') }}" required>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_keterangan" class="col-12 col-sm-2 col-form-label fw-bold">
                    Keterangan
                </label>
                <div class="col">
                    <textarea name="keterangan" id="input_keterangan" class="form-control" rows="3">{{ old('keterangan', $kasbankPembayaran->keterangan ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success">
            Detail
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="table_data_detail" class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th class="text-center align-middle" style="min-width: 250px;">Akun<span class="text-danger">*</span></th>
                            <th class="text-center align-middle" style="min-width: 200px;">Nominal<span class="text-danger">*</span></th>
                            <th class="text-center align-middle" style="min-width: 200px;">Catatan</th>
                            <th class="text-center align-middle" style="width: 50px;">#</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $index => $item)
                            <tr>
                                <td class="align-middle">
                                    <select name="rincian[{{ $index }}][coa_id]" class="form-select select2-coa" required>
                                        <option value="">Pilih Akun</option>
                                        @foreach ($coaOptions as $coa)
                                            <option value="{{ $coa->id }}" @selected((string) ($item['coa_id'] ?? '') === (string) $coa->id)>
                                                {{ $coa->kode }} - {{ $coa->nama }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <input type="text" name="rincian[{{ $index }}][nominal]" value="{{ number_format((float) ($item['nominal'] ?? 0), 0, ',', '.') }}" class="form-control text-end input-nominal" required>
                                </td>
                                <td class="align-middle">
                                    <input type="text" name="rincian[{{ $index }}][catatan]" value="{{ $item['catatan'] ?? '' }}" class="form-control input-catatan">
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-light text-danger btn-delete-detail">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-end align-middle" colspan="2">Total</th>
                            <td>
                                <input type="text" name="total" id="input_total" class="form-control text-end" value="{{ number_format((float) old('total', $kasbankPembayaran->total ?? 0), 0, ',', '.') }}" readonly>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-2">
                <button type="button" id="btn_add_detail" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle-fill"></i> Add Detail
                </button>
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
                    <a href="{{ route('kasbank.pembayaran.index') }}" class="btn btn-light fw-bold">
                        <i class="bi bi-x-circle-fill"></i> Batal
                    </a>

                    <button type="submit" class="btn btn-success fw-bold btn_submit">
                        <i class="bi bi-check-circle-fill"></i> {{ $isEdit ? 'Update' : 'Simpan' }}
                    </button>

                    @if (! $isEdit)
                        <button type="submit" name="action" value="save_print" class="btn btn-primary fw-bold btn_submit">
                            <i class="bi bi-printer-fill"></i> Simpan & Print
                        </button>
                    @else
                        <a class="btn btn-primary fw-bold" target="_blank" href="{{ route('kasbank.pembayaran.print', $kasbankPembayaran) }}">
                            <i class="bi bi-printer-fill"></i> Print
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
