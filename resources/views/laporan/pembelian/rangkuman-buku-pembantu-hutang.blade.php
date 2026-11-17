@extends('layouts.app')

@section('title', 'Rangkuman Buku Pembantu Hutang')

@php
    use Illuminate\Support\Carbon;

    $reportDateCarbon = Carbon::parse($reportDate);
    $formatAmount = fn ($value) => (float) $value > 0
        ? number_format((float) $value, 2, ',', '.')
        : '-';
@endphp

@section('content')
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.pembelian.index') }}" class="text-dark" aria-label="Kembali ke laporan pembelian">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Rangkuman Buku Pembantu Hutang</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah mb-2 report-shell">
        <div class="card-body d-flex flex-column gap-3">
            @isset($errors)
                <div class="no-print">
                    @include('partials.validation-errors')
                </div>
            @endisset

            @include('laporan.pembelian._hutang-filter', [
                'formAction' => route('laporan.pembelian.rangkuman-buku-pembantu-hutang'),
                'resetAction' => route('laporan.pembelian.rangkuman-buku-pembantu-hutang'),
                'exportAction' => route('laporan.pembelian.rangkuman-buku-pembantu-hutang.export-csv'),
            ])

            <div class="summary-report">
                <header class="report-header text-center mb-4">
                    @if ($logoRsUrl)
                        <img src="{{ $logoRsUrl }}" alt="Logo rumah sakit" class="report-logo mb-2">
                    @endif
                    <div class="hospital-name">{{ $namaRumahSakit }}</div>
                    <h1>RANGKUMAN BUKU PEMBANTU HUTANG</h1>
                    <div class="report-date">Posisi per {{ $reportDateCarbon->translatedFormat('d F Y') }}</div>
                </header>

                @if ($rows !== [])
                    <div class="table-responsive report-table-wrapper">
                        <table class="table table-sm mb-0 summary-table">
                            <thead>
                                <tr>
                                    <th>Nama Supplier</th>
                                    <th class="text-end">0 - 30 Hari</th>
                                    <th class="text-end">31 - 60 Hari</th>
                                    <th class="text-end">61 - 90 Hari</th>
                                    <th class="text-end">&gt; 90 Hari</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ strtoupper($row['nama_supplier']) }} | {{ $row['kode_supplier'] ?: '-' }}</td>
                                        <td class="text-end">{{ $formatAmount($row['days_0_30']) }}</td>
                                        <td class="text-end">{{ $formatAmount($row['days_31_60']) }}</td>
                                        <td class="text-end">{{ $formatAmount($row['days_61_90']) }}</td>
                                        <td class="text-end">{{ $formatAmount($row['days_over_90']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>GRAND TOTAL</td>
                                    <td class="text-end">{{ number_format($summary['days_0_30'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_31_60'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_61_90'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_over_90'], 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="text-end fw-bold mt-2 report-balance">
                        Total Hutang: {{ number_format($summary['saldo_hutang'], 2, ',', '.') }}
                    </div>
                @else
                    <div class="empty-report text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 no-print"></i>
                        <p class="mb-1 fw-semibold">Tidak ada hutang terbuka pada tanggal laporan.</p>
                        <p class="mb-0 no-print">Coba ubah tanggal laporan atau pilihan supplier.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .report-shell {
            background-color: #fff;
        }

        .summary-report {
            color: #111827;
        }

        .report-header .hospital-name {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .report-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: .25rem 0;
            letter-spacing: .04em;
        }

        .report-header .report-date {
            font-size: .95rem;
            color: #4b5563;
        }

        .report-logo {
            max-height: 64px;
            width: auto;
            object-fit: contain;
        }

        .report-table-wrapper {
            background: #fff;
        }

        .summary-table {
            font-size: .875rem;
            border: 1px solid #dee2e6;
        }

        .summary-table thead th {
            background-color: #f8f9fa;
            font-weight: 700;
            border-bottom: 2px solid #adb5bd;
            white-space: nowrap;
        }

        .summary-table tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.04);
        }

        .summary-table tfoot td {
            font-weight: 800;
            background-color: #d1e7dd;
            color: #0f5132;
            border-top: 2px solid #198754;
            border-bottom: 2px solid #198754;
        }

        .report-balance {
            font-size: 1.05rem;
            color: #0f5132;
        }

        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
            }

            .no-print,
            .main-header,
            .sidebar-wrapper,
            nav,
            header:not(.report-header) {
                display: none !important;
            }

            .report-shell,
            .report-shell .card-body {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .summary-table {
                font-size: 9pt;
            }
        }
    </style>
@endpush
