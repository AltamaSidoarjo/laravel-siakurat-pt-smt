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
                    <section class="customer-section">
                        <h2 class="customer-title">
                            {{ strtoupper($card['nama_supplier']) }} | {{ $card['kode_supplier'] ?: '-' }} (IDR)
                        </h2>

                        <div class="table-responsive report-table-wrapper">
                            <table class="table table-sm mb-0 aging-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                        <th>No. Referensi</th>
                                        <th>Keterangan</th>
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
                                            <td>{{ $row['tipe'] }}</td>
                                            <td class="text-nowrap">
                                                <a href="{{ route('pembelian.invoice.read', $row['faktur_id']) }}" class="reference-link">
                                                    {{ $row['nomor_referensi'] }}
                                                </a>
                                            </td>
                                            <td>{{ $row['keterangan'] !== '' ? $row['keterangan'] : '-' }}</td>
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
                    <footer class="grand-total-section">
                        <div class="table-responsive report-table-wrapper">
                            <table class="table table-sm mb-0 grand-total-table">
                                <thead>
                                    <tr>
                                        <th colspan="4" class="grand-total-title">GRAND TOTAL</th>
                                        <th class="text-end">{{ number_format($summary['days_0_30'], 2, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format($summary['days_31_60'], 2, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format($summary['days_61_90'], 2, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format($summary['days_over_90'], 2, ',', '.') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        <div class="text-end fw-bold mt-2 report-balance">
                            Total Hutang: {{ number_format($summary['saldo_hutang'], 2, ',', '.') }}
                        </div>
                    </footer>
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

        .aging-report {
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

        .customer-section {
            margin-bottom: 2rem;
            page-break-inside: avoid;
        }

        .customer-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .5rem;
            padding-bottom: .25rem;
            border-bottom: 2px solid #198754;
            color: #0f5132;
            letter-spacing: .02em;
        }

        .report-table-wrapper {
            background: #fff;
        }

        .aging-table {
            font-size: .875rem;
            border: 1px solid #dee2e6;
        }

        .aging-table thead th {
            background-color: #f8f9fa;
            font-weight: 700;
            border-bottom: 2px solid #adb5bd;
            white-space: nowrap;
        }

        .aging-table tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.04);
        }

        .aging-table tfoot td {
            font-weight: 700;
            background-color: #f8f9fa;
            border-top: 2px solid #adb5bd;
            border-bottom: 1px solid #dee2e6;
        }

        .grand-total-section {
            margin-top: 2rem;
            page-break-inside: avoid;
        }

        .grand-total-table {
            font-size: .925rem;
            border: 2px solid #198754;
        }

        .grand-total-table th {
            background-color: #d1e7dd;
            color: #0f5132;
            font-weight: 800;
            padding-top: .65rem;
            padding-bottom: .65rem;
        }

        .grand-total-title {
            letter-spacing: .05em;
        }

        .report-balance {
            font-size: 1.05rem;
            color: #0f5132;
        }

        .reference-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
        }

        .reference-link:hover {
            text-decoration: underline;
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
            header:not(.report-header),
            footer:not(.grand-total-section) {
                display: none !important;
            }

            .report-shell,
            .report-shell .card-body {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .customer-section {
                page-break-inside: avoid;
            }

            .reference-link {
                color: #000 !important;
                text-decoration: none !important;
            }

            .aging-table,
            .grand-total-table {
                font-size: 9pt;
            }
        }
    </style>
@endpush
