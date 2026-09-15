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
        .report-logo {
            display: inline-block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 58px;
            object-fit: contain;
        }

        .summary-report {
            background: #fff;
            padding: 1.5rem;
        }

        .hospital-name {
            color: #202020;
            font-size: 1rem;
            font-weight: 700;
        }

        .report-header h1 {
            color: #1680a8;
            font-size: 1.15rem;
            font-weight: 800;
            margin: .35rem 0;
        }

        .report-date {
            color: #842029;
            font-size: .82rem;
            font-weight: 600;
        }

        .summary-table {
            border-collapse: collapse;
            font-size: .8rem;
            table-layout: fixed;
            width: 100%;
        }

        .summary-table th,
        .summary-table td {
            border: 0;
            padding: .35rem .45rem;
            vertical-align: middle;
        }

        .summary-table th:first-child,
        .summary-table td:first-child {
            width: 40%;
        }

        .summary-table th:not(:first-child),
        .summary-table td:not(:first-child) {
            width: 15%;
        }

        .summary-table thead th {
            background: #e1e1e1;
            color: #222;
            font-size: .73rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .summary-table tbody tr:nth-child(even) td {
            background: #f2f2f2;
        }

        .summary-table tfoot td {
            background: #d9d9d9;
            border-top: 2px solid #777;
            font-weight: 800;
        }

        .empty-report {
            border: 1px dashed var(--bs-border-color);
            border-radius: .5rem;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            #app-sidebar,
            #sidebar-backdrop,
            #mobile-sidebar-toggle,
            footer,
            .no-print {
                display: none !important;
            }

            html,
            body,
            #app-content,
            main,
            .container-fluid {
                background: #fff !important;
                margin: 0 !important;
                max-width: none !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .report-shell,
            .report-shell > .card-body {
                border: 0 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .summary-report {
                padding: 0;
            }

            .report-logo {
                max-height: 42px;
            }

            .report-header {
                margin-bottom: 4mm !important;
            }

            .hospital-name {
                font-size: 10pt;
            }

            .report-header h1 {
                font-size: 11pt;
            }

            .report-date {
                font-size: 8pt;
            }

            .report-table-wrapper {
                overflow: visible !important;
            }

            .summary-table {
                font-size: 7pt;
            }

            .summary-table thead {
                display: table-header-group;
            }

            .summary-table tfoot {
                display: table-row-group;
            }

            .summary-table tr {
                break-inside: avoid;
            }

            .summary-table th,
            .summary-table td {
                padding: 1.2mm 1mm;
            }

            .report-balance {
                break-inside: avoid;
            }
        }
    </style>
@endpush
