@extends('layouts.app')

@section('title', 'Menu Laporan Pendapatan')

@php
    $menuItems = [
        [
            'label' => 'Laporan Pendapatan Kunjungan',
            'route' => route('laporan.pendapatan.kunjungan'),
            'icon' => 'bi-people-fill',
            'color' => 'primary',
            'description' => 'Rekap billing kunjungan pasien berdasarkan poli dan penjamin dari hasil import SIMRS.',
        ],
        [
            'label' => 'Laporan Pendapatan Penjualan Obat',
            'route' => route('laporan.pendapatan.penjualan-obat'),
            'icon' => 'bi-capsule',
            'color' => 'success',
            'description' => 'Rekap transaksi penjualan obat, alkes, dan BHP farmasi dari hasil import SIMRS.',
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
            <h2 class="fw-bold text-dark d-none">Menu Laporan Pendapatan</h2>
        </div>
    </div>

    <!-- Hero Banner -->
    <div class="card reports-hero-banner border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary text-white px-2.5 py-1.5 rounded-pill fs-7">Modul Pendapatan</span>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-2">Pusat Laporan Pendapatan</h3>
                    <p class="text-muted mb-0">Pantau ringkasan pendapatan dari kunjungan pasien dan penjualan obat SIMRS secara akurat dan real-time.</p>
                </div>
                <div class="col-lg-4 text-lg-end d-none d-lg-block">
                    <i class="bi bi-graph-up text-primary opacity-25" style="font-size: 4.5rem;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="row row-cols-1 row-cols-md-2 g-4">
        @foreach ($menuItems as $item)
            <div class="col">
                <a href="{{ $item['route'] }}" class="card h-100 text-decoration-none text-dark shadow-sm border-0 report-card">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="icon-wrapper bg-{{ $item['color'] }}-subtle text-{{ $item['color'] }} rounded-4 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; flex-shrink: 0;">
                            <i class="bi {{ $item['icon'] }} fs-3"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1 report-card-title">{{ $item['label'] }}</h5>
                            <div class="text-muted small report-card-desc">{{ $item['description'] }}</div>
                        </div>
                        <div class="chevron-arrow text-muted ms-2">
                            <i class="bi bi-chevron-right fs-4 transition-transform"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection

@push('styles')
    <style>
        .report-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
            border-radius: 0.75rem;
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(37, 99, 235, 0.2) !important;
        }

        .report-card:hover .chevron-arrow i {
            transform: translateX(4px);
            color: #2563eb;
        }

        .report-card-title {
            color: #212529;
            transition: color 0.2s ease;
        }

        .report-card:hover .report-card-title {
            color: #2563eb;
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
            background: radial-gradient(circle at 100% 100%, rgba(37, 99, 235, 0.06), transparent 45%),
                        radial-gradient(circle at 0% 0%, rgba(2, 126, 63, 0.04), transparent 35%),
                        linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(37, 99, 235, 0.1) !important;
            border-radius: 1rem;
        }

        .fs-7 {
            font-size: 0.825rem;
        }

        .fw-extrabold {
            font-weight: 800;
        }

        .hover-opacity:hover {
            opacity: 0.8;
        }
    </style>
@endpush
