@extends('layouts.app')

@section('title', 'Setting Indikator Kinerja')

@push('styles')
<style>
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .spreadsheet-header-months {
        background: #0d6efd !important;
        color: #ffffff !important;
    }

    /* Tab 1: Sticky Aksi Column */
    .table-tab1 {
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-tab1 thead th.sticky-col-action-tab1 {
        position: sticky;
        right: 0;
        width: 95px;
        min-width: 95px;
        max-width: 95px;
        z-index: 4;
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
        box-shadow: -4px 0 6px -2px rgba(0, 0, 0, 0.08);
        border-left: 1px solid #cbd5e1 !important;
    }
    .table-tab1 tbody td.sticky-col-action-tab1 {
        position: sticky;
        right: 0;
        width: 95px;
        min-width: 95px;
        max-width: 95px;
        z-index: 2;
        background-color: #ffffff !important;
        --bs-table-bg: #ffffff !important;
        --bs-table-accent-bg: #ffffff !important;
        box-shadow: -4px 0 6px -2px rgba(0, 0, 0, 0.08);
        border-left: 1px solid #cbd5e1 !important;
    }
    .table-tab1 tbody tr:hover > td.sticky-col-action-tab1 {
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
    }

    /* Tab 2: 2D Sticky Panes & Resizable Matrix Viewport */
    .matrix-scroll-container {
        overflow: auto;
        max-height: 80vh;
        min-height: 220px;
        position: relative;
    }
    .matrix-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    /* Sticky Kolom Horizontal (Tbody & Thead) */
    .matrix-table .sticky-col-no {
        position: sticky;
        left: 0;
        width: 45px;
        min-width: 45px;
        max-width: 45px;
        z-index: 2;
    }
    .matrix-table .sticky-col-kode {
        position: sticky;
        left: 45px;
        width: 130px;
        min-width: 130px;
        max-width: 130px;
        z-index: 2;
    }
    .matrix-table .sticky-col-nama {
        position: sticky;
        left: 175px;
        min-width: 240px;
        z-index: 2;
        box-shadow: 4px 0 6px -2px rgba(0, 0, 0, 0.08);
        border-right: 1px solid #cbd5e1 !important;
    }
    .matrix-table .sticky-col-action {
        position: sticky;
        right: 0;
        width: 90px;
        min-width: 90px;
        max-width: 90px;
        z-index: 2;
        box-shadow: -4px 0 6px -2px rgba(0, 0, 0, 0.08);
        border-left: 1px solid #cbd5e1 !important;
    }

    /* Solid Opaque Background for Sticky Cells in Tbody */
    .matrix-table tbody td.sticky-col-no,
    .matrix-table tbody td.sticky-col-kode,
    .matrix-table tbody td.sticky-col-nama,
    .matrix-table tbody td.sticky-col-action {
        background-color: #ffffff !important;
        --bs-table-bg: #ffffff !important;
        --bs-table-accent-bg: #ffffff !important;
    }

    /* Hover & Active States for Sticky Cells */
    .matrix-table tbody tr:hover > td.sticky-col-no,
    .matrix-table tbody tr:hover > td.sticky-col-kode,
    .matrix-table tbody tr:hover > td.sticky-col-nama,
    .matrix-table tbody tr:hover > td.sticky-col-action {
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
    }
    .matrix-table tbody tr.cell-active-row > td.sticky-col-no,
    .matrix-table tbody tr.cell-active-row > td.sticky-col-kode,
    .matrix-table tbody tr.cell-active-row > td.sticky-col-nama,
    .matrix-table tbody tr.cell-active-row > td.sticky-col-action {
        background-color: #f1f5f9 !important;
        --bs-table-bg: #f1f5f9 !important;
        --bs-table-accent-bg: #f1f5f9 !important;
    }

    /* Sticky Header Vertikal */
    .matrix-table thead tr:first-child th {
        position: sticky;
        top: 0;
        z-index: 4;
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
    }
    .matrix-table thead tr:first-child th.bg-primary-subtle {
        background-color: var(--bs-primary-bg-subtle) !important;
        --bs-table-bg: var(--bs-primary-bg-subtle) !important;
        --bs-table-accent-bg: var(--bs-primary-bg-subtle) !important;
    }
    .matrix-table thead tr:nth-child(2) th {
        position: sticky;
        top: 35px;
        z-index: 3;
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
    }
    .matrix-table thead tr:nth-child(2) th.bg-primary-subtle {
        background-color: var(--bs-primary-bg-subtle) !important;
        --bs-table-bg: var(--bs-primary-bg-subtle) !important;
        --bs-table-accent-bg: var(--bs-primary-bg-subtle) !important;
    }

    /* Corner Intersection Cells (Header Kolom 1, 2, 3 & Aksi pada thead) */
    .matrix-table thead th.sticky-col-no,
    .matrix-table thead th.sticky-col-kode,
    .matrix-table thead th.sticky-col-nama,
    .matrix-table thead th.sticky-col-action {
        z-index: 10 !important;
        background-color: #f8fafc !important;
        --bs-table-bg: #f8fafc !important;
        --bs-table-accent-bg: #f8fafc !important;
    }

    /* Active Cell / Crosshair Highlight */
    tr.cell-active-row > td {
        background-color: #f1f5f9 !important;
        --bs-table-bg: #f1f5f9 !important;
        --bs-table-accent-bg: #f1f5f9 !important;
    }
    th.cell-active-col, td.cell-active-col {
        background-color: #f0fdf4 !important;
        --bs-table-bg: #f0fdf4 !important;
        --bs-table-accent-bg: #f0fdf4 !important;
    }
    /* Resizable Handle Bar */
    .table-resize-handle {
        cursor: row-resize;
        user-select: none;
        transition: background-color 0.15s ease;
    }
    .table-resize-handle:hover {
        background-color: #e2e8f0;
    }
    /* Flash Highlight Animation */
    @keyframes flashHighlight {
        0% { background-color: #fef08a; }
        70% { background-color: #fef9c3; }
        100% { background-color: inherit; }
    }
    .row-flash-highlight > td {
        animation: flashHighlight 2s ease-out;
    }
    body.matrix-resizing {
        cursor: row-resize !important;
        user-select: none !important;
    }

    /* Custom Popover Pemetaan COA */
    .popover.popover-coa-custom {
        --bs-popover-max-width: 420px;
        --bs-popover-border-color: #cbd5e1;
        --bs-popover-header-bg: #f8fafc;
        --bs-popover-header-color: #0f172a;
        --bs-popover-body-padding-x: 0;
        --bs-popover-body-padding-y: 0;
        border-radius: 0.625rem;
        border: 1px solid #cbd5e1;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.14), 0 8px 10px -6px rgba(15, 23, 42, 0.08) !important;
    }
    .popover-coa-custom .popover-header {
        font-size: 0.8125rem;
        font-weight: 600;
        padding: 0.625rem 0.875rem;
        border-bottom: 1px solid #e2e8f0;
        background-color: #f8fafc;
        color: #1e293b;
        margin: 0;
        border-top-left-radius: calc(0.625rem - 1px);
        border-top-right-radius: calc(0.625rem - 1px);
    }
    .popover-coa-custom .popover-body {
        padding: 0 !important;
        background-color: #ffffff;
        border-bottom-left-radius: calc(0.625rem - 1px);
        border-bottom-right-radius: calc(0.625rem - 1px);
    }
    .popover-coa-item {
        transition: background-color 0.15s ease;
    }
    .popover-coa-item:hover {
        background-color: #f8fafc;
    }
    .popover-coa-item:last-child {
        border-bottom: 0 !important;
    }
</style>
@endpush

@php
    $formatBand = function ($min, $max, $satuan = '', $pola = null) {
        $satuanStr = $satuan ? ' '.$satuan : '';
        if ($min !== null && $max !== null) {
            $minFormatted = rtrim(rtrim(number_format((float) $min, 2, ',', '.'), '0'), ',');
            $maxFormatted = rtrim(rtrim(number_format((float) $max, 2, ',', '.'), '0'), ',');
            return ($min == $max) ? "= {$minFormatted}{$satuanStr}" : "{$minFormatted} – {$maxFormatted}{$satuanStr}";
        }
        if ($min !== null) {
            $minFormatted = rtrim(rtrim(number_format((float) $min, 2, ',', '.'), '0'), ',');
            return "≥ {$minFormatted}{$satuanStr}";
        }
        if ($max !== null) {
            $maxFormatted = rtrim(rtrim(number_format((float) $max, 2, ',', '.'), '0'), ',');
            return "≤ {$maxFormatted}{$satuanStr}";
        }
        $polaVal = $pola instanceof \App\Enums\IndikatorKinerja\PolaEvaluasi ? $pola->value : (string) $pola;
        if ($polaVal === 'TARGET_BAND') {
            return 'Di luar rentang';
        }
        return '-';
    };

    $formatShortBand = function ($min, $max, $satuan = '', $pola = null) {
        $satuanStr = $satuan ? ($satuan === '%' ? '%' : ' '.$satuan) : '';
        $formatNum = function ($val) {
            return rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',');
        };
        if ($min !== null && $max !== null) {
            $minF = $formatNum($min);
            $maxF = $formatNum($max);
            return ($min == $max) ? "= {$minF}{$satuanStr}" : "{$minF}–{$maxF}{$satuanStr}";
        }
        if ($min !== null) {
            $minF = $formatNum($min);
            return "≥ {$minF}{$satuanStr}";
        }
        if ($max !== null) {
            $maxF = $formatNum($max);
            return "≤ {$maxF}{$satuanStr}";
        }
        $polaVal = $pola instanceof \App\Enums\IndikatorKinerja\PolaEvaluasi ? $pola->value : (string) $pola;
        if ($polaVal === 'TARGET_BAND') {
            return 'Luar rentang';
        }
        return '-';
    };

    $totalIndikator = $summaryCounts['total'] ?? $indikators->count();
    $aktifIndikator = $summaryCounts['aktif'] ?? $indikators->where('status_aktif', true)->count();
    $nonaktifIndikator = $summaryCounts['nonaktif'] ?? $indikators->where('status_aktif', false)->count();
    $totalVariabel = $variabelManuals->count();
@endphp

@section('content')
<div class="d-flex flex-column gap-3">
    <!-- Header -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('home') }}" class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px;" title="Kembali ke Dashboard">
                        <i class="bi bi-arrow-left fs-5"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="mb-0 fw-bold text-dark">Setting Indikator Kinerja</h4>
                            <span class="badge bg-success-subtle text-success border border-success-subtle small">Superadmin</span>
                        </div>
                        <p class="text-muted mb-0 small mt-1">
                            Konfigurasi master indikator rumah sakit, ambang batas target RAG, formula akuntansi & entri data manual bulanan.
                        </p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="btnOpenModalPanduan" class="btn btn-sm btn-outline-success fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalPanduanSetting" title="Buka Panduan &amp; Glosarium Indikator Kinerja">
                        <i class="bi bi-book-half text-success"></i>
                        <span>Panduan &amp; Glosarium</span>
                    </button>

                    @if (auth()->user()?->hasModuleAccess('dashboard.indikator-kinerja', 'view'))
                        <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-sm btn-success fw-semibold d-inline-flex align-items-center gap-2 shadow-xs">
                            <i class="bi bi-speedometer2"></i>
                            <span>Buka Dashboard Eksekutif</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    <!-- Ringkasan Master Indikator (Executive Strip) -->
    <div class="row g-3">
        <!-- Total Indikator -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-primary">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Total Indikator</div>
                        <h3 class="mb-0 fw-bold text-dark mt-1">{{ $totalIndikator }}</h3>
                        <div class="small text-muted mt-0.5">Master Metrik</div>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                        <i class="bi bi-collection-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indikator Aktif -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-success">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-success small fw-semibold text-uppercase tracking-wider">Indikator Aktif</div>
                        <h3 class="mb-0 fw-bold text-success mt-1">{{ $aktifIndikator }}</h3>
                        <div class="small text-success mt-0.5">Ditampilkan di Dashboard</div>
                    </div>
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indikator Nonaktif -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-secondary">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small fw-semibold text-uppercase tracking-wider">Indikator Nonaktif</div>
                        <h3 class="mb-0 fw-bold text-secondary mt-1">{{ $nonaktifIndikator }}</h3>
                        <div class="small text-secondary mt-0.5">Diarsipkan / Sementara</div>
                    </div>
                    <div class="rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                        <i class="bi bi-pause-circle-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variabel Manual -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-info">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-info-emphasis small fw-semibold text-uppercase tracking-wider">Variabel Manual</div>
                        <h3 class="mb-0 fw-bold text-info-emphasis mt-1">{{ $totalVariabel }}</h3>
                        <div class="small text-info-emphasis mt-0.5">Non-Akuntansi (BAHV/SDM)</div>
                    </div>
                    <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                        <i class="bi bi-table fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="settingTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-semibold px-3 py-2.5 {{ $tab !== 'manual' ? 'active text-primary' : 'text-muted' }}" 
                       href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator', 'tahun' => $tahun]) }}">
                        <i class="bi bi-speedometer2 me-1.5"></i> 1. Manajemen Indikator & Ambang Batas
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ $indikators->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-semibold px-3 py-2.5 {{ $tab === 'manual' ? 'active text-primary' : 'text-muted' }}" 
                       href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun]) }}">
                        <i class="bi bi-calendar3 me-1.5"></i> 2. Entri Data Manual Bulanan
                        <span class="badge bg-amber-subtle text-warning-emphasis rounded-pill ms-1">{{ $totalVariabel }} Variabel</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-3 p-md-4">
            @if ($tab !== 'manual')
                <!-- ============================================== -->
                <!-- TAB 1: MANAJEMEN INDIKATOR & TARGET RAG        -->
                <!-- ============================================== -->
                <div class="tab-pane fade show active">
                    <!-- Toolbar Filter Pencarian -->
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <form method="GET" action="{{ route('pengaturan.setting-indikator-kinerja.index') }}" class="row g-2 align-items-end">
                            <input type="hidden" name="tab" value="indikator">

                            <div class="col-12 col-md-8 col-lg-9">
                                <label for="qInput" class="form-label small fw-semibold text-muted mb-1">Cari Indikator</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted border-end-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="qInput" name="q" value="{{ $q ?? '' }}" placeholder="Cari Kode, Nama, atau Formula...">
                                </div>
                            </div>

                            <div class="col-12 col-md-4 col-lg-3 d-flex gap-2 justify-content-md-end">
                                <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                                <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']) }}" class="btn btn-sm btn-light border fw-semibold">
                                    Reset
                                </a>

                                @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'create'))
                                    <a href="{{ route('pengaturan.setting-indikator-kinerja.create') }}" class="btn btn-sm btn-success fw-bold text-nowrap ms-auto">
                                        <i class="bi bi-plus-circle me-1"></i> Tambah Indikator
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>

                    <!-- Table Master Indikator -->
                    <div class="table-responsive border rounded-3 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 text-sm table-tab1">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase tracking-wider text-secondary">
                                    <th class="py-2.5 px-3 text-center" style="width: 50px;">Urutan</th>
                                    <th class="py-2.5 px-3" style="width: 120px;">Kode</th>
                                    <th class="py-2.5 px-3" style="min-width: 200px;">Nama Indikator</th>
                                    <th class="py-2.5 px-3" style="min-width: 240px;">Definisi Operasional / Formula</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 120px;">Pola & Satuan</th>
                                    <th class="py-2.5 px-3" style="min-width: 240px;">Ambang Batas Target (RAG)</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 90px;">Status</th>
                                    <th class="py-2.5 px-3 text-center sticky-col-action-tab1" style="width: 95px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($indikators as $item)
                                    @php
                                        $satuanVal = $item->satuan?->value ?? ($item->satuan ?? '');
                                        $targetHijau = $formatBand($item->target_hijau_min, $item->target_hijau_max, $satuanVal, $item->pola_evaluasi);
                                        $targetKuning = $formatBand($item->target_kuning_min, $item->target_kuning_max, $satuanVal, $item->pola_evaluasi);
                                        $targetMerah = $formatBand($item->target_merah_min, $item->target_merah_max, $satuanVal, $item->pola_evaluasi);
                                        $targetHijauShort = $formatShortBand($item->target_hijau_min, $item->target_hijau_max, $satuanVal, $item->pola_evaluasi);
                                        $targetKuningShort = $formatShortBand($item->target_kuning_min, $item->target_kuning_max, $satuanVal, $item->pola_evaluasi);
                                        $targetMerahShort = $formatShortBand($item->target_merah_min, $item->target_merah_max, $satuanVal, $item->pola_evaluasi);
                                        $polaLabel = $item->pola_evaluasi ? $item->pola_evaluasi->label() : '-';
                                        $polaEvaluasiVal = $item->pola_evaluasi instanceof \App\Enums\IndikatorKinerja\PolaEvaluasi
                                            ? $item->pola_evaluasi->value
                                            : (string) ($item->pola_evaluasi?->value ?? $item->pola_evaluasi);
                                        $tipeVal = $item->tipe_kalkulasi instanceof \App\Enums\IndikatorKinerja\TipeKalkulasi
                                            ? $item->tipe_kalkulasi->value
                                            : (string) ($item->tipe_kalkulasi?->value ?? $item->tipe_kalkulasi);

                                        // Persiapan Popover COA
                                        $popoverTitle = '';
                                        $popoverCoaContent = '';
                                        if ($item->mappingCoa->isNotEmpty()) {
                                            $popoverTitle = '<div class="d-flex align-items-center gap-1.5">'
                                                . '<i class="bi bi-diagram-3 text-primary"></i>'
                                                . '<span>Pemetaan Akun: <strong class="text-dark">'.e($item->nama).'</strong></span>'
                                                . '</div>';

                                            $coaRows = [];
                                            foreach ($item->mappingCoa as $m) {
                                                $coaKode = e($m->coa?->kode ?? '-');
                                                $coaNama = e($m->coa?->nama ?? '-');
                                                $peranVal = $m->peran instanceof \App\Enums\IndikatorKinerja\PeranCoa ? $m->peran->value : (string) ($m->peran?->value ?? $m->peran);
                                                
                                                if ($m->is_excluded) {
                                                    $bHtml = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Dikecualikan</span>';
                                                    $tClass = 'text-decoration-line-through text-muted';
                                                } elseif ($peranVal === 'deduction') {
                                                    $bHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">- Pengurang</span>';
                                                    $tClass = 'text-dark';
                                                } elseif ($peranVal === 'addition') {
                                                    $bHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle">+ Penambah</span>';
                                                    $tClass = 'text-dark';
                                                } elseif ($peranVal === 'denominator') {
                                                    $bHtml = '<span class="badge bg-info-subtle text-info border border-info-subtle">Penyebut</span>';
                                                    $tClass = 'text-dark';
                                                } else {
                                                    $bHtml = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Pembilang</span>';
                                                    $tClass = 'text-dark';
                                                }

                                                $coaRows[] = '<li class="popover-coa-item d-flex align-items-center justify-content-between gap-3 px-3 py-2 border-bottom border-light">'
                                                    . '<div class="d-flex align-items-center gap-2 overflow-hidden me-2">'
                                                    . '<span class="badge bg-light text-dark border font-monospace px-1.5 py-0.5 flex-shrink-0">'.$coaKode.'</span>'
                                                    . '<span class="'.$tClass.' small fw-medium text-break">'.$coaNama.'</span>'
                                                    . '</div>'
                                                    . '<span class="flex-shrink-0">'.$bHtml.'</span>'
                                                    . '</li>';
                                            }

                                            $popoverCoaContent = '<div class="popover-coa-list custom-scrollbar" style="max-height: 240px; overflow-y: auto;">'
                                                . '<ul class="list-unstyled mb-0">' . implode('', $coaRows) . '</ul>'
                                                . '</div>';
                                        }

                                        // Basis labels
                                        $pembilangLabel = match ($item->basis_pembilang?->value ?? '') {
                                            'COA' => 'Buku Besar',
                                            'SURPLUS' => 'Surplus Defisit',
                                            default => $item->basis_pembilang?->label() ?? 'Pembilang',
                                        };
                                        $penyebutLabel = match ($item->basis_penyebut?->value ?? '') {
                                            'COA' => 'Buku Besar',
                                            'RBA' => 'Target RBA',
                                            'MANUAL' => $item->variabelManual ? 'Variabel: '.$item->variabelManual->nama : 'Variabel Manual',
                                            default => $item->basis_penyebut?->label() ?? 'Penyebut',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-center align-middle fw-bold text-muted">{{ $item->urutan }}</td>
                                        <td class="align-middle">
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">{{ $item->kode }}</span>
                                        </td>
                                        <td class="align-middle">
                                            <div class="fw-bold text-dark">{{ $item->nama }}</div>
                                            <div class="mt-1 d-flex flex-wrap align-items-center gap-1.5">
                                                @if ($tipeVal === 'MANUAL')
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-0.5 px-2">
                                                        <i class="bi bi-pencil-square me-1"></i> Manual (Non-Akuntansi)
                                                    </span>
                                                @elseif ($tipeVal === 'TURNOVER')
                                                    <span class="badge bg-info-subtle text-dark border border-info-subtle py-0.5 px-2">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Turnover (Hari)
                                                    </span>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-2">
                                                        <i class="bi bi-calculator me-1"></i> Rasio
                                                    </span>
                                                @endif

                                                @if ($item->mappingCoa->count() > 0)
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light border py-0.5 px-2 text-secondary small text-decoration-none d-inline-flex align-items-center gap-1.5 shadow-xs" 
                                                            data-bs-toggle="popover" 
                                                            data-bs-placement="top" 
                                                            data-bs-html="true" 
                                                            data-bs-custom-class="popover-coa-custom" 
                                                            data-bs-title="{{ $popoverTitle }}" 
                                                            data-bs-content="{{ $popoverCoaContent }}">
                                                        <i class="bi bi-diagram-3 text-primary"></i>
                                                        <span>{{ $item->mappingCoa->count() }} COA dipetakan</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-middle text-muted small">
                                            <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                                @if ($tipeVal === 'MANUAL')
                                                    <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun, 'focus_var' => $item->variabelManual?->id]) }}" 
                                                       class="badge bg-warning-subtle text-dark border text-decoration-none" 
                                                       title="Klik untuk entri data manual di Tab 2">
                                                        <i class="bi bi-pencil-square me-1"></i>Manual: {{ $item->variabelManual?->nama ?? 'Data Manual' }} <i class="bi bi-arrow-right-short"></i>
                                                    </a>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary border">{{ $pembilangLabel }}</span>
                                                    <span class="text-muted fw-bold mx-1">÷</span>
                                                    @if ($item->basis_penyebut?->value === 'MANUAL' && $item->variabelManual)
                                                        <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun, 'focus_var' => $item->variabelManual->id]) }}" 
                                                           class="badge bg-info-subtle text-info-emphasis border text-decoration-none" 
                                                           title="Klik untuk entri data manual di Tab 2">
                                                            <i class="bi bi-pencil-square me-1"></i>{{ $penyebutLabel }} <i class="bi bi-arrow-right-short"></i>
                                                        </a>
                                                    @else
                                                        <span class="badge bg-info-subtle text-info-emphasis border">{{ $penyebutLabel }}</span>
                                                    @endif

                                                    @if ($satuanVal === '%')
                                                        <span class="badge bg-secondary-subtle text-secondary border ms-1">× 100%</span>
                                                    @elseif (strtolower($satuanVal) === 'hari')
                                                        <span class="badge bg-secondary-subtle text-secondary border ms-1">× 365 Hari</span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="text-truncate text-muted" style="max-width: 280px;" title="{{ $item->definisi_operasional }}">
                                                {{ $item->definisi_operasional ?: 'Formula kalkulasi berdasarkan konfigurasi indikator.' }}
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 fw-semibold d-block mb-1">
                                                {{ $satuanVal ?: '-' }}
                                            </span>
                                            <span class="small text-muted font-monospace" style="font-size: 0.72rem;">
                                                {{ $polaLabel }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <div class="rag-bar-wrapper" style="min-width: 220px;">
                                                <div class="d-flex rounded-2 overflow-hidden border" style="height: 24px; font-size: 0.72rem; font-weight: 600;">
                                                    @if ($polaEvaluasiVal === 'LOWER_IS_BETTER')
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-success-subtle text-success border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Hijau (Optimal):</strong> {{ $targetHijau }}">
                                                            <span class="text-truncate px-1">🟢 {{ $targetHijauShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Kuning (Waspada):</strong> {{ $targetKuning }}">
                                                            <span class="text-truncate px-1">🟡 {{ $targetKuningShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-danger-subtle text-danger" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Merah (Kritis):</strong> {{ $targetMerah }}">
                                                            <span class="text-truncate px-1">🔴 {{ $targetMerahShort }}</span>
                                                        </div>
                                                    @elseif ($polaEvaluasiVal === 'TARGET_BAND')
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-danger-subtle text-danger border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Merah (Luar Rentang):</strong> {{ $targetMerah }}">
                                                            <span class="text-truncate px-1">🔴 {{ $targetMerahShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-success-subtle text-success border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Hijau (Target Optimal):</strong> {{ $targetHijau }}">
                                                            <span class="text-truncate px-1">🟢 {{ $targetHijauShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Kuning (Peringatan):</strong> {{ $targetKuning }}">
                                                            <span class="text-truncate px-1">🟡 {{ $targetKuningShort }}</span>
                                                        </div>
                                                    @else
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-danger-subtle text-danger border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Merah (Kritis):</strong> {{ $targetMerah }}">
                                                            <span class="text-truncate px-1">🔴 {{ $targetMerahShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis border-end" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Kuning (Waspada):</strong> {{ $targetKuning }}">
                                                            <span class="text-truncate px-1">🟡 {{ $targetKuningShort }}</span>
                                                        </div>
                                                        <div class="flex-fill d-flex align-items-center justify-content-center bg-success-subtle text-success" 
                                                             data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Zona Hijau (Optimal):</strong> {{ $targetHijau }}">
                                                            <span class="text-truncate px-1">🟢 {{ $targetHijauShort }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            @if ($item->status_aktif)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-semibold">
                                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Aktif
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                    Nonaktif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle text-nowrap sticky-col-action-tab1">
                                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'update'))
                                                <a href="{{ route('pengaturan.setting-indikator-kinerja.edit', $item->id) }}" class="btn btn-sm btn-outline-primary py-0.5 px-2" title="Edit Indikator & Mapping COA">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endif
                                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'delete'))
                                                <form action="{{ route('pengaturan.setting-indikator-kinerja.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus indikator ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2 ms-1" title="Hapus Indikator">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada data indikator kinerja yang sesuai dengan filter pencarian.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <!-- ============================================== -->
                <!-- TAB 2: ENTRI DATA MANUAL BULANAN               -->
                <!-- ============================================== -->
                <div class="tab-pane fade show active">
                    <!-- Toolbar Filter Tahun & Tombol Tambah Variabel -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 bg-light p-3 rounded-3 border">
                        <form method="GET" action="{{ route('pengaturan.setting-indikator-kinerja.index') }}" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="tab" value="manual">
                            <label for="tahunSelect" class="form-label fw-bold mb-0 text-nowrap small text-muted">
                                <i class="bi bi-calendar3 me-1"></i> Tahun Anggaran:
                            </label>
                            <select name="tahun" id="tahunSelect" class="form-select form-select-sm fw-bold" style="width: 120px;" onchange="this.form.submit()">
                                @for ($y = now()->year + 1; $y >= now()->year - 4; $y--)
                                    <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option>
                                @endfor
                            </select>
                            <button type="submit" class="btn btn-sm btn-secondary fw-semibold">
                                <i class="bi bi-arrow-clockwise"></i> Muat
                            </button>
                        </form>

                        <div class="d-flex align-items-center gap-2">
                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'create'))
                                <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahVariabel">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Variabel Baru
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Spreadsheet Form Input Matriks Bulanan -->
                    <form method="POST" action="{{ route('pengaturan.setting-indikator-kinerja.simpan-manual-data') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <div class="matrix-wrapper mb-3">
                            <div id="matrixScrollContainer" class="table-responsive border rounded-top custom-scrollbar matrix-scroll-container" style="height: 520px;">
                                <table class="table table-sm table-bordered table-hover align-middle mb-0 text-sm matrix-table" style="min-width: 1300px;">
                                    <thead class="table-light text-center align-middle border-bottom">
                                        <tr>
                                            <th rowspan="2" class="py-2.5 px-2 bg-light text-secondary sticky-col-no" style="width: 45px;">No</th>
                                            <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary sticky-col-kode" style="width: 130px;">Kode</th>
                                            <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary sticky-col-nama" style="min-width: 240px;">Nama Variabel</th>
                                            <th rowspan="2" class="py-2.5 px-2 bg-light text-secondary" style="width: 85px;">Satuan</th>
                                            <th colspan="12" class="bg-primary-subtle text-primary py-1.5 fw-bold text-uppercase border-bottom" style="letter-spacing: 0.5px; height: 35px;">Bulan Transaksi (Tahun {{ $tahun }})</th>
                                            <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary" style="width: 110px;">Rata-rata</th>
                                            <th rowspan="2" class="py-2.5 px-2 bg-light text-secondary text-center sticky-col-action" style="width: 90px;">Aksi</th>
                                        </tr>
                                        <tr class="small text-secondary bg-light">
                                            @php
                                                $namaBulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
                                                $isCurrentYear = ($tahun === (int) now()->year);
                                                $currentMonth = (int) now()->month;
                                            @endphp
                                            @foreach ($namaBulan as $mNum => $mNama)
                                                @php
                                                    $isThisMonth = $isCurrentYear && ($mNum === $currentMonth);
                                                @endphp
                                                <th class="py-1 px-1 col-month-{{ $mNum }} {{ $isThisMonth ? 'bg-primary-subtle text-primary border-primary border-start border-end' : '' }}" style="min-width: 85px;">
                                                    <div>{{ $mNama }}</div>
                                                    @if ($isThisMonth)
                                                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Bulan Ini</span>
                                                    @endif
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($variabelManuals as $v)
                                            @php
                                                $rowIdx = $loop->index;
                                                $dataMap = $v->manualData->keyBy('bulan');
                                                $sum = 0;
                                                $count = 0;
                                                for ($m = 1; $m <= 12; $m++) {
                                                    $rec = $dataMap->get($m);
                                                    if ($rec && $rec->nilai !== null) {
                                                        $sum += (float) $rec->nilai;
                                                        $count++;
                                                    }
                                                }
                                                $initialAvg = $count > 0 ? ($sum / $count) : null;
                                            @endphp
                                            <tr id="var-row-{{ $v->id }}" data-var-id="{{ $v->id }}">
                                                <td class="text-center align-middle text-muted fw-bold sticky-col-no">{{ $loop->iteration }}</td>
                                                <td class="text-center align-middle sticky-col-kode">
                                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1">{{ $v->kode }}</span>
                                                </td>
                                                <td class="align-middle sticky-col-nama">
                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                        <span class="fw-bold text-dark">{{ $v->nama }}</span>
                                                        @if ($count === 12)
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace" style="font-size: 0.7rem;">12/12 Bulan</span>
                                                        @else
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace" style="font-size: 0.7rem;">{{ $count }}/12 Bulan</span>
                                                        @endif
                                                    </div>
                                                    @if ($v->deskripsi)
                                                        <small class="text-muted d-block text-truncate mt-0.5" style="max-width: 240px;" title="{{ $v->deskripsi }}">{{ $v->deskripsi }}</small>
                                                    @endif
                                                    <div class="mt-1 d-flex flex-wrap align-items-center gap-1">
                                                        @forelse ($v->indikatorKinerja as $ind)
                                                            <a href="{{ route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator', 'q' => $ind->kode]) }}" 
                                                               class="badge bg-light text-primary border text-decoration-none small" 
                                                               title="Lihat indikator {{ $ind->nama }} di Tab 1">
                                                                <i class="bi bi-speedometer2 me-1"></i>{{ $ind->nama }}
                                                            </a>
                                                        @empty
                                                            <span class="badge bg-light text-muted border small">Belum dipetakan ke indikator</span>
                                                        @endforelse
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 fw-semibold">
                                                        {{ $v->satuan?->value ?? ($v->satuan ?? '-') }}
                                                    </span>
                                                </td>
                                                @for ($b = 1; $b <= 12; $b++)
                                                    @php
                                                        $rec = $dataMap->get($b);
                                                        $val = $rec ? $rec->nilai : null;
                                                        $isThisColMonth = $isCurrentYear && ($b === $currentMonth);
                                                    @endphp
                                                    <td class="p-1 col-month-{{ $b }} {{ $isThisColMonth ? 'bg-primary-subtle bg-opacity-25 border-primary border-start border-end' : '' }}">
                                                        <input type="number" 
                                                               step="any" 
                                                               name="data[{{ $v->id }}][{{ $b }}]" 
                                                               value="{{ $val !== null ? (float) $val : '' }}" 
                                                               class="form-control form-control-sm text-end matrix-input p-1" 
                                                               data-row-id="{{ $v->id }}"
                                                               data-row="{{ $v->id }}"
                                                               data-row-idx="{{ $rowIdx }}" 
                                                               data-col-idx="{{ $b }}"
                                                               placeholder="-">
                                                    </td>
                                                @endfor
                                                <td class="text-end fw-bold font-monospace bg-light p-2" id="avg-{{ $v->id }}">
                                                    {{ $initialAvg !== null ? number_format($initialAvg, 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="text-center align-middle text-nowrap p-1 sticky-col-action">
                                                    @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'update'))
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-primary py-0.5 px-2 btn-edit-variabel" 
                                                                title="Edit Variabel Manual"
                                                                data-id="{{ $v->id }}"
                                                                data-kode="{{ $v->kode }}"
                                                                data-nama="{{ $v->nama }}"
                                                                data-satuan="{{ $v->satuan?->value ?? (string) $v->satuan }}"
                                                                data-deskripsi="{{ $v->deskripsi }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    @endif
                                                    @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'delete'))
                                                        @if ($v->indikatorKinerja->isNotEmpty())
                                                            @php
                                                                $usedInList = $v->indikatorKinerja->pluck('nama')->join(', ');
                                                            @endphp
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-secondary py-0.5 px-2 ms-1 btn-delete-variabel disabled" 
                                                                    disabled 
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Tidak dapat dihapus karena masih digunakan oleh indikator: {{ $usedInList }}">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        @else
                                                            <form action="{{ route('pengaturan.setting-indikator-kinerja.destroy-variabel', $v->id) }}" 
                                                                  method="POST" 
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus variabel manual \'{{ e($v->nama) }}\' beserta seluruh riwayat datanya?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="tahun" value="{{ $tahun }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2 ms-1 btn-delete-variabel" title="Hapus Variabel">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="17" class="text-center py-5 text-muted">
                                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                    Belum ada variabel manual yang didaftarkan. Klik tombol <strong>+ Tambah Variabel Baru</strong> di atas untuk menambahkan variabel seperti BAHV BPJS atau SDM.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <!-- Resizable Drag Handle Bar -->
                            <div id="matrixResizeHandle" class="table-resize-handle text-center py-1 bg-light border-bottom border-start border-end rounded-bottom user-select-none" style="cursor: row-resize;" title="Tarik ke atas atau ke bawah untuk mengatur tinggi tabel">
                                <i class="bi bi-grip-horizontal text-muted fs-6"></i>
                            </div>
                        </div>

                        @if ($variabelManuals->isNotEmpty() && auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'update'))
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-success fw-bold px-4 shadow-xs">
                                    <i class="bi bi-save me-1.5"></i> Simpan Data Bulanan
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Tambah Variabel Manual -->
<div class="modal fade" id="modalTambahVariabel" tabindex="-1" aria-labelledby="modalTambahVariabelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0 overflow-hidden">
            <form method="POST" action="{{ route('pengaturan.setting-indikator-kinerja.store-variabel') }}">
                @csrf
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalTambahVariabelLabel">
                        <i class="bi bi-plus-circle me-1.5 text-success"></i> Tambah Variabel Manual Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="varKode" class="form-label small fw-semibold">Kode Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="kode" id="varKode" class="form-control form-control-sm font-monospace text-uppercase" required placeholder="Contoh: JML_PASIEN_BPJS" value="{{ old('kode') }}">
                        <div class="form-text small">Kode unik huruf kapital & underscore untuk identifikasi formula.</div>
                    </div>
                    <div class="mb-3">
                        <label for="varNama" class="form-label small fw-semibold">Nama Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="varNama" class="form-control form-control-sm" required placeholder="Contoh: Jumlah Pasien BPJS Rawat Inap" value="{{ old('nama') }}">
                    </div>
                    <div class="mb-3">
                        <label for="varSatuan" class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                        <select name="satuan" id="varSatuan" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Satuan --</option>
                            @foreach ($satuanOptions as $satuan)
                                <option value="{{ $satuan->value }}" @selected(old('satuan') === $satuan->value)>
                                    {{ $satuan->label() }} ({{ $satuan->value }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="varDeskripsi" class="form-label small fw-semibold">Deskripsi / Catatan Operasional</label>
                        <textarea name="deskripsi" id="varDeskripsi" rows="3" class="form-control form-control-sm" placeholder="Penjelasan singkat mengenai sumber data fisik (misal: Berita Acara Rekapitulasi BPJS)...">{{ old('deskripsi') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2 border-top">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                        <i class="bi bi-check-circle me-1"></i> Simpan Variabel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Variabel Manual -->
<div class="modal fade" id="modalEditVariabel" tabindex="-1" aria-labelledby="modalEditVariabelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0 overflow-hidden">
            <form id="formEditVariabel" method="POST" action="">
                @csrf
                @method('PUT')
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalEditVariabelLabel">
                        <i class="bi bi-pencil-square me-1.5 text-primary"></i> Edit Variabel Manual
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editVarKode" class="form-label small fw-semibold">Kode Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="kode" id="editVarKode" class="form-control form-control-sm font-monospace text-uppercase" required placeholder="Contoh: JML_PASIEN_BPJS">
                        <div class="form-text small">Kode unik huruf kapital & underscore untuk identifikasi formula.</div>
                    </div>
                    <div class="mb-3">
                        <label for="editVarNama" class="form-label small fw-semibold">Nama Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="editVarNama" class="form-control form-control-sm" required placeholder="Contoh: Jumlah Pasien BPJS Rawat Inap">
                    </div>
                    <div class="mb-3">
                        <label for="editVarSatuan" class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                        <select name="satuan" id="editVarSatuan" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Satuan --</option>
                            @foreach ($satuanOptions as $satuan)
                                <option value="{{ $satuan->value }}">
                                    {{ $satuan->label() }} ({{ $satuan->value }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="editVarDeskripsi" class="form-label small fw-semibold">Deskripsi / Catatan Operasional</label>
                        <textarea name="deskripsi" id="editVarDeskripsi" rows="3" class="form-control form-control-sm" placeholder="Penjelasan singkat mengenai sumber data fisik..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2 border-top">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                        <i class="bi bi-check-circle me-1"></i> Perbarui Variabel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('pengaturan.setting-indikator-kinerja.partials.modal-panduan')
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Bootstrap Popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        const popoverList = popoverTriggerList.map(function (el) {
            return new bootstrap.Popover(el, {
                sanitize: false,
                trigger: 'click'
            });
        });

        // Close popovers on click outside
        document.addEventListener('click', function (e) {
            const isPopoverTrigger = e.target.closest('[data-bs-toggle="popover"]');
            const isInsidePopover = e.target.closest('.popover');

            if (!isPopoverTrigger && !isInsidePopover) {
                popoverList.forEach(function (p) {
                    p.hide();
                });
            } else if (isPopoverTrigger) {
                popoverList.forEach(function (p) {
                    if (p._element !== isPopoverTrigger) {
                        p.hide();
                    }
                });
            }
        });

        // Initialize Bootstrap Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        // Tab 2: Resizable Matrix Container with LocalStorage persistence
        const matrixContainer = document.getElementById('matrixScrollContainer');
        const matrixResizer = document.getElementById('matrixResizeHandle');

        if (matrixContainer && matrixResizer) {
            const savedHeight = localStorage.getItem('setting_indikator_matrix_height');
            if (savedHeight) {
                matrixContainer.style.height = savedHeight;
            }

            let startY = 0;
            let startHeight = 0;

            const onMouseMove = function (e) {
                const dy = e.clientY - startY;
                const minH = 220;
                const maxH = Math.floor(window.innerHeight * 0.8);
                let newHeight = startHeight + dy;
                if (newHeight < minH) newHeight = minH;
                if (newHeight > maxH) newHeight = maxH;
                matrixContainer.style.height = newHeight + 'px';
            };

            const onMouseUp = function () {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                document.body.classList.remove('matrix-resizing');
                localStorage.setItem('setting_indikator_matrix_height', matrixContainer.style.height);
            };

            matrixResizer.addEventListener('mousedown', function (e) {
                startY = e.clientY;
                startHeight = parseInt(window.getComputedStyle(matrixContainer).height, 10);
                document.body.classList.add('matrix-resizing');
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
                e.preventDefault();
            });
        }

        // Tab 2: Excel-like Keyboard Nav & Crosshair Highlight
        const matrixInputs = document.querySelectorAll('.matrix-input');
        matrixInputs.forEach(function (inp) {
            inp.addEventListener('keydown', function (e) {
                const rowIdx = parseInt(this.getAttribute('data-row-idx'), 10);
                const colIdx = parseInt(this.getAttribute('data-col-idx'), 10);
                let targetInput = null;

                if (['ArrowUp', 'ArrowDown', 'Enter'].includes(e.key)) {
                    e.preventDefault();
                } else if ((e.key === 'ArrowRight' && colIdx < 12) || (e.key === 'ArrowLeft' && colIdx > 1)) {
                    e.preventDefault();
                }

                if (e.key === 'ArrowRight') {
                    if (colIdx < 12) {
                        targetInput = document.querySelector(`.matrix-input[data-row-idx="${rowIdx}"][data-col-idx="${colIdx + 1}"]`);
                    }
                } else if (e.key === 'ArrowLeft') {
                    if (colIdx > 1) {
                        targetInput = document.querySelector(`.matrix-input[data-row-idx="${rowIdx}"][data-col-idx="${colIdx - 1}"]`);
                    }
                } else if (e.key === 'ArrowDown' || e.key === 'Enter') {
                    targetInput = document.querySelector(`.matrix-input[data-row-idx="${rowIdx + 1}"][data-col-idx="${colIdx}"]`);
                } else if (e.key === 'ArrowUp') {
                    if (rowIdx > 0) {
                        targetInput = document.querySelector(`.matrix-input[data-row-idx="${rowIdx - 1}"][data-col-idx="${colIdx}"]`);
                    }
                }

                if (targetInput) {
                    targetInput.focus();
                    targetInput.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            });

            // Crosshair highlight
            inp.addEventListener('focus', function () {
                const row = this.closest('tr');
                if (row) row.classList.add('cell-active-row');
                const colIdx = this.getAttribute('data-col-idx');
                document.querySelectorAll(`.col-month-${colIdx}`).forEach(function (c) {
                    c.classList.add('cell-active-col');
                });
            });

            inp.addEventListener('blur', function () {
                const row = this.closest('tr');
                if (row) row.classList.remove('cell-active-row');
                const colIdx = this.getAttribute('data-col-idx');
                document.querySelectorAll(`.col-month-${colIdx}`).forEach(function (c) {
                    c.classList.remove('cell-active-col');
                });
            });
        });

        // Tab 2: Edit Variabel Manual Modal Handler
        const modalEditEl = document.getElementById('modalEditVariabel');
        if (modalEditEl) {
            const modalEdit = new bootstrap.Modal(modalEditEl);
            document.querySelectorAll('.btn-edit-variabel').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const kode = this.getAttribute('data-kode');
                    const nama = this.getAttribute('data-nama');
                    const satuan = this.getAttribute('data-satuan');
                    const deskripsi = this.getAttribute('data-deskripsi') || '';

                    const form = document.getElementById('formEditVariabel');
                    form.action = '{{ url('/pengaturan/setting-indikator-kinerja/variabel') }}/' + id;

                    document.getElementById('editVarKode').value = kode;
                    document.getElementById('editVarNama').value = nama;
                    document.getElementById('editVarSatuan').value = satuan;
                    document.getElementById('editVarDeskripsi').value = deskripsi;

                    modalEdit.show();
                });
            });
        }

        // Tab 2: Deep-link Focus & Flash Highlight
        const urlParams = new URLSearchParams(window.location.search);
        const focusVar = urlParams.get('focus_var');
        if (focusVar) {
            const targetRow = document.getElementById('var-row-' + focusVar);
            if (targetRow) {
                targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                targetRow.classList.add('row-flash-highlight');
                setTimeout(function () {
                    targetRow.classList.remove('row-flash-highlight');
                }, 2000);
            }
        }

        function recalculateRowAverage(rowId) {
            const inputs = document.querySelectorAll('.matrix-input[data-row="' + rowId + '"]');
            let sum = 0;
            let count = 0;
            inputs.forEach(function (inp) {
                const val = inp.value.trim();
                if (val !== '' && !isNaN(val)) {
                    sum += parseFloat(val);
                    count++;
                }
            });

            const avgCell = document.getElementById('avg-' + rowId);
            if (avgCell) {
                if (count > 0) {
                    const avg = sum / count;
                    avgCell.textContent = avg.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    avgCell.textContent = '-';
                }
            }
        }

        document.querySelectorAll('.matrix-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                const rowId = this.getAttribute('data-row');
                recalculateRowAverage(rowId);
            });
        });
    });
</script>
@endpush
