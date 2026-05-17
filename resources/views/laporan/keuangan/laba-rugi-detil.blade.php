@extends('layouts.app')

@section('title', 'Laba Rugi Detil')

@php
    $totals = collect($rows)->groupBy('tipe_coa')->map->sum('nominal');
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laba Rugi Detil</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Dari tanggal</label>
                                <input type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sampai tanggal</label>
                                <input type="date" name="endDate" class="form-control" value="{{ $endDate }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="laporan-header text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="laporan-header__logo">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" class="laporan-header__logo-image">
                            </div>
                        @endif
                        <div class="laporan-header__identity">
                            <div class="fw-bold laporan-header__title-rs">{{ $namaRumahSakit }}</div>
                            <div class="fw-bold laporan-header__title-report">Laba Rugi Detil</div>
                            <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Deskripsi</th>
                                    <th>Tipe</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $row['kode'] }}</td>
                                        <td>{{ $row['deskripsi'] }}</td>
                                        <td>{{ $row['tipe_coa'] }}</td>
                                        <td class="text-end">{{ number_format((float) $row['nominal'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data untuk periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (count($rows) > 0)
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                    @foreach ($totals as $tipe => $nominal)
                                        <tr class="table-light fw-bold">
                                            <td>{{ $tipe }}</td>
                                            <td class="text-end">{{ number_format((float) $nominal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .laporan-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .laporan-header__logo {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .laporan-header__logo-image {
            max-width: 100px;
            max-height: 84px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .laporan-header__title-rs {
            font-size: 20px;
            line-height: 1.25;
        }

        .laporan-header__title-report {
            font-size: 18px;
            line-height: 1.2;
        }

        @media print {
            .laporan-header__logo-image {
                max-width: 90px;
                max-height: 72px;
            }
        }
    </style>
@endpush
