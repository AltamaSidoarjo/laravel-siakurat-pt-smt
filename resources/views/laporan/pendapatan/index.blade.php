@extends('layouts.app')

@section('title', 'Menu Laporan Pendapatan')

@php
    $menuItems = [
        [
            'label' => 'Laporan Pendapatan Kunjungan',
            'route' => route('laporan.pendapatan.kunjungan'),
            'description' => 'Rekap billing kunjungan dari hasil import pendapatan SIMRS.',
        ],
        [
            'label' => 'Laporan Pendapatan Penjualan Obat',
            'route' => route('laporan.pendapatan.penjualan-obat'),
            'description' => 'Rekap penjualan obat dan BHP dari hasil import SIMRS.',
        ],
    ];
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Menu Laporan Pendapatan</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @foreach ($menuItems as $item)
                            <div class="col">
                                <a href="{{ $item['route'] }}" class="card h-100 text-decoration-none text-dark shadow-sm">
                                    <div class="card-body d-flex flex-column gap-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold">{{ $item['label'] }}</span>
                                            <i class="bi bi-chevron-right"></i>
                                        </div>
                                        <div class="text-muted small">{{ $item['description'] }}</div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
