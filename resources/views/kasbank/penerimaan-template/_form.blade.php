@php
    $isEdit = isset($template);
    $rincian = old('rincian', $isEdit ? $template->rincian->map(fn ($item) => [
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
            Informasi Template Penerimaan
        </div>
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <label for="input_nama" class="col-12 col-sm-2 col-form-label fw-bold">
                    Nama Template<span class="text-danger">*</span>
                </label>
                <div class="col">
                    <input type="text" name="nama" id="input_nama" class="form-control" value="{{ old('nama', $template->nama ?? '') }}" placeholder="Contoh: Penerimaan Pendapatan Kantin & Parkir" required>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="select_coa" class="col-12 col-sm-2 col-form-label fw-bold">
                    Default Bank/Kas Tujuan
                </label>
                <div class="col">
                    <select name="coa_id" id="select_coa" class="form-select select2-coa">
                        <option value="">-- Pilih Akun Bank/Kas (Opsional) --</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}" @selected((string) old('coa_id', $template->coa_id ?? '') === (string) $coa->id)>
                                {{ $coa->kode }} - {{ $coa->nama }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text text-muted">Jika dipilih, akun ini akan otomatis terpilih pada field "Diterima ke" saat template diterapkan.</div>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <label for="input_keterangan" class="col-12 col-sm-2 col-form-label fw-bold">
                    Keterangan Default
                </label>
                <div class="col">
                    <textarea name="keterangan" id="input_keterangan" class="form-control" rows="2" placeholder="Keterangan transaksi default yang otomatis terisi saat template diterapkan (opsional)">{{ old('keterangan', $template->keterangan ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success d-flex justify-content-between align-items-center">
            <span>Rincian Akun Sumber Penerimaan</span>
            <span class="badge bg-light text-dark fw-normal border">
                <i class="bi bi-info-circle text-primary me-1"></i> Nominal boleh 0 (hanya struktur akun) atau diisi nominal default
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="table_data_detail" class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th class="text-center align-middle" style="min-width: 250px;">Akun Sumber Penerimaan<span class="text-danger">*</span></th>
                            <th class="text-center align-middle" style="min-width: 200px;">Nominal Default</th>
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
                                    <input type="text" name="rincian[{{ $index }}][catatan]" value="{{ $item['catatan'] ?? '' }}" class="form-control input-catatan" placeholder="Catatan baris (opsional)">
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
                            <th class="text-end align-middle">Total</th>
                            <td>
                                <input type="text" id="input_total" class="form-control text-end" value="0" readonly>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-2">
                <button type="button" id="btn_add_detail" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle-fill me-1"></i> Add Detail
                </button>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="d-flex {{ $isEdit ? 'justify-content-between' : 'justify-content-end' }} gap-3">
                @if ($isEdit)
                    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                        <i class="bi bi-trash3-fill me-1"></i> Hapus
                    </button>
                @endif

                <div class="d-flex gap-3">
                    <a href="{{ route('kasbank.penerimaan-template.index') }}" class="btn btn-light fw-bold">
                        <i class="bi bi-x-circle-fill me-1"></i> Batal
                    </a>

                    <button type="submit" class="btn btn-success fw-bold btn_submit">
                        <i class="bi bi-check-circle-fill me-1"></i> {{ $isEdit ? 'Update Template' : 'Simpan Template' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
