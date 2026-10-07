@extends('layouts.app')

@section('title', 'Neraca Standard')

@php
    $formatNominal = function ($val) {
        $num = (float) $val;
        if (abs($num) < 0.0001) {
            return '0';
        }
        $formatted = number_format(abs($num), 0, ',', '.');
        return $num < 0 ? '(' . $formatted . ')' : $formatted;
    };

    $rowsCollection = collect($rows ?? []);
    $hasRows = $rowsCollection->isNotEmpty();
@endphp

@section('content')
    {{-- Breadcrumb & Title --}}
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-4">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark text-decoration-none hover-opacity" title="Kembali ke Menu Laporan">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Neraca Standard</span>
            </div>
            <div class="text-muted small ps-4 ms-2">Laporan posisi keuangan (Aktiva, Pasiva, dan Ekuitas) per tanggal cut-off tertentu</div>
        </div>
    </div>

    <div class="card border-muhammadiyah shadow-sm mb-4">
        <div class="card-body d-flex flex-column gap-3 p-3 p-md-4">

            {{-- 1. Header Filter (Jurnal.id Style) --}}
            <div class="card border-light bg-light bg-opacity-50 shadow-sm no-print">
                <div class="card-body p-3">
                    <form method="get" action="{{ route('laporan.keuangan.neraca-standard') }}" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="periodeSelect" class="form-label fw-semibold text-secondary small mb-1">
                                    <i class="bi bi-calendar-check me-1"></i>Periode / Cut-off
                                </label>
                                <select id="periodeSelect" class="form-select form-select-sm">
                                    <option value="hari_ini">Hari Ini</option>
                                    <option value="akhir_bulan_ini">Akhir Bulan Ini</option>
                                    <option value="akhir_bulan_lalu">Akhir Bulan Lalu</option>
                                    <option value="akhir_tahun_ini">Akhir Tahun Ini</option>
                                    <option value="akhir_tahun_lalu">Akhir Tahun Lalu</option>
                                    <option value="kustom">Kustom</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="perDate" class="form-label fw-semibold text-secondary small mb-1">Per Tanggal</label>
                                <input type="date" name="perDate" id="perDate" class="form-control form-control-sm" value="{{ $perDate }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-funnel"></i>
                                        <span>Filter</span>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-inline-flex align-items-center gap-1" type="button" id="dropdownExport" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-download"></i>
                                            <span>Ekspor</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownExport">
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:void(0)" onclick="exportTableToExcel()">
                                                    <i class="bi bi-file-earmark-excel text-success fs-5"></i>
                                                    <div>
                                                        <div class="fw-semibold">Excel (.xlsx)</div>
                                                        <small class="text-muted">Unduh lembar kerja data bersih</small>
                                                    </div>
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:void(0)" onclick="printReport()">
                                                    <i class="bi bi-printer text-primary fs-5"></i>
                                                    <div>
                                                        <div class="fw-semibold">Cetak / PDF</div>
                                                        <small class="text-muted">Tampilan cetak rapi</small>
                                                    </div>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 2. Executive Metric Cards (Modern, Clean, Corporate) --}}
            <div class="row g-3 no-print">
                {{-- Total Aktiva --}}
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border border-light-subtle shadow-sm h-100 bg-white">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.725rem; letter-spacing: 0.04em;">Total Aktiva (Aset)</span>
                                <span class="badge bg-success-subtle text-success rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                    <i class="bi bi-wallet2 fs-7"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-5 fw-bold font-monospace text-dark text-truncate">{{ number_format((float) $subtotalAktiva, 0, ',', '.') }}</div>
                                <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">Total seluruh aset per {{ $perDate }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Total Pasiva --}}
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border border-light-subtle shadow-sm h-100 bg-white">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.725rem; letter-spacing: 0.04em;">Total Pasiva (Kewajiban)</span>
                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                    <i class="bi bi-credit-card-2-front fs-7"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-5 fw-bold font-monospace text-dark text-truncate">{{ number_format((float) $subtotalPasiva, 0, ',', '.') }}</div>
                                <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">Total seluruh liabilitas & hutang</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Total Ekuitas --}}
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border border-light-subtle shadow-sm h-100 bg-white">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.725rem; letter-spacing: 0.04em;">Total Ekuitas (Modal)</span>
                                <span class="badge bg-info-subtle text-info-emphasis rounded-circle p-1.5 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                    <i class="bi bi-pie-chart fs-7"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-5 fw-bold font-monospace text-dark text-truncate">{{ number_format((float) $subtotalEkuitas, 0, ',', '.') }}</div>
                                <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">Modal & laba periode berjalan</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Total Pasiva + Ekuitas & Status --}}
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border {{ $isBalance ? 'border-success-subtle' : 'border-danger-subtle' }} shadow-sm h-100 bg-white">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.725rem; letter-spacing: 0.04em;">Pasiva + Ekuitas</span>
                                @if ($isBalance)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 small fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Balance
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5 small fw-semibold">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Tidak Balance
                                    </span>
                                @endif
                            </div>
                            <div>
                                <div class="fs-5 fw-bold font-monospace text-dark text-truncate">{{ number_format((float) $subtotalPasivaEkuitas, 0, ',', '.') }}</div>
                                <div class="small mt-0.5 {{ $isBalance ? 'text-success' : 'text-danger fw-semibold' }}" style="font-size: 0.75rem;">
                                    @if ($isBalance)
                                        <i class="bi bi-shield-check me-1"></i> Posisi seimbang dengan aktiva
                                    @else
                                        <i class="bi bi-exclamation-circle me-1"></i> Selisih: Rp {{ number_format((float) $selisih, 2, ',', '.') }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Printable Area & Table --}}
            <div class="card border-0 shadow-none" id="printableArea">
                <div class="card-body p-0">
                    {{-- Header Identitas Laporan --}}
                    <div class="laporan-header text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="laporan-header__logo mb-2">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" class="laporan-header__logo-image">
                            </div>
                        @endif
                        <div class="laporan-header__identity">
                            <div class="fw-bold fs-5 text-dark laporan-header__title-rs">{{ $namaRumahSakit }}</div>
                            <div class="fw-bold fs-4 text-dark laporan-header__title-report">Neraca Standard</div>
                            <div class="text-muted small">Per {{ \Carbon\Carbon::parse($perDate)->translatedFormat('d F Y') }}</div>
                        </div>
                    </div>

                    {{-- Table Responsive with Sticky First Column & Tree Toggle --}}
                    <div class="table-responsive border rounded-3 bg-white">
                        <table class="table table-sm table-neraca align-middle mb-0" id="neraca-standard-table">
                            <thead>
                                <tr>
                                    {{-- Kolom Akun (Sticky Kiri) + Toggle Expand/Collapse All --}}
                                    <th class="align-middle sticky-col col-akun-header" style="min-width: 340px;">
                                        <div class="d-flex align-items-center justify-content-between py-1">
                                            <span class="fw-bold text-dark">Akun</span>
                                            <button type="button" id="btnToggleAll" class="btn btn-xs btn-outline-secondary py-0 px-2 fs-8 no-print d-inline-flex align-items-center gap-1" data-state="expanded" title="Buka / Tutup Seluruh Sub-Akun">
                                                <i class="bi bi-dash-square"></i>
                                                <span>[-] Collapse All</span>
                                            </button>
                                        </div>
                                    </th>

                                    {{-- Kolom Tipe --}}
                                    <th class="align-middle col-tipe-header" style="width: 170px;">
                                        <div class="fw-bold text-dark">Tipe Akun</div>
                                    </th>

                                    {{-- Kolom Saldo --}}
                                    <th class="text-end align-middle col-saldo-header" style="width: 220px;">
                                        <div class="fw-bold text-dark">Saldo (Rp)</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    // Helper render baris akun individual/parent
                                    $renderAccountRow = function ($row, $sectionKey) use ($perDate, $formatNominal) {
                                        $indentLevel = max((int) ($row['level'] ?? 1) - 1, 0);
                                        $hasChildren = (bool) ($row['has_children'] ?? false);
                                        $rowId = 'coa-' . ($row['coa_id'] ?? uniqid());
                                        $parentId = !empty($row['parent_coa']) ? (string) $row['parent_coa'] : '';

                                        $rowClass = 'neraca-account-row';
                                        if ($hasChildren) {
                                            $rowClass .= ' parent-account-row fw-semibold';
                                        } else {
                                            $rowClass .= ' child-account-row';
                                        }

                                        $saldoVal = (float) ($row['saldo'] ?? 0.0);
                                        $excelLabel = str_repeat('   ', $indentLevel) . ($row['kode_coa'] ?? '') . ' - ' . ($row['nama_coa'] ?? '');

                                        $html = '<tr id="' . $rowId . '" class="' . $rowClass . '" data-id="' . ($row['coa_id'] ?? '') . '" data-parent-id="' . $parentId . '" data-level="' . ($row['level'] ?? 1) . '" data-has-children="' . ($hasChildren ? '1' : '0') . '" data-section="' . $sectionKey . '">';

                                        // Kolom 1: Akun (Sticky Kiri)
                                        $html .= '<td class="sticky-col text-nowrap" data-excel-label="' . e($excelLabel) . '">';
                                        $html .= '<div class="d-flex align-items-center" style="padding-left: ' . ($indentLevel * 1.5) . 'rem;">';
                                        if ($hasChildren) {
                                            $html .= '<button type="button" class="btn btn-tree-toggle text-secondary p-0 border-0 bg-transparent me-1 no-print" data-coa-id="' . ($row['coa_id'] ?? '') . '" title="Buka / Tutup Sub Akun">';
                                            $html .= '<i class="bi bi-dash-square tree-icon text-muted"></i>';
                                            $html .= '</button>';
                                        } else {
                                            $html .= '<span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>';
                                        }
                                        $html .= '<span class="account-code text-muted me-2 font-monospace">' . e($row['kode_coa'] ?? '') . '</span>';
                                        $html .= '<span class="text-muted me-2">-</span>';

                                        if (!$row['is_root'] && $hasChildren && !empty($row['coa_id'])) {
                                            $url = route('laporan.keuangan.neraca-per-parent-coa', ['coaId' => $row['coa_id'], 'perDate' => $perDate]);
                                            $html .= '<a href="' . $url . '" class="account-name text-decoration-none text-dark hover-underline" title="Lihat detail per parent COA">';
                                            $html .= e($row['nama_coa'] ?? '');
                                            $html .= '<i class="bi bi-box-arrow-up-right ms-1 text-muted fs-8 no-print"></i>';
                                            $html .= '</a>';
                                        } else {
                                            $html .= '<span class="account-name">' . e($row['nama_coa'] ?? '') . '</span>';
                                        }
                                        $html .= '</div>';
                                        $html .= '</td>';

                                        // Kolom 2: Tipe Akun
                                        $html .= '<td class="text-secondary small" data-excel-tipe="' . e($row['tipe_coa'] ?? '') . '">';
                                        $html .= '<span class="badge bg-light text-secondary border fw-normal">' . e($row['tipe_coa'] ?? '') . '</span>';
                                        $html .= '</td>';

                                        // Kolom 3: Saldo
                                        $html .= '<td class="text-end" data-excel-value="' . $saldoVal . '">';
                                        $html .= '<div class="nominal-val font-monospace ' . ($hasChildren ? 'fw-semibold text-dark' : 'text-body') . '">' . $formatNominal($saldoVal) . '</div>';
                                        $html .= '</td>';

                                        $html .= '</tr>';
                                        return $html;
                                    };

                                    $currentSection = null;
                                @endphp

                                @forelse ($rowsCollection as $row)
                                    @if ($row['is_root'])
                                        {{-- Jika section sebelumnya berakhir, tampilkan subtotal section tersebut --}}
                                        @if ($currentSection === 'AKTIVA')
                                            <tr class="section-subtotal-row table-light fw-bold" data-section="aktiva">
                                                <td class="sticky-col ps-3" data-excel-label="Subtotal Aktiva">
                                                    <div class="d-flex align-items-center">
                                                        <span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>
                                                        <span class="text-uppercase">Total Aktiva</span>
                                                    </div>
                                                </td>
                                                <td class="text-secondary small" data-excel-tipe="AKTIVA">AKTIVA</td>
                                                <td class="text-end" data-excel-value="{{ (float) $subtotalAktiva }}">
                                                    <div class="nominal-val font-monospace fw-bold">{{ $formatNominal($subtotalAktiva) }}</div>
                                                </td>
                                            </tr>
                                        @elseif ($currentSection === 'PASIVA')
                                            <tr class="section-subtotal-row table-light fw-bold" data-section="pasiva">
                                                <td class="sticky-col ps-3" data-excel-label="Subtotal Pasiva">
                                                    <div class="d-flex align-items-center">
                                                        <span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>
                                                        <span class="text-uppercase">Total Pasiva (Kewajiban)</span>
                                                    </div>
                                                </td>
                                                <td class="text-secondary small" data-excel-tipe="PASIVA">PASIVA</td>
                                                <td class="text-end" data-excel-value="{{ (float) $subtotalPasiva }}">
                                                    <div class="nominal-val font-monospace fw-bold">{{ $formatNominal($subtotalPasiva) }}</div>
                                                </td>
                                            </tr>
                                        @endif

                                        @php
                                            $currentSection = strtoupper($row['nama_coa']);
                                            $icon = match($currentSection) {
                                                'AKTIVA' => 'bi-wallet2 text-success',
                                                'PASIVA' => 'bi-credit-card-2-front text-warning-emphasis',
                                                'EKUITAS' => 'bi-pie-chart text-info-emphasis',
                                                default => 'bi-folder2-open text-primary'
                                            };
                                            $label = match($currentSection) {
                                                'AKTIVA' => 'Aktiva (Aset)',
                                                'PASIVA' => 'Pasiva (Kewajiban)',
                                                'EKUITAS' => 'Ekuitas (Modal)',
                                                default => $row['nama_coa']
                                            };
                                            $sublabel = match($currentSection) {
                                                'AKTIVA' => 'Bagian 1',
                                                'PASIVA' => 'Bagian 2',
                                                'EKUITAS' => 'Bagian 3',
                                                default => 'Bagian'
                                            };
                                        @endphp

                                        {{-- Section Header Row --}}
                                        <tr class="section-header-row" data-section="{{ strtolower($currentSection) }}">
                                            <td colspan="3" class="sticky-col text-uppercase fw-bold py-2 px-3">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <i class="bi {{ $icon }} fs-6"></i>
                                                        <span class="fs-6">{{ $label }}</span>
                                                    </span>
                                                    <span class="badge bg-light text-secondary border fw-normal text-capitalize fs-8 no-print">{{ $sublabel }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        {!! $renderAccountRow($row, strtolower($currentSection ?? 'general')) !!}
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Tidak ada data neraca untuk tanggal ini.</td>
                                    </tr>
                                @endforelse

                                {{-- Setelah loop selesai, jika ada data, tutup section terakhir dan tampilkan grand total --}}
                                @if ($hasRows)
                                    @if ($currentSection === 'EKUITAS')
                                        <tr class="section-subtotal-row table-light fw-bold" data-section="ekuitas">
                                            <td class="sticky-col ps-3" data-excel-label="Subtotal Ekuitas">
                                                <div class="d-flex align-items-center">
                                                    <span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>
                                                    <span class="text-uppercase">Total Ekuitas (Modal)</span>
                                                </div>
                                            </td>
                                            <td class="text-secondary small" data-excel-tipe="EKUITAS">EKUITAS</td>
                                            <td class="text-end" data-excel-value="{{ (float) $subtotalEkuitas }}">
                                                <div class="nominal-val font-monospace fw-bold">{{ $formatNominal($subtotalEkuitas) }}</div>
                                            </td>
                                        </tr>
                                    @endif

                                    {{-- Grand Total: Total Pasiva + Ekuitas --}}
                                    <tr class="row-neraca-total fw-bold" data-section="pasiva-ekuitas">
                                        <td class="sticky-col ps-3" data-excel-label="Subtotal Pasiva + Ekuitas">
                                            <div class="d-flex align-items-center">
                                                <span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>
                                                <span class="text-uppercase">Total Pasiva + Ekuitas</span>
                                            </div>
                                        </td>
                                        <td class="text-secondary small" data-excel-tipe="PASIVA + EKUITAS">PASIVA + EKUITAS</td>
                                        <td class="text-end" data-excel-value="{{ (float) $subtotalPasivaEkuitas }}">
                                            <div class="nominal-val font-monospace fw-bold">{{ $formatNominal($subtotalPasivaEkuitas) }}</div>
                                        </td>
                                    </tr>

                                    {{-- Baris Status Balance / Selisih --}}
                                    <tr class="{{ $isBalance ? 'row-neraca-balance row-neraca-balance--positive' : 'row-neraca-balance row-neraca-balance--negative' }} fw-bold" data-section="status">
                                        <td class="sticky-col ps-3" data-excel-label="Status Neraca">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi {{ $isBalance ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
                                                <span>Status Neraca</span>
                                            </div>
                                        </td>
                                        <td class="small">{{ $isBalance ? 'BALANCE' : 'TIDAK BALANCE' }}</td>
                                        <td class="text-end font-monospace" data-excel-value="{{ (float) $selisih }}">
                                            {{ $isBalance ? 'BALANCE' : 'TIDAK BALANCE' }} | Selisih {{ number_format((float) $selisih, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- 4. Ikhtisar Ringkasan Neraca / Audit Keseimbangan Table --}}
                    <div class="card border border-light-subtle shadow-sm mt-3 bg-white">
                        <div class="card-header bg-light bg-opacity-50 py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-primary"></i>
                                <span class="fw-bold small text-dark">Ikhtisar & Verifikasi Keseimbangan Neraca</span>
                            </div>
                            <span class="badge {{ $isBalance ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }} fw-semibold px-2.5 py-1">
                                <i class="bi {{ $isBalance ? 'bi-check-circle-fill me-1' : 'bi-exclamation-octagon-fill me-1' }}"></i>
                                {{ $isBalance ? 'Posisi Seimbang (Balance)' : 'Perlu Penyesuaian (Tidak Balance)' }}
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-neraca table-hover align-middle mb-0" id="neraca-standard-summary-table">
                                    <tbody>
                                        <tr>
                                            <td class="ps-3 py-2 fw-semibold text-secondary" style="width: 45%;">Subtotal Aktiva</td>
                                            <td class="text-end pe-3 py-2 font-monospace fw-bold text-dark">{{ number_format((float) $subtotalAktiva, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-3 py-2 fw-semibold text-secondary">Subtotal Pasiva</td>
                                            <td class="text-end pe-3 py-2 font-monospace fw-bold text-dark">{{ number_format((float) $subtotalPasiva, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-3 py-2 fw-semibold text-secondary">Subtotal Ekuitas</td>
                                            <td class="text-end pe-3 py-2 font-monospace fw-bold text-dark">{{ number_format((float) $subtotalEkuitas, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr class="table-light border-top">
                                            <td class="ps-3 py-2 fw-bold text-dark">Subtotal Pasiva + Ekuitas</td>
                                            <td class="text-end pe-3 py-2 font-monospace fw-bold text-dark fs-7">{{ number_format((float) $subtotalPasivaEkuitas, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr class="{{ $isBalance ? 'table-success bg-opacity-50' : 'table-danger bg-opacity-50' }} fw-bold border-top">
                                            <td class="ps-3 py-2">
                                                <i class="bi {{ $isBalance ? 'bi-check2-circle text-success me-1' : 'bi-exclamation-triangle-fill text-danger me-1' }}"></i>
                                                Status Neraca
                                            </td>
                                            <td class="text-end pe-3 py-2 font-monospace">
                                                {{ $isBalance ? 'BALANCE' : 'TIDAK BALANCE' }} | Selisih {{ number_format((float) $selisih, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initPeriodeDropdown();
            initTreeAccordion();
        });

        /* ─── 1. Filter Dropdown Periode Cut-off Otomatis ─────────────── */
        function initPeriodeDropdown() {
            const periodeSelect = document.getElementById('periodeSelect');
            const perDateInput = document.getElementById('perDate');

            if (!periodeSelect || !perDateInput) return;

            function formatYmd(d) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            function getCutoffDate(type) {
                const now = new Date();
                const curYear = now.getFullYear();
                const curMonth = now.getMonth();

                if (type === 'hari_ini') {
                    return formatYmd(now);
                } else if (type === 'akhir_bulan_ini') {
                    const lastDayThisMonth = new Date(curYear, curMonth + 1, 0);
                    return formatYmd(lastDayThisMonth);
                } else if (type === 'akhir_bulan_lalu') {
                    const lastDayPrevMonth = new Date(curYear, curMonth, 0);
                    return formatYmd(lastDayPrevMonth);
                } else if (type === 'akhir_tahun_ini') {
                    return `${curYear}-12-31`;
                } else if (type === 'akhir_tahun_lalu') {
                    return `${curYear - 1}-12-31`;
                }
                return null;
            }

            // Cek nilai awal input tanggal terhadap preset
            const curVal = perDateInput.value;
            const hIni = getCutoffDate('hari_ini');
            const abIni = getCutoffDate('akhir_bulan_ini');
            const abLalu = getCutoffDate('akhir_bulan_lalu');
            const atIni = getCutoffDate('akhir_tahun_ini');
            const atLalu = getCutoffDate('akhir_tahun_lalu');

            if (curVal === hIni) {
                periodeSelect.value = 'hari_ini';
            } else if (curVal === abIni) {
                periodeSelect.value = 'akhir_bulan_ini';
            } else if (curVal === abLalu) {
                periodeSelect.value = 'akhir_bulan_lalu';
            } else if (curVal === atIni) {
                periodeSelect.value = 'akhir_tahun_ini';
            } else if (curVal === atLalu) {
                periodeSelect.value = 'akhir_tahun_lalu';
            } else {
                periodeSelect.value = 'kustom';
            }

            // Listener perubahan dropdown periode
            periodeSelect.addEventListener('change', function() {
                const date = getCutoffDate(this.value);
                if (date) {
                    perDateInput.value = date;
                }
            });

            // Jika user merubah input tanggal secara manual
            perDateInput.addEventListener('change', function() {
                const v = this.value;
                if (v === hIni) {
                    periodeSelect.value = 'hari_ini';
                } else if (v === abIni) {
                    periodeSelect.value = 'akhir_bulan_ini';
                } else if (v === abLalu) {
                    periodeSelect.value = 'akhir_bulan_lalu';
                } else if (v === atIni) {
                    periodeSelect.value = 'akhir_tahun_ini';
                } else if (v === atLalu) {
                    periodeSelect.value = 'akhir_tahun_lalu';
                } else {
                    periodeSelect.value = 'kustom';
                }
            });
        }

        /* ─── 2. Interaktivitas Accordion Tree COA & Expand/Collapse All ─── */
        const collapsedParentIds = new Set();

        function refreshTreeVisibility() {
            const allAccountRows = document.querySelectorAll('.neraca-account-row');
            allAccountRows.forEach(row => {
                const parentId = row.getAttribute('data-parent-id');
                if (!parentId) {
                    // Akun induk level paling atas selalu tampil
                    row.style.display = '';
                    return;
                }

                // Cek apakah ada ancestor dari row ini yang sedang di-collapse
                let curr = parentId;
                let shouldHide = false;
                while (curr) {
                    if (collapsedParentIds.has(curr)) {
                        shouldHide = true;
                        break;
                    }
                    const parentElem = document.querySelector(`.neraca-account-row[data-id="${curr}"]`);
                    curr = parentElem ? parentElem.getAttribute('data-parent-id') : null;
                }

                row.style.display = shouldHide ? 'none' : '';
            });
        }

        function initTreeAccordion() {
            const btnToggleAll = document.getElementById('btnToggleAll');
            const parentRows = document.querySelectorAll('.parent-account-row');

            // Handler toggle per parent COA
            parentRows.forEach(row => {
                const coaId = row.getAttribute('data-id');
                const toggleBtn = row.querySelector('.btn-tree-toggle');

                function toggleParent(e) {
                    if (e.target.closest('a') && !e.target.closest('.btn-tree-toggle')) {
                        return; // Biarkan navigasi link jika ada
                    }

                    const icon = row.querySelector('.tree-icon');
                    if (collapsedParentIds.has(coaId)) {
                        collapsedParentIds.delete(coaId);
                        if (icon) {
                            icon.classList.remove('bi-plus-square');
                            icon.classList.add('bi-dash-square');
                        }
                    } else {
                        collapsedParentIds.add(coaId);
                        if (icon) {
                            icon.classList.remove('bi-dash-square');
                            icon.classList.add('bi-plus-square');
                        }
                    }

                    refreshTreeVisibility();

                    // Update tombol toggle all di header
                    if (btnToggleAll && parentRows.length > 0) {
                        if (collapsedParentIds.size === parentRows.length) {
                            btnToggleAll.setAttribute('data-state', 'collapsed');
                            btnToggleAll.innerHTML = '<i class="bi bi-plus-square"></i><span>[+] Expand All</span>';
                        } else {
                            btnToggleAll.setAttribute('data-state', 'expanded');
                            btnToggleAll.innerHTML = '<i class="bi bi-dash-square"></i><span>[-] Collapse All</span>';
                        }
                    }
                }

                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        toggleParent(e);
                    });
                }

                // Klik nama parent row juga membuka/menutup child rows
                row.addEventListener('click', toggleParent);
            });

            // Handler tombol toggle all '[-]' / '[+]'
            if (btnToggleAll) {
                btnToggleAll.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const currentState = this.getAttribute('data-state');

                    if (currentState === 'expanded') {
                        // Collapse seluruh parent
                        parentRows.forEach(row => {
                            const cid = row.getAttribute('data-id');
                            collapsedParentIds.add(cid);
                            const icon = row.querySelector('.tree-icon');
                            if (icon) {
                                icon.classList.remove('bi-dash-square');
                                icon.classList.add('bi-plus-square');
                            }
                        });
                        this.setAttribute('data-state', 'collapsed');
                        this.innerHTML = '<i class="bi bi-plus-square"></i><span>[+] Expand All</span>';
                    } else {
                        // Expand seluruh parent
                        collapsedParentIds.clear();
                        parentRows.forEach(row => {
                            const icon = row.querySelector('.tree-icon');
                            if (icon) {
                                icon.classList.remove('bi-plus-square');
                                icon.classList.add('bi-dash-square');
                            }
                        });
                        this.setAttribute('data-state', 'expanded');
                        this.innerHTML = '<i class="bi bi-dash-square"></i><span>[-] Collapse All</span>';
                    }

                    refreshTreeVisibility();
                });
            }
        }

        /* ─── 3. Fitur Ekspor Excel Bersih (SheetJS) ──────────────────── */
        function exportTableToExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Library SheetJS belum termuat. Mohon pastikan file script excel aktif.');
                return;
            }

            const table = document.getElementById('neraca-standard-table');
            if (!table) return;

            const perDate = "{{ $perDate }}";
            const namaRS = "{{ $namaRumahSakit }}";

            // Header informasi dokumen
            const aoa = [
                [namaRS],
                ['Neraca Standard'],
                [`Per Tanggal: ${perDate}`],
                [],
                ['Akun', 'Tipe Akun', 'Saldo (Rp)']
            ];

            const trList = table.querySelectorAll('tbody tr');
            trList.forEach(tr => {
                if (tr.classList.contains('section-header-row')) {
                    const text = tr.innerText.replace('Bagian 1', '').replace('Bagian 2', '').replace('Bagian 3', '').replace('Kategori Utama', '').trim();
                    aoa.push([text, '', '']);
                    return;
                }

                const tds = tr.querySelectorAll('td');
                if (tds.length === 0) return;

                if (tds.length === 1 && tr.innerText.includes('Tidak ada data')) {
                    aoa.push([tr.innerText.trim(), '', '']);
                    return;
                }

                const label = tds[0].getAttribute('data-excel-label') || tds[0].innerText.trim();
                const tipe = tds[1] ? (tds[1].getAttribute('data-excel-tipe') || tds[1].innerText.trim()) : '';
                const rawVal = tds[2] ? tds[2].getAttribute('data-excel-value') : null;
                const saldo = (rawVal !== null && rawVal !== undefined && rawVal !== '') ? (parseFloat(rawVal) || 0) : '';

                aoa.push([label, tipe, saldo]);
            });

            // Tambahkan baris kosong sebelum ikhtisar
            aoa.push([]);
            aoa.push(['IKHTISAR POSISI KEUANGAN', '', '']);
            aoa.push(['Subtotal Aktiva', 'AKTIVA', {{ (float) $subtotalAktiva }}]);
            aoa.push(['Subtotal Pasiva', 'PASIVA', {{ (float) $subtotalPasiva }}]);
            aoa.push(['Subtotal Ekuitas', 'EKUITAS', {{ (float) $subtotalEkuitas }}]);
            aoa.push(['Subtotal Pasiva + Ekuitas', 'PASIVA + EKUITAS', {{ (float) $subtotalPasivaEkuitas }}]);
            aoa.push(['Status Neraca', '{{ $isBalance ? 'BALANCE' : 'TIDAK BALANCE' }}', 'Selisih {{ number_format((float) $selisih, 2, ',', '.') }}']);

            const worksheet = XLSX.utils.aoa_to_sheet(aoa);

            // Lebar kolom
            worksheet['!cols'] = [
                { wch: 48 },
                { wch: 20 },
                { wch: 24 }
            ];

            // Format accounting (#,##0;(#,##0);0)
            if (worksheet['!ref']) {
                const range = XLSX.utils.decode_range(worksheet['!ref']);
                for (let R = 4; R <= range.e.r; ++R) {
                    const cellRef = XLSX.utils.encode_cell({ c: 2, r: R });
                    if (worksheet[cellRef] && typeof worksheet[cellRef].v === 'number') {
                        worksheet[cellRef].z = '#,##0;(#,##0);0';
                    }
                }
            }

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'NeracaStandard');
            const filename = `Neraca_Standard_${perDate}.xlsx`;
            XLSX.writeFile(workbook, filename);
        }

        /* ─── 4. Fitur Print Bersih ──────────────────────────────────── */
        function printReport() {
            window.print();
        }
    </script>
@endpush

@push('styles')
    <style>
        /* ─── Typography & Utilities ─────────────────────────────────── */
        .fs-7 { font-size: 0.825rem; }
        .fs-8 { font-size: 0.725rem; }
        .hover-opacity:hover { opacity: 0.8; }
        .hover-underline:hover { text-decoration: underline !important; }

        .laporan-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .laporan-header__logo-image {
            max-width: 95px;
            max-height: 80px;
            object-fit: contain;
        }

        /* ─── Table Structure (Jurnal.id Style) ──────────────────────── */
        .table-neraca {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 0.85rem;
        }

        .table-neraca th,
        .table-neraca td {
            padding: 0.45rem 0.65rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            border-right: 1px solid #f1f3f5;
        }

        .table-neraca thead th {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom: 2px solid #ced4da;
            color: #212529;
            font-weight: 700;
        }

        /* ─── Sticky First Column (Kolom Akun) ───────────────────────── */
        .table-neraca th.sticky-col,
        .table-neraca td.sticky-col {
            position: sticky;
            left: 0;
            z-index: 5;
            background-color: #ffffff;
            box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.08);
        }

        .table-neraca thead th.sticky-col {
            z-index: 10;
            background-color: #f8f9fa;
        }

        /* ─── Cells: Nominal & Tree Indentations ─────────────────────── */
        .nominal-val {
            line-height: 1.25;
            color: #212529;
        }

        .parent-account-row {
            cursor: pointer;
            user-select: none;
        }

        .parent-account-row:hover td {
            background-color: #f8fafc !important;
        }

        .parent-account-row:hover .account-name {
            color: #027e3f !important;
        }

        .btn-tree-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.15rem;
            height: 1.15rem;
        }

        .btn-tree-toggle:hover .tree-icon {
            color: #027e3f !important;
        }

        /* ─── Section Headers & Subtotals ────────────────────────────── */
        .table-neraca tr.section-header-row td {
            background-color: #f1f5f9 !important;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: 0.025em;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .table-neraca tr.section-subtotal-row td {
            background-color: #f8fafc !important;
            font-weight: 700;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .table-neraca tr.section-subtotal-row td.sticky-col {
            background-color: #f8fafc !important;
        }

        /* ─── Standout Total Rows ────────────────────────────────────── */
        .table-neraca tr.row-neraca-total td {
            background-color: #e0f2fe !important;
            color: #0369a1;
            font-weight: 700;
            border-top: 2px solid #0f172a !important;
            border-bottom: 2px solid #0f172a !important;
        }

        .table-neraca tr.row-neraca-total td.sticky-col {
            background-color: #e0f2fe !important;
        }

        /* ─── Status Neraca Balance Indicator ────────────────────────── */
        .table-neraca tr.row-neraca-balance td {
            font-weight: 800;
            border-top: 2px solid #0f172a !important;
            border-bottom: 4px double #0f172a !important;
            font-size: 0.925rem;
        }

        .table-neraca tr.row-neraca-balance--positive td {
            background-color: #dcfce7 !important;
            color: #14532d;
        }

        .table-neraca tr.row-neraca-balance--negative td {
            background-color: #fee2e2 !important;
            color: #991b1b;
        }

        .table-neraca tr.row-neraca-balance--positive td.sticky-col {
            background-color: #dcfce7 !important;
        }

        .table-neraca tr.row-neraca-balance--negative td.sticky-col {
            background-color: #fee2e2 !important;
        }

        /* ─── Hover Behavior ─────────────────────────────────────────── */
        .table-neraca tbody tr.neraca-account-row:hover td {
            background-color: #f8fafc !important;
        }

        .table-neraca tbody tr.neraca-account-row:hover td.sticky-col {
            background-color: #f8fafc !important;
        }

        /* ─── Media Print Bersih ─────────────────────────────────────── */
        @media print {
            @page {
                size: portrait;
                margin: 8mm;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            body::before,
            #app-sidebar,
            #sidebar-backdrop,
            #mobile-sidebar-toggle,
            .no-print,
            .footer {
                display: none !important;
            }

            #app-content {
                margin-left: 0 !important;
                padding: 0 !important;
                min-height: auto !important;
            }

            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .card-body {
                padding: 0 !important;
            }

            .table-responsive {
                overflow: visible !important;
                border: none !important;
            }

            .table-neraca {
                width: 100% !important;
                font-size: 8pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .table-neraca th.sticky-col,
            .table-neraca td.sticky-col {
                position: static !important;
                box-shadow: none !important;
            }

            .table-neraca th,
            .table-neraca td {
                padding: 4px 6px !important;
                border: 1px solid #94a3b8 !important;
            }

            .nominal-val {
                font-size: 8pt !important;
            }
        }
    </style>
@endpush
