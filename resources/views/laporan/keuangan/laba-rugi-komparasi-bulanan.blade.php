@extends('layouts.app')

@section('title', 'Laba Rugi Komparasi Bulanan')

@php
    $formatNominal = function ($val) {
        $num = (float) $val;
        if (abs($num) < 0.0001) {
            return '0';
        }
        $formatted = number_format(abs($num), 0, ',', '.');
        return $num < 0 ? '(' . $formatted . ')' : $formatted;
    };

    $formatGrowthBadge = function ($growth, $isFirstMonth = false) {
        if ($isFirstMonth || $growth === null) {
            return '<span class="text-muted growth-indicator">-</span>';
        }
        $g = (float) $growth;
        if (abs($g) < 0.0001) {
            return '<span class="text-muted growth-indicator">-</span>';
        }
        $formatted = number_format(abs($g), 1, ',', '.');
        if ($g > 0) {
            return '<span class="text-success fw-semibold growth-indicator">▲ ' . $formatted . '%</span>';
        } else {
            return '<span class="text-danger fw-semibold growth-indicator">▼ ' . $formatted . '%</span>';
        }
    };
@endphp

@section('content')
    {{-- Breadcrumb & Title --}}
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-4">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark text-decoration-none hover-opacity" title="Kembali ke Menu Laporan">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laba Rugi Komparasi Bulanan</span>
            </div>
            <div class="text-muted small ps-4 ms-2">Komparasi kinerja laba rugi antar bulan dengan indikator pertumbuhan (format Jurnal.id)</div>
        </div>
    </div>

    <div class="card border-muhammadiyah shadow-sm mb-4">
        <div class="card-body d-flex flex-column gap-3 p-3 p-md-4">

            {{-- 1. Header Filter (Jurnal.id Style) --}}
            <div class="card border-light bg-light bg-opacity-50 shadow-sm no-print">
                <div class="card-body p-3">
                    <form method="get" action="{{ route('laporan.keuangan.laba-rugi-komparasi-bulanan') }}" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="periodeSelect" class="form-label fw-semibold text-secondary small mb-1">
                                    <i class="bi bi-calendar-range me-1"></i>Periode
                                </label>
                                <select id="periodeSelect" class="form-select form-select-sm">
                                    <option value="tahun_ini">Laporan Tahunan Ini</option>
                                    <option value="tahun_lalu">Laporan Tahun Lalu</option>
                                    <option value="bulan_ini">Bulan Ini</option>
                                    <option value="kustom">Kustom</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="startDate" class="form-label fw-semibold text-secondary small mb-1">Tanggal Awal</label>
                                <input type="date" name="startDate" id="startDate" class="form-control form-control-sm" value="{{ $startDate }}" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="endDate" class="form-label fw-semibold text-secondary small mb-1">Tanggal Akhir</label>
                                <input type="date" name="endDate" id="endDate" class="form-control form-control-sm" value="{{ $endDate }}" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
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
                                                        <small class="text-muted">Tampilan cetak rapi landscape</small>
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

            {{-- 2. Printable Area & Table --}}
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
                            <div class="fw-bold fs-4 text-dark laporan-header__title-report">Laba Rugi Komparasi Bulanan</div>
                            <div class="text-muted small">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    {{-- Table Responsive with Sticky First Column --}}
                    <div class="table-responsive border rounded-3 bg-white">
                        <table class="table table-sm table-komparasi align-middle mb-0" id="datatable">
                            <thead>
                                <tr>
                                    {{-- Kolom Akun (Sticky Kiri) + Toggle Expand/Collapse All --}}
                                    <th class="align-middle sticky-col col-akun-header" style="min-width: 320px; width: 340px;">
                                        <div class="d-flex align-items-center justify-content-between py-1">
                                            <span class="fw-bold text-dark">Akun</span>
                                            <button type="button" id="btnToggleAll" class="btn btn-xs btn-outline-secondary py-0 px-2 fs-8 no-print d-inline-flex align-items-center gap-1" data-state="expanded" title="Buka / Tutup Seluruh Sub-Akun">
                                                <i class="bi bi-dash-square"></i>
                                                <span>[-] Collapse All</span>
                                            </button>
                                        </div>
                                    </th>

                                    {{-- Kolom Tiap Bulan (Satu Header per Bulan) --}}
                                    @foreach ($months as $m)
                                        <th class="text-end align-middle col-month-header" style="min-width: 140px;">
                                            <div class="fw-bold text-dark text-nowrap">{{ $m['label'] }}</div>
                                        </th>
                                    @endforeach

                                    {{-- Kolom Total Periode --}}
                                    <th class="text-end align-middle col-total-header" style="min-width: 150px;">
                                        <div class="fw-bold text-dark">Total</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $colCount = count($months) + 2;

                                    // Helper render baris-baris akun COA
                                    $renderAccountRows = function ($rows, $sectionKey) use ($months, $formatNominal, $formatGrowthBadge) {
                                        $html = '';
                                        foreach ($rows as $row) {
                                            $indentLevel = max((int) ($row['level'] ?? 1) - 1, 0);
                                            $hasChildren = (bool) ($row['has_children'] ?? false);
                                            $rowId = 'coa-' . ($row['coa_id'] ?? uniqid());
                                            $parentId = !empty($row['parent_coa']) ? (string) $row['parent_coa'] : '';

                                            $rowClass = 'laba-rugi-account-row';
                                            if ($hasChildren) {
                                                $rowClass .= ' parent-account-row fw-semibold';
                                            } else {
                                                $rowClass .= ' child-account-row';
                                            }

                                            $html .= '<tr id="' . $rowId . '" class="' . $rowClass . '" data-id="' . $row['coa_id'] . '" data-parent-id="' . $parentId . '" data-level="' . ($row['level'] ?? 1) . '" data-has-children="' . ($hasChildren ? '1' : '0') . '" data-section="' . $sectionKey . '">';

                                            // Kolom 1: Akun (Sticky Kiri)
                                            $excelLabel = str_repeat('   ', $indentLevel) . ($row['kode'] ?? '') . ' - ' . ($row['nama'] ?? '');
                                            $html .= '<td class="sticky-col text-nowrap" data-excel-label="' . e($excelLabel) . '">';
                                            $html .= '<div class="d-flex align-items-center" style="padding-left: ' . ($indentLevel * 1.5) . 'rem;">';
                                            if ($hasChildren) {
                                                $html .= '<button type="button" class="btn btn-tree-toggle text-secondary p-0 border-0 bg-transparent me-1 no-print" data-coa-id="' . $row['coa_id'] . '" title="Buka / Tutup Sub Akun">';
                                                $html .= '<i class="bi bi-dash-square tree-icon text-muted"></i>';
                                                $html .= '</button>';
                                            } else {
                                                $html .= '<span class="tree-spacer d-inline-block" style="width: 1.25rem;"></span>';
                                            }
                                            $html .= '<span class="account-code text-muted me-1 font-monospace">' . e($row['kode'] ?? '') . '</span>';
                                            $html .= '<span class="text-muted me-1">-</span>';
                                            $html .= '<span class="account-name">' . e($row['nama'] ?? '') . '</span>';
                                            $html .= '</div>';
                                            $html .= '</td>';

                                            // Kolom Bulanan
                                            foreach ($months as $idx => $m) {
                                                $mKey = $m['key'];
                                                $nominal = (float) ($row['monthly'][$mKey]['nominal'] ?? 0.0);
                                                $growth = $row['monthly'][$mKey]['persen_perubahan'] ?? null;

                                                $html .= '<td class="text-end" data-excel-value="' . $nominal . '">';
                                                $html .= '<div class="nominal-val font-monospace">' . $formatNominal($nominal) . '</div>';
                                                $html .= '<div class="growth-val">' . $formatGrowthBadge($growth, $idx === 0) . '</div>';
                                                $html .= '</td>';
                                            }

                                            // Kolom Total Periode
                                            $totalVal = (float) ($row['total'] ?? 0.0);
                                            $html .= '<td class="text-end" data-excel-value="' . $totalVal . '">';
                                            $html .= '<div class="nominal-val font-monospace fw-semibold">' . $formatNominal($totalVal) . '</div>';
                                            $html .= '<div class="growth-val text-muted growth-indicator">-</div>';
                                            $html .= '</td>';

                                            $html .= '</tr>';
                                        }
                                        return $html;
                                    };

                                    // Helper render baris total tiap section
                                    $renderSectionTotal = function ($totalRow, $sectionKey) use ($months, $formatNominal, $formatGrowthBadge) {
                                        $label = $totalRow['label'] ?? '';
                                        $html = '<tr class="section-subtotal-row table-light fw-bold" data-section="' . $sectionKey . '">';
                                        $html .= '<td class="sticky-col ps-3" data-excel-label="' . e($label) . '">' . e($label) . '</td>';
                                        foreach ($months as $idx => $m) {
                                            $mKey = $m['key'];
                                            $nominal = (float) ($totalRow['monthly'][$mKey]['nominal'] ?? 0.0);
                                            $growth = $totalRow['monthly'][$mKey]['persen_perubahan'] ?? null;

                                            $html .= '<td class="text-end" data-excel-value="' . $nominal . '">';
                                            $html .= '<div class="nominal-val font-monospace">' . $formatNominal($nominal) . '</div>';
                                            $html .= '<div class="growth-val">' . $formatGrowthBadge($growth, $idx === 0) . '</div>';
                                            $html .= '</td>';
                                        }
                                        $totalVal = (float) ($totalRow['total'] ?? 0.0);
                                        $html .= '<td class="text-end" data-excel-value="' . $totalVal . '">';
                                        $html .= '<div class="nominal-val font-monospace">' . $formatNominal($totalVal) . '</div>';
                                        $html .= '<div class="growth-val text-muted growth-indicator">-</div>';
                                        $html .= '</td>';
                                        $html .= '</tr>';
                                        return $html;
                                    };

                                    // Helper render baris ringkasan (Laba Kotor, Laba Operasional, Laba Bersih, dll)
                                    $renderSummaryRow = function ($summaryRow, $extraClass = 'table-light fw-bold') use ($months, $formatNominal, $formatGrowthBadge) {
                                        $label = $summaryRow['label'] ?? '';
                                        $html = '<tr class="' . $extraClass . '">';
                                        $html .= '<td class="sticky-col ps-3" data-excel-label="' . e($label) . '">' . e($label) . '</td>';
                                        foreach ($months as $idx => $m) {
                                            $mKey = $m['key'];
                                            $nominal = (float) ($summaryRow['monthly'][$mKey]['nominal'] ?? 0.0);
                                            $growth = $summaryRow['monthly'][$mKey]['persen_perubahan'] ?? null;

                                            $html .= '<td class="text-end" data-excel-value="' . $nominal . '">';
                                            $html .= '<div class="nominal-val font-monospace">' . $formatNominal($nominal) . '</div>';
                                            $html .= '<div class="growth-val">' . $formatGrowthBadge($growth, $idx === 0) . '</div>';
                                            $html .= '</td>';
                                        }
                                        $totalVal = (float) ($summaryRow['total'] ?? 0.0);
                                        $html .= '<td class="text-end" data-excel-value="' . $totalVal . '">';
                                        $html .= '<div class="nominal-val font-monospace">' . $formatNominal($totalVal) . '</div>';
                                        $html .= '<div class="growth-val text-muted growth-indicator">-</div>';
                                        $html .= '</td>';
                                        $html .= '</tr>';
                                        return $html;
                                    };
                                @endphp

                                {{-- 1. PENDAPATAN --}}
                                @if (isset($sections['pendapatan']))
                                    <tr class="section-header-row">
                                        <td colspan="{{ $colCount }}" class="sticky-col text-uppercase fw-bold py-2 px-3">
                                            Pendapatan
                                        </td>
                                    </tr>
                                    {!! $renderAccountRows($sections['pendapatan']['rows'] ?? [], 'pendapatan') !!}
                                    {!! $renderSectionTotal($sections['pendapatan']['total'], 'pendapatan') !!}
                                @endif

                                {{-- 2. BEBAN POKOK PENDAPATAN --}}
                                @if (isset($sections['beban_pokok_pendapatan']))
                                    <tr class="section-header-row">
                                        <td colspan="{{ $colCount }}" class="sticky-col text-uppercase fw-bold py-2 px-3">
                                            Beban Pokok Pendapatan
                                        </td>
                                    </tr>
                                    {!! $renderAccountRows($sections['beban_pokok_pendapatan']['rows'] ?? [], 'beban_pokok_pendapatan') !!}
                                    {!! $renderSectionTotal($sections['beban_pokok_pendapatan']['total'], 'beban_pokok_pendapatan') !!}
                                @endif

                                {{-- 3. LABA KOTOR (Border tebal atas & bawah, styling menonjol) --}}
                                @if (isset($labaKotor))
                                    {!! $renderSummaryRow($labaKotor, 'row-laba-kotor fw-bold') !!}
                                @endif

                                {{-- 4. BEBAN OPERASIONAL --}}
                                @if (isset($sections['beban_operasional']))
                                    <tr class="section-header-row">
                                        <td colspan="{{ $colCount }}" class="sticky-col text-uppercase fw-bold py-2 px-3">
                                            Beban Operasional
                                        </td>
                                    </tr>
                                    {!! $renderAccountRows($sections['beban_operasional']['rows'] ?? [], 'beban_operasional') !!}
                                    {!! $renderSectionTotal($sections['beban_operasional']['total'], 'beban_operasional') !!}
                                @endif

                                {{-- 5. LABA OPERASIONAL (Border tebal atas & bawah, styling menonjol) --}}
                                @if (isset($labaOperasional))
                                    {!! $renderSummaryRow($labaOperasional, 'row-laba-operasional fw-bold') !!}
                                @endif

                                {{-- 6. PENDAPATAN (BEBAN LAIN-LAIN) --}}
                                <tr class="section-header-row">
                                    <td colspan="{{ $colCount }}" class="sticky-col text-uppercase fw-bold py-2 px-3">
                                        Pendapatan (Beban Lain-lain)
                                    </td>
                                </tr>

                                {{-- 6a. Pendapatan Lain-lain --}}
                                @if (isset($sections['pendapatan_lain']))
                                    <tr class="subsection-header-row">
                                        <td colspan="{{ $colCount }}" class="sticky-col fw-semibold py-1 px-3 ps-4 text-secondary">
                                            Pendapatan Lain-lain
                                        </td>
                                    </tr>
                                    {!! $renderAccountRows($sections['pendapatan_lain']['rows'] ?? [], 'pendapatan_lain') !!}
                                    {!! $renderSectionTotal($sections['pendapatan_lain']['total'], 'pendapatan_lain') !!}
                                @endif

                                {{-- 6b. Beban Lain-lain --}}
                                @if (isset($sections['beban_lain']))
                                    <tr class="subsection-header-row">
                                        <td colspan="{{ $colCount }}" class="sticky-col fw-semibold py-1 px-3 ps-4 text-secondary">
                                            Beban Lain-lain
                                        </td>
                                    </tr>
                                    {!! $renderAccountRows($sections['beban_lain']['rows'] ?? [], 'beban_lain') !!}
                                    {!! $renderSectionTotal($sections['beban_lain']['total'], 'beban_lain') !!}
                                @endif

                                {{-- 6c. Total dari Pendapatan (Beban Lain-lain) --}}
                                @if (isset($totalPendapatanBebanLain))
                                    {!! $renderSummaryRow($totalPendapatanBebanLain, 'section-subtotal-row table-light fw-bold') !!}
                                @endif

                                {{-- 7. LABA BERSIH (Border bawah ganda, styling menonjol) --}}
                                @if (isset($labaBersih))
                                    @php
                                        $isPositive = ($labaBersih['total'] ?? 0) >= 0;
                                        $netClass = $isPositive ? 'row-laba-bersih row-laba-bersih--positive' : 'row-laba-bersih row-laba-bersih--negative';
                                    @endphp
                                    {!! $renderSummaryRow($labaBersih, $netClass) !!}
                                @endif
                            </tbody>
                        </table>
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

        /* ─── 1. Filter Dropdown Periode Otomatis ────────────────────── */
        function initPeriodeDropdown() {
            const periodeSelect = document.getElementById('periodeSelect');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');

            if (!periodeSelect || !startDateInput || !endDateInput) return;

            function formatYmd(d) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            function getPeriodDates(type) {
                const now = new Date();
                const curYear = now.getFullYear();

                if (type === 'tahun_ini') {
                    return {
                        start: `${curYear}-01-01`,
                        end: `${curYear}-12-31`
                    };
                } else if (type === 'tahun_lalu') {
                    const prevYear = curYear - 1;
                    return {
                        start: `${prevYear}-01-01`,
                        end: `${prevYear}-12-31`
                    };
                } else if (type === 'bulan_ini') {
                    const firstDay = new Date(curYear, now.getMonth(), 1);
                    const lastDay = new Date(curYear, now.getMonth() + 1, 0);
                    return {
                        start: formatYmd(firstDay),
                        end: formatYmd(lastDay)
                    };
                }
                return null;
            }

            // Cek nilai awal input tanggal terhadap preset
            const curStart = startDateInput.value;
            const curEnd = endDateInput.value;
            const tIni = getPeriodDates('tahun_ini');
            const tLalu = getPeriodDates('tahun_lalu');
            const bIni = getPeriodDates('bulan_ini');

            if (tIni && curStart === tIni.start && curEnd === tIni.end) {
                periodeSelect.value = 'tahun_ini';
            } else if (tLalu && curStart === tLalu.start && curEnd === tLalu.end) {
                periodeSelect.value = 'tahun_lalu';
            } else if (bIni && curStart === bIni.start && curEnd === bIni.end) {
                periodeSelect.value = 'bulan_ini';
            } else {
                periodeSelect.value = 'kustom';
            }

            // Listener perubahan dropdown periode
            periodeSelect.addEventListener('change', function() {
                const dates = getPeriodDates(this.value);
                if (dates) {
                    startDateInput.value = dates.start;
                    endDateInput.value = dates.end;
                }
            });

            // Jika user merubah input tanggal secara manual, ubah dropdown menjadi 'kustom'
            function onDateManualChange() {
                const sVal = startDateInput.value;
                const eVal = endDateInput.value;
                if (tIni && sVal === tIni.start && eVal === tIni.end) {
                    periodeSelect.value = 'tahun_ini';
                } else if (tLalu && sVal === tLalu.start && eVal === tLalu.end) {
                    periodeSelect.value = 'tahun_lalu';
                } else if (bIni && sVal === bIni.start && eVal === bIni.end) {
                    periodeSelect.value = 'bulan_ini';
                } else {
                    periodeSelect.value = 'kustom';
                }
            }

            startDateInput.addEventListener('change', onDateManualChange);
            endDateInput.addEventListener('change', onDateManualChange);
        }

        /* ─── 2. Interaktivitas Accordion Tree COA & Expand/Collapse All ─── */
        const collapsedParentIds = new Set();

        function refreshTreeVisibility() {
            const allAccountRows = document.querySelectorAll('.laba-rugi-account-row');
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
                    const parentElem = document.querySelector(`.laba-rugi-account-row[data-id="${curr}"]`);
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
                        return; // Biarkan link jika ada
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

            const table = document.getElementById('datatable');
            if (!table) return;

            const startDate = "{{ $startDate }}";
            const endDate = "{{ $endDate }}";
            const namaRS = "{{ $namaRumahSakit }}";
            const monthLabels = @json(array_map(fn($m) => $m['label'], $months));

            // Header informasi dokumen
            const aoa = [
                [namaRS],
                ['Laba Rugi Komparasi Bulanan'],
                [`Periode: ${startDate} s/d ${endDate}`],
                [],
                ['Akun', ...monthLabels, 'Total']
            ];

            const trList = table.querySelectorAll('tbody tr');
            trList.forEach(tr => {
                const isSectionHeader = tr.classList.contains('section-header-row') || tr.classList.contains('subsection-header-row');
                if (isSectionHeader) {
                    const text = tr.innerText.trim();
                    aoa.push([text]);
                    return;
                }

                const rowData = [];
                const tds = tr.querySelectorAll('td');
                if (tds.length === 0) return;

                tds.forEach((td, idx) => {
                    if (idx === 0) {
                        const label = td.getAttribute('data-excel-label') || td.innerText.trim();
                        rowData.push(label);
                    } else {
                        const rawVal = td.getAttribute('data-excel-value');
                        if (rawVal !== null && rawVal !== undefined && rawVal !== '') {
                            rowData.push(parseFloat(rawVal) || 0);
                        } else {
                            rowData.push('');
                        }
                    }
                });
                aoa.push(rowData);
            });

            const worksheet = XLSX.utils.aoa_to_sheet(aoa);

            // Set lebar kolom agar rapi
            const colWidths = [{ wch: 45 }];
            for (let i = 0; i < monthLabels.length; i++) {
                colWidths.push({ wch: 18 });
            }
            colWidths.push({ wch: 20 });
            worksheet['!cols'] = colWidths;

            // Format angka accounting (#,##0;(#,##0);0) pada sel numeric
            if (worksheet['!ref']) {
                const range = XLSX.utils.decode_range(worksheet['!ref']);
                for (let R = 5; R <= range.e.r; ++R) {
                    for (let C = 1; C <= range.e.c; ++C) {
                        const cellRef = XLSX.utils.encode_cell({ c: C, r: R });
                        if (worksheet[cellRef] && typeof worksheet[cellRef].v === 'number') {
                            worksheet[cellRef].z = '#,##0;(#,##0);0';
                        }
                    }
                }
            }

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'LabaRugiKomparasi');
            const filename = `Laba_Rugi_Komparasi_${startDate}_sampai_${endDate}.xlsx`;
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

        /* ─── Table Structure (Jurnal.id Horisontal) ──────────────────── */
        .table-komparasi {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 0.85rem;
        }

        .table-komparasi th,
        .table-komparasi td {
            padding: 0.45rem 0.65rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            border-right: 1px solid #f1f3f5;
        }

        .table-komparasi thead th {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom: 2px solid #ced4da;
            color: #212529;
            font-weight: 700;
        }

        /* ─── Sticky First Column (Kolom Akun) ───────────────────────── */
        .table-komparasi th.sticky-col,
        .table-komparasi td.sticky-col {
            position: sticky;
            left: 0;
            z-index: 5;
            background-color: #ffffff;
            box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
        }

        .table-komparasi thead th.sticky-col {
            z-index: 10;
            background-color: #f8f9fa;
        }

        /* ─── Cells: Nominal & Indikator Pertumbuhan ─────────────────── */
        .nominal-val {
            line-height: 1.25;
            color: #212529;
        }

        .growth-val {
            font-size: 0.725rem;
            line-height: 1;
            margin-top: 2px;
        }

        .growth-indicator {
            display: inline-block;
        }

        /* ─── Tree Accordion Elements ────────────────────────────────── */
        .parent-account-row {
            cursor: pointer;
            user-select: none;
        }

        .parent-account-row:hover td {
            background-color: #f8fafc !important;
        }

        .parent-account-row:hover .account-name {
            color: #027e3f;
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
        .table-komparasi tr.section-header-row td {
            background-color: #f1f3f5 !important;
            color: #1f2937;
            font-weight: 700;
            letter-spacing: 0.025em;
            border-top: 2px solid #dee2e6;
            border-bottom: 1px solid #ced4da;
        }

        .table-komparasi tr.subsection-header-row td {
            background-color: #fbfcfd !important;
            color: #4b5563;
            font-size: 0.8rem;
            border-bottom: 1px dashed #dee2e6;
        }

        .table-komparasi tr.section-subtotal-row td {
            background-color: #f8fafc !important;
            font-weight: 700;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .table-komparasi tr.section-subtotal-row td.sticky-col {
            background-color: #f8fafc !important;
        }

        /* ─── Laba Kotor & Laba Operasional (Standout Styling) ────────── */
        .table-komparasi tr.row-laba-kotor td,
        .table-komparasi tr.row-laba-operasional td {
            background-color: #f0f7ff !important;
            color: #0c4a6e;
            font-weight: 700;
            border-top: 2px solid #0f172a !important;
            border-bottom: 2px solid #0f172a !important;
        }

        .table-komparasi tr.row-laba-kotor td.sticky-col,
        .table-komparasi tr.row-laba-operasional td.sticky-col {
            background-color: #f0f7ff !important;
        }

        /* ─── Laba Bersih (Double Bottom Border & Highlight) ─────────── */
        .table-komparasi tr.row-laba-bersih td {
            font-weight: 800;
            border-top: 2px solid #0f172a !important;
            border-bottom: 3px double #0f172a !important;
            font-size: 0.925rem;
        }

        .table-komparasi tr.row-laba-bersih--positive td {
            background-color: #ecfdf5 !important;
            color: #065f46;
        }

        .table-komparasi tr.row-laba-bersih--negative td {
            background-color: #fef2f2 !important;
            color: #991b1b;
        }

        .table-komparasi tr.row-laba-bersih--positive td.sticky-col {
            background-color: #ecfdf5 !important;
        }

        .table-komparasi tr.row-laba-bersih--negative td.sticky-col {
            background-color: #fef2f2 !important;
        }

        /* ─── Hover Behavior ─────────────────────────────────────────── */
        .table-komparasi tbody tr.laba-rugi-account-row:hover td {
            background-color: #f8fafc !important;
        }

        .table-komparasi tbody tr.laba-rugi-account-row:hover td.sticky-col {
            background-color: #f8fafc !important;
        }

        /* ─── Media Print Bersih ─────────────────────────────────────── */
        @media print {
            @page {
                size: landscape;
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

            .table-komparasi {
                width: 100% !important;
                font-size: 7.75pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .table-komparasi th.sticky-col,
            .table-komparasi td.sticky-col {
                position: static !important;
                box-shadow: none !important;
            }

            .table-komparasi th,
            .table-komparasi td {
                padding: 3px 5px !important;
                border: 1px solid #94a3b8 !important;
            }

            .nominal-val {
                font-size: 7.75pt !important;
            }

            .growth-val {
                font-size: 6.75pt !important;
            }
        }
    </style>
@endpush
