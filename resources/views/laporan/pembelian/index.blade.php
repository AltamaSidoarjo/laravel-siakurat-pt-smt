@extends('layouts.app')

@section('title', 'Menu Laporan Pembelian')

@section('content')
    <div class="row mb-4">
        <div class="col">
            <a href="{{ route('home') }}" class="text-secondary text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <div class="card reports-hero-banner border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge bg-primary text-white px-2 py-1 rounded-pill mb-2">Modul Pembelian</span>
                    <h3 class="fw-extrabold text-dark mb-2">Pusat Laporan Pembelian</h3>
                    <p class="text-muted mb-0">Pantau posisi hutang dan riwayat transaksi pembelian per supplier.</p>
                </div>
                <div class="col-lg-4 text-lg-end d-none d-lg-block">
                    <i class="bi bi-cart-check text-primary opacity-25" style="font-size: 4.5rem;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 g-4">
        <div class="col">
            <a href="{{ route('laporan.pembelian.buku-pembantu-hutang') }}" class="card h-100 text-decoration-none text-dark shadow-sm border-0 report-card">
                <div class="card-body p-4 d-flex align-items-center gap-3">
                    <div class="icon-wrapper bg-warning-subtle text-warning rounded-4 d-flex align-items-center justify-content-center">
                        <i class="bi bi-journal-text fs-3"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-1 report-card-title">Buku Pembantu Hutang</h5>
                        <div class="text-muted small">Kartu mutasi dan saldo hutang per supplier berdasarkan faktur dan pembayaran pembelian.</div>
                    </div>
                    <i class="bi bi-chevron-right fs-4 text-muted"></i>
                </div>
            </a>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .report-card { transition: all .3s ease; border: 1px solid rgba(0, 0, 0, .05) !important; border-radius: .75rem; }
        .report-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0, 0, 0, .08) !important; }
        .report-card-title { color: #212529; }
        .report-card:hover .report-card-title { color: #2563eb; }
        .icon-wrapper { width: 56px; height: 56px; flex-shrink: 0; transition: transform .3s ease; }
        .report-card:hover .icon-wrapper { transform: scale(1.08); }
        .reports-hero-banner { background: radial-gradient(circle at 100% 100%, rgba(37, 99, 235, .06), transparent 45%), linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border: 1px solid rgba(37, 99, 235, .1) !important; border-radius: 1rem; }
        .fw-extrabold { font-weight: 800; }
    </style>
@endpush
