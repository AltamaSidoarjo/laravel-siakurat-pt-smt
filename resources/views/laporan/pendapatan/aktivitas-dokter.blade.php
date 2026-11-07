@extends('layouts.app')

@section('title', 'Laporan Aktivitas Dokter')

@php
    $exportParams = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'pelaksanaId' => $pelaksanaId,
    ];
@endphp

@push('styles')
    <style>
        .aktivitas-hero-card {
            background: linear-gradient(135deg, #f8fbfd 0%, #eef5fa 100%);
            border: 1px solid #d9e7f2;
            border-radius: 0.75rem;
        }
        .stat-card {
            border: 1px solid #e5edf5;
            border-radius: 0.75rem;
            background: #ffffff;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.6rem;
            font-size: 1.25rem;
        }
        .doctor-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }
        .doctor-card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
        }
        .report-table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 0.75rem 0.85rem;
            border-bottom: 2px solid #cbd5e1;
        }
        .report-table tbody td {
            font-size: 0.82rem;
            padding: 0.65rem 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .category-header-row td {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.6rem 0.85rem;
        }
        .category-subtotal-row td {
            background-color: #fafbfc;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1.5px solid #94a3b8;
            font-size: 0.82rem;
            padding: 0.65rem 0.85rem;
        }
        .doctor-total-row td {
            background-color: #f0fdf4;
            border-top: 2px solid #22c55e;
            border-bottom: 2px solid #22c55e;
            font-size: 0.88rem;
            padding: 0.75rem 0.85rem;
        }
        .invoice-link {
            color: #0284c7;
            font-weight: 600;
            text-decoration: none;
        }
        .invoice-link:hover {
            color: #0369a1;
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
    <!-- Breadcrumb & Page Header -->
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('laporan.pendapatan.index') }}" class="text-secondary text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Menu Laporan Pendapatan
            </a>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Laporan Aktivitas Dokter</h3>
                    <p class="text-muted small mb-0">Rekapitulasi riwayat tindakan medis, visite bangsal, dan layanan poliklinik per dokter pelaksana.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('laporan.pendapatan.aktivitas-dokter.export-pdf', $exportParams) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-file-earmark-pdf"></i> Ekspor PDF
                    </a>
                    <a href="{{ route('laporan.pendapatan.aktivitas-dokter.export-excel', $exportParams) }}"
                       class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-file-earmark-excel"></i> Ekspor Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="{{ route('laporan.pendapatan.aktivitas-dokter') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="startDate" class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" id="startDate" name="startDate" class="form-control" value="{{ $startDate }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="endDate" class="form-label small fw-bold text-secondary">Tanggal Sampai</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-check"></i></span>
                            <input type="date" id="endDate" name="endDate" class="form-control" value="{{ $endDate }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="pelaksanaId" class="form-label small fw-bold text-secondary">Dokter / Pelaksana</label>
                        <select id="pelaksanaId" name="pelaksanaId" class="form-select form-select-sm select2">
                            <option value="">Semua dokter / pelaksana</option>
                            @foreach ($pelaksanaOptions as $pelaksana)
                                <option value="{{ $pelaksana->id }}" @selected((string) $pelaksanaId === (string) $pelaksana->id)>
                                    {{ $pelaksana->nama_pelaksana }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('laporan.pendapatan.aktivitas-dokter') }}" class="btn btn-sm btn-light border" title="Reset filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div>
                    <div class="text-muted small">Dokter Terpilih</div>
                    <div class="fw-bold fs-5 text-dark">{{ $reportData['dokterGroups']->count() }} Dokter</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Tindakan</div>
                    <div class="fw-bold fs-5 text-dark">{{ number_format($reportData['totalTransaksi'], 0, ',', '.') }} Transaksi</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-tags"></i>
                </div>
                <div>
                    <div class="text-muted small">Kategori Layanan</div>
                    <div class="fw-bold fs-5 text-dark">{{ $reportData['totalKategori'] }} Akun Tindakan</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Jasa Medis</div>
                    <div class="fw-bold fs-5 text-success font-monospace">Rp {{ number_format($reportData['grandTotalMutasi'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doctor Activity List -->
    @forelse ($reportData['dokterGroups'] as $dokter)
        <div class="doctor-card mb-4">
            <!-- Doctor Card Header -->
            <div class="doctor-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">
                        <i class="bi bi-hospital fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="fw-bold text-dark mb-0">{{ $dokter['dokter'] }}</h5>
                            @if (!empty($dokter['no_proyek']))
                                <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $dokter['no_proyek'] }}</span>
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            <span><i class="bi bi-activity me-1"></i>{{ $dokter['jumlah_transaksi'] }} Tindakan</span>
                            <span class="mx-2">&bull;</span>
                            <span><i class="bi bi-calendar3 me-1"></i>Periode: {{ $reportData['periodLabel'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="text-muted small d-block">Subtotal Jasa Dokter</span>
                    <span class="fw-extrabold fs-5 text-success font-monospace">
                        Rp {{ number_format($dokter['total_mutasi'], 2, ',', '.') }}
                    </span>
                </div>
            </div>

            <!-- Doctor Card Table -->
            <div class="table-responsive">
                <table class="table report-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 10%;">TANGGAL</th>
                            <th style="width: 13%;">NO. FAKTUR</th>
                            <th style="width: 38%;">PASIEN & KETERANGAN</th>
                            <th style="width: 15%;">LAYANAN / AKUN</th>
                            <th class="text-end" style="width: 10%;">NOMINAL JASA</th>
                            <th class="text-end" style="width: 10%;">KUMULATIF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNumber = 1; @endphp
                        @foreach ($dokter['kategori'] as $kategori)
                            <!-- Category Heading Row -->
                            <tr class="category-header-row">
                                <td colspan="7">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary rounded-pill px-2 py-1"><i class="bi bi-tag-fill"></i></span>
                                            <span class="fw-bold text-dark">{{ $kategori['nama_akun'] }}</span>
                                            <span class="text-muted small">({{ count($kategori['rincian']) }} transaksi)</span>
                                        </div>
                                        <span class="text-muted small font-monospace">Kode: {{ $kategori['kode_akun'] }}</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Detail Transaction Rows -->
                            @foreach ($kategori['rincian'] as $item)
                                <tr>
                                    <td class="text-muted small">{{ $rowNumber++ }}</td>
                                    <td class="text-nowrap">{{ $item['tanggal_format'] ?? $item['tanggal'] }}</td>
                                    <td>
                                        <a href="{{ route('pendapatan.invoice.read', $item['faktur_id']) }}" class="invoice-link font-monospace">
                                            <i class="bi bi-receipt me-1"></i>{{ $item['no_ref'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="fw-semibold text-dark">{{ $item['nama_pasien'] ?: $item['deskripsi'] }}</span>
                                            @if (!empty($item['poli']) || !empty($item['penjamin']))
                                                <div class="d-flex gap-1 mt-1">
                                                    @if (!empty($item['poli']))
                                                        <span class="badge bg-light text-secondary border small">{{ $item['poli'] }}</span>
                                                    @endif
                                                    @if (!empty($item['penjamin']))
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle small">{{ $item['penjamin'] }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($item['deskripsi'] && $item['deskripsi'] !== $item['nama_pasien'])
                                                <div class="text-muted small text-truncate" style="max-width: 480px;" title="{{ $item['deskripsi'] }}">
                                                    {{ $item['deskripsi'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-secondary small">{{ $kategori['nama_akun'] }}</td>
                                    <td class="text-end text-nowrap font-monospace fw-semibold text-dark">
                                        Rp {{ number_format($item['kredit'], 2, ',', '.') }}
                                    </td>
                                    <td class="text-end text-nowrap font-monospace text-muted small">
                                        Rp {{ number_format($item['mutasi'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach

                            <!-- Category Subtotal Row -->
                            <tr class="category-subtotal-row fw-bold">
                                <td colspan="5" class="text-end text-secondary pe-3">
                                    TOTAL {{ $kategori['nama_akun'] }}:
                                </td>
                                <td class="text-end text-nowrap font-monospace text-primary">
                                    Rp {{ number_format($kategori['total_kredit'], 2, ',', '.') }}
                                </td>
                                <td class="text-end text-nowrap font-monospace text-primary">
                                    Rp {{ number_format($kategori['total_mutasi'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach

                        <!-- Doctor Total Row -->
                        <tr class="doctor-total-row fw-bold">
                            <td colspan="5" class="text-end pe-3 text-dark">
                                TOTAL {{ $dokter['dokter'] }}:
                            </td>
                            <td class="text-end text-nowrap font-monospace text-success fs-7">
                                Rp {{ number_format($dokter['total_kredit'], 2, ',', '.') }}
                            </td>
                            <td class="text-end text-nowrap font-monospace text-success fs-7">
                                Rp {{ number_format($dokter['total_mutasi'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-file-earmark-medical fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold text-dark">Tidak Ada Data Aktivitas Dokter</h6>
                <p class="mb-0 small">Tidak ditemukan catatan transaksi tindakan dokter untuk periode dan filter yang dipilih.</p>
            </div>
        </div>
    @endforelse

    <!-- Grand Total Banner (If Multiple Doctors) -->
    @if ($reportData['dokterGroups']->count() > 1)
        <div class="card border-0 bg-dark text-white shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center py-3 px-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-trophy-fill text-warning fs-5"></i>
                    <span class="fw-bold fs-6">TOTAL KESELURUHAN SELURUH DOKTER</span>
                </div>
                <div class="d-flex align-items-center gap-4 font-monospace">
                    <div>
                        <span class="text-secondary small d-block">Total Transaksi</span>
                        <span class="fw-bold">{{ number_format($reportData['totalTransaksi'], 0, ',', '.') }} Item</span>
                    </div>
                    <div>
                        <span class="text-secondary small d-block">Grand Total Jasa</span>
                        <span class="fw-bold text-success fs-5">Rp {{ number_format($reportData['grandTotalMutasi'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            if ($.fn.select2) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
        });
    </script>
@endpush
