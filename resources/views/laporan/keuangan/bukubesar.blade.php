@extends('layouts.app')

@section('title', 'Bukubesar')

@php
    use Illuminate\Support\Carbon;

    $startDateCarbon = Carbon::parse($startDate);
    $endDateCarbon = Carbon::parse($endDate);
    $fileStartDate = $startDateCarbon->format('Ymd');
    $fileEndDate = $endDateCarbon->format('Ymd');
@endphp

@section('content')
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Bukubesar</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="card border-light shadow-sm no-print">
                            <div class="card-body">
                                <form method="get" action="" id="filterForm">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Dari tanggal</label>
                                            <input type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Sampai tanggal</label>
                                            <input type="date" name="endDate" class="form-control" value="{{ $endDate }}">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Pilih COA <small class="text-muted">(wajib pilih minimal 1 akun)</small></label>
                                            <select
                                                name="coaIds[]"
                                                class="form-select coa-multiselect"
                                                id="coaSelect"
                                                multiple
                                                data-placeholder="Cari dan pilih COA..."
                                            >
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa['id'] }}" @selected(in_array($coa['id'], $selectedCoaIds, true))>
                                                        [{{ $coa['kode'] }}] {{ $coa['nama'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-2 d-flex align-items-end gap-2">
                                            <button type="submit" class="btn btn-primary flex-grow-1">
                                                <i class="bi bi-funnel me-1"></i>Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="resetFilter()">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>

                                    @if (count($selectedCoaIds) > 0)
                                        <div class="mt-3">
                                            <div class="alert alert-info mb-0 d-flex align-items-center">
                                                <i class="bi bi-info-circle me-2"></i>
                                                <span>Menampilkan <strong>{{ count($selectedCoaIds) }} COA</strong> terpilih</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="mt-3">
                                            <div class="alert alert-warning mb-0 d-flex align-items-center">
                                                <i class="bi bi-exclamation-circle me-2"></i>
                                                <span>Pilih minimal satu COA terlebih dulu agar bukubesar tidak memuat seluruh akun sekaligus.</span>
                                            </div>
                                        </div>
                                    @endif
                                </form>

                                <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                                    <button type="button" class="btn btn-outline-dark" onclick="printBukubesar()">
                                        <i class="bi bi-printer me-1"></i> Print
                                    </button>

                                    <button type="button" class="btn btn-success" onclick="exportBukubesarExcel()">
                                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm" id="printAreaStart">
                            <div class="card-body">
                                <div class="text-center mb-3 laporan-header">
                                    @if ($logoRsUrl)
                                        <div class="mb-2 rs-logo no-print">
                                            <img
                                                src="{{ $logoRsUrl }}"
                                                alt="Logo Rumah Sakit"
                                                class="rs-logo__image"
                                            />
                                        </div>
                                    @endif

                                    @if ($namaRumahSakit)
                                        <div class="fw-bold" id="namaRsExport" style="font-size: 20px;">
                                            {{ $namaRumahSakit }}
                                        </div>
                                    @endif

                                    <div class="fw-bold" id="judulExport" style="font-size: 18px;">
                                        Bukubesar
                                    </div>

                                    <div class="text-muted" id="periodeExport" style="font-size: 14px;">
                                        Periode {{ $startDateCarbon->translatedFormat('d F Y') }} s/d {{ $endDateCarbon->translatedFormat('d F Y') }}
                                    </div>

                                    @if (count($selectedCoaIds) > 0)
                                        <div class="text-muted" style="font-size: 13px;">
                                            <i class="bi bi-filter-circle"></i> Filter: {{ count($selectedCoaIds) }} COA Terpilih
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm no-print">
                            <div class="card-body">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Cari transaksi</label>
                                        <input
                                            type="text"
                                            id="globalSearch"
                                            class="form-control"
                                            placeholder="Cari Nomor / Sumber Transaksi / Keterangan..."
                                        >
                                        <small class="text-muted">Pencarian hanya memfilter data yang sedang tampil (hasil filter tanggal &amp; COA).</small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <div class="text-muted">
                                            <span id="searchResultInfo"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if (count($selectedCoaIds) === 0)
                            <div class="card border-light shadow-sm">
                                <div class="card-body text-center text-muted py-5">
                                    <i class="bi bi-funnel fs-1 d-block mb-3"></i>
                                    <p class="mb-1 fw-semibold">Pilih minimal satu COA untuk menampilkan bukubesar.</p>
                                    <p class="mb-0">Gunakan kolom pencarian COA di atas agar halaman tetap ringan saat data akun banyak.</p>
                                </div>
                            </div>
                        @else
                        @forelse ($rowsByCoa as $coa)
                            @php
                                $footerSaldo = collect($coa['rows'])->last()['saldo_berjalan'] ?? 0;
                            @endphp
                            <div class="card border-light shadow-sm coa-card" data-coa="{{ $coa['kode_coa'] }}">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <div class="fw-bold text-primary coa-title" style="font-size: 16px;">
                                            [{{ $coa['kode_coa'] }}] {{ $coa['nama_coa'] }}
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-hover mb-0 exportable-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 120px;">Tanggal</th>
                                                    <th style="width: 150px;">Nomor</th>
                                                    <th style="width: 150px;">Sumber Transaksi</th>
                                                    <th>Keterangan</th>
                                                    <th style="width: 150px;" class="text-end">Debit</th>
                                                    <th style="width: 150px;" class="text-end">Kredit</th>
                                                    <th style="width: 150px;" class="text-end">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="coa-tbody">
                                                @foreach ($coa['rows'] as $row)
                                                    <tr class="coa-row {{ $row['sumber_transaksi'] === 'SALDO AWAL' ? 'table-info fw-bold' : '' }}">
                                                        <td class="text-nowrap">
                                                            {{ Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}
                                                        </td>
                                                        <td>{{ $row['nomer'] }}</td>
                                                        <td>{{ $row['sumber_transaksi'] }}</td>
                                                        <td>{{ $row['keterangan'] }}</td>
                                                        <td class="text-end">
                                                            {{ $row['debit'] > 0 ? number_format((float) $row['debit'], 0, ',', '.') : '' }}
                                                        </td>
                                                        <td class="text-end">
                                                            {{ $row['kredit'] > 0 ? number_format((float) $row['kredit'], 0, ',', '.') : '' }}
                                                        </td>
                                                        <td class="text-end fw-bold">
                                                            {{ number_format((float) $row['saldo_berjalan'], 0, ',', '.') }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-secondary export-exclude print-exclude">
                                                <tr class="fw-bold">
                                                    <td colspan="4" class="text-end">Total</td>
                                                    <td class="text-end">{{ number_format((float) collect($coa['rows'])->sum('debit'), 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) collect($coa['rows'])->sum('kredit'), 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $footerSaldo, 0, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="card border-light shadow-sm">
                                <div class="card-body text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                    <p class="mb-1 fw-semibold">Tidak ada data bukubesar untuk COA yang dipilih.</p>
                                    <p class="mb-0">Coba ganti rentang tanggal atau pilih COA lain.</p>
                                </div>
                            </div>
                        @endforelse
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.jQuery(function () {
            const $ = window.jQuery;

            if ($ && $.fn.select2) {
                $('#coaSelect').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Cari dan pilih COA...',
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                    minimumInputLength: 1,
                    ajax: {
                        url: '{{ route('laporan.keuangan.bukubesar.search-coa') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || ''
                            };
                        },
                        processResults: function (data) {
                            return data;
                        },
                        cache: true
                    },
                    language: {
                        noResults: function () {
                            return 'COA tidak ditemukan';
                        },
                        searching: function () {
                            return 'Mencari...';
                        },
                        inputTooShort: function () {
                            return 'Ketik minimal 1 huruf untuk mencari COA';
                        }
                    }
                });
            }

            function normalizeText(text) {
                return (text || '').toString().toLowerCase().trim();
            }

            function applyGlobalSearch() {
                const keyword = normalizeText(document.getElementById('globalSearch')?.value);
                let totalVisibleRows = 0;
                let totalRows = 0;

                document.querySelectorAll('.coa-card').forEach(function (card) {
                    const rows = card.querySelectorAll('tbody.coa-tbody tr.coa-row');
                    let anyVisibleInThisCoa = false;

                    totalRows += rows.length;

                    rows.forEach(function (row) {
                        const cols = row.querySelectorAll('td');
                        const nomor = normalizeText(cols[1]?.textContent);
                        const sumber = normalizeText(cols[2]?.textContent);
                        const ket = normalizeText(cols[3]?.textContent);
                        const haystack = `${nomor} ${sumber} ${ket}`;
                        const matched = keyword === '' || haystack.includes(keyword);

                        row.style.display = matched ? '' : 'none';

                        if (matched) {
                            anyVisibleInThisCoa = true;
                            totalVisibleRows++;
                        }
                    });

                    card.style.display = keyword !== '' && !anyVisibleInThisCoa ? 'none' : '';
                });

                const resultInfo = document.getElementById('searchResultInfo');
                if (!resultInfo) {
                    return;
                }

                resultInfo.textContent = keyword === ''
                    ? ''
                    : `Menampilkan ${totalVisibleRows} baris dari ${totalRows} baris (sesuai pencarian).`;
            }

            let searchTimer = null;
            document.getElementById('globalSearch')?.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyGlobalSearch, 150);
            });
        });

        function resetFilter() {
            const coaSelect = document.getElementById('coaSelect');
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(coaSelect).val(null).trigger('change');
            } else if (coaSelect) {
                Array.from(coaSelect.options).forEach(function (option) {
                    option.selected = false;
                });
            }

            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);

            const formatDate = function (date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            document.querySelector('input[name="startDate"]').value = formatDate(firstDay);
            document.querySelector('input[name="endDate"]').value = formatDate(now);
            document.getElementById('filterForm').submit();
        }

        function printBukubesar() {
            window.print();
        }

        function exportBukubesarExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Library XLSX belum termuat. Pastikan CDN SheetJS dapat diakses.');
                return;
            }

            const namaRS = (document.getElementById('namaRsExport')?.textContent || '').trim();
            const judul = (document.getElementById('judulExport')?.textContent || 'Bukubesar').trim();
            const periode = (document.getElementById('periodeExport')?.textContent || '').trim();
            const workbook = XLSX.utils.book_new();
            const aoa = [];

            if (namaRS) {
                aoa.push([namaRS]);
            }
            aoa.push([judul]);
            if (periode) {
                aoa.push([periode]);
            }
            aoa.push([]);

            let hasData = false;

            document.querySelectorAll('.coa-card').forEach(function (card) {
                if (card.style.display === 'none') {
                    return;
                }

                const coaTitle = (card.querySelector('.coa-title')?.textContent || '').trim();
                const table = card.querySelector('table');
                if (!table) {
                    return;
                }

                if (coaTitle) {
                    aoa.push([coaTitle]);
                }

                const headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
                    return (th.textContent || '').trim();
                });
                aoa.push(headers);

                let rowCount = 0;
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    if (row.style.display === 'none') {
                        return;
                    }

                    const exportRow = [];
                    row.querySelectorAll('td').forEach(function (cell, index) {
                        const cellText = (cell.textContent || '').trim();
                        if (index >= 4 && index <= 6) {
                            const numericValue = Number(cellText.replace(/\./g, '').replace(',', '.'));
                            exportRow.push(Number.isFinite(numericValue) ? numericValue : '');
                            return;
                        }

                        exportRow.push(cellText);
                    });

                    aoa.push(exportRow);
                    rowCount++;
                });

                aoa.push([]);

                if (rowCount > 0) {
                    hasData = true;
                }
            });

            if (!hasData) {
                alert('Tidak ada data yang dapat diexport (tidak ada baris transaksi yang tampil).');
                return;
            }

            const worksheet = XLSX.utils.aoa_to_sheet(aoa);
            forceAllCellsAsText(worksheet);
            worksheet['!cols'] = [
                { wch: 14 },
                { wch: 18 },
                { wch: 22 },
                { wch: 40 },
                { wch: 16 },
                { wch: 16 },
                { wch: 16 }
            ];

            XLSX.utils.book_append_sheet(workbook, worksheet, 'Bukubesar');
            XLSX.writeFile(workbook, 'Bukubesar_{{ $fileStartDate }}_{{ $fileEndDate }}.xlsx');
        }

        function forceAllCellsAsText(worksheet) {
            const ref = worksheet['!ref'];
            if (!ref) {
                return;
            }

            const range = XLSX.utils.decode_range(ref);
            const numericColumns = new Set([4, 5, 6]);

            for (let row = range.s.r; row <= range.e.r; row++) {
                for (let col = range.s.c; col <= range.e.c; col++) {
                    const address = XLSX.utils.encode_cell({ r: row, c: col });
                    const cell = worksheet[address];
                    if (!cell) {
                        continue;
                    }

                    if (numericColumns.has(col) && row > 0 && typeof cell.v === 'number') {
                        cell.t = 'n';
                        continue;
                    }

                    cell.t = 's';
                    cell.v = (cell.v ?? '').toString();
                }
            }
        }
    </script>
@endpush

@push('styles')
    <style>
        .laporan-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .rs-logo {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .rs-logo__image {
            display: block;
            max-width: 100%;
            max-height: 80px;
            width: auto;
            height: auto;
            margin: 0 auto;
            object-fit: contain;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 38px;
            border-color: var(--bs-border-color);
            padding: 0.25rem 0.5rem;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background: var(--bs-success-bg-subtle);
            border: 1px solid var(--bs-success-border-subtle);
            color: var(--bs-success-text-emphasis);
            padding: 0.15rem 0.45rem;
        }

        .select2-container--bootstrap-5 .select2-search--inline .select2-search__field {
            min-height: 24px;
        }

        @media print {
            .no-print,
            .select2-container {
                display: none !important;
            }

            .rs-logo {
                display: none !important;
            }

            tfoot.print-exclude {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .table {
                font-size: 11px !important;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }

            .coa-card {
                page-break-inside: avoid;
            }

            @page {
                size: A4 portrait;
                margin: 12mm;
            }
        }
    </style>
@endpush
