@extends('layouts.app')

@section('title', 'Edit Indikator Kinerja')

@push('styles')
<style>
    .kpi-preview-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.05);
        transition: all 0.2s ease;
    }
    .rag-zone-card {
        border-radius: 0.5rem;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .rag-zone-green {
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
    }
    .rag-zone-yellow {
        background-color: #fefce8;
        border: 1px solid #fef08a;
    }
    .rag-zone-red {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
    }
    .status-dot-sm {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .table-mapping thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        border-bottom: 1px solid #e2e8f0;
    }
    .sticky-panel {
        position: sticky;
        top: 1.25rem;
        z-index: 10;
    }
    @media (max-width: 991.98px) {
        .sticky-panel {
            position: static;
        }
    }
</style>
@endpush

@php
    $currentSatuan = $indikator->satuan instanceof \App\Enums\IndikatorKinerja\SatuanIndikator 
        ? $indikator->satuan->value 
        : (string)$indikator->satuan;
    $currentTipe = $indikator->tipe_kalkulasi instanceof \App\Enums\IndikatorKinerja\TipeKalkulasi 
        ? $indikator->tipe_kalkulasi->value 
        : (string)$indikator->tipe_kalkulasi;
    $currentBasisPembilang = $indikator->basis_pembilang instanceof \App\Enums\IndikatorKinerja\BasisPembilang 
        ? $indikator->basis_pembilang->value 
        : (string)($indikator->basis_pembilang ?? 'COA');
    $currentBasisPenyebut = $indikator->basis_penyebut instanceof \App\Enums\IndikatorKinerja\BasisPenyebut 
        ? $indikator->basis_penyebut->value 
        : (string)($indikator->basis_penyebut ?? 'COA');
    $currentPola = $indikator->pola_evaluasi instanceof \App\Enums\IndikatorKinerja\PolaEvaluasi 
        ? $indikator->pola_evaluasi->value 
        : (string)$indikator->pola_evaluasi;
@endphp

@section('content')
<div class="d-flex flex-column gap-3 mb-5">
    <!-- Header Halaman -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" 
                       class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs" 
                       style="width: 40px; height: 40px;" 
                       title="Kembali ke Daftar Indikator">
                        <i class="bi bi-arrow-left fs-5"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h4 class="mb-0 fw-bold text-dark">Edit Indikator Kinerja: {{ $indikator->nama }}</h4>
                            <span class="badge bg-light text-dark border font-monospace small">#{{ $indikator->kode }}</span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Perbarui konfigurasi master indikator, formula kalkulasi pembagian, ambang batas target RAG, atau pemetaan akun COA.
                        </p>
                    </div>
                </div>

                <div class="d-none d-md-flex align-items-center gap-2">
                    <button type="button" id="btnOpenModalPanduan" class="btn btn-outline-success px-3 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalPanduanSetting" title="Buka Panduan &amp; Glosarium Indikator Kinerja">
                        <i class="bi bi-book-half"></i>
                        <span>Panduan &amp; Glosarium</span>
                    </button>
                    <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" class="btn btn-outline-secondary px-3 fw-semibold">
                        Batal
                    </a>
                    <button type="button" class="btn btn-success px-4 fw-bold shadow-xs btn-trigger-submit">
                        <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    <form action="{{ route('pengaturan.setting-indikator-kinerja.update', $indikator->id) }}" method="POST" id="formIndikatorKinerja">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <!-- Kolom Utama Form (Kiri / Col-lg-8) -->
            <div class="col-lg-8 col-xl-8 d-flex flex-column gap-4">

                {{-- Sticky Stepper Navigation --}}
                @include('pengaturan.setting-indikator-kinerja.partials.stepper')

                <!-- 1. IDENTITAS DASAR & FORMULA -->
                <div class="card border-0 shadow-sm bg-white" id="sectionIdentitas">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-2">
                                <i class="bi bi-sliders fs-6"></i>
                            </span>
                            <h5 class="card-title fw-bold text-dark mb-0">Identitas Dasar Indikator</h5>
                        </div>
                        <span class="badge bg-light text-secondary border small">Master Indikator</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- Nama Indikator -->
                            <div class="col-md-7">
                                <label for="nama" class="form-label fw-semibold text-dark">
                                    Nama Indikator <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama" id="nama" 
                                       class="form-control @error('nama') is-invalid @enderror" 
                                       value="{{ old('nama', $indikator->nama) }}" 
                                       placeholder="Contoh: Current Ratio, Bed Occupancy Rate, Rasio PNBP" 
                                       required>
                                @error('nama')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Kode Unik Indikator -->
                            <div class="col-md-5">
                                <label for="kode" class="form-label fw-semibold text-dark">
                                    Kode Unik Indikator <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="kode" id="kode" 
                                       class="form-control font-monospace @error('kode') is-invalid @enderror" 
                                       value="{{ old('kode', $indikator->kode) }}" 
                                       placeholder="Contoh: cr, pnbp_ratio, bor" 
                                       required>
                                <div class="form-text small">Huruf kecil, angka, atau underscore (_) tanpa spasi.</div>
                                @error('kode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Satuan Indikator -->
                            <div class="col-md-6">
                                <label for="satuan" class="form-label fw-semibold text-dark">
                                    Satuan Nilai <span class="text-danger">*</span>
                                </label>
                                <select name="satuan" id="satuan" class="form-select @error('satuan') is-invalid @enderror" required>
                                    <option value="">Pilih Satuan Nilai...</option>
                                    @foreach ($satuanOptions as $satuan)
                                        <option value="{{ $satuan->value }}" @selected(old('satuan', $currentSatuan) === $satuan->value)>
                                            {{ $satuan->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('satuan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Tipe Kalkulasi -->
                            <div class="col-md-6">
                                <label for="tipe_kalkulasi" class="form-label fw-semibold text-dark">
                                    Tipe Kalkulasi <span class="text-danger">*</span>
                                </label>
                                <select name="tipe_kalkulasi" id="tipe_kalkulasi" class="form-select @error('tipe_kalkulasi') is-invalid @enderror" required>
                                    @foreach ($tipeKalkulasiOptions as $tipe)
                                        <option value="{{ $tipe->value }}" @selected(old('tipe_kalkulasi', $currentTipe) === $tipe->value)>
                                            {{ $tipe->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tipe_kalkulasi')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Sub-kontainer Basis Sumber Data / Formula -->
                            <div class="col-12 mt-3">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                        <span class="fw-bold small text-dark d-flex align-items-center gap-1.5">
                                            <i class="bi bi-diagram-3-fill text-primary"></i> Sumber Data & Formula Kalkulasi
                                        </span>
                                        <span id="formulaVisualBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace small">
                                            Basis: Buku Besar ÷ Buku Besar
                                        </span>
                                    </div>

                                    <div class="row g-3" id="basisContainer">
                                        <div class="col-md-6" id="colBasisPembilang">
                                            <label for="basis_pembilang" class="form-label small fw-semibold text-secondary">
                                                Basis Pembilang (Numerator)
                                            </label>
                                            <select name="basis_pembilang" id="basis_pembilang" class="form-select form-select-sm">
                                                @foreach ($basisPembilangOptions as $basis)
                                                    <option value="{{ $basis->value }}" @selected(old('basis_pembilang', $currentBasisPembilang) === $basis->value)>
                                                        {{ $basis->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">Sumber data angka di sisi pembilang formula.</div>
                                        </div>

                                        <div class="col-md-6" id="colBasisPenyebut">
                                            <label for="basis_penyebut" class="form-label small fw-semibold text-secondary">
                                                Basis Penyebut (Denominator)
                                            </label>
                                            <select name="basis_penyebut" id="basis_penyebut" class="form-select form-select-sm">
                                                @foreach ($basisPenyebutOptions as $basis)
                                                    <option value="{{ $basis->value }}" @selected(old('basis_penyebut', $currentBasisPenyebut) === $basis->value)>
                                                        {{ $basis->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">Sumber data angka di sisi penyebut formula.</div>
                                        </div>

                                        <div class="col-12" id="colVariabelManual" style="display: none;">
                                            <label for="variabel_manual_id" class="form-label small fw-semibold text-secondary">
                                                Variabel Manual Rumah Sakit
                                            </label>
                                            <select name="variabel_manual_id" id="variabel_manual_id" class="form-select form-select-sm">
                                                <option value="">-- Pilih Variabel Manual --</option>
                                                @foreach ($variabelManuals as $vm)
                                                    <option value="{{ $vm->id }}" data-nama="{{ $vm->nama }}" @selected((string)old('variabel_manual_id', $indikator->variabel_manual_id) === (string)$vm->id)>
                                                        {{ $vm->nama }} ({{ $vm->satuan }}) - [{{ $vm->kode }}]
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">Digunakan bila basis penyebut atau tipe kalkulasi adalah entri data manual RS.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Definisi Operasional -->
                            <div class="col-12">
                                <label for="definisi_operasional" class="form-label fw-semibold text-dark">
                                    Definisi Operasional / Keterangan Formula
                                </label>
                                <textarea name="definisi_operasional" id="definisi_operasional" rows="2" 
                                          class="form-control" 
                                          placeholder="Uraikan ringkas formula kalkulasi, acuan standar kementerian, atau panduan interpretasi...">{{ old('definisi_operasional', $indikator->definisi_operasional) }}</textarea>
                                <div class="form-text small">Teks ini akan muncul sebagai tooltip bantuan interpretasi pada dashboard eksekutif.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. PARAMETER AMBANG BATAS TARGET (RAG) -->
                <div class="card border-0 shadow-sm bg-white" id="sectionTargetRag">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success-subtle text-success p-2 rounded-2">
                                <i class="bi bi-speedometer2 fs-6"></i>
                            </span>
                            <h5 class="card-title fw-bold text-dark mb-0">Ambang Batas Target (RAG)</h5>
                        </div>
                        <span class="badge bg-light text-secondary border small">Evaluasi Capaian</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="pola_evaluasi" class="form-label fw-semibold text-dark">
                                    Pola Evaluasi Target <span class="text-danger">*</span>
                                </label>
                                <select name="pola_evaluasi" id="pola_evaluasi" class="form-select @error('pola_evaluasi') is-invalid @enderror" required>
                                    @foreach ($polaEvaluasiOptions as $pola)
                                        <option value="{{ $pola->value }}" @selected(old('pola_evaluasi', $currentPola) === $pola->value)>
                                            {{ $pola->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('pola_evaluasi')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-center">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-info-circle-fill text-primary small"></i>
                                        <span class="fw-semibold small text-dark" id="polaGuideTitle">Logika Pola Evaluasi</span>
                                    </div>
                                    <p class="text-muted small mb-0" id="polaExplanation">
                                        Makin tinggi capaian semakin baik. Nilai di atas batas hijau dinilai efisien dan target terpenuhi.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Matriks Ambang Batas 3 Kolom RAG (Refined Grid - No Nested Cards) -->
                        <div class="row g-3">
                            <!-- Ambang Hijau -->
                            <div class="col-md-4">
                                <div class="rag-zone-card rag-zone-green p-3 h-100 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom border-success-subtle">
                                        <span class="badge bg-success text-white px-2 py-1 small d-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i> Target Hijau
                                        </span>
                                        <span class="small fw-semibold text-success">Baik / Efisien</span>
                                    </div>
                                    <div class="d-flex flex-column gap-2 mt-auto">
                                        <div>
                                            <label for="target_hijau_min" class="form-label small fw-semibold text-secondary mb-1">Nilai Minimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_hijau_min" id="target_hijau_min" 
                                                       class="form-control" 
                                                       value="{{ old('target_hijau_min', $indikator->target_hijau_min !== null ? (float)$indikator->target_hijau_min : '') }}" 
                                                       placeholder="Contoh: 80">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="target_hijau_max" class="form-label small fw-semibold text-secondary mb-1">Nilai Maksimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_hijau_max" id="target_hijau_max" 
                                                       class="form-control" 
                                                       value="{{ old('target_hijau_max', $indikator->target_hijau_max !== null ? (float)$indikator->target_hijau_max : '') }}" 
                                                       placeholder="Tak berbatas">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ambang Kuning -->
                            <div class="col-md-4">
                                <div class="rag-zone-card rag-zone-yellow p-3 h-100 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom border-warning-subtle">
                                        <span class="badge bg-warning text-dark px-2 py-1 small d-flex align-items-center gap-1">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Target Kuning
                                        </span>
                                        <span class="small fw-semibold text-warning-emphasis">Waspada</span>
                                    </div>
                                    <div class="d-flex flex-column gap-2 mt-auto">
                                        <div>
                                            <label for="target_kuning_min" class="form-label small fw-semibold text-secondary mb-1">Nilai Minimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_kuning_min" id="target_kuning_min" 
                                                       class="form-control" 
                                                       value="{{ old('target_kuning_min', $indikator->target_kuning_min !== null ? (float)$indikator->target_kuning_min : '') }}" 
                                                       placeholder="Contoh: 60">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="target_kuning_max" class="form-label small fw-semibold text-secondary mb-1">Nilai Maksimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_kuning_max" id="target_kuning_max" 
                                                       class="form-control" 
                                                       value="{{ old('target_kuning_max', $indikator->target_kuning_max !== null ? (float)$indikator->target_kuning_max : '') }}" 
                                                       placeholder="Contoh: 79.99">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ambang Merah -->
                            <div class="col-md-4">
                                <div class="rag-zone-card rag-zone-red p-3 h-100 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom border-danger-subtle">
                                        <span class="badge bg-danger text-white px-2 py-1 small d-flex align-items-center gap-1">
                                            <i class="bi bi-x-circle-fill"></i> Target Merah
                                        </span>
                                        <span class="small fw-semibold text-danger">Kritis</span>
                                    </div>
                                    <div class="d-flex flex-column gap-2 mt-auto">
                                        <div>
                                            <label for="target_merah_min" class="form-label small fw-semibold text-secondary mb-1">Nilai Minimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_merah_min" id="target_merah_min" 
                                                       class="form-control" 
                                                       value="{{ old('target_merah_min', $indikator->target_merah_min !== null ? (float)$indikator->target_merah_min : '') }}" 
                                                       placeholder="Tak berbatas">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="target_merah_max" class="form-label small fw-semibold text-secondary mb-1">Nilai Maksimal</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" name="target_merah_max" id="target_merah_max" 
                                                       class="form-control" 
                                                       value="{{ old('target_merah_max', $indikator->target_merah_max !== null ? (float)$indikator->target_merah_max : '') }}" 
                                                       placeholder="Contoh: 59.99">
                                                <span class="input-group-text bg-white dynamic-unit-suffix">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-3 pt-2 text-muted small">
                            <i class="bi bi-lightbulb text-warning"></i>
                            <span>Kosongkan nilai batas jika batas tersebut terbuka (unbounded). Nilai batas dievaluasi secara otomatis pada dashboard.</span>
                        </div>
                    </div>
                </div>

                <!-- 3. PEMETAAN AKUN COA -->
                <div class="card border-0 shadow-sm bg-white" id="sectionMappingCoa">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info-subtle text-info-emphasis p-2 rounded-2">
                                <i class="bi bi-table fs-6"></i>
                            </span>
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0">Pemetaan Akun COA</h5>
                                <div class="text-muted small mt-0.5">Petakan akun pembukuan ke dalam numerator, denominator, penambah, atau pengurang.</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-secondary border" id="mappedCountBadge">0 Akun Terpetakan</span>
                            <button type="button" id="btnAddRow" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Akun COA
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div id="alertManualInfo" class="alert alert-info py-2.5 px-3 small mb-3" style="display: none;">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Mode Manual Aktif:</strong> Indikator bertipe kalkulasi <em>Manual</em> tidak memerlukan pemetaan COA karena nilai diinput secara berkala dari entri bulanan rumah sakit.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 table-mapping" id="tableMappingCoa">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 45px;">No</th>
                                        <th style="min-width: 280px;">Akun COA <span class="text-danger">*</span></th>
                                        <th style="width: 200px;">Peran dalam Formula <span class="text-danger">*</span></th>
                                        <th class="text-center" style="width: 120px;" title="Kecualikan saldo akun ini beserta anak-anak akunnya">Pengecualian</th>
                                        <th class="text-center" style="width: 60px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tableMappingBody">
                                    @php
                                        $defaultMappings = $indikator->mappingCoa->map(function ($m) {
                                            return [
                                                'coa_id' => $m->coa_id,
                                                'peran' => $m->peran instanceof \App\Enums\IndikatorKinerja\PeranCoa ? $m->peran->value : (string)$m->peran,
                                                'is_excluded' => $m->is_excluded ? 1 : 0,
                                            ];
                                        })->toArray();
                                        $mappings = old('mappings', $defaultMappings);
                                    @endphp
                                    @forelse ($mappings as $index => $mapping)
                                        <tr class="mapping-row">
                                            <td class="text-center fw-bold text-secondary row-number">{{ $loop->iteration }}</td>
                                            <td>
                                                <select name="mappings[{{ $index }}][coa_id]" class="form-select select2-coa" required>
                                                    <option value="">Pilih Akun COA...</option>
                                                    @foreach ($coas as $coa)
                                                        <option value="{{ $coa->id }}" @selected((string)($mapping['coa_id'] ?? '') === (string)$coa->id)>
                                                            {{ $coa->kode }} - {{ $coa->nama }}{{ isset($coa->status_aktif) && (int)$coa->status_aktif === 0 ? ' (Nonaktif)' : '' }}
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
                                                           {{ !empty($mapping['is_excluded']) ? 'checked' : '' }} title="Centang jika akun ini dikecualikan (exclude)">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" title="Hapus Akun">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="emptyMappingPlaceholder">
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-journal-x fs-2 d-block mb-1 text-secondary"></i>
                                                <span>Belum ada akun COA yang dipetakan.</span>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnEmptyAdd">
                                                        <i class="bi bi-plus-circle me-1"></i> Tambah Baris Akun Sekarang
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Kolom Samping (Kanan / Col-lg-4) : Sticky Sidebar -->
            <div class="col-lg-4 col-xl-4">
                <div class="sticky-panel d-flex flex-column gap-3">

                    <!-- Kartu Live Preview Indikator -->
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between">
                            <span class="text-uppercase fw-bold text-muted small" style="letter-spacing: 0.05em; font-size: 0.75rem;">
                                <i class="bi bi-eye me-1"></i> Live Preview Kartu KPI
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 small" id="previewStatusBadge">
                                Aktif
                            </span>
                        </div>
                        <div class="card-body p-3.5">
                            <div class="kpi-preview-card p-3 mb-3">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <h6 class="fw-bold text-dark mb-0 text-truncate" id="previewTitle" style="max-width: 200px;">
                                        {{ $indikator->nama }}
                                    </h6>
                                    <span class="badge bg-light text-secondary border font-monospace small" id="previewKode">
                                        {{ $indikator->kode }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" id="previewSatuanBadge">
                                        Satuan: {{ $indikator->satuan?->label() ?? '-' }}
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" id="previewTipeBadge">
                                        {{ $indikator->tipe_kalkulasi?->label() ?? 'Rasio' }}
                                    </span>
                                </div>
                                <!-- Formula Visualisasi Preview -->
                                <div class="p-2 mb-2 bg-white rounded border border-light-subtle small shadow-xs" id="previewFormulaWrapper">
                                    <div class="text-muted small mb-1 fw-semibold d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
                                        <span><i class="bi bi-calculator me-1 text-primary"></i>Formula Kalkulasi:</span>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap gap-1 font-monospace" id="previewFormulaContent" style="font-size: 0.75rem;">
                                        <!-- Dinamis di-render via JS -->
                                    </div>
                                </div>
                                <div class="p-2 bg-light rounded border small">
                                    <div class="text-muted small mb-1">Ambang Target Evaluasi:</div>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="d-flex align-items-center gap-1 small text-success">
                                                <span class="status-dot-sm bg-success"></span> Hijau (Baik):
                                            </span>
                                            <span class="fw-bold small text-dark" id="previewHijauText">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="d-flex align-items-center gap-1 small text-warning-emphasis">
                                                <span class="status-dot-sm bg-warning"></span> Kuning (Waspada):
                                            </span>
                                            <span class="fw-bold small text-dark" id="previewKuningText">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="d-flex align-items-center gap-1 small text-danger">
                                                <span class="status-dot-sm bg-danger"></span> Merah (Kritis):
                                            </span>
                                            <span class="fw-bold small text-dark" id="previewMerahText">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle me-1"></i> Perubahan konfigurasi target dan mapping langsung berdampak pada evaluasi dashboard eksekutif.
                            </p>
                        </div>
                    </div>

                    <!-- Kartu Pengaturan Publikasi & Aksi -->
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-header bg-white py-3 px-3.5 border-bottom">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-gear me-1 text-secondary"></i> Status & Publikasi
                            </h6>
                        </div>
                        <div class="card-body p-3.5 d-flex flex-column gap-3">
                            <!-- Status Aktif Switch -->
                            <div>
                                <label class="form-label fw-semibold text-dark mb-1">Status Keaktifan</label>
                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between bg-light rounded p-2.5 border">
                                    <label class="form-check-label fw-semibold small text-dark mb-0 ms-1 cursor-pointer" for="status_aktif">
                                        Aktif di Dashboard
                                    </label>
                                    <input type="hidden" name="status_aktif" value="0">
                                    <input class="form-check-input m-0 cursor-pointer" type="checkbox" name="status_aktif" id="status_aktif" value="1" 
                                           {{ old('status_aktif', $indikator->status_aktif ? '1' : '0') == '1' ? 'checked' : '' }}>
                                </div>
                                <div class="form-text small mt-1">Bila nonaktif, indikator tidak akan dihitung di dashboard publik.</div>
                            </div>

                            <!-- Urutan Tampilan -->
                            <div>
                                <label for="urutan" class="form-label fw-semibold text-dark mb-1">Urutan Prioritas</label>
                                <input type="number" name="urutan" id="urutan" class="form-control" value="{{ old('urutan', $indikator->urutan) }}" min="1">
                                <div class="form-text small">Urutan posisi kartu saat ditampilkan pada dashboard eksekutif.</div>
                            </div>

                            <!-- Metadata Info -->
                            <div class="p-2.5 bg-light rounded border text-muted small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>ID Indikator:</span>
                                    <span class="font-monospace fw-semibold text-dark">#{{ $indikator->id }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Dibuat:</span>
                                    <span class="text-dark">{{ $indikator->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Diperbarui:</span>
                                    <span class="text-dark">{{ $indikator->updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
                                </div>
                            </div>

                            <!-- Tombol Aksi Simpan -->
                            <div class="pt-2 d-flex flex-column gap-2">
                                <button type="submit" class="btn btn-success fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnSubmitForm">
                                    <i class="bi bi-check-circle-fill"></i> Simpan Perubahan
                                </button>
                                <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" class="btn btn-outline-secondary fw-semibold py-1.5">
                                    Batal & Kembali
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<!-- Template Baris Pemetaan COA Dinamis -->
<template id="rowMappingTemplate">
    <tr class="mapping-row">
        <td class="text-center fw-bold text-secondary row-number">__NO__</td>
        <td>
            <select name="mappings[__INDEX__][coa_id]" class="form-select select2-coa" required>
                <option value="">Pilih Akun COA...</option>
                @foreach ($coas as $coa)
                    <option value="{{ $coa->id }}">
                        {{ $coa->kode }} - {{ $coa->nama }}{{ isset($coa->status_aktif) && (int)$coa->status_aktif === 0 ? ' (Nonaktif)' : '' }}
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
                <input class="form-check-input" type="checkbox" name="mappings[__INDEX__][is_excluded]" value="1" title="Centang jika akun ini dikecualikan (exclude)">
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" title="Hapus Akun">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@include('pengaturan.setting-indikator-kinerja.partials.modal-panduan')
@endsection

@push('scripts')
<script>
    window.jQuery(function () {
        const form = document.getElementById('formIndikatorKinerja');
        const tableBody = document.getElementById('tableMappingBody');
        const btnAddRow = document.getElementById('btnAddRow');
        const btnEmptyAdd = document.getElementById('btnEmptyAdd');
        const template = document.getElementById('rowMappingTemplate');
        const emptyPlaceholder = document.getElementById('emptyMappingPlaceholder');
        const mappedCountBadge = document.getElementById('mappedCountBadge');
        const alertManualInfo = document.getElementById('alertManualInfo');

        // Form interactive inputs
        const namaInput = document.getElementById('nama');
        const kodeInput = document.getElementById('kode');
        const satuanSelect = document.getElementById('satuan');
        const tipeKalkulasiSelect = document.getElementById('tipe_kalkulasi');
        const basisPembilangSelect = document.getElementById('basis_pembilang');
        const basisPenyebutSelect = document.getElementById('basis_penyebut');
        const variabelManualCol = document.getElementById('colVariabelManual');
        const colBasisPembilang = document.getElementById('colBasisPembilang');
        const colBasisPenyebut = document.getElementById('colBasisPenyebut');
        const polaEvaluasiSelect = document.getElementById('pola_evaluasi');
        const statusAktifCheckbox = document.getElementById('status_aktif');

        // Ambang inputs
        const targetHijauMin = document.getElementById('target_hijau_min');
        const targetHijauMax = document.getElementById('target_hijau_max');
        const targetKuningMin = document.getElementById('target_kuning_min');
        const targetKuningMax = document.getElementById('target_kuning_max');
        const targetMerahMin = document.getElementById('target_merah_min');
        const targetMerahMax = document.getElementById('target_merah_max');

        // Preview elements
        const previewTitle = document.getElementById('previewTitle');
        const previewKode = document.getElementById('previewKode');
        const previewSatuanBadge = document.getElementById('previewSatuanBadge');
        const previewTipeBadge = document.getElementById('previewTipeBadge');
        const previewStatusBadge = document.getElementById('previewStatusBadge');
        const previewHijauText = document.getElementById('previewHijauText');
        const previewKuningText = document.getElementById('previewKuningText');
        const previewMerahText = document.getElementById('previewMerahText');
        const formulaVisualBadge = document.getElementById('formulaVisualBadge');
        const previewFormulaContent = document.getElementById('previewFormulaContent');
        const variabelManualSelect = document.getElementById('variabel_manual_id');
        const polaExplanation = document.getElementById('polaExplanation');
        const polaGuideTitle = document.getElementById('polaGuideTitle');

        // Header sync button
        document.querySelectorAll('.btn-trigger-submit').forEach(btn => {
            btn.addEventListener('click', function () {
                if (form) form.requestSubmit();
            });
        });

        function initSelect2(scope = document) {
            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery(scope).find('.select2-coa').each(function () {
                const $select = window.jQuery(this);
                if ($select.data('select2')) {
                    return;
                }

                const placeholder = $select.find('option[value=""]').first().text().trim() || 'Pilih Akun COA...';

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: window.jQuery(document.body),
                    placeholder: placeholder,
                    allowClear: !$select.prop('required')
                });

                const $container = $select.next('.select2-container');
                if ($container.length) {
                    $container.data('select2', true);
                    $container.removeClass('select2');
                }
            });
        }

        function reindexRows() {
            const rows = tableBody.querySelectorAll('tr.mapping-row');
            const count = rows.length;

            if (mappedCountBadge) {
                mappedCountBadge.textContent = `${count} Akun Terpetakan`;
            }

            if (count === 0) {
                if (emptyPlaceholder) emptyPlaceholder.style.display = '';
                return;
            }

            if (emptyPlaceholder) emptyPlaceholder.style.display = 'none';

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

        function addNewRow() {
            if (emptyPlaceholder) emptyPlaceholder.style.display = 'none';

            const clone = template.content.cloneNode(true);
            const nextIndex = tableBody.querySelectorAll('tr.mapping-row').length;

            clone.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', nextIndex);
            });

            tableBody.appendChild(clone);
            const addedRow = tableBody.lastElementChild;
            initSelect2(addedRow);
            reindexRows();
        }

        if (btnAddRow) btnAddRow.addEventListener('click', addNewRow);
        if (btnEmptyAdd) btnEmptyAdd.addEventListener('click', addNewRow);

        if (tableBody) {
            tableBody.addEventListener('click', function (e) {
                const btnRemove = e.target.closest('.btn-remove-row');
                if (!btnRemove) return;

                const row = btnRemove.closest('tr.mapping-row');
                if (row) {
                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(row).find('.select2-coa').each(function () {
                            if (window.jQuery(this).data('select2')) {
                                window.jQuery(this).select2('destroy');
                            }
                        });
                    }
                    row.remove();
                    reindexRows();
                }
            });
        }

        function updateUnitSuffixes() {
            const selectedText = satuanSelect && satuanSelect.selectedIndex > 0 
                ? satuanSelect.options[satuanSelect.selectedIndex].text.trim() 
                : '';
            let suffix = '%';
            if (selectedText.includes('%') || selectedText.toLowerCase().includes('persen')) {
                suffix = '%';
            } else if (selectedText.toLowerCase().includes('hari')) {
                suffix = 'hari';
            } else if (selectedText.toLowerCase().includes('kali')) {
                suffix = 'kali';
            } else if (selectedText.toLowerCase().includes('rupiah') || selectedText.toLowerCase().includes('rp')) {
                suffix = 'Rp';
            } else if (selectedText.toLowerCase().includes('skor')) {
                suffix = 'skor';
            } else if (selectedText) {
                suffix = selectedText;
            }

            document.querySelectorAll('.dynamic-unit-suffix').forEach(el => {
                el.textContent = suffix;
            });
        }

        function formatRangeText(minEl, maxEl, suffix) {
            const min = minEl && minEl.value !== '' ? minEl.value : null;
            const max = maxEl && maxEl.value !== '' ? maxEl.value : null;

            if (min !== null && max !== null) {
                return (min === max) ? `= ${min} ${suffix}` : `${min} – ${max} ${suffix}`;
            }
            if (min !== null) return `≥ ${min} ${suffix}`;
            if (max !== null) return `≤ ${max} ${suffix}`;
            return '-';
        }

        function updatePolaGuide() {
            const pola = polaEvaluasiSelect ? polaEvaluasiSelect.value : 'HIGHER_IS_BETTER';
            if (!polaExplanation || !polaGuideTitle) return;

            if (pola === 'HIGHER_IS_BETTER') {
                polaGuideTitle.textContent = 'Pola: Makin Tinggi Makin Baik';
                polaExplanation.textContent = 'Semakin tinggi nilai capaian semakin baik. Nilai di atas batas hijau dinilai efisien, sedangkan nilai di bawah batas merah dinilai kritis.';
            } else if (pola === 'LOWER_IS_BETTER') {
                polaGuideTitle.textContent = 'Pola: Makin Rendah Makin Baik';
                polaExplanation.textContent = 'Semakin rendah nilai capaian semakin baik (contoh: Rasio NPL, Waktu Tunggu). Nilai di bawah batas hijau dinilai baik, dan nilai di atas batas merah dinilai kritis.';
            } else if (pola === 'TARGET_BAND') {
                polaGuideTitle.textContent = 'Pola: Rentang Target Optimal (Band)';
                polaExplanation.textContent = 'Capaian dinilai optimal jika berada di dalam rentang Hijau (contoh: BOR 60%–85%, ALOS 3–5 hari). Angka yang melenceng terlalu rendah atau tinggi masuk rentang peringatan.';
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getBasisPembilangLabel(val) {
            if (val === 'COA') return 'Buku Besar';
            if (val === 'SURPLUS') return 'Surplus Defisit';
            return 'Pembilang';
        }

        function getBasisPenyebutLabel(val, manualText) {
            if (val === 'COA') return 'Buku Besar';
            if (val === 'RBA') return 'Target RBA';
            if (val === 'MANUAL') return manualText ? `Variabel: ${manualText}` : 'Variabel Manual';
            return 'Penyebut';
        }

        function updateBasisInteractivity() {
            const tipe = tipeKalkulasiSelect ? tipeKalkulasiSelect.value : 'RASIO';
            const penyebutVal = basisPenyebutSelect ? basisPenyebutSelect.value : 'COA';
            const pembilangVal = basisPembilangSelect ? basisPembilangSelect.value : 'COA';

            let manualName = '';
            if (variabelManualSelect && variabelManualSelect.selectedIndex > 0) {
                const opt = variabelManualSelect.options[variabelManualSelect.selectedIndex];
                manualName = opt.dataset.nama || opt.text.split('(')[0].trim();
            }

            if (tipe === 'MANUAL') {
                if (colBasisPembilang) colBasisPembilang.style.display = 'none';
                if (colBasisPenyebut) colBasisPenyebut.style.display = 'none';
                if (variabelManualCol) variabelManualCol.style.display = '';
                if (alertManualInfo) alertManualInfo.style.display = '';
                if (formulaVisualBadge) {
                    formulaVisualBadge.textContent = manualName 
                        ? `Basis: Manual (${manualName})` 
                        : 'Basis: Variabel Manual Bulanan';
                }
            } else {
                if (colBasisPembilang) colBasisPembilang.style.display = '';
                if (colBasisPenyebut) colBasisPenyebut.style.display = '';
                if (alertManualInfo) alertManualInfo.style.display = 'none';

                if (penyebutVal === 'MANUAL') {
                    if (variabelManualCol) variabelManualCol.style.display = '';
                } else {
                    if (variabelManualCol) variabelManualCol.style.display = 'none';
                }

                if (formulaVisualBadge) {
                    const pembLabel = getBasisPembilangLabel(pembilangVal);
                    const penyLabel = getBasisPenyebutLabel(penyebutVal, manualName);
                    formulaVisualBadge.textContent = `Basis: ${pembLabel} ÷ ${penyLabel}`;
                }
            }
        }

        function updateLivePreview() {
            if (previewTitle) {
                previewTitle.textContent = (namaInput && namaInput.value.trim() !== '') 
                    ? namaInput.value 
                    : 'Nama Indikator Kinerja';
            }
            if (previewKode) {
                previewKode.textContent = (kodeInput && kodeInput.value.trim() !== '') 
                    ? kodeInput.value 
                    : 'kode_unik';
            }
            if (previewSatuanBadge) {
                const satuanText = satuanSelect && satuanSelect.selectedIndex > 0 
                    ? satuanSelect.options[satuanSelect.selectedIndex].text.trim() 
                    : 'Belum dipilih';
                previewSatuanBadge.textContent = `Satuan: ${satuanText}`;
            }
            if (previewTipeBadge) {
                const tipeText = tipeKalkulasiSelect && tipeKalkulasiSelect.selectedIndex >= 0 
                    ? tipeKalkulasiSelect.options[tipeKalkulasiSelect.selectedIndex].text.trim() 
                    : 'Rasio';
                previewTipeBadge.textContent = tipeText;
            }
            if (previewStatusBadge) {
                const isAktif = statusAktifCheckbox ? statusAktifCheckbox.checked : true;
                if (isAktif) {
                    previewStatusBadge.textContent = 'Aktif';
                    previewStatusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 small';
                } else {
                    previewStatusBadge.textContent = 'Nonaktif';
                    previewStatusBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5 small';
                }
            }

            if (previewFormulaContent) {
                const tipe = tipeKalkulasiSelect ? tipeKalkulasiSelect.value : 'RASIO';
                const pembilangVal = basisPembilangSelect ? basisPembilangSelect.value : 'COA';
                const penyebutVal = basisPenyebutSelect ? basisPenyebutSelect.value : 'COA';

                let manualName = '';
                if (variabelManualSelect && variabelManualSelect.selectedIndex > 0) {
                    const opt = variabelManualSelect.options[variabelManualSelect.selectedIndex];
                    manualName = opt.dataset.nama || opt.text.split('(')[0].trim();
                }

                if (tipe === 'MANUAL') {
                    const labelText = manualName || 'Pilih Variabel Manual';
                    const safeLabel = escapeHtml(labelText);
                    previewFormulaContent.innerHTML = `
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-0.5 px-1.5 text-truncate" style="max-width: 100%;" title="${safeLabel}">
                            <i class="bi bi-pencil-square me-1"></i>Manual: ${safeLabel}
                        </span>
                    `;
                } else {
                    const pembLabel = getBasisPembilangLabel(pembilangVal);
                    const penyLabel = getBasisPenyebutLabel(penyebutVal, manualName);
                    const safePemb = escapeHtml(pembLabel);
                    const safePeny = escapeHtml(penyLabel);

                    const satuanVal = satuanSelect ? satuanSelect.value : '';
                    let multiplierBadge = '';
                    if (tipe === 'TURNOVER') {
                        multiplierBadge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0.5 px-1.5 ms-0.5">× 365 Hari</span>';
                    } else if (tipe === 'RASIO' && satuanVal === '%') {
                        multiplierBadge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0.5 px-1.5 ms-0.5">× 100%</span>';
                    }

                    previewFormulaContent.innerHTML = `
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1.5">${safePemb}</span>
                        <span class="text-muted fw-bold mx-0.5">÷</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-0.5 px-1.5 text-truncate" style="max-width: 130px;" title="${safePeny}">${safePeny}</span>
                        ${multiplierBadge}
                    `;
                }
            }

            const suffix = document.querySelector('.dynamic-unit-suffix')?.textContent || '';
            if (previewHijauText) previewHijauText.textContent = formatRangeText(targetHijauMin, targetHijauMax, suffix);
            if (previewKuningText) previewKuningText.textContent = formatRangeText(targetKuningMin, targetKuningMax, suffix);
            if (previewMerahText) previewMerahText.textContent = formatRangeText(targetMerahMin, targetMerahMax, suffix);
        }

        // Event listeners
        if (namaInput) namaInput.addEventListener('input', updateLivePreview);
        if (kodeInput) kodeInput.addEventListener('input', updateLivePreview);
        if (statusAktifCheckbox) statusAktifCheckbox.addEventListener('change', updateLivePreview);

        if (satuanSelect) {
            satuanSelect.addEventListener('change', function () {
                updateUnitSuffixes();
                updateLivePreview();
            });
        }

        if (tipeKalkulasiSelect) {
            tipeKalkulasiSelect.addEventListener('change', function () {
                updateBasisInteractivity();
                updateLivePreview();
            });
        }

        if (basisPembilangSelect) {
            basisPembilangSelect.addEventListener('change', function () {
                updateBasisInteractivity();
                updateLivePreview();
            });
        }

        if (basisPenyebutSelect) {
            basisPenyebutSelect.addEventListener('change', function () {
                updateBasisInteractivity();
                updateLivePreview();
            });
        }

        if (variabelManualSelect) {
            variabelManualSelect.addEventListener('change', function () {
                updateBasisInteractivity();
                updateLivePreview();
            });
        }

        if (polaEvaluasiSelect) {
            polaEvaluasiSelect.addEventListener('change', function () {
                updatePolaGuide();
                updateLivePreview();
            });
        }

        [targetHijauMin, targetHijauMax, targetKuningMin, targetKuningMax, targetMerahMin, targetMerahMax].forEach(el => {
            if (el) el.addEventListener('input', updateLivePreview);
        });

        // Initialize UI states
        initSelect2(document);
        reindexRows();
        updateUnitSuffixes();
        updateBasisInteractivity();
        updatePolaGuide();
        updateLivePreview();
    });
</script>
@endpush
