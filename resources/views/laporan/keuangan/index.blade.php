@extends('layouts.app')

@section('title', 'Menu Laporan Keuangan')

@php
    $menuGroups = [
        [
            'title' => 'Kelompok Neraca',
            'color' => 'success',
            'icon' => 'bi-journal-album',
            'items' => [
                [
                    'label' => 'Neraca Standard',
                    'route' => route('laporan.keuangan.neraca-standard'),
                    'state' => 'active',
                    'icon' => 'bi-file-earmark-spreadsheet',
                    'description' => 'Laporan posisi keuangan standar yang menyajikan aset, kewajiban, dan ekuitas.'
                ],
                [
                    'label' => 'Neraca Saldo',
                    'route' => route('laporan.keuangan.neraca-saldo'),
                    'state' => 'active',
                    'icon' => 'bi-grid-3x3-gap-fill',
                    'description' => 'Ringkasan saldo debit dan kredit dari seluruh akun COA untuk mengecek keseimbangan.'
                ],
                [
                    'label' => 'Neraca Detil',
                    'route' => route('laporan.keuangan.neraca-detil'),
                    'state' => 'active',
                    'icon' => 'bi-list-columns-reverse',
                    'description' => 'Laporan posisi keuangan dengan rincian sub-akun secara mendalam.'
                ],
                [
                    'label' => 'Neraca Rinci',
                    'route' => route('laporan.keuangan.neraca-rinci'),
                    'state' => 'active',
                    'icon' => 'bi-indent',
                    'description' => 'Laporan neraca terperinci yang membagi pos laporan berdasarkan klasifikasi sub-akun.'
                ],
            ],
        ],
        [
            'title' => 'Kelompok Laba Rugi',
            'color' => 'primary',
            'icon' => 'bi-graph-up-arrow',
            'items' => [
                [
                    'label' => 'Laba Rugi Standard',
                    'route' => route('laporan.keuangan.laba-rugi-standard'),
                    'state' => 'active',
                    'icon' => 'bi-file-earmark-bar-graph',
                    'description' => 'Laporan laba rugi standar menyajikan pendapatan, beban, dan laba bersih.'
                ],
                [
                    'label' => 'Laba Rugi Detil',
                    'route' => route('laporan.keuangan.laba-rugi-detil'),
                    'state' => 'active',
                    'icon' => 'bi-card-list',
                    'description' => 'Laporan laba rugi dengan rincian pendapatan dan beban secara mendalam.'
                ],
            ],
        ],
        [
            'title' => 'Kelompok Buku Besar',
            'color' => 'warning',
            'icon' => 'bi-journal-text',
            'items' => [
                [
                    'label' => 'Bukubesar',
                    'route' => route('laporan.keuangan.bukubesar'),
                    'state' => 'active',
                    'icon' => 'bi-book-half',
                    'description' => 'Kumpulan transaksi historis terperinci untuk masing-masing akun COA terpilih.'
                ],
                [
                    'label' => 'Rincian Transaksi Bukubesar',
                    'route' => route('laporan.keuangan.rincian-transaksi-bukubesar'),
                    'state' => 'active',
                    'icon' => 'bi-file-earmark-medical',
                    'description' => 'Rincian detail per transaksi debit dan kredit buku besar secara kronologis.'
                ],
            ],
        ],
        [
            'title' => 'Kelompok Arus Kas',
            'color' => 'info',
            'icon' => 'bi-cash-stack',
            'items' => [
                [
                    'label' => 'Arus Kas',
                    'route' => route('laporan.keuangan.arus-kas'),
                    'state' => 'active',
                    'icon' => 'bi-currency-exchange',
                    'description' => 'Laporan arus kas masuk dan keluar dari aktivitas operasi, investasi, dan pendanaan.'
                ],
            ],
        ],
        [
            'title' => 'Analisa & Audit',
            'color' => 'danger',
            'icon' => 'bi-shield-check',
            'items' => [
                [
                    'label' => 'Deteksi Jurnal Tidak Balance',
                    'route' => route('laporan.keuangan.deteksi-jurnal-tidak-balance'),
                    'state' => 'active',
                    'icon' => 'bi-exclamation-triangle',
                    'description' => 'Fitur deteksi otomatis untuk menemukan ketidakseimbangan debit/kredit pada jurnal.'
                ],
            ],
        ],
    ];
@endphp

@section('content')
    <!-- Breadcrumb & Title -->
    <div class="row mb-4">
        <div class="col">
            <a href="{{ route('home') }}" class="text-secondary text-decoration-none d-inline-flex align-items-center gap-1 mb-2 hover-opacity">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda
            </a>
            <h2 class="fw-bold text-dark d-none">Menu Laporan Keuangan</h2>
        </div>
    </div>

    <!-- Hero Banner with Search Bar -->
    <div class="card reports-hero-banner border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success text-white px-2.5 py-1.5 rounded-pill fs-7">Modul Keuangan</span>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-2">Pusat Laporan Keuangan</h3>
                    <p class="text-muted mb-0">Kelola, pantau, dan analisis kondisi finansial serta arus kas rumah sakit secara real-time dan terstruktur.</p>
                </div>
                <div class="col-lg-5">
                    <div class="input-group input-group-lg border rounded-3 bg-white shadow-sm">
                        <span class="input-group-text bg-transparent border-0 pe-1">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="menuSearch" class="form-control border-0 bg-transparent fs-6 py-3" placeholder="Cari jenis laporan...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Group Sections and Cards -->
    <div class="row">
        <div class="col">
            @foreach ($menuGroups as $group)
                <div class="report-group-section mb-4" id="group-{{ Str::slug($group['title']) }}">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi {{ $group['icon'] }} text-{{ $group['color'] }}"></i>
                            {{ $group['title'] }}
                        </h5>
                        <span class="badge bg-{{ $group['color'] }}-subtle text-{{ $group['color'] }} rounded-pill px-2.5 py-1 fs-7 fw-semibold">
                            {{ count($group['items']) }} Laporan
                        </span>
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @foreach ($group['items'] as $item)
                            <div class="col report-menu-item" data-label="{{ $item['label'] }}" data-desc="{{ $item['description'] }}">
                                @if ($item['state'] === 'active')
                                    <a href="{{ $item['route'] }}" class="card h-100 text-decoration-none text-dark shadow-sm border-0 report-card">
                                        <div class="card-body p-4 d-flex align-items-center gap-3">
                                            <div class="icon-wrapper bg-{{ $group['color'] }}-subtle text-{{ $group['color'] }} rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                                                <i class="bi {{ $item['icon'] }} fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1 report-card-title">{{ $item['label'] }}</h6>
                                                <div class="text-muted small report-card-desc">{{ $item['description'] }}</div>
                                            </div>
                                            <div class="chevron-arrow text-muted ms-2">
                                                <i class="bi bi-chevron-right fs-5 transition-transform"></i>
                                            </div>
                                        </div>
                                    </a>
                                @elseif ($item['state'] === 'blocked')
                                    <div class="card h-100 shadow-sm border-0 border-warning-subtle bg-warning-subtle bg-opacity-25 opacity-75">
                                        <div class="card-body p-4 d-flex align-items-center gap-3">
                                            <div class="icon-wrapper bg-warning text-warning-emphasis rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                                                <i class="bi bi-slash-circle fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <h6 class="fw-bold mb-0 text-dark-emphasis">{{ $item['label'] }}</h6>
                                                    <span class="badge bg-warning text-warning-emphasis rounded-pill px-2 py-0.5 fs-8">Ditunda</span>
                                                </div>
                                                <div class="text-muted small mt-1">{{ $item['note'] ?? 'Fitur dinonaktifkan sementara.' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="card h-100 shadow-sm border-0 border-info-subtle bg-info-subtle bg-opacity-25 opacity-75">
                                        <div class="card-body p-4 d-flex align-items-center gap-3">
                                            <div class="icon-wrapper bg-info text-info-emphasis rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                                                <i class="bi bi-info-circle fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <h6 class="fw-bold mb-0 text-dark-emphasis">{{ $item['label'] }}</h6>
                                                    <span class="badge bg-info text-info-emphasis rounded-pill px-2 py-0.5 fs-8">Info</span>
                                                </div>
                                                <div class="text-muted small mt-1">{{ $item['note'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuSearch = document.getElementById('menuSearch');
            if (menuSearch) {
                menuSearch.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase().trim();
                    const items = document.querySelectorAll('.report-menu-item');
                    const groups = document.querySelectorAll('.report-group-section');

                    groups.forEach(group => {
                        let hasVisibleItems = false;
                        const groupItems = group.querySelectorAll('.report-menu-item');

                        groupItems.forEach(item => {
                            const label = item.getAttribute('data-label').toLowerCase();
                            const desc = item.getAttribute('data-desc').toLowerCase();
                            if (label.includes(query) || desc.includes(query)) {
                                item.style.setProperty('display', '', 'important');
                                hasVisibleItems = true;
                            } else {
                                item.style.setProperty('display', 'none', 'important');
                            }
                        });

                        if (hasVisibleItems) {
                            group.style.setProperty('display', '', 'important');
                        } else {
                            group.style.setProperty('display', 'none', 'important');
                        }
                    });
                });
            }
        });
    </script>
@endpush

@push('styles')
    <style>
        .report-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(2, 126, 63, 0.25) !important;
        }

        .report-card:hover .chevron-arrow i {
            transform: translateX(4px);
            color: #027e3f;
        }

        .report-card-title {
            color: #212529;
            transition: color 0.2s ease;
        }

        .report-card:hover .report-card-title {
            color: #027e3f;
        }

        .transition-transform {
            transition: transform 0.2s ease;
            display: inline-block;
        }

        .icon-wrapper {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .report-card:hover .icon-wrapper {
            transform: scale(1.08);
        }

        .reports-hero-banner {
            background: radial-gradient(circle at 100% 100%, rgba(2, 126, 63, 0.06), transparent 45%),
                        radial-gradient(circle at 0% 0%, rgba(37, 99, 235, 0.04), transparent 35%),
                        linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(2, 126, 63, 0.1) !important;
            border-radius: 1rem;
        }

        .fs-7 {
            font-size: 0.825rem;
        }

        .fs-8 {
            font-size: 0.725rem;
        }

        .fw-extrabold {
            font-weight: 800;
        }

        .hover-opacity:hover {
            opacity: 0.8;
        }
    </style>
@endpush
