@extends('layouts.app')

@section('title', 'Dashboard Indikator Kinerja')

@php
    $formatBand = function ($min, $max, $satuan = '') {
        $satuanStr = $satuan ? ' '.$satuan : '';
        if ($min !== null && $max !== null) {
            $minFormatted = rtrim(rtrim(number_format((float) $min, 2, ',', '.'), '0'), ',');
            $maxFormatted = rtrim(rtrim(number_format((float) $max, 2, ',', '.'), '0'), ',');
            return ($min == $max) ? "= {$minFormatted}{$satuanStr}" : "{$minFormatted} - {$maxFormatted}{$satuanStr}";
        }
        if ($min !== null) {
            $minFormatted = rtrim(rtrim(number_format((float) $min, 2, ',', '.'), '0'), ',');
            return "≥ {$minFormatted}{$satuanStr}";
        }
        if ($max !== null) {
            $maxFormatted = rtrim(rtrim(number_format((float) $max, 2, ',', '.'), '0'), ',');
            return "≤ {$maxFormatted}{$satuanStr}";
        }
        return '-';
    };
@endphp

@section('content')
<div class="d-flex flex-column gap-4">
    <!-- Header Page -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="text-uppercase text-primary fw-semibold small tracking-wide mb-1">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard Eksekutif
                    </div>
                    <h3 class="mb-1 fw-bold text-dark">Dashboard Indikator Kinerja</h3>
                    <p class="text-muted mb-0 small">
                        Monitoring realisasi indikator kinerja utama rumah sakit berbasis standar evaluasi Red, Amber, Green (RAG).
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                        <i class="bi bi-calendar3 me-1 text-primary"></i>
                        Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('dashboard.indikator-kinerja.index') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="dariTanggal" class="form-label small fw-semibold text-muted">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="dariTanggal" name="dariTanggal" value="{{ $startDate }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="sampaiTanggal" class="form-label small fw-semibold text-muted">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="sampaiTanggal" name="sampaiTanggal" value="{{ $endDate }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="q" class="form-label small fw-semibold text-muted">Cari Indikator</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="q" name="q" value="{{ $q }}" placeholder="Cari nama atau kode indikator...">
                        </div>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-funnel me-1"></i> Terapkan
                        </button>
                        <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-light border flex-fill text-center">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Status Strip -->
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Indikator</div>
                        <h2 class="mb-0 fw-bold text-dark mt-1">{{ $ringkasanStatus['total'] }}</h2>
                    </div>
                    <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                        <i class="bi bi-collection fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-success small fw-semibold text-uppercase">Tercapai (Hijau)</div>
                        <h2 class="mb-0 fw-bold text-success mt-1">{{ $ringkasanStatus['hijau'] }}</h2>
                    </div>
                    <div class="rounded-circle p-3 bg-success-subtle text-success">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-warning-emphasis small fw-semibold text-uppercase">Perhatian (Kuning)</div>
                        <h2 class="mb-0 fw-bold text-warning-emphasis mt-1">{{ $ringkasanStatus['kuning'] }}</h2>
                    </div>
                    <div class="rounded-circle p-3 bg-warning-subtle text-warning">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small fw-semibold text-uppercase">Kritis (Merah)</div>
                        <h2 class="mb-0 fw-bold text-danger mt-1">{{ $ringkasanStatus['merah'] }}</h2>
                    </div>
                    <div class="rounded-circle p-3 bg-danger-subtle text-danger">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (($ringkasanStatus['netral'] ?? 0) > 0)
        <div class="alert alert-secondary border-0 shadow-sm py-2 px-3 mb-0 small d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-secondary flex-shrink-0"></i>
            <div>
                Terdapat <strong>{{ $ringkasanStatus['netral'] }}</strong> indikator berstatus netral / informasi (belum memiliki target RAG atau data manual belum terisi).
            </div>
        </div>
    @endif

    <!-- Content Card with View Switcher -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark fs-6">Daftar Indikator Kinerja</h5>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                    {{ $indikatorList->count() }} Indikator
                </span>
            </div>

            <!-- View Switcher Toggle -->
            <div class="btn-group" role="group" aria-label="View Switcher">
                <button type="button" class="btn btn-sm btn-outline-primary active" id="btnToggleCards" title="Tampilkan Mode Kartu Eksekutif">
                    <i class="bi bi-grid-fill me-1"></i> Mode Kartu
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnToggleTable" title="Tampilkan Mode Tabel Rinci">
                    <i class="bi bi-table me-1"></i> Mode Tabel Rinci
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            @if ($indikatorList->isEmpty())
                <div class="text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-search fs-1"></i>
                    </div>
                    <h5 class="text-muted">Tidak ada indikator yang sesuai dengan filter pencarian.</h5>
                    <p class="text-muted small mb-3">Coba gunakan kata kunci pencarian yang lain atau reset filter tanggal.</p>
                    <a href="{{ route('dashboard.indikator-kinerja.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
                    </a>
                </div>
            @else
                <!-- MODE 1: KARTU EKSEKUTIF (Card Grid) -->
                <div id="viewModeCards">
                    <div class="row g-3 g-lg-4">
                        @foreach ($indikatorList as $item)
                            @php
                                $ind = $item['indikator'];
                                $badgeClass = $item['status']->badgeClass();
                                $satuanStr = $item['satuan'] ?? '';
                                $targetHijauText = $formatBand($ind->target_hijau_min, $ind->target_hijau_max, $satuanStr);
                                $targetKuningText = $formatBand($ind->target_kuning_min, $ind->target_kuning_max, $satuanStr);
                                $targetMerahText = $formatBand($ind->target_merah_min, $ind->target_merah_max, $satuanStr);
                                $polaLabel = $ind->pola_evaluasi ? $ind->pola_evaluasi->label() : '-';
                                $tipeKalkulasiLabel = $ind->tipe_kalkulasi ? $ind->tipe_kalkulasi->label() : '-';
                            @endphp
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100 border border-{{ $badgeClass }}-subtle shadow-sm transition-hover">
                                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-light text-muted border">#{{ $ind->urutan }}</span>
                                            <span class="text-muted small font-monospace">{{ $item['kode'] }}</span>
                                        </div>
                                        <span class="badge bg-{{ $badgeClass }} text-white px-2 py-1">
                                            {{ $item['status']->label() }}
                                        </span>
                                    </div>
                                    <div class="card-body pt-2 pb-3 d-flex flex-column justify-content-between">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-2 text-truncate" title="{{ $item['nama'] }}">
                                                {{ $item['nama'] }}
                                            </h6>
                                            <div class="d-flex align-items-baseline gap-2 mb-3">
                                                <div class="fs-2 fw-bold text-{{ $badgeClass === 'secondary' ? 'dark' : $badgeClass }}">
                                                    {{ $item['nilai_formatted'] }}
                                                </div>
                                            </div>

                                            <!-- Target Bands Micro-guide -->
                                            <div class="p-2 rounded bg-light border small mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Hijau:</span>
                                                    <span class="text-dark">{{ $targetHijauText }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-warning-emphasis fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Kuning:</span>
                                                    <span class="text-dark">{{ $targetKuningText }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Merah:</span>
                                                    <span class="text-dark">{{ $targetMerahText }}</span>
                                                </div>
                                            </div>

                                            @if ($item['catatan'])
                                                <div class="text-muted small fst-italic mb-2">
                                                    <i class="bi bi-info-circle me-1"></i>{{ $item['catatan'] }}
                                                </div>
                                            @endif
                                        </div>

                                        <button type="button"
                                            class="btn btn-outline-primary btn-sm w-100 fw-semibold btn-detail-tren"
                                            data-kode="{{ $item['kode'] }}"
                                            data-nama="{{ $item['nama'] }}"
                                            data-satuan="{{ $item['satuan'] ?? '' }}"
                                            data-nilai="{{ $item['nilai_formatted'] }}"
                                            data-status-label="{{ $item['status']->label() }}"
                                            data-status-badge="{{ $badgeClass }}"
                                            data-definisi="{{ $ind->definisi_operasional }}"
                                            data-pola="{{ $polaLabel }}"
                                            data-tipe="{{ $tipeKalkulasiLabel }}"
                                            data-target-hijau="{{ $targetHijauText }}"
                                            data-target-kuning="{{ $targetKuningText }}"
                                            data-target-merah="{{ $targetMerahText }}">
                                            <i class="bi bi-graph-up me-1"></i> Detail & Tren
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- MODE 2: TABEL RINCI (Detailed Table) -->
                <div id="viewModeTable" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 border">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="text-center" style="width: 50px;">No</th>
                                    <th scope="col" style="min-width: 220px;">Indikator</th>
                                    <th scope="col" class="text-center" style="width: 100px;">Satuan</th>
                                    <th scope="col" style="min-width: 140px;">Tipe Kalkulasi</th>
                                    <th scope="col" class="text-end" style="min-width: 130px;">Nilai Aktual</th>
                                    <th scope="col" style="min-width: 220px;">Panduan Target</th>
                                    <th scope="col" class="text-center" style="width: 140px;">Status</th>
                                    <th scope="col" class="text-center" style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($indikatorList as $item)
                                    @php
                                        $ind = $item['indikator'];
                                        $badgeClass = $item['status']->badgeClass();
                                        $satuanStr = $item['satuan'] ?? '';
                                        $targetHijauText = $formatBand($ind->target_hijau_min, $ind->target_hijau_max, $satuanStr);
                                        $targetKuningText = $formatBand($ind->target_kuning_min, $ind->target_kuning_max, $satuanStr);
                                        $targetMerahText = $formatBand($ind->target_merah_min, $ind->target_merah_max, $satuanStr);
                                        $polaLabel = $ind->pola_evaluasi ? $ind->pola_evaluasi->label() : '-';
                                        $tipeKalkulasiLabel = $ind->tipe_kalkulasi ? $ind->tipe_kalkulasi->label() : '-';
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted fw-semibold">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item['nama'] }}</div>
                                            <div class="small text-muted font-monospace">{{ $item['kode'] }}</div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">{{ $item['satuan'] ?: '-' }}</span>
                                        </td>
                                        <td class="small text-secondary">
                                            {{ $tipeKalkulasiLabel }}
                                        </td>
                                        <td class="text-end fw-bold fs-6 text-{{ $badgeClass === 'secondary' ? 'dark' : $badgeClass }}">
                                            {{ $item['nilai_formatted'] }}
                                        </td>
                                        <td class="small">
                                            <div><span class="text-success fw-semibold">H:</span> {{ $targetHijauText }}</div>
                                            <div><span class="text-warning-emphasis fw-semibold">K:</span> {{ $targetKuningText }}</div>
                                            <div><span class="text-danger fw-semibold">M:</span> {{ $targetMerahText }}</div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $badgeClass }} text-white">
                                                {{ $item['status']->label() }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-outline-primary btn-sm fw-semibold btn-detail-tren"
                                                data-kode="{{ $item['kode'] }}"
                                                data-nama="{{ $item['nama'] }}"
                                                data-satuan="{{ $item['satuan'] ?? '' }}"
                                                data-nilai="{{ $item['nilai_formatted'] }}"
                                                data-status-label="{{ $item['status']->label() }}"
                                                data-status-badge="{{ $badgeClass }}"
                                                data-definisi="{{ $ind->definisi_operasional }}"
                                                data-pola="{{ $polaLabel }}"
                                                data-tipe="{{ $tipeKalkulasiLabel }}"
                                                data-target-hijau="{{ $targetHijauText }}"
                                                data-target-kuning="{{ $targetKuningText }}"
                                                data-target-merah="{{ $targetMerahText }}">
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

<!-- Modal Drill-down Detail & Tren -->
<div class="modal fade" id="modalDetailTren" tabindex="-1" aria-labelledby="modalDetailTrenLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded bg-primary-subtle text-primary">
                        <i class="bi bi-speedometer2 fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalDetailTrenLabel">Detail Indikator Kinerja</h5>
                        <div class="small text-muted font-monospace" id="modalIndicatorKode">-</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="modalIndicatorStatusBadge" class="badge bg-secondary">-</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <!-- Section 1: Definisi & Panduan -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2">Definisi Operasional</h6>
                    <div class="p-3 bg-light rounded border text-secondary small" id="modalIndicatorDefinisi" style="line-height: 1.6;">
                        -
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small fw-semibold">Nilai Periode Aktif</div>
                            <div class="fs-4 fw-bold text-dark mt-1" id="modalIndicatorNilai">-</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small fw-semibold">Tipe Kalkulasi</div>
                            <div class="small fw-semibold text-dark mt-1" id="modalIndicatorTipe">-</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small fw-semibold">Pola Evaluasi</div>
                            <div class="small fw-semibold text-dark mt-1" id="modalIndicatorPola">-</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 bg-light p-3 mb-4">
                    <div class="fw-semibold text-dark small mb-2">Panduan Target Standar (RAG):</div>
                    <div class="row g-2 text-center small">
                        <div class="col-4">
                            <div class="p-2 bg-success-subtle text-success border border-success-subtle rounded">
                                <div class="fw-bold">Hijau (Tercapai)</div>
                                <div id="modalTargetHijau" class="fw-semibold text-dark mt-1">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded">
                                <div class="fw-bold">Kuning (Waspada)</div>
                                <div id="modalTargetKuning" class="fw-semibold text-dark mt-1">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-danger-subtle text-danger border border-danger-subtle rounded">
                                <div class="fw-bold">Merah (Kritis)</div>
                                <div id="modalTargetMerah" class="fw-semibold text-dark mt-1">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Section 2: Grafik Tren 12 Bulan -->
                <div>
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Tren Realisasi Bulanan</h6>
                            <div class="text-muted small">Pergerakan capaian indikator selama 12 bulan pada tahun terpilih.</div>
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
                    <div class="position-relative bg-white rounded border p-3" style="min-height: 280px;">
                        <div id="trenChartLoading" class="position-absolute top-50 start-50 translate-middle text-center d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Memuat data...</span>
                            </div>
                            <div class="small text-muted mt-2">Memuat data tren bulanan...</div>
                        </div>

                        <div id="trenChartError" class="position-absolute top-50 start-50 translate-middle text-center d-none w-75">
                            <i class="bi bi-exclamation-triangle text-warning fs-3"></i>
                            <div class="small text-danger fw-semibold mt-1">Gagal memuat data tren indikator.</div>
                        </div>

                        <div id="trenChartCanvasWrapper" style="height: 260px;">
                            <canvas id="trenChartCanvas"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
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

    // Attach click event to all Detail & Tren buttons
    document.querySelectorAll('.btn-detail-tren').forEach(function (button) {
        button.addEventListener('click', function () {
            const data = this.dataset;
            currentKode = data.kode;

            document.getElementById('modalDetailTrenLabel').textContent = data.nama;
            document.getElementById('modalIndicatorKode').textContent = data.kode;
            document.getElementById('modalIndicatorNilai').textContent = data.nilai;
            document.getElementById('modalIndicatorDefinisi').textContent = data.definisi || 'Tidak ada definisi operasional tercatat.';
            document.getElementById('modalIndicatorTipe').textContent = data.tipe || '-';
            document.getElementById('modalIndicatorPola').textContent = data.pola || '-';
            document.getElementById('modalTargetHijau').textContent = data.targetHijau || '-';
            document.getElementById('modalTargetKuning').textContent = data.targetKuning || '-';
            document.getElementById('modalTargetMerah').textContent = data.targetMerah || '-';

            const badgeEl = document.getElementById('modalIndicatorStatusBadge');
            badgeEl.className = 'badge bg-' + (data.statusBadge || 'secondary');
            badgeEl.textContent = data.statusLabel || '-';

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
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.08)',
                    pointBackgroundColor: pointBackgroundColors,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.25,
                    spanGaps: false // null values break the line gracefully for future months
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
                            color: '#f0f0f0'
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
