@extends('layouts.app')

@section('title', 'Rekonsiliasi Pendapatan Dokter')

@php
    $exportParams = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'coaId' => $coaId,
        'filter_applied' => '1',
        'hanyaBerselisih' => $hanyaBerselisih ? '1' : '0',
    ];
@endphp

@push('styles')
    <style>
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
        .rekon-table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 0.75rem 0.85rem;
            border-bottom: 2px solid #cbd5e1;
        }
        .rekon-table tbody td {
            font-size: 0.82rem;
            padding: 0.7rem 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-detail-table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 0.65rem 0.75rem;
            border-bottom: 2px solid #cbd5e1;
        }
        .modal-detail-table tbody td {
            font-size: 0.8rem;
            padding: 0.6rem 0.75rem;
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <!-- Breadcrumb & Header -->
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('laporan.pendapatan.index') }}" class="text-secondary text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Menu Laporan Pendapatan
            </a>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Rekonsiliasi Pendapatan Dokter</h3>
                    <p class="text-muted small mb-0">Audit perbandingan saldo buku besar (Laba Rugi) dengan pendapatan dokter untuk melacak transaksi tanpa nama dokter/pelaksana.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('laporan.pendapatan.rekonsiliasi-dokter.export-excel', $exportParams) }}"
                       class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-file-earmark-excel"></i> Ekspor Excel
                    </a>
                    <a href="{{ route('laporan.pendapatan.rekonsiliasi-dokter.export-csv', $exportParams) }}"
                       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-filetype-csv"></i> Ekspor CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="{{ route('laporan.pendapatan.rekonsiliasi-dokter') }}">
                <input type="hidden" name="filter_applied" value="1">
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
                        <label for="coaId" class="form-label small fw-bold text-secondary">Akun Pendapatan (COA)</label>
                        <select id="coaId" name="coaId" class="form-select form-select-sm select2">
                            <option value="">Semua Akun Pendapatan</option>
                            @foreach ($coaOptions as $coa)
                                <option value="{{ $coa->id }}" @selected((string) $coaId === (string) $coa->id)>
                                    [{{ $coa->kode }}] {{ $coa->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('laporan.pendapatan.rekonsiliasi-dokter') }}" class="btn btn-sm btn-light border" title="Reset filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="hanyaBerselisih" name="hanyaBerselisih" value="1" @checked($hanyaBerselisih)>
                            <label class="form-check-label small text-muted" for="hanyaBerselisih">
                                Hanya tampilkan akun yang berselisih (memiliki transaksi tanpa dokter)
                            </label>
                        </div>
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
                    <i class="bi bi-journal-text"></i>
                </div>
                <div>
                    <div class="text-muted small">Buku Besar (Laba Rugi)</div>
                    <div class="fw-bold fs-5 text-dark">Rp {{ number_format($reportData['summary']['totalBukuBesar'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="text-muted small">Pendapatan Dokter</div>
                    <div class="fw-bold fs-5 text-dark">Rp {{ number_format($reportData['summary']['totalPendapatanDokter'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <div class="text-muted small">Selisih (Tanpa Dokter)</div>
                    <div class="fw-bold fs-5 text-danger">Rp {{ number_format($reportData['summary']['totalSelisih'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <div>
                    <div class="text-muted small">Akun Berselisih</div>
                    <div class="fw-bold fs-5 text-dark">{{ $reportData['summary']['jumlahAkunBerselisih'] }} / {{ $reportData['summary']['jumlahTotalAkun'] }} Akun</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Comparison Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-dark mb-0">Ringkasan Rekonsiliasi Per Akun COA</h6>
                <span class="text-muted small">Perbandingan saldo buku besar mutasi pendapatan dengan pendapatan tercatat dokter</span>
            </div>
            <span class="badge bg-light text-secondary border">Total {{ count($reportData['rows']) }} Baris</span>
        </div>
        <div class="table-responsive">
            <table class="table rekon-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th style="width: 140px;">Kode Akun</th>
                        <th>Nama Akun / Layanan</th>
                        <th style="width: 130px;">Tipe COA</th>
                        <th class="text-end" style="width: 160px;">Buku Besar</th>
                        <th class="text-end" style="width: 160px;">Pendapatan Dokter</th>
                        <th class="text-end" style="width: 160px;">Tanpa Dokter</th>
                        <th class="text-end" style="width: 150px;">Selisih</th>
                        <th class="text-center" style="width: 110px;">Status</th>
                        <th class="text-center" style="width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reportData['rows'] as $index => $row)
                        <tr>
                            <td class="text-center text-muted">{{ $index + 1 }}</td>
                            <td>
                                <span class="fw-bold font-monospace text-primary">{{ $row['kode_akun'] }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $row['nama_akun'] }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">{{ $row['tipe_coa'] }}</span>
                            </td>
                            <td class="text-end font-monospace">
                                Rp {{ number_format($row['total_buku_besar'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace text-success">
                                Rp {{ number_format($row['total_pendapatan_dokter'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace text-warning">
                                Rp {{ number_format($row['total_tanpa_dokter'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace {{ $row['ada_selisih'] ? 'text-danger fw-bold' : 'text-muted' }}">
                                Rp {{ number_format($row['selisih'], 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                @if ($row['ada_selisih'])
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-exclamation-circle me-1"></i> Berselisih
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i> Sesuai
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($row['jumlah_transaksi_tanpa_dokter'] > 0 || $row['ada_selisih'])
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger btn-detail-tanpa-dokter d-inline-flex align-items-center gap-1"
                                            data-coa-id="{{ $row['coa_id'] }}"
                                            data-kode="{{ $row['kode_akun'] }}"
                                            data-nama="{{ $row['nama_akun'] }}"
                                            data-selisih="{{ number_format($row['selisih'], 0, ',', '.') }}"
                                            data-count="{{ $row['jumlah_transaksi_tanpa_dokter'] }}">
                                        <i class="bi bi-search"></i> Rincian ({{ $row['jumlah_transaksi_tanpa_dokter'] }})
                                    </button>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <div class="py-3">
                                    <i class="bi bi-check2-circle text-success fs-1 mb-2 d-block"></i>
                                    <h6 class="fw-bold mb-1">Tidak Ditemukan Selisih Akun Pendapatan</h6>
                                    <p class="small text-muted mb-0">Seluruh saldo pendapatan buku besar telah terpetakan dengan tepat ke nama dokter pelaksana pada periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($reportData['rows']) > 0)
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">TOTAL:</td>
                            <td class="text-end font-monospace">Rp {{ number_format($reportData['summary']['totalBukuBesar'], 0, ',', '.') }}</td>
                            <td class="text-end font-monospace text-success">Rp {{ number_format($reportData['summary']['totalPendapatanDokter'], 0, ',', '.') }}</td>
                            <td class="text-end font-monospace text-warning">Rp {{ number_format($reportData['summary']['totalTanpaDokter'], 0, ',', '.') }}</td>
                            <td class="text-end font-monospace text-danger">Rp {{ number_format($reportData['summary']['totalSelisih'], 0, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Modal Drill-Down Transaksi Tanpa Dokter -->
    <div class="modal fade" id="modalDetailTanpaDokter" tabindex="-1" aria-labelledby="modalDetailTanpaDokterLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger-subtle text-danger py-3 border-0">
                    <div>
                        <h5 class="modal-title fw-bold" id="modalDetailTanpaDokterLabel">
                            <i class="bi bi-clipboard2-x me-2"></i>Rincian Transaksi Tanpa Pelaksana
                        </h5>
                        <div class="small text-muted" id="modalSubHeader">Memuat rincian transaksi...</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="small text-muted mt-2">Sedang mengambil rincian transaksi...</div>
                    </div>

                    <div id="modalTableContainer" class="table-responsive d-none">
                        <table class="table modal-detail-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px;">No</th>
                                    <th style="width: 100px;">Tanggal</th>
                                    <th style="width: 130px;">No Referensi</th>
                                    <th style="width: 120px;">Sumber</th>
                                    <th>Pasien / RM</th>
                                    <th>Poli & Penjamin</th>
                                    <th>Status SIMRS / Catatan</th>
                                    <th class="text-end" style="width: 120px;">Nominal</th>
                                    <th class="text-center" style="width: 110px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>

                    <div id="modalEmpty" class="text-center py-5 d-none">
                        <i class="bi bi-info-circle text-muted fs-2 mb-2 d-block"></i>
                        <p class="text-muted small mb-0">Tidak ada rincian baris transaksi yang pelaksananya kosong untuk akun ini.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <span class="text-muted small me-auto">
                        <i class="bi bi-info-circle me-1"></i> Klik tombol <strong>Buka Faktur / Jurnal</strong> untuk melengkapi dokter di form aslinya.
                    </span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const detailModalElem = document.getElementById('modalDetailTanpaDokter');
            if (!detailModalElem) return;

            const modal = new bootstrap.Modal(detailModalElem);
            const subHeader = document.getElementById('modalSubHeader');
            const loadingElem = document.getElementById('modalLoading');
            const tableContainer = document.getElementById('modalTableContainer');
            const tableBody = document.getElementById('modalTableBody');
            const emptyElem = document.getElementById('modalEmpty');

            const startDate = '{{ $startDate }}';
            const endDate = '{{ $endDate }}';

            document.querySelectorAll('.btn-detail-tanpa-dokter').forEach(button => {
                button.addEventListener('click', function () {
                    const coaId = this.getAttribute('data-coa-id');
                    const kode = this.getAttribute('data-kode');
                    const nama = this.getAttribute('data-nama');
                    const selisih = this.getAttribute('data-selisih');
                    const count = this.getAttribute('data-count');

                    subHeader.innerHTML = `Akun: <span class="fw-bold text-dark font-monospace">[${kode}] ${nama}</span> &bull; Selisih: <span class="fw-bold text-danger">Rp ${selisih}</span> (${count} Transaksi)`;

                    loadingElem.classList.remove('d-none');
                    tableContainer.classList.add('d-none');
                    emptyElem.classList.add('d-none');
                    tableBody.innerHTML = '';

                    modal.show();

                    const url = `{{ route('laporan.pendapatan.rekonsiliasi-dokter.detail') }}?startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}&coaId=${encodeURIComponent(coaId)}`;

                    fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(async response => {
                        if (!response.ok) {
                            const errJson = await response.json().catch(() => null);
                            const errMsg = errJson?.message || 'Gagal memuat data (HTTP ' + response.status + ')';
                            throw new Error(errMsg);
                        }
                        return response.json();
                    })
                    .then(res => {
                        loadingElem.classList.add('d-none');
                        const data = res.data || [];

                        if (data.length === 0) {
                            emptyElem.classList.remove('d-none');
                            return;
                        }

                        tableContainer.classList.remove('d-none');
                        let rowsHtml = '';
                        data.forEach((item, idx) => {
                            const badgeColor = item.sumber === 'Invoice Pendapatan' ? 'bg-primary-subtle text-primary border-primary-subtle' : (item.sumber === 'Jurnal Umum' ? 'bg-info-subtle text-info border-info-subtle' : 'bg-warning-subtle text-warning border-warning-subtle');
                            const simrsBadge = item.kode_proyek ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">${escapeHtml(item.status_simrs)}</span>` : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${escapeHtml(item.status_simrs)}</span>`;
                            const formattedNominal = new Intl.NumberFormat('id-ID').format(item.nominal);

                            rowsHtml += `
                                <tr>
                                    <td class="text-center text-muted">${idx + 1}</td>
                                    <td><span class="font-monospace">${escapeHtml(item.tanggal)}</span></td>
                                    <td>
                                        <a href="${item.link_url}" target="_blank" class="fw-bold font-monospace text-decoration-none">
                                            ${escapeHtml(item.nomor_ref)} <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge ${badgeColor} border">${escapeHtml(item.sumber)}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">${escapeHtml(item.nama_pasien)}</div>
                                        <div class="small text-muted font-monospace">${escapeHtml(item.nomer_rekam_medis)}</div>
                                    </td>
                                    <td>
                                        <div>${escapeHtml(item.nama_poli)}</div>
                                        <div class="small text-muted">${escapeHtml(item.nama_penjamin)}</div>
                                    </td>
                                    <td>
                                        <div class="mb-1">${simrsBadge}</div>
                                        <div class="small text-muted text-truncate" style="max-width: 250px;" title="${escapeHtml(item.catatan)}">${escapeHtml(item.catatan)}</div>
                                    </td>
                                    <td class="text-end font-monospace fw-semibold text-danger">
                                        Rp ${formattedNominal}
                                    </td>
                                    <td class="text-center">
                                        <a href="${item.link_url}" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-pencil-square"></i> ${escapeHtml(item.link_label)}
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                        tableBody.innerHTML = rowsHtml;
                    })
                    .catch(err => {
                        loadingElem.classList.add('d-none');
                        emptyElem.classList.remove('d-none');
                        emptyElem.innerHTML = `<div class="text-danger py-4"><i class="bi bi-exclamation-triangle fs-2 mb-2 d-block"></i> Terjadi kesalahan saat memuat rincian data: ${escapeHtml(err.message)}</div>`;
                    });
                });
            });

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        });
    </script>
@endpush
