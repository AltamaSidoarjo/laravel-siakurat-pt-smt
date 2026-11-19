@extends('layouts.app')

@section('title', 'Dashboard Indikator Kinerja')

@push('styles')
<style>
    .kpi-executive-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        cursor: pointer;
    }
    .kpi-executive-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.65rem 1.5rem rgba(15, 23, 42, 0.1) !important;
    }
    .kpi-executive-card:hover .kpi-card-title {
        color: var(--bs-primary) !important;
    }
    .kpi-line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .status-dot-pulse {
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7);
        animation: statusPulse 2s infinite;
    }
    @keyframes statusPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
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

    $formatNominalExplisit = function ($nilai, $satuan = '', $basis = null) {
        if ($nilai === null) {
            return '-';
        }
        $num = (float) $nilai;
        $decimals = (abs($num - round($num)) > 0.00001) ? 2 : 0;
        $formatted = number_format($num, $decimals, ',', '.');

        $basisVal = $basis instanceof \BackedEnum ? $basis->value : (string) $basis;
        $satuanStr = $satuan instanceof \BackedEnum ? $satuan->value : (string) $satuan;

        if ($basisVal === 'COA' || $basisVal === 'SURPLUS' || $basisVal === 'RBA') {
            return 'Rp ' . $formatted;
        }
        if ($satuanStr === 'Rp' || $satuanStr === 'Juta Rp') {
            return 'Rp ' . $formatted;
        }
        if ($satuanStr === 'Orang' || $satuanStr === 'Pasien' || $satuanStr === 'TT' || $satuanStr === 'Dokumen') {
            return $formatted . ' ' . $satuanStr;
        }
        if ($basisVal === 'MANUAL') {
            return $formatted;
        }

        return 'Rp ' . $formatted;
    };

    $getBasisLabel = function ($basis, $variabelManual = null, $peran = 'pembilang') {
        $basisVal = $basis instanceof \BackedEnum ? $basis->value : (string) $basis;
        if ($peran === 'pembilang') {
            return match ($basisVal) {
                'COA' => 'Saldo COA',
                'SURPLUS' => 'Surplus Defisit',
                'NONE' => 'Non-Akuntansi',
                default => 'Saldo COA',
            };
        }

        return match ($basisVal) {
            'COA' => 'Saldo COA',
            'RBA' => 'Target RBA',
            'MANUAL' => $variabelManual ? 'Manual: '.$variabelManual->nama : 'Variabel Manual',
            'NONE' => 'Non-Akuntansi',
            default => 'Penyebut',
        };
    };

    $getPengaliLabel = function ($tipe, $satuan, $daysCount) {
        $tipeVal = $tipe instanceof \BackedEnum ? $tipe->value : (string) $tipe;
        $satuanVal = $satuan instanceof \BackedEnum ? $satuan->value : (string) $satuan;

        if ($tipeVal === 'TURNOVER' || strtolower($satuanVal) === 'hari') {
            return "× {$daysCount} Hari";
        }
        if ($satuanVal === '%') {
            return '× 100%';
        }

        return '';
    };

    $totalCount = $ringkasanStatus['total'] ?? 0;
    $pctHijau = $totalCount > 0 ? round((($ringkasanStatus['hijau'] ?? 0) / $totalCount) * 100) : 0;
    $pctKuning = $totalCount > 0 ? round((($ringkasanStatus['kuning'] ?? 0) / $totalCount) * 100) : 0;
    $pctMerah = $totalCount > 0 ? round((($ringkasanStatus['merah'] ?? 0) / $totalCount) * 100) : 0;

    $daysCount = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
    $periodeText = \Carbon\Carbon::parse($startDate)->format('d M Y') . ' s.d. ' . \Carbon\Carbon::parse($endDate)->format('d M Y') . " ({$daysCount} Hari)";
@endphp

@section('content')
<div class="d-flex flex-column gap-3">

    <!-- Header Page: Executive Identity -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-3 bg-success text-white p-2 d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 42px; height: 42px;">
                        <i class="bi bi-activity fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark">Dashboard Indikator Kinerja</h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.7rem;">Eksekutif</span>
                        </div>
                        <p class="text-muted mb-0 mt-0.5" style="max-width: 480px; font-size: 0.78rem; line-height: 1.35;">
                            Monitoring capaian kinerja keuangan & operasional rumah sakit berbasis standar evaluasi Red, Amber, Green (RAG).
                        </p>
                    </div>
                </div>

                <div class="d-flex align-items-center flex-nowrap gap-2 flex-shrink-0 ms-lg-auto">
                    <div class="d-flex align-items-center bg-light px-2.5 py-1.5 rounded-3 border small">
                        <span class="status-dot bg-success status-dot-pulse me-2"></span>
                        <span class="text-secondary me-1">Periode:</span>
                        <strong class="text-dark">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong>
                        <span class="text-muted mx-1">s/d</span>
                        <strong class="text-dark">{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-2">{{ $daysCount }} Hari Riil</span>
                    </div>

                    @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'view'))
                        <a href="{{ route('pengaturan.setting-indikator-kinerja.index') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1.5 shadow-xs text-nowrap" title="Buka Pengaturan Indikator">
                            <i class="bi bi-sliders text-success"></i>
                            <span>Setting Indikator</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Executive Summary Status Strip -->
    <div class="row g-3">
        <!-- Total Indikator -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-primary">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Total Indikator</div>
                        <h2 class="mb-0 fw-bold text-dark mt-1">{{ $totalCount }}</h2>
                        <div class="small text-muted mt-1">Metrik Terpantau</div>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="bi bi-bar-chart-line-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hijau (Baik) -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-success">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-success small fw-semibold text-uppercase tracking-wider">Status Hijau (Baik)</div>
                        <h2 class="mb-0 fw-bold text-success mt-1">{{ $ringkasanStatus['hijau'] ?? 0 }}</h2>
                        <div class="small text-success fw-medium mt-1">{{ $pctHijau }}% Memenuhi Target</div>
                    </div>
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="bi bi-check-circle-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kuning (Waspada) -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-warning">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-warning-emphasis small fw-semibold text-uppercase tracking-wider">Status Kuning (Waspada)</div>
                        <h2 class="mb-0 fw-bold text-warning-emphasis mt-1">{{ $ringkasanStatus['kuning'] ?? 0 }}</h2>
                        <div class="small text-warning-emphasis fw-medium mt-1">{{ $pctKuning }}% Perlu Perhatian</div>
                    </div>
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Merah (Kritis) -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-4 border-danger">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small fw-semibold text-uppercase tracking-wider">Status Merah (Kritis)</div>
                        <h2 class="mb-0 fw-bold text-danger mt-1">{{ $ringkasanStatus['merah'] ?? 0 }}</h2>
                        <div class="small text-danger fw-medium mt-1">{{ $pctMerah }}% Evaluasi Diperlukan</div>
                    </div>
                    <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="bi bi-x-circle-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (($ringkasanStatus['netral'] ?? 0) > 0)
        <div class="alert alert-secondary border-0 shadow-sm py-2 px-3 mb-0 small d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-secondary flex-shrink-0"></i>
            <div>
                Terdapat <strong>{{ $ringkasanStatus['netral'] }}</strong> indikator berstatus netral / informasi (belum memiliki parameter target RAG lengkap atau data manual belum terisi).
            </div>
        </div>
    @endif

    <!-- Toolbar: Server-Side Filters & View Modes -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('dashboard.indikator-kinerja.index') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <!-- Tanggal Mulai -->
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="dariTanggal" class="form-label small fw-semibold text-muted mb-1">
                            <i class="bi bi-calendar-event me-1"></i> Tanggal Mulai
                        </label>
                        <input type="date" class="form-control form-control-sm" id="dariTanggal" name="dariTanggal" value="{{ $startDate }}">
                    </div>

                    <!-- Tanggal Selesai -->
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="sampaiTanggal" class="form-label small fw-semibold text-muted mb-1">
                            <i class="bi bi-calendar-check me-1"></i> Tanggal Selesai
                        </label>
                        <input type="date" class="form-control form-control-sm" id="sampaiTanggal" name="sampaiTanggal" value="{{ $endDate }}">
                    </div>

                    <!-- Search Input -->
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="q" class="form-label small fw-semibold text-muted mb-1">
                            <i class="bi bi-search me-1"></i> Cari Indikator
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="q" name="q" value="{{ $q }}" placeholder="Nama atau kode metrik...">
                        </div>
                    </div>

                    <!-- Status RAG Filter -->
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="statusSelect" class="form-label small fw-semibold text-muted mb-1">
                            <i class="bi bi-funnel me-1"></i> Status RAG
                        </label>
                        <select class="form-select form-select-sm" id="statusSelect" name="status">
                            <option value="ALL" @selected(($statusFilter ?? 'ALL') === 'ALL')>Semua Status</option>
                            <option value="HIJAU" @selected(($statusFilter ?? '') === 'HIJAU')>🟢 Hijau (Baik)</option>
                            <option value="KUNING" @selected(($statusFilter ?? '') === 'KUNING')>🟡 Kuning (Waspada)</option>
                            <option value="MERAH" @selected(($statusFilter ?? '') === 'MERAH')>🔴 Merah (Kritis)</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-12 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill fw-semibold">
                            <i class="bi bi-filter me-1"></i> Terapkan
                        </button>
                        <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-sm btn-light border flex-fill text-center fw-semibold">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Card with Mode Toggle -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark fs-6">Daftar Indikator Terpantau</h5>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                    {{ $indikatorList->count() }} Indikator
                </span>
            </div>

            <!-- View Switcher Segmented Buttons -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Pilihan Tampilan">
                <button type="button" class="btn btn-outline-primary active" id="btnToggleCards" title="Tampilkan Mode Kartu Eksekutif">
                    <i class="bi bi-grid-fill me-1"></i> Kartu Eksekutif
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnToggleTable" title="Tampilkan Mode Tabel Rinci">
                    <i class="bi bi-table me-1"></i> Tabel Rinci
                </button>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            @if ($indikatorList->isEmpty())
                <div class="text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-search fs-1"></i>
                    </div>
                    <h5 class="text-muted fw-bold">Tidak ada indikator yang sesuai dengan filter pencarian.</h5>
                    <p class="text-muted small mb-3">Coba gunakan kata kunci pencarian yang lain atau sesuaikan filter status RAG & tanggal.</p>
                    <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
                    </a>
                </div>
            @else

                <!-- ============================================== -->
                <!-- VIEW 1: EXECUTIVE CARDS GRID                   -->
                <!-- ============================================== -->
                <div id="viewModeCards">
                    <div class="row g-3 g-lg-4">
                        @foreach ($indikatorList as $item)
                            @php
                                $ind = $item['indikator'];
                                $statusEnum = $item['status'];
                                $badgeClass = $statusEnum->badgeClass();
                                $satuanStr = $item['satuan'] ?? '';
                                $targetHijauText = $formatBand($ind->target_hijau_min, $ind->target_hijau_max, $satuanStr, $ind->pola_evaluasi);
                                $targetKuningText = $formatBand($ind->target_kuning_min, $ind->target_kuning_max, $satuanStr, $ind->pola_evaluasi);
                                $targetMerahText = $formatBand($ind->target_merah_min, $ind->target_merah_max, $satuanStr, $ind->pola_evaluasi);
                                $polaLabel = $ind->pola_evaluasi ? $ind->pola_evaluasi->label() : '-';
                                $tipeKalkulasiLabel = $ind->tipe_kalkulasi ? $ind->tipe_kalkulasi->label() : '-';
                                $tipeVal = $ind->tipe_kalkulasi instanceof \BackedEnum ? $ind->tipe_kalkulasi->value : (string) ($ind->tipe_kalkulasi?->value ?? $ind->tipe_kalkulasi);

                                $pembilangVal = $item['pembilang'];
                                $penyebutVal = $item['penyebut'];
                                $pembilangFormatted = $formatNominalExplisit($pembilangVal, $satuanStr, $ind->basis_pembilang);
                                $penyebutFormatted = $formatNominalExplisit($penyebutVal, $satuanStr, $ind->basis_penyebut);
                                $pembilangLabel = $getBasisLabel($ind->basis_pembilang, null, 'pembilang');
                                $penyebutLabel = $getBasisLabel($ind->basis_penyebut, $ind->variabelManual, 'penyebut');
                                $pengaliLabel = $getPengaliLabel($ind->tipe_kalkulasi, $satuanStr, $daysCount);

                                // Color accents matching RAG
                                $borderAccent = match($badgeClass) {
                                    'success' => 'border-success',
                                    'warning' => 'border-warning',
                                    'danger' => 'border-danger',
                                    default => 'border-secondary',
                                };
                                $bgSubtle = match($badgeClass) {
                                    'success' => 'bg-success-subtle text-success border-success-subtle',
                                    'warning' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                    'danger' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    default => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                };
                            @endphp

                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100 border-0 shadow-sm border-start border-4 {{ $borderAccent }} kpi-executive-card btn-detail-tren"
                                     data-kode="{{ $item['kode'] }}"
                                     data-nama="{{ $item['nama'] }}"
                                     data-urutan="{{ $ind->urutan }}"
                                     data-satuan="{{ $item['satuan'] ?? '' }}"
                                     data-nilai="{{ $item['nilai_formatted'] }}"
                                     data-status-label="{{ $statusEnum->label() }}"
                                     data-status-badge="{{ $badgeClass }}"
                                     data-definisi="{{ $ind->definisi_operasional }}"
                                     data-pola="{{ $polaLabel }}"
                                     data-tipe="{{ $tipeVal }}"
                                     data-tipe-label="{{ $tipeKalkulasiLabel }}"
                                     data-pembilang-val="{{ $pembilangVal }}"
                                     data-pembilang-formatted="{{ $pembilangFormatted }}"
                                     data-penyebut-val="{{ $penyebutVal }}"
                                     data-penyebut-formatted="{{ $penyebutFormatted }}"
                                     data-pembilang-label="{{ $pembilangLabel }}"
                                     data-penyebut-label="{{ $penyebutLabel }}"
                                     data-pengali-label="{{ $pengaliLabel }}"
                                     data-periode-text="{{ $periodeText }}"
                                     data-hari-riil="{{ $daysCount }}"
                                     data-catatan="{{ $item['catatan'] ?? '' }}"
                                     data-target-hijau="{{ $targetHijauText }}"
                                     data-target-kuning="{{ $targetKuningText }}"
                                     data-target-merah="{{ $targetMerahText }}">

                                    <!-- Top Meta Bar -->
                                    <div class="card-body p-4 pb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center gap-1.5">
                                                <span class="badge bg-light text-muted border font-monospace">#{{ $ind->urutan }}</span>
                                                <span class="small text-muted font-monospace">{{ $item['kode'] }}</span>
                                            </div>

                                            <span class="badge {{ $bgSubtle }} border px-2.5 py-1 rounded-pill fw-semibold d-inline-flex align-items-center">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                                {{ $statusEnum->label() }}
                                            </span>
                                        </div>

                                        <!-- Title -->
                                        <h6 class="fw-bold text-dark mb-1 kpi-card-title text-truncate" title="{{ $item['nama'] }}">
                                            {{ $item['nama'] }}
                                        </h6>

                                        <!-- Formula / Operational Definition -->
                                        <div class="text-muted small kpi-line-clamp-2 mb-2" style="min-height: 38px;" title="{{ $ind->definisi_operasional }}">
                                            <i class="bi bi-info-circle me-1 text-muted"></i>
                                            {{ $ind->definisi_operasional ?: 'Formula kalkulasi berdasarkan konfigurasi indikator.' }}
                                        </div>

                                        <!-- Visualisasi Formula Badge -->
                                        <div class="d-flex align-items-center flex-wrap gap-1 mb-3 py-1 px-2 bg-light rounded border border-light-subtle font-monospace" style="font-size: 0.72rem;">
                                            @if ($tipeVal === 'MANUAL')
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle">
                                                    <i class="bi bi-pencil-square me-1"></i>Manual: {{ $ind->variabelManual?->nama ?? 'Data Manual' }}
                                                </span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $pembilangLabel }}</span>
                                                <span class="text-muted fw-bold">÷</span>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">{{ $penyebutLabel }}</span>
                                                @if ($pengaliLabel)
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ $pengaliLabel }}</span>
                                                @endif
                                            @endif
                                        </div>

                                        <!-- Realization Value & Target Benchmark Pill -->
                                        <div class="pt-3 border-top d-flex align-items-baseline justify-content-between">
                                            <div>
                                                <div class="text-muted small" style="font-size: 0.75rem;">Nilai Realisasi:</div>
                                                <div class="fs-2 fw-bolder text-dark tracking-tight">
                                                    {{ $item['nilai_formatted'] }}
                                                </div>
                                            </div>

                                            <div class="text-end">
                                                <div class="text-muted small" style="font-size: 0.75rem;">Target Hijau:</div>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold mt-0.5">
                                                    {{ $targetHijauText }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Footer Note & Detail Affordance -->
                                    <div class="card-footer bg-light border-top py-2 px-4 d-flex align-items-center justify-content-between">
                                        <span class="text-muted small text-truncate pe-2" style="max-width: 200px;" title="{{ $item['catatan'] }}">
                                            {{ $item['catatan'] ?: 'Kinerja terpantau aktif' }}
                                        </span>
                                        <span class="text-primary small fw-bold d-inline-flex align-items-center">
                                            Detail & Tren <i class="bi bi-chevron-right ms-1"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- VIEW 2: DETAILED TABLE VIEW                    -->
                <!-- ============================================== -->
                <div id="viewModeTable" class="d-none">
                    <div class="table-responsive border rounded-3 shadow-xs">
                        <table class="table table-hover align-middle mb-0 text-sm w-100">
                            <thead class="table-light border-bottom">
                                <tr class="small text-uppercase tracking-wider text-secondary">
                                    <th scope="col" class="text-center py-2.5" style="width: 4%;">No</th>
                                    <th scope="col" class="py-2.5" style="width: 22%;">Indikator Kinerja</th>
                                    <th scope="col" class="py-2.5" style="width: 28%;">Definisi Operasional (Rumus)</th>
                                    <th scope="col" class="text-end py-2.5" style="width: 12%;">Nilai Realisasi</th>
                                    <th scope="col" class="text-center py-2.5" style="width: 12%;">Status RAG</th>
                                    <th scope="col" class="py-2.5" style="width: 14%;">Ambang Batas Target</th>
                                    <th scope="col" class="text-center py-2.5" style="width: 8%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($indikatorList as $item)
                                    @php
                                        $ind = $item['indikator'];
                                        $statusEnum = $item['status'];
                                        $badgeClass = $statusEnum->badgeClass();
                                        $satuanStr = $item['satuan'] ?? '';
                                        $targetHijauText = $formatBand($ind->target_hijau_min, $ind->target_hijau_max, $satuanStr, $ind->pola_evaluasi);
                                        $targetKuningText = $formatBand($ind->target_kuning_min, $ind->target_kuning_max, $satuanStr, $ind->pola_evaluasi);
                                        $targetMerahText = $formatBand($ind->target_merah_min, $ind->target_merah_max, $satuanStr, $ind->pola_evaluasi);
                                        $polaLabel = $ind->pola_evaluasi ? $ind->pola_evaluasi->label() : '-';
                                        $tipeKalkulasiLabel = $ind->tipe_kalkulasi ? $ind->tipe_kalkulasi->label() : '-';
                                        $tipeVal = $ind->tipe_kalkulasi instanceof \BackedEnum ? $ind->tipe_kalkulasi->value : (string) ($ind->tipe_kalkulasi?->value ?? $ind->tipe_kalkulasi);

                                        $pembilangVal = $item['pembilang'];
                                        $penyebutVal = $item['penyebut'];
                                        $pembilangFormatted = $formatNominalExplisit($pembilangVal, $satuanStr, $ind->basis_pembilang);
                                        $penyebutFormatted = $formatNominalExplisit($penyebutVal, $satuanStr, $ind->basis_penyebut);
                                        $pembilangLabel = $getBasisLabel($ind->basis_pembilang, null, 'pembilang');
                                        $penyebutLabel = $getBasisLabel($ind->basis_penyebut, $ind->variabelManual, 'penyebut');
                                        $pengaliLabel = $getPengaliLabel($ind->tipe_kalkulasi, $satuanStr, $daysCount);

                                        $bgSubtle = match($badgeClass) {
                                            'success' => 'bg-success-subtle text-success border-success-subtle',
                                            'warning' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                            'danger' => 'bg-danger-subtle text-danger border-danger-subtle',
                                            default => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-center align-middle text-muted fw-bold">{{ $loop->iteration }}</td>
                                        <td class="align-middle">
                                            <div class="fw-bold text-dark">{{ $item['nama'] }}</div>
                                            <div class="small text-muted font-monospace">#{{ $ind->urutan }} • {{ $item['kode'] }}</div>
                                        </td>
                                        <td class="align-middle text-muted small">
                                            <div class="d-flex align-items-center flex-wrap gap-1 mb-1 font-monospace" style="font-size: 0.72rem;">
                                                @if ($tipeVal === 'MANUAL')
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-0.5 px-1.5">
                                                        <i class="bi bi-pencil-square me-1"></i>Manual: {{ $ind->variabelManual?->nama ?? 'Data Manual' }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1.5">{{ $pembilangLabel }}</span>
                                                    <span class="text-muted fw-bold">÷</span>
                                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-0.5 px-1.5">{{ $penyebutLabel }}</span>
                                                    @if ($pengaliLabel)
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0.5 px-1.5">{{ $pengaliLabel }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="text-truncate" style="max-width: 320px;" title="{{ $ind->definisi_operasional }}">
                                                {{ $ind->definisi_operasional ?: '-' }}
                                            </div>
                                        </td>
                                        <td class="text-end align-middle fw-bolder fs-6 text-dark font-monospace">
                                            {{ $item['nilai_formatted'] }}
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge {{ $bgSubtle }} border px-2.5 py-1 rounded-pill fw-semibold d-inline-flex align-items-center">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                                {{ $statusEnum->label() }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <div class="d-flex flex-column gap-1 small">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2 text-start font-monospace" style="font-size: 0.72rem;" title="Target Hijau">
                                                    🟢 {{ $targetHijauText }}
                                                </span>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-2 text-start font-monospace" style="font-size: 0.72rem;" title="Target Kuning">
                                                    🟡 {{ $targetKuningText }}
                                                </span>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0.5 px-2 text-start font-monospace" style="font-size: 0.72rem;" title="Target Merah">
                                                    🔴 {{ $targetMerahText }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary fw-semibold btn-detail-tren"
                                                data-kode="{{ $item['kode'] }}"
                                                data-nama="{{ $item['nama'] }}"
                                                data-urutan="{{ $ind->urutan }}"
                                                data-satuan="{{ $item['satuan'] ?? '' }}"
                                                data-nilai="{{ $item['nilai_formatted'] }}"
                                                data-status-label="{{ $statusEnum->label() }}"
                                                data-status-badge="{{ $badgeClass }}"
                                                data-definisi="{{ $ind->definisi_operasional }}"
                                                data-pola="{{ $polaLabel }}"
                                                data-tipe="{{ $tipeVal }}"
                                                data-tipe-label="{{ $tipeKalkulasiLabel }}"
                                                data-pembilang-val="{{ $pembilangVal }}"
                                                data-pembilang-formatted="{{ $pembilangFormatted }}"
                                                data-penyebut-val="{{ $penyebutVal }}"
                                                data-penyebut-formatted="{{ $penyebutFormatted }}"
                                                data-pembilang-label="{{ $pembilangLabel }}"
                                                data-penyebut-label="{{ $penyebutLabel }}"
                                                data-pengali-label="{{ $pengaliLabel }}"
                                                data-periode-text="{{ $periodeText }}"
                                                data-hari-riil="{{ $daysCount }}"
                                                data-catatan="{{ $item['catatan'] ?? '' }}"
                                                data-target-hijau="{{ $targetHijauText }}"
                                                data-target-kuning="{{ $targetKuningText }}"
                                                data-target-merah="{{ $targetMerahText }}"
                                                title="Lihat Detail & Tren Grafis">
                                                <i class="bi bi-graph-up me-1"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @endif
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL DRILL-DOWN: DETAIL & TREN HISTORIS       -->
<!-- ============================================== -->
<div class="modal fade" id="modalDetailTren" tabindex="-1" aria-labelledby="modalDetailTrenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0 overflow-hidden">
            <!-- Modal Header: Clean Executive Style (No Black) -->
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-3">
                    <span id="modalIndicatorNumber" class="badge bg-success-subtle text-success border border-success-subtle font-monospace fw-bold fs-6 px-2.5 py-1.5 rounded-3">1</span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalDetailTrenLabel">Detail Indikator Kinerja</h5>
                        <div class="small text-muted font-monospace mt-0.5" id="modalIndicatorKode">-</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span id="modalIndicatorStatusBadge" class="badge bg-secondary px-3 py-1.5 rounded-pill fw-semibold shadow-xs">-</span>
                    <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-4 space-y-4">
                <!-- Section 1: Explicit Formula Box (Variasi C) -->
                <div class="bg-light p-3 p-md-4 rounded-3 border mb-4" id="modalFormulaBox">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-6 fw-bold text-dark d-flex align-items-center gap-1.5">
                                <i class="bi bi-calculator text-primary"></i>
                                <span>Formula Kalkulasi Riil Periode</span>
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary border font-monospace small" id="modalIndicatorTipe">-</span>
                        </div>
                        <span class="badge bg-white text-dark border font-monospace py-1.5 px-2.5 shadow-xs" id="modalFormulaPeriodeText">
                            -
                        </span>
                    </div>

                    <!-- Equation Math Container -->
                    <div class="d-flex align-items-center justify-content-center p-3 bg-white rounded-3 border shadow-xs mb-3 overflow-x-auto" id="modalFormulaEquationContainer">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>

                    <!-- Legend Box -->
                    <div class="small p-2.5 bg-white rounded-2 border border-light-subtle mb-3" id="modalFormulaLegendContainer">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>

                    <!-- Operational Definition Footer -->
                    <div class="pt-2 border-top">
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 0.7rem;">Definisi Operasional:</span>
                        <div class="text-secondary small fst-italic" id="modalIndicatorDefinisi" style="line-height: 1.5;">
                            -
                        </div>
                    </div>
                </div>

                <!-- Section 2: 3 Benchmark Boxes (Hijau, Kuning, Merah) -->
                <div class="mb-4">
                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-2">Panduan Ambang Batas Target (RAG)</div>
                    <div class="row g-2 text-center small">
                        <div class="col-4">
                            <div class="p-2.5 bg-success-subtle text-success border border-success-subtle rounded-3">
                                <span class="d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Hijau (Baik)</span>
                                <div id="modalTargetHijau" class="fw-bold fs-6 text-dark mt-1">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2.5 bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-3">
                                <span class="d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Kuning (Waspada)</span>
                                <div id="modalTargetKuning" class="fw-bold fs-6 text-dark mt-1">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2.5 bg-danger-subtle text-danger border border-danger-subtle rounded-3">
                                <span class="d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Merah (Kritis)</span>
                                <div id="modalTargetMerah" class="fw-bold fs-6 text-dark mt-1">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Grafik Tren 12 Bulan -->
                <div>
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Tren Historis Realisasi Bulanan</h6>
                            <div class="text-muted small">Pergerakan capaian indikator selama 12 bulan pada tahun anggaran terpilih.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label for="selectTahunTren" class="small text-muted fw-semibold mb-0">Tahun:</label>
                            <select id="selectTahunTren" class="form-select form-select-sm" style="width: 100px;">
                                @php
                                    $currentYear = (int) date('Y');
                                @endphp
                                @for ($y = $currentYear; $y >= $currentYear - 3; $y--)
                                    <option value="{{ $y }}" {{ $y === $currentYear ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <!-- Chart Container -->
                    <div class="position-relative bg-white rounded-3 border p-3" style="min-height: 270px;">
                        <div id="trenChartLoading" class="position-absolute top-50 start-50 translate-middle text-center d-none">
                            <div class="spinner-border text-primary spinner-border-sm" role="status">
                                <span class="visually-hidden">Memuat...</span>
                            </div>
                            <div class="small text-muted mt-2">Memuat tren realisasi...</div>
                        </div>

                        <div id="trenChartError" class="position-absolute top-50 start-50 translate-middle text-center d-none w-75">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                            <div class="small text-danger fw-semibold mt-1">Gagal memuat data tren indikator.</div>
                        </div>

                        <div id="trenChartCanvasWrapper" style="height: 250px;">
                            <canvas id="trenChartCanvas"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-4 border-top">
                <button type="button" class="btn btn-sm btn-secondary fw-semibold px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // 1. View Switcher Logic (Card Grid vs Detailed Table)
    const btnToggleCards = document.getElementById('btnToggleCards');
    const btnToggleTable = document.getElementById('btnToggleTable');
    const viewModeCards = document.getElementById('viewModeCards');
    const viewModeTable = document.getElementById('viewModeTable');

    function setViewMode(mode) {
        if (!viewModeCards || !viewModeTable) return;

        if (mode === 'table') {
            viewModeCards.classList.add('d-none');
            viewModeTable.classList.remove('d-none');
            btnToggleTable?.classList.add('active');
            btnToggleCards?.classList.remove('active');
            try { localStorage.setItem('kpi_dashboard_view', 'table'); } catch (e) {}
        } else {
            viewModeTable.classList.add('d-none');
            viewModeCards.classList.remove('d-none');
            btnToggleCards?.classList.add('active');
            btnToggleTable?.classList.remove('active');
            try { localStorage.setItem('kpi_dashboard_view', 'cards'); } catch (e) {}
        }
    }

    btnToggleCards?.addEventListener('click', function () {
        setViewMode('cards');
    });

    btnToggleTable?.addEventListener('click', function () {
        setViewMode('table');
    });

    try {
        const savedView = localStorage.getItem('kpi_dashboard_view');
        if (savedView === 'table') {
            setViewMode('table');
        }
    } catch (e) {}

    // 2. Modal Drill-down & Chart.js Logic
    let currentKode = null;
    let trenChart = null;

    const modalEl = document.getElementById('modalDetailTren');
    const modalBs = modalEl ? new bootstrap.Modal(modalEl) : null;
    const selectTahun = document.getElementById('selectTahunTren');
    const loadingEl = document.getElementById('trenChartLoading');
    const errorEl = document.getElementById('trenChartError');
    const canvasWrapper = document.getElementById('trenChartCanvasWrapper');
    const canvasEl = document.getElementById('trenChartCanvas');

    // Attach click event to all cards and buttons with .btn-detail-tren
    document.querySelectorAll('.btn-detail-tren').forEach(function (element) {
        element.addEventListener('click', function (e) {
            const data = this.dataset;
            currentKode = data.kode;

            document.getElementById('modalDetailTrenLabel').textContent = data.nama;
            document.getElementById('modalIndicatorNumber').textContent = data.urutan || '#';
            document.getElementById('modalIndicatorKode').textContent = data.kode;
            document.getElementById('modalIndicatorDefinisi').textContent = data.definisi || 'Tidak ada definisi operasional tercatat.';
            document.getElementById('modalIndicatorTipe').textContent = data.tipeLabel || data.tipe || '-';
            document.getElementById('modalFormulaPeriodeText').textContent = data.periodeText || '-';
            document.getElementById('modalTargetHijau').textContent = data.targetHijau || '-';
            document.getElementById('modalTargetKuning').textContent = data.targetKuning || '-';
            document.getElementById('modalTargetMerah').textContent = data.targetMerah || '-';

            const badgeEl = document.getElementById('modalIndicatorStatusBadge');
            const badgeClass = data.statusBadge || 'secondary';
            const isWarning = badgeClass === 'warning';
            badgeEl.className = 'badge px-3 py-1.5 rounded-pill fw-semibold shadow-xs bg-' + badgeClass + (isWarning ? ' text-dark' : ' text-white');
            badgeEl.textContent = data.statusLabel || '-';

            // Dynamic Formula Rendering (Variasi C - Pecahan Matematika Ber-Badge)
            const tipe = (data.tipe || '').toUpperCase();
            const satuan = data.satuan || '';
            const pembilangFormatted = data.pembilangFormatted || '-';
            const penyebutFormatted = data.penyebutFormatted || '-';
            const pembilangLabel = data.pembilangLabel || 'Pembilang';
            const penyebutLabel = data.penyebutLabel || 'Penyebut';
            const pengaliLabel = data.pengaliLabel || '';
            const nilai = data.nilai || '-';
            const catatan = data.catatan || '';

            // Safe escaped variables for DOM insertion
            const safePembilangFormatted = escapeHtml(pembilangFormatted);
            const safePenyebutFormatted = escapeHtml(penyebutFormatted);
            const safePembilangLabel = escapeHtml(pembilangLabel);
            const safePenyebutLabel = escapeHtml(penyebutLabel);
            const safePengaliLabel = escapeHtml(pengaliLabel);
            const safeNilai = escapeHtml(nilai);
            const safeCatatan = escapeHtml(catatan);

            let resultBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
            if (badgeClass === 'success') {
                resultBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
            } else if (badgeClass === 'warning') {
                resultBadgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
            } else if (badgeClass === 'danger') {
                resultBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            }

            const equationEl = document.getElementById('modalFormulaEquationContainer');
            const legendEl = document.getElementById('modalFormulaLegendContainer');

            if (tipe.includes('MANUAL')) {
                const catatanHtml = safeCatatan
                    ? `<div class="mt-2 pt-2 border-top text-muted small" style="font-size: 0.76rem;"><i class="bi bi-clock-history me-1 text-warning"></i>Periode entri data: <strong>${safeCatatan}</strong></div>`
                    : '';

                equationEl.innerHTML = `
                    <div class="d-flex align-items-center flex-wrap justify-content-center gap-2.5 py-1">
                        <span class="text-muted fw-semibold small text-uppercase">Nilai Entri Manual:</span>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle font-monospace px-3 py-2 fs-6 fw-bold">
                            <i class="bi bi-pencil-square me-1"></i>${safePenyebutLabel}: ${safeNilai}
                        </span>
                        <span class="mx-1 text-muted fw-bold">=</span>
                        <span class="badge ${resultBadgeClass} px-3 py-2 fs-6 font-monospace fw-bold shadow-xs" id="modalIndicatorNilai">
                            ${safeNilai}
                        </span>
                    </div>
                `;

                legendEl.innerHTML = `
                    <div class="d-flex align-items-center gap-2 text-muted">
                        <i class="bi bi-info-circle-fill text-warning flex-shrink-0"></i>
                        <span>Data bersumber dari entri manual non-akuntansi (<strong>${safePenyebutLabel}</strong>).</span>
                    </div>
                    ${catatanHtml}
                `;
            } else if (satuan === 'Juta/org/bln') {
                equationEl.innerHTML = `
                    <div class="d-flex align-items-center flex-nowrap justify-content-center gap-2 py-1">
                        <div class="d-inline-flex flex-column align-items-center text-center px-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold font-monospace px-2.5 py-1.5" style="font-size: 0.85rem;" title="${safePembilangLabel}">
                                ${safePembilangFormatted}
                            </span>
                            <div class="w-100 my-1" style="height: 2px; background-color: #94a3b8; min-width: 90px;"></div>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-bold font-monospace px-2.5 py-1.5" style="font-size: 0.85rem;" title="${safePenyebutLabel}">
                                ${safePenyebutFormatted}
                            </span>
                        </div>
                        <span class="fw-bold text-secondary font-monospace small">÷ Bln ÷ 1 Jt</span>
                        <span class="fw-bold text-dark fs-5 mx-1">=</span>
                        <span class="badge ${resultBadgeClass} px-3 py-2 fs-6 font-monospace fw-bold shadow-xs" id="modalIndicatorNilai">
                            ${safeNilai}
                        </span>
                    </div>
                `;

                legendEl.innerHTML = `
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-1.5 py-0.5 mt-0.5 flex-shrink-0">Pembilang</span>
                                <div>
                                    <strong class="text-dark d-block">${safePembilangLabel}</strong>
                                    <span class="text-muted small font-monospace">${safePembilangFormatted}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle font-monospace px-1.5 py-0.5 mt-0.5 flex-shrink-0">Penyebut</span>
                                <div>
                                    <strong class="text-dark d-block">${safePenyebutLabel}</strong>
                                    <span class="text-muted small font-monospace">${safePenyebutFormatted}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top text-muted small" style="font-size: 0.76rem;">
                        <i class="bi bi-info-circle me-1 text-primary"></i>Dihitung sebagai rata-rata pendapatan bulanan dibagi jumlah SDM non-organik dalam satuan juta rupiah.
                    </div>
                `;
            } else {
                const isTurnover = tipe.includes('TURNOVER');
                const pengaliHtml = safePengaliLabel
                    ? `<span class="fw-bold text-secondary font-monospace px-1" style="font-size: 0.9rem;">${safePengaliLabel}</span>`
                    : '';

                equationEl.innerHTML = `
                    <div class="d-flex align-items-center flex-nowrap justify-content-center gap-2 py-1">
                        <div class="d-inline-flex flex-column align-items-center text-center px-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold font-monospace px-2.5 py-1.5" style="font-size: 0.85rem;" title="${safePembilangLabel}">
                                ${safePembilangFormatted}
                            </span>
                            <div class="w-100 my-1" style="height: 2px; background-color: #94a3b8; min-width: 90px;"></div>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-bold font-monospace px-2.5 py-1.5" style="font-size: 0.85rem;" title="${safePenyebutLabel}">
                                ${safePenyebutFormatted}
                            </span>
                        </div>
                        ${pengaliHtml}
                        <span class="fw-bold text-dark fs-5 mx-1">=</span>
                        <span class="badge ${resultBadgeClass} px-3 py-2 fs-6 font-monospace fw-bold shadow-xs" id="modalIndicatorNilai">
                            ${safeNilai}
                        </span>
                    </div>
                `;

                const hariRiil = data.hariRiil || '';
                const hariNote = (isTurnover && hariRiil)
                    ? `<div class="mt-2 pt-2 border-top text-muted small" style="font-size: 0.76rem;"><i class="bi bi-calendar3 me-1 text-primary"></i>Faktor pengali perputaran menggunakan jumlah hari riil periode evaluasi: <strong>${escapeHtml(hariRiil)} Hari</strong>.</div>`
                    : '';

                legendEl.innerHTML = `
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-1.5 py-0.5 mt-0.5 flex-shrink-0">Pembilang</span>
                                <div>
                                    <strong class="text-dark d-block">${safePembilangLabel}</strong>
                                    <span class="text-muted small font-monospace">${safePembilangFormatted}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle font-monospace px-1.5 py-0.5 mt-0.5 flex-shrink-0">Penyebut</span>
                                <div>
                                    <strong class="text-dark d-block">${safePenyebutLabel}</strong>
                                    <span class="text-muted small font-monospace">${safePenyebutFormatted}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${hariNote}
                `;
            }

            modalBs?.show();

            const tahun = selectTahun ? selectTahun.value : new Date().getFullYear();
            fetchTrenData(currentKode, tahun);
        });
    });

    selectTahun?.addEventListener('change', function () {
        if (currentKode) {
            fetchTrenData(currentKode, this.value);
        }
    });

    function fetchTrenData(kode, tahun) {
        if (!kode) return;

        loadingEl?.classList.remove('d-none');
        errorEl?.classList.add('d-none');
        if (canvasWrapper) canvasWrapper.style.opacity = '0.3';

        const url = `{{ url('/dashboard/indikator-kinerja') }}/${encodeURIComponent(kode)}/tren?tahun=${encodeURIComponent(tahun)}`;

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(function (result) {
            loadingEl?.classList.add('d-none');
            if (canvasWrapper) canvasWrapper.style.opacity = '1';
            renderChart(result);
        })
        .catch(function (error) {
            console.error('Error fetching trend:', error);
            loadingEl?.classList.add('d-none');
            errorEl?.classList.remove('d-none');
            if (canvasWrapper) canvasWrapper.style.opacity = '1';
        });
    }

    function renderChart(result) {
        if (!canvasEl) return;

        const dataPoints = result.data || [];
        const labels = dataPoints.map(item => item.label_bulan);
        const values = dataPoints.map(item => item.nilai);
        const statuses = dataPoints.map(item => item.status);
        const satuan = (result.indikator && result.indikator.satuan) ? result.indikator.satuan : '';

        // Generate dot color for each month based on RAG status
        const pointBackgroundColors = statuses.map((status, idx) => {
            if (values[idx] === null) return '#6c757d';
            if (status === 'hijau') return '#198754';
            if (status === 'kuning') return '#ffc107';
            if (status === 'merah') return '#dc3545';
            return '#0d6efd';
        });

        if (trenChart) {
            trenChart.destroy();
        }

        const ctx = canvasEl.getContext('2d');
        trenChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: result.indikator ? result.indikator.nama : 'Nilai',
                    data: values,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.08)',
                    pointBackgroundColor: pointBackgroundColors,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.25,
                    spanGaps: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const idx = context.dataIndex;
                                const val = context.parsed.y;
                                if (val === null || val === undefined || isNaN(val)) {
                                    return 'Belum ada data (bulan mendatang)';
                                }
                                const status = statuses[idx] || 'N/A';
                                return `Nilai: ${val.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${satuan} [${status.toUpperCase()}]`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            callback: function (value) {
                                return value.toLocaleString('id-ID') + (satuan ? ' ' + satuan : '');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
