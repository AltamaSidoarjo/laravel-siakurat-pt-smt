@extends('layouts.app')

@section('title', 'Rincian Buku Pembantu Hutang')

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
                <span class="fw-bold">Rincian Buku Pembantu Hutang</span>
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
                'formAction' => route('laporan.pembelian.buku-pembantu-hutang'),
                'resetAction' => route('laporan.pembelian.buku-pembantu-hutang'),
                'exportAction' => route('laporan.pembelian.buku-pembantu-hutang.export-csv'),
            ])

            <div class="aging-report" id="agingReport">
                <header class="report-header text-center mb-4">
                    @if ($logoRsUrl)
                        <img src="{{ $logoRsUrl }}" alt="Logo rumah sakit" class="report-logo mb-2">
                    @endif
                    <div class="hospital-name">{{ $namaRumahSakit }}</div>
                    <h1>RINCIAN BUKU PEMBANTU HUTANG</h1>
                    <div class="report-date">Posisi per {{ $reportDateCarbon->translatedFormat('d F Y') }}</div>
                </header>

                @forelse ($cards as $card)
                    <section class="supplier-section">
                        <h2 class="supplier-title">
                            {{ strtoupper($card['nama_supplier']) }} | {{ $card['kode_supplier'] ?: '-' }} (IDR)
                        </h2>

                        <div class="table-responsive report-table-wrapper">
                            <table class="table table-sm mb-0 aging-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Tipe</th>
                                        <th>No. Referensi</th>
                                        <th class="text-end">0 - 30 Hari</th>
                                        <th class="text-end">31 - 60 Hari</th>
                                        <th class="text-end">61 - 90 Hari</th>
                                        <th class="text-end">&gt; 90 Hari</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($card['rows'] as $row)
                                        <tr>
                                            <td class="text-nowrap">{{ Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                                            <td class="text-nowrap">{{ Carbon::parse($row['tanggal_jatuh_tempo'])->format('d/m/Y') }}</td>
                                            <td>{{ $row['tipe'] }}</td>
                                            <td class="text-nowrap">
                                                <a href="{{ route('pembelian.invoice.read', $row['faktur_id']) }}" class="reference-link">
                                                    {{ $row['nomor_referensi'] }}
                                                </a>
                                            </td>
                                            <td class="text-end">{{ $formatAmount($row['days_0_30']) }}</td>
                                            <td class="text-end">{{ $formatAmount($row['days_31_60']) }}</td>
                                            <td class="text-end">{{ $formatAmount($row['days_61_90']) }}</td>
                                            <td class="text-end">{{ $formatAmount($row['days_over_90']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4">Saldo {{ $card['nama_supplier'] }}:</td>
                                        <td class="text-end">{{ number_format($card['totals']['days_0_30'], 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($card['totals']['days_31_60'], 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($card['totals']['days_61_90'], 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($card['totals']['days_over_90'], 2, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="empty-report text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 no-print"></i>
                        <p class="mb-1 fw-semibold">Tidak ada hutang terbuka pada tanggal laporan.</p>
                        <p class="mb-0 no-print">Coba ubah tanggal laporan atau pilihan supplier.</p>
                    </div>
                @endforelse

                @if ($cards !== [])
                    <div class="table-responsive report-table-wrapper grand-total-wrapper">
                        <table class="table table-sm mb-0 aging-table grand-total-table">
                            <tbody>
                                <tr>
                                    <td colspan="4">GRAND TOTAL</td>
                                    <td class="text-end">{{ number_format($summary['days_0_30'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_31_60'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_61_90'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($summary['days_over_90'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end fw-bold mt-2 report-balance">
                        Total Hutang: IDR {{ number_format($summary['saldo_hutang'], 2, ',', '.') }}
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

        .aging-report {
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

        .supplier-section {
            margin-bottom: 1.25rem;
        }

        .supplier-title {
            color: #1680a8;
            font-size: .95rem;
            font-weight: 800;
            margin: 0 0 .4rem;
            text-align: center;
        }

        .aging-table {
            border-collapse: collapse;
            font-size: .76rem;
            table-layout: fixed;
            width: 100%;
        }

        .aging-table th,
        .aging-table td {
            border: 0;
            padding: .32rem .4rem;
            vertical-align: middle;
        }

        .aging-table thead th {
            background: #e9e9e9;
            color: #222;
            font-size: .7rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .aging-table tbody tr:nth-child(even) td {
            background: #f2f2f2;
        }

        .aging-table tfoot td,
        .grand-total-table td {
            background: #d9d9d9;
            font-weight: 800;
        }

        .aging-table th:nth-child(1),
        .aging-table td:nth-child(1),
        .aging-table th:nth-child(2),
        .aging-table td:nth-child(2) {
            width: 11%;
        }

        .aging-table th:nth-child(3),
        .aging-table td:nth-child(3) {
            width: 7%;
        }

        .aging-table th:nth-child(4),
        .aging-table td:nth-child(4) {
            width: 21%;
        }

        .aging-table th:nth-child(n+5),
        .aging-table td:nth-child(n+5) {
            width: 12.5%;
        }

        .reference-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }

        .reference-link:hover {
            color: var(--bs-primary);
            text-decoration: underline;
        }

        .grand-total-wrapper {
            margin-top: .25rem;
        }

        .grand-total-table td {
            border-top: 2px solid #777;
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

            .aging-report {
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

            .report-date,
            .supplier-title {
                font-size: 8pt;
            }

            .supplier-section {
                break-inside: auto;
                margin-bottom: 4mm;
            }

            .supplier-title {
                break-after: avoid;
            }

            .report-table-wrapper {
                overflow: visible !important;
            }

            .aging-table {
                font-size: 6.8pt;
            }

            .aging-table thead {
                display: table-header-group;
            }

            .aging-table tfoot {
                display: table-row-group;
            }

            .aging-table tr {
                break-inside: avoid;
            }

            .aging-table th,
            .aging-table td {
                padding: 1.2mm 1mm;
            }

            .reference-link {
                color: #000 !important;
                text-decoration: none !important;
            }

            .grand-total-wrapper,
            .report-balance {
                break-inside: avoid;
            }
        }
    </style>
@endpush
