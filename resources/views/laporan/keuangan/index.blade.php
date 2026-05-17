@extends('layouts.app')

@section('title', 'Menu Laporan Keuangan')

@php
    $menuGroups = [
        [
            'title' => 'Kelompok Neraca',
            'items' => [
                ['label' => 'Neraca Standard', 'route' => route('laporan.keuangan.neraca-standard'), 'state' => 'active'],
                ['label' => 'Neraca Saldo', 'route' => route('laporan.keuangan.neraca-saldo'), 'state' => 'active'],
                ['label' => 'Neraca Detil', 'route' => route('laporan.keuangan.neraca-detil'), 'state' => 'active'],
                ['label' => 'Neraca Rinci', 'route' => route('laporan.keuangan.neraca-rinci'), 'state' => 'active'],
            ],
        ],
        [
            'title' => 'Kelompok Laba Rugi',
            'items' => [
                ['label' => 'Laba Rugi Standard', 'route' => route('laporan.keuangan.laba-rugi-standard'), 'state' => 'active'],
                ['label' => 'Laba Rugi Detil', 'route' => route('laporan.keuangan.laba-rugi-detil'), 'state' => 'active'],
            ],
        ],
        [
            'title' => 'Kelompok Buku Besar',
            'items' => [
                ['label' => 'Bukubesar', 'route' => route('laporan.keuangan.bukubesar'), 'state' => 'active'],
                ['label' => 'Rincian Transaksi Bukubesar', 'route' => route('laporan.keuangan.rincian-transaksi-bukubesar'), 'state' => 'active'],
            ],
        ],
        [
            'title' => 'Kelompok Arus Kas',
            'items' => [
                ['label' => 'Arus Kas', 'route' => route('laporan.keuangan.arus-kas'), 'state' => 'active'],
            ],
        ],
        [
            'title' => 'Analisa & Audit',
            'items' => [
                ['label' => 'Deteksi Jurnal Tidak Balance', 'route' => route('laporan.keuangan.deteksi-jurnal-tidak-balance'), 'state' => 'active'],
            ],
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
                <span class="fw-bold">Menu Laporan Keuangan</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-4">
                        @foreach ($menuGroups as $group)
                            <div class="card border-light shadow-sm">
                                <div class="card-header bg-light fw-bold">
                                    {{ $group['title'] }}
                                </div>
                                <div class="card-body">
                                    <div class="row row-cols-1 row-cols-md-2 g-3">
                                        @foreach ($group['items'] as $item)
                                            <div class="col">
                                                @if ($item['state'] === 'active')
                                                    <a href="{{ $item['route'] }}" class="card h-100 text-decoration-none text-dark shadow-sm">
                                                        <div class="card-body d-flex align-items-center justify-content-between">
                                                            <span>{{ $item['label'] }}</span>
                                                            <i class="bi bi-chevron-right"></i>
                                                        </div>
                                                    </a>
                                                @elseif ($item['state'] === 'blocked')
                                                    <div class="card h-100 shadow-sm border-warning-subtle bg-warning-subtle">
                                                        <div class="card-body">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <span class="fw-semibold">{{ $item['label'] }}</span>
                                                                <span class="badge text-bg-warning">Ditunda</span>
                                                            </div>
                                                            <div class="text-muted small mt-2">{{ $item['note'] }}</div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="card h-100 shadow-sm border-info-subtle bg-info-subtle">
                                                        <div class="card-body">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <span class="fw-semibold">{{ $item['label'] }}</span>
                                                                <span class="badge text-bg-info">Info</span>
                                                            </div>
                                                            <div class="text-muted small mt-2">{{ $item['note'] }}</div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
