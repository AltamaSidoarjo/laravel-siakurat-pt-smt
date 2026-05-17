@extends('layouts.app')

@section('title', 'Home')

@php
    $chartCards = [
        ['id' => 'kunjunganHarianChart', 'title' => 'Kunjungan Harian', 'tone' => 'emerald'],
        ['id' => 'poliChart', 'title' => 'Distribusi Poli', 'tone' => 'blue'],
        ['id' => 'dokterChart', 'title' => 'Top Dokter', 'tone' => 'amber'],
        ['id' => 'pendapatanHarianChart', 'title' => 'Pendapatan Harian', 'tone' => 'teal'],
        ['id' => 'penjaminChart', 'title' => 'Komposisi Penjamin', 'tone' => 'indigo'],
    ];
@endphp

@section('content')
    <div class="row mb-4">
        <div class="col">
            <div class="card border-light shadow-sm dashboard-welcome-card">
                <div class="card-body">
                    <div class="alert dashboard-info-alert" role="alert">
                        Layanan Informasi : Saat ini tidak ada informasi...
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <div class="text-uppercase dashboard-eyebrow">Dashboard Monitoring</div>
                        <h4 class="mb-0">Ringkasan kunjungan dan pendapatan SIMRS</h4>
                        <div class="text-muted">
                            Anda sedang login sebagai
                            <strong>{{ session('auth.preview_user.username', 'preview-user') }}</strong>
                            di aplikasi {{ config('siakurat.app_name') }}.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card dashboard-filter-card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
                <div>
                    <div class="text-uppercase dashboard-eyebrow mb-2">Filter Dashboard</div>
                    <h5 class="mb-1">Pilih rentang tanggal grafik</h5>
                    <div class="text-muted small">Semua panel akan mengikuti rentang tanggal yang sama.</div>
                </div>
                <div class="row g-3 w-100 dashboard-filter-grid">
                    <div class="col-md-4">
                        <label for="dariTanggal" class="form-label">Dari</label>
                        <input type="date" id="dariTanggal" class="form-control" value="{{ $defaultStartDate }}">
                    </div>
                    <div class="col-md-4">
                        <label for="sampaiTanggal" class="form-label">Sampai</label>
                        <input type="date" id="sampaiTanggal" class="form-control" value="{{ $defaultEndDate }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button class="btn btn-primary flex-fill" type="button" id="applyDashboardFilter">
                            <i class="bi bi-funnel me-1"></i> Terapkan
                        </button>
                        <button class="btn btn-light flex-fill" type="button" id="resetDashboardFilter">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @foreach ($chartCards as $chartCard)
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm dashboard-graph-card dashboard-graph-card--{{ $chartCard['tone'] }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <div class="text-uppercase dashboard-eyebrow">{{ $chartCard['title'] }}</div>
                                <h5 class="mb-0">{{ $chartCard['title'] }}</h5>
                            </div>
                            <div class="dashboard-chart-badge">Live</div>
                        </div>
                        <div class="dashboard-chart-shell">
                            <canvas id="{{ $chartCard['id'] }}"></canvas>
                            <div class="dashboard-chart-empty d-none" data-empty-for="{{ $chartCard['id'] }}">
                                Belum ada data pada rentang tanggal ini.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputDari = document.getElementById('dariTanggal');
            const inputSampai = document.getElementById('sampaiTanggal');
            const applyButton = document.getElementById('applyDashboardFilter');
            const resetButton = document.getElementById('resetDashboardFilter');
            const defaultStartDate = '{{ $defaultStartDate }}';
            const defaultEndDate = '{{ $defaultEndDate }}';
            const chartInstances = {};

            const chartConfigs = {
                kunjunganHarianChart: {
                    endpoint: '{{ route('home.kunjungan-harian') }}',
                    transform: (rows) => ({
                        labels: rows.map(row => row.tanggal),
                        datasets: [{
                            label: 'Jumlah Kunjungan',
                            data: rows.map(row => Number(row.total) || 0),
                            borderColor: '#0b6b3a',
                            backgroundColor: 'rgba(11, 107, 58, 0.18)',
                            fill: true,
                            tension: 0.32,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }],
                    }),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    },
                    type: 'line',
                },
                poliChart: {
                    endpoint: '{{ route('home.poli') }}',
                    transform: (rows) => ({
                        labels: rows.map(row => row.poli),
                        datasets: [{
                            label: 'Total Pasien',
                            data: rows.map(row => Number(row.total) || 0),
                            backgroundColor: ['#155dfc', '#0ea5e9', '#38bdf8', '#7dd3fc', '#bfdbfe', '#1d4ed8'],
                            borderRadius: 10,
                            maxBarThickness: 36,
                        }],
                    }),
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    },
                    type: 'bar',
                },
                dokterChart: {
                    endpoint: '{{ route('home.dokter') }}',
                    transform: (rows) => ({
                        labels: rows.map(row => row.dokter),
                        datasets: [{
                            label: 'Jumlah Pasien',
                            data: rows.map(row => Number(row.total) || 0),
                            backgroundColor: '#f59e0b',
                            borderColor: '#b45309',
                            borderWidth: 1,
                            borderRadius: 10,
                            maxBarThickness: 34,
                        }],
                    }),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    },
                    type: 'bar',
                },
                pendapatanHarianChart: {
                    endpoint: '{{ route('home.pendapatan-harian') }}',
                    transform: (rows) => ({
                        labels: rows.map(row => row.tanggal),
                        datasets: [{
                            label: 'Pendapatan',
                            data: rows.map(row => Number(row.pendapatan) || 0),
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15, 118, 110, 0.18)',
                            fill: true,
                            tension: 0.28,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }],
                    }),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => formatRupiah(value),
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: (context) => `Pendapatan: ${formatRupiah(context.raw)}`,
                                }
                            }
                        }
                    },
                    type: 'line',
                },
                penjaminChart: {
                    endpoint: '{{ route('home.penjamin') }}',
                    transform: (rows) => ({
                        labels: rows.map(row => row.penjamin),
                        datasets: [{
                            label: 'Total',
                            data: rows.map(row => Number(row.total) || 0),
                            backgroundColor: ['#4338ca', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#312e81'],
                            borderWidth: 0,
                        }],
                    }),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    },
                    type: 'doughnut',
                },
            };

            function formatRupiah(value) {
                return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
            }

            function getQueryString() {
                const dariTanggal = inputDari.value;
                const sampaiTanggal = inputSampai.value;

                const params = new URLSearchParams();
                if (dariTanggal) {
                    params.set('dariTanggal', dariTanggal);
                }
                if (sampaiTanggal) {
                    params.set('sampaiTanggal', sampaiTanggal);
                }

                const query = params.toString();
                return query ? `?${query}` : '';
            }

            function toggleEmptyState(chartId, isEmpty) {
                const canvas = document.getElementById(chartId);
                const emptyState = document.querySelector(`[data-empty-for="${chartId}"]`);
                if (!canvas || !emptyState) {
                    return;
                }

                canvas.classList.toggle('d-none', isEmpty);
                emptyState.classList.toggle('d-none', !isEmpty);
            }

            async function loadChart(chartId, config) {
                const response = await fetch(`${config.endpoint}${getQueryString()}`);
                const rows = await response.json();
                const chartData = config.transform(rows);

                if (chartInstances[chartId]) {
                    chartInstances[chartId].destroy();
                }

                const hasData = chartData.labels.length > 0 && chartData.datasets.some(dataset =>
                    Array.isArray(dataset.data) && dataset.data.some(value => Number(value) > 0)
                );

                toggleEmptyState(chartId, !hasData);
                if (!hasData) {
                    chartInstances[chartId] = null;
                    return;
                }

                const canvas = document.getElementById(chartId);
                chartInstances[chartId] = new Chart(canvas, {
                    type: config.type,
                    data: chartData,
                    options: {
                        animation: {
                            duration: 600,
                        },
                        plugins: {
                            legend: {
                                display: config.type === 'doughnut',
                            }
                        },
                        ...config.options,
                    }
                });
            }

            async function loadAllCharts() {
                await Promise.all(
                    Object.entries(chartConfigs).map(([chartId, config]) => loadChart(chartId, config))
                );
            }

            applyButton.addEventListener('click', loadAllCharts);
            resetButton.addEventListener('click', function () {
                inputDari.value = defaultStartDate;
                inputSampai.value = defaultEndDate;
                loadAllCharts();
            });

            loadAllCharts();
        });
    </script>
@endpush

@push('styles')
    <style>
        .dashboard-welcome-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(236, 253, 245, 0.94));
            border: 1px solid rgba(17, 109, 10, 0.12);
        }

        .dashboard-filter-card {
            border: 1px solid rgba(5, 6, 117, 0.08);
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 28%),
                linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.94));
        }

        .dashboard-info-alert {
            background: rgba(5, 103, 117, 0.08);
            border: 1px solid rgba(5, 103, 117, 0.12);
            color: #0f4c5c;
        }

        .dashboard-eyebrow {
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            font-weight: 700;
            color: #0f766e;
        }

        .dashboard-filter-grid {
            max-width: 860px;
        }

        .dashboard-graph-card {
            height: 100%;
            border: 1px solid rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .dashboard-graph-card::before {
            content: "";
            display: block;
            height: 6px;
            background: linear-gradient(90deg, rgba(17, 109, 10, 0.9), rgba(14, 165, 233, 0.8));
        }

        .dashboard-graph-card--emerald::before {
            background: linear-gradient(90deg, #0b6b3a, #22c55e);
        }

        .dashboard-graph-card--blue::before {
            background: linear-gradient(90deg, #1d4ed8, #38bdf8);
        }

        .dashboard-graph-card--amber::before {
            background: linear-gradient(90deg, #b45309, #f59e0b);
        }

        .dashboard-graph-card--teal::before {
            background: linear-gradient(90deg, #0f766e, #2dd4bf);
        }

        .dashboard-graph-card--indigo::before {
            background: linear-gradient(90deg, #3730a3, #818cf8);
        }

        .dashboard-chart-shell {
            position: relative;
            min-height: 320px;
        }

        .dashboard-chart-shell canvas {
            width: 100% !important;
            height: 320px !important;
        }

        .dashboard-chart-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.85rem;
            background:
                repeating-linear-gradient(
                    -45deg,
                    rgba(226, 232, 240, 0.55),
                    rgba(226, 232, 240, 0.55) 12px,
                    rgba(248, 250, 252, 0.82) 12px,
                    rgba(248, 250, 252, 0.82) 24px
                );
            color: #64748b;
            border: 1px dashed rgba(100, 116, 139, 0.4);
            font-weight: 600;
        }

        .dashboard-chart-badge {
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #065f46;
            background: rgba(16, 185, 129, 0.12);
        }

        @media (max-width: 991.98px) {
            .dashboard-chart-shell,
            .dashboard-chart-shell canvas {
                min-height: 280px;
                height: 280px !important;
            }
        }
    </style>
@endpush
