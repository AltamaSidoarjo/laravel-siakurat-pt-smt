@extends('layouts.app')

@section('title', 'Tambah Indikator Kinerja')

@section('content')
<div class="d-flex flex-column gap-3">
    <!-- Header -->
    <div class="row">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" class="text-dark text-decoration-none">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Tambah Indikator Kinerja Baru</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Definisikan master indikator kinerja rumah sakit, konfigurasi basis kalkulasi, parameter target RAG, dan pemetaan akun COA.
            </p>
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    <form action="{{ route('pengaturan.setting-indikator-kinerja.store') }}" method="POST" id="formIndikatorKinerja">
        @csrf

        <!-- BAGIAN A: IDENTITAS DASAR INDIKATOR -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold text-primary mb-0">
                    <i class="bi bi-info-circle me-2"></i>Bagian A: Identitas Dasar Indikator
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nama" class="form-label fw-semibold">Nama Indikator <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control @error('nama') is-invalid @enderror" 
                               value="{{ old('nama') }}" placeholder="Contoh: Current Ratio, Rasio PNBP Operasional" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="kode" class="form-label fw-semibold">Kode Unik Indikator <span class="text-danger">*</span></label>
                        <input type="text" name="kode" id="kode" class="form-control font-monospace @error('kode') is-invalid @enderror" 
                               value="{{ old('kode') }}" placeholder="Contoh: cr, pnbp_ratio, alos" required>
                        <div class="form-text">Gunakan huruf kecil, angka, atau garis bawah (_) tanpa spasi.</div>
                        @error('kode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="satuan" class="form-label fw-semibold">Satuan <span class="text-danger">*</span></label>
                        <select name="satuan" id="satuan" class="form-select @error('satuan') is-invalid @enderror" required>
                            <option value="">Pilih Satuan...</option>
                            @foreach ($satuanOptions as $satuan)
                                <option value="{{ $satuan->value }}" @selected(old('satuan') === $satuan->value)>
                                    {{ $satuan->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('satuan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="tipe_kalkulasi" class="form-label fw-semibold">Tipe Kalkulasi <span class="text-danger">*</span></label>
                        <select name="tipe_kalkulasi" id="tipe_kalkulasi" class="form-select @error('tipe_kalkulasi') is-invalid @enderror" required>
                            @foreach ($tipeKalkulasiOptions as $tipe)
                                <option value="{{ $tipe->value }}" @selected(old('tipe_kalkulasi', 'RASIO') === $tipe->value)>
                                    {{ $tipe->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('tipe_kalkulasi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="urutan" class="form-label fw-semibold">Urutan Tampilan</label>
                        <input type="number" name="urutan" id="urutan" class="form-control" value="{{ old('urutan', 1) }}" min="0">
                        <div class="form-text">Prioritas urutan di dashboard.</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold d-block">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="status_aktif" value="0">
                            <input class="form-check-input" type="checkbox" name="status_aktif" id="status_aktif" value="1" 
                                   {{ old('status_aktif', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="status_aktif">Aktif</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="definisi_operasional" class="form-label fw-semibold">Definisi Operasional / Keterangan</label>
                        <textarea name="definisi_operasional" id="definisi_operasional" rows="2" class="form-control" 
                                  placeholder="Penjelasan ringkas formula indikator atau panduan interpretasi...">{{ old('definisi_operasional') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- BAGIAN B: DEKLARASI BASIS SUMBER DATA -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold text-primary mb-0">
                    <i class="bi bi-diagram-3 me-2"></i>Bagian B: Deklarasi Basis Sumber Data
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2 px-3 small mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    <strong>Formula Kalkulasi:</strong> Indikator tipe <em>Rasio / Turnover</em> menghitung nilai pembagian antara Pembilang (Numerator) dan Penyebut (Denominator). Indikator <em>Manual</em> mengambil data langsung dari entri bulanan tanpa formula akuntansi.
                </div>

                <div class="row g-3" id="basisContainer">
                    <div class="col-md-4" id="colBasisPembilang">
                        <label for="basis_pembilang" class="form-label fw-semibold">Basis Pembilang (Numerator)</label>
                        <select name="basis_pembilang" id="basis_pembilang" class="form-select">
                            @foreach ($basisPembilangOptions as $basis)
                                <option value="{{ $basis->value }}" @selected(old('basis_pembilang', 'COA') === $basis->value)>
                                    {{ $basis->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Sumber data angka di sisi pembilang formula.</div>
                    </div>

                    <div class="col-md-4" id="colBasisPenyebut">
                        <label for="basis_penyebut" class="form-label fw-semibold">Basis Penyebut (Denominator)</label>
                        <select name="basis_penyebut" id="basis_penyebut" class="form-select">
                            @foreach ($basisPenyebutOptions as $basis)
                                <option value="{{ $basis->value }}" @selected(old('basis_penyebut', 'COA') === $basis->value)>
                                    {{ $basis->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Sumber data angka di sisi penyebut formula.</div>
                    </div>

                    <div class="col-md-4" id="colVariabelManual">
                        <label for="variabel_manual_id" class="form-label fw-semibold">Variabel Manual RS</label>
                        <select name="variabel_manual_id" id="variabel_manual_id" class="form-select">
                            <option value="">-- Pilih Variabel Manual --</option>
                            @foreach ($variabelManuals as $vm)
                                <option value="{{ $vm->id }}" @selected((string)old('variabel_manual_id') === (string)$vm->id)>
                                    {{ $vm->nama }} ({{ $vm->satuan }}) - [{{ $vm->kode }}]
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Wajib dipilih jika basis penyebut atau tipe kalkulasi adalah Manual.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BAGIAN C: PARAMETER AMBANG BATAS TARGET (RAG) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold text-primary mb-0">
                    <i class="bi bi-speedometer me-2"></i>Bagian C: Parameter Ambang Batas Target (RAG)
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="pola_evaluasi" class="form-label fw-semibold">Pola Evaluasi Target <span class="text-danger">*</span></label>
                        <select name="pola_evaluasi" id="pola_evaluasi" class="form-select @error('pola_evaluasi') is-invalid @enderror" required>
                            @foreach ($polaEvaluasiOptions as $pola)
                                <option value="{{ $pola->value }}" @selected(old('pola_evaluasi', 'HIGHER_IS_BETTER') === $pola->value)>
                                    {{ $pola->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('pola_evaluasi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border text-muted small" id="polaExplanation">
                            Pola evaluasi menentukan bagaimana warna Hijau, Kuning, dan Merah dihitung pada dashboard eksekutif. Kosongkan nilai batas bila batas tersebut tidak dibatasi (unbounded).
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Ambang Hijau -->
                    <div class="col-md-4">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success-subtle text-success fw-bold py-2">
                                <i class="bi bi-check-circle-fill me-1"></i> Target Hijau (Baik / Efisien)
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-2">
                                    <label for="target_hijau_min" class="form-label small fw-semibold">Nilai Minimal</label>
                                    <input type="number" step="any" name="target_hijau_min" id="target_hijau_min" 
                                           class="form-control form-control-sm" value="{{ old('target_hijau_min') }}" placeholder="Contoh: 80">
                                </div>
                                <div>
                                    <label for="target_hijau_max" class="form-label small fw-semibold">Nilai Maksimal</label>
                                    <input type="number" step="any" name="target_hijau_max" id="target_hijau_max" 
                                           class="form-control form-control-sm" value="{{ old('target_hijau_max') }}" placeholder="Kosongkan jika tak berbatas">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ambang Kuning -->
                    <div class="col-md-4">
                        <div class="card border-warning h-100">
                            <div class="card-header bg-warning-subtle text-dark fw-bold py-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Target Kuning (Waspada)
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-2">
                                    <label for="target_kuning_min" class="form-label small fw-semibold">Nilai Minimal</label>
                                    <input type="number" step="any" name="target_kuning_min" id="target_kuning_min" 
                                           class="form-control form-control-sm" value="{{ old('target_kuning_min') }}" placeholder="Contoh: 60">
                                </div>
                                <div>
                                    <label for="target_kuning_max" class="form-label small fw-semibold">Nilai Maksimal</label>
                                    <input type="number" step="any" name="target_kuning_max" id="target_kuning_max" 
                                           class="form-control form-control-sm" value="{{ old('target_kuning_max') }}" placeholder="Contoh: 79.99">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ambang Merah -->
                    <div class="col-md-4">
                        <div class="card border-danger h-100">
                            <div class="card-header bg-danger-subtle text-danger fw-bold py-2">
                                <i class="bi bi-x-circle-fill me-1"></i> Target Merah (Kritis)
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-2">
                                    <label for="target_merah_min" class="form-label small fw-semibold">Nilai Minimal</label>
                                    <input type="number" step="any" name="target_merah_min" id="target_merah_min" 
                                           class="form-control form-control-sm" value="{{ old('target_merah_min') }}" placeholder="Kosongkan jika tak berbatas">
                                </div>
                                <div>
                                    <label for="target_merah_max" class="form-label small fw-semibold">Nilai Maksimal</label>
                                    <input type="number" step="any" name="target_merah_max" id="target_merah_max" 
                                           class="form-control form-control-sm" value="{{ old('target_merah_max') }}" placeholder="Contoh: 59.99">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BAGIAN D: TABEL DINAMIS PEMETAAN AKUN COA -->
        <div class="card border-0 shadow-sm mb-4" id="sectionMappingCoa">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title fw-bold text-primary mb-0">
                        <i class="bi bi-table me-2"></i>Bagian D: Pemetaan Akun COA
                    </h5>
                    <p class="text-muted small mb-0 mt-1">
                        Petakan daftar akun COA untuk dikelompokkan ke dalam pembilang (numerator), penyebut (denominator), penambah (addition), atau pengurang (deduction).
                    </p>
                </div>
                <button type="button" id="btnAddRow" class="btn btn-sm btn-primary fw-bold">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Akun COA
                </button>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="tableMappingCoa">
                        <thead class="table-light text-center small text-nowrap">
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="min-width: 300px;">Akun COA <span class="text-danger">*</span></th>
                                <th style="width: 220px;">Peran dalam Formula <span class="text-danger">*</span></th>
                                <th style="width: 140px;">Pengecualian (Exclude)</th>
                                <th style="width: 70px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableMappingBody">
                            @php
                                $oldMappings = old('mappings', []);
                            @endphp
                            @forelse ($oldMappings as $index => $mapping)
                                <tr class="mapping-row">
                                    <td class="text-center fw-bold row-number">{{ $loop->iteration }}</td>
                                    <td>
                                        <select name="mappings[{{ $index }}][coa_id]" class="form-select select2-coa" required>
                                            <option value="">Pilih Akun COA...</option>
                                            @foreach ($coas as $coa)
                                                <option value="{{ $coa->id }}" @selected((string)($mapping['coa_id'] ?? '') === (string)$coa->id)>
                                                    {{ $coa->kode }} - {{ $coa->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="mappings[{{ $index }}][peran]" class="form-select select-peran" required>
                                            @foreach ($peranCoaOptions as $peran)
                                                <option value="{{ $peran->value }}" @selected(($mapping['peran'] ?? '') === $peran->value)>
                                                    {{ $peran->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input type="hidden" name="mappings[{{ $index }}][is_excluded]" value="0">
                                            <input class="form-check-input" type="checkbox" name="mappings[{{ $index }}][is_excluded]" value="1" 
                                                   {{ !empty($mapping['is_excluded']) ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" title="Hapus Baris">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <!-- Empty state or initial empty row -->
                                <tr id="emptyMappingPlaceholder">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-journal-x fs-2 d-block mb-1"></i>
                                        Belum ada akun COA yang dipetakan. Klik tombol <strong>Tambah Akun COA</strong> di atas untuk memetakan akun.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Simpan -->
        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" class="btn btn-outline-secondary px-4 fw-semibold">
                Batal
            </a>
            <button type="submit" class="btn btn-success px-4 fw-bold">
                <i class="bi bi-save me-1"></i> Simpan Indikator Kinerja
            </button>
        </div>
    </form>
</div>

<!-- Template baris COA untuk penambahan dinamis -->
<template id="rowMappingTemplate">
    <tr class="mapping-row">
        <td class="text-center fw-bold row-number">__NO__</td>
        <td>
            <select name="mappings[__INDEX__][coa_id]" class="form-select select2-coa" required>
                <option value="">Pilih Akun COA...</option>
                @foreach ($coas as $coa)
                    <option value="{{ $coa->id }}">
                        {{ $coa->kode }} - {{ $coa->nama }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="mappings[__INDEX__][peran]" class="form-select select-peran" required>
                @foreach ($peranCoaOptions as $peran)
                    <option value="{{ $peran->value }}">
                        {{ $peran->label() }}
                    </option>
                @endforeach
            </select>
        </td>
        <td class="text-center">
            <div class="form-check form-switch d-inline-block">
                <input type="hidden" name="mappings[__INDEX__][is_excluded]" value="0">
                <input class="form-check-input" type="checkbox" name="mappings[__INDEX__][is_excluded]" value="1">
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" title="Hapus Baris">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('tableMappingBody');
        const btnAddRow = document.getElementById('btnAddRow');
        const template = document.getElementById('rowMappingTemplate');
        const emptyPlaceholder = document.getElementById('emptyMappingPlaceholder');
        const tipeKalkulasiSelect = document.getElementById('tipe_kalkulasi');
        const basisPembilangSelect = document.getElementById('basis_pembilang');
        const basisPenyebutSelect = document.getElementById('basis_penyebut');
        const variabelManualCol = document.getElementById('colVariabelManual');

        function initSelect2(scope) {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(scope).find('.select2-coa').each(function () {
                    const $select = window.jQuery(this);
                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Pilih Akun COA...',
                        allowClear: true
                    });
                });
            }
        }

        function reindexRows() {
            const rows = tableBody.querySelectorAll('tr.mapping-row');
            if (rows.length === 0) {
                if (emptyPlaceholder) {
                    emptyPlaceholder.style.display = '';
                }
                return;
            }

            if (emptyPlaceholder) {
                emptyPlaceholder.style.display = 'none';
            }

            rows.forEach((row, index) => {
                const numberCell = row.querySelector('.row-number');
                if (numberCell) {
                    numberCell.textContent = index + 1;
                }

                row.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(/mappings\[\d+\]|mappings\[__INDEX__\]/, `mappings[${index}]`);
                });
            });
        }

        if (btnAddRow && template && tableBody) {
            btnAddRow.addEventListener('click', function () {
                if (emptyPlaceholder) {
                    emptyPlaceholder.style.display = 'none';
                }

                const clone = template.content.cloneNode(true);
                const nextIndex = tableBody.querySelectorAll('tr.mapping-row').length;

                clone.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace('__INDEX__', nextIndex);
                });

                tableBody.appendChild(clone);
                const addedRow = tableBody.lastElementChild;
                initSelect2(addedRow);
                reindexRows();
            });

            tableBody.addEventListener('click', function (e) {
                const btnRemove = e.target.closest('.btn-remove-row');
                if (!btnRemove) return;

                const row = btnRemove.closest('tr.mapping-row');
                if (row) {
                    row.remove();
                    reindexRows();
                }
            });
        }

        // Interactivity for Tipe Kalkulasi & Basis Sumber Data
        function updateBasisInteractivity() {
            const tipe = tipeKalkulasiSelect ? tipeKalkulasiSelect.value : '';
            const penyebut = basisPenyebutSelect ? basisPenyebutSelect.value : '';

            if (variabelManualCol) {
                variabelManualCol.style.display = (tipe === 'MANUAL' || penyebut === 'MANUAL') ? '' : 'none';
            }
        }

        if (tipeKalkulasiSelect) {
            tipeKalkulasiSelect.addEventListener('change', updateBasisInteractivity);
        }
        if (basisPenyebutSelect) {
            basisPenyebutSelect.addEventListener('change', updateBasisInteractivity);
        }

        initSelect2(document);
        reindexRows();
        updateBasisInteractivity();
    });
</script>
@endpush
