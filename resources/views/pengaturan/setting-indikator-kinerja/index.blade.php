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

                @if (auth()->user()?->hasModuleAccess('dashboard.indikator-kinerja', 'view'))
                    <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-sm btn-success fw-semibold d-inline-flex align-items-center gap-2 shadow-xs">
                        <i class="bi bi-speedometer2"></i>
                        <span>Buka Dashboard Eksekutif</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    <!-- Informative Banner: Arsitektur Ekstensibel -->
    <div class="alert alert-primary bg-primary-subtle border-primary-subtle text-primary-emphasis d-flex align-items-start gap-3 p-3 shadow-xs mb-0 rounded-3">
        <div class="p-2 bg-white rounded-3 shadow-xs text-primary flex-shrink-0 mt-0.5">
            <i class="bi bi-info-circle-fill fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold mb-1 text-primary-emphasis">Arsitektur Pengaturan Dinamis & Ekstensibel</h6>
            <p class="small mb-0 opacity-90" style="line-height: 1.5;">
                Modul ini tidak terkunci pada 11 indikator awal. Superadmin dapat menambah indikator baru, mengubah rumus dan ambang batas target (RAG), memetakan akun COA sesuai bagan akun rumah sakit, serta mencatat data non-akuntansi bulanan (seperti surat verifikasi BAHV BPJS Kesehatan & SDM).
            </p>
        </div>
    </div>

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
                    <!-- Toolbar Filter Pencarian & Pola Evaluasi -->
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <form method="GET" action="{{ route('pengaturan.setting-indikator-kinerja.index') }}" class="row g-2 align-items-end">
                            <input type="hidden" name="tab" value="indikator">

                            <div class="col-12 col-md-5">
                                <label for="qInput" class="form-label small fw-semibold text-muted mb-1">Cari Indikator</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted border-end-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="qInput" name="q" value="{{ $q ?? '' }}" placeholder="Cari Kode, Nama, atau Formula...">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="polaSelect" class="form-label small fw-semibold text-muted mb-1">Pola Evaluasi Target</label>
                                <select class="form-select form-select-sm" id="polaSelect" name="pola">
                                    <option value="ALL" @selected(($pola ?? 'ALL') === 'ALL')>Semua Pola Evaluasi</option>
                                    @foreach ($polaEvaluasiOptions ?? [] as $polaOpt)
                                        <option value="{{ $polaOpt->value }}" @selected(($pola ?? '') === $polaOpt->value)>
                                            {{ $polaOpt->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-3 d-flex gap-2 justify-content-md-end">
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
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase tracking-wider text-secondary">
                                    <th class="py-2.5 px-3 text-center" style="width: 50px;">Urutan</th>
                                    <th class="py-2.5 px-3" style="width: 120px;">Kode</th>
                                    <th class="py-2.5 px-3" style="min-width: 200px;">Nama Indikator</th>
                                    <th class="py-2.5 px-3" style="min-width: 240px;">Definisi Operasional / Formula</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 120px;">Pola & Satuan</th>
                                    <th class="py-2.5 px-3" style="min-width: 260px;">Ambang Batas Target (RAG)</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 90px;">Status</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 90px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($indikators as $item)
                                    @php
                                        $satuanVal = $item->satuan?->value ?? ($item->satuan ?? '');
                                        $targetHijau = $formatBand($item->target_hijau_min, $item->target_hijau_max, $satuanVal, $item->pola_evaluasi);
                                        $targetKuning = $formatBand($item->target_kuning_min, $item->target_kuning_max, $satuanVal, $item->pola_evaluasi);
                                        $targetMerah = $formatBand($item->target_merah_min, $item->target_merah_max, $satuanVal, $item->pola_evaluasi);
                                        $polaLabel = $item->pola_evaluasi ? $item->pola_evaluasi->label() : '-';
                                        $tipeVal = $item->tipe_kalkulasi instanceof \App\Enums\IndikatorKinerja\TipeKalkulasi
                                            ? $item->tipe_kalkulasi->value
                                            : (string) ($item->tipe_kalkulasi?->value ?? $item->tipe_kalkulasi);
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
                                                    <span class="text-secondary small font-monospace">• {{ $item->mappingCoa->count() }} COA dipetakan</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-middle text-muted small">
                                            <div class="text-truncate" style="max-width: 280px;" title="{{ $item->definisi_operasional }}">
                                                {{ $item->definisi_operasional ?: 'Formula kalkulasi berdasarkan konfigurasi indikator.' }}
                                            </div>
                                            @if ($item->variabelManual)
                                                <div class="small text-info-emphasis mt-1">
                                                    <i class="bi bi-link-45deg me-0.5"></i> Variabel: <strong>{{ $item->variabelManual->nama }}</strong>
                                                </div>
                                            @endif
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
                                            <div class="d-flex flex-column gap-1 small" style="min-width: 260px;">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle text-start py-1 px-2" title="Target Hijau">
                                                    🟢 <strong>Hijau:</strong> {{ $targetHijau }}
                                                </span>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-start py-1 px-2" title="Target Kuning">
                                                    🟡 <strong>Kuning:</strong> {{ $targetKuning }}
                                                </span>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-start py-1 px-2" title="Target Merah">
                                                    🔴 <strong>Merah:</strong> {{ $targetMerah }}
                                                </span>
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
                                        <td class="text-center align-middle text-nowrap">
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

                        <div class="table-responsive border rounded-3 mb-3 custom-scrollbar">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0 text-sm" style="min-width: 1300px;">
                                <thead class="table-light text-center align-middle border-bottom">
                                    <tr>
                                        <th rowspan="2" class="py-2.5 px-2 bg-light text-secondary" style="width: 40px;">No</th>
                                        <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary" style="width: 140px;">Kode</th>
                                        <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary" style="min-width: 220px;">Nama Variabel</th>
                                        <th rowspan="2" class="py-2.5 px-2 bg-light text-secondary" style="width: 90px;">Satuan</th>
                                        <th colspan="12" class="bg-primary-subtle text-primary py-1.5 fw-bold text-uppercase border-bottom" style="letter-spacing: 0.5px;">Bulan Transaksi (Tahun {{ $tahun }})</th>
                                        <th rowspan="2" class="py-2.5 px-3 bg-light text-secondary" style="width: 120px;">Rata-rata Tahunan</th>
                                    </tr>
                                    <tr class="small text-secondary bg-light">
                                        <th class="py-1 px-1" style="min-width: 85px;">Jan</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Feb</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Mar</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Apr</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Mei</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Jun</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Jul</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Agu</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Sep</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Okt</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Nov</th>
                                        <th class="py-1 px-1" style="min-width: 85px;">Des</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($variabelManuals as $v)
                                        @php
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
                                        <tr data-var-id="{{ $v->id }}">
                                            <td class="text-center align-middle text-muted fw-bold">{{ $loop->iteration }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">{{ $v->kode }}</span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="fw-bold text-dark">{{ $v->nama }}</div>
                                                @if ($v->deskripsi)
                                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;" title="{{ $v->deskripsi }}">{{ $v->deskripsi }}</small>
                                                @endif
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
                                                @endphp
                                                <td class="p-1">
                                                    <input type="number" 
                                                           step="any" 
                                                           name="data[{{ $v->id }}][{{ $b }}]" 
                                                           value="{{ $val !== null ? (float) $val : '' }}" 
                                                           class="form-control form-control-sm text-end matrix-input p-1" 
                                                           data-row="{{ $v->id }}"
                                                           placeholder="-">
                                                </td>
                                            @endfor
                                            <td class="text-end fw-bold font-monospace bg-light p-2" id="avg-{{ $v->id }}">
                                                {{ $initialAvg !== null ? number_format($initialAvg, 2, ',', '.') : '-' }}
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
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
