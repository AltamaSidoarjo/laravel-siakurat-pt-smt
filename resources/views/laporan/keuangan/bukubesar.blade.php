@extends('layouts.app')

@section('title', 'Bukubesar')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Bukubesar</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="">
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
                                <label class="form-label">Pilih COA</label>
                                <select name="coaIds[]" class="form-select coa-multiselect" multiple data-placeholder="Pilih satu atau beberapa COA">
                                    @foreach ($coaOptions as $coa)
                                        <option value="{{ $coa['id'] }}" @selected(in_array($coa['id'], $selectedCoaIds, true))>
                                            [{{ $coa['kode'] }}] {{ $coa['nama'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-12 d-flex justify-content-end">
                                <div class="d-flex gap-2 w-100 w-md-auto">
                                    <button type="button" class="btn btn-success" onclick="printReport()">
                                        <i class="bi bi-printer me-1"></i> Print
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="exportTableToExcel()">
                                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-light shadow-sm" id="printableArea">
                <div class="card-body">
                    <div class="laporan-header text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="laporan-header__logo">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" class="laporan-header__logo-image">
                            </div>
                        @endif
                        <div class="laporan-header__identity">
                            <div class="fw-bold laporan-header__title-rs">{{ $namaRumahSakit }}</div>
                            <div class="fw-bold laporan-header__title-report">Bukubesar</div>
                            <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    @forelse ($rowsByCoa as $coa)
                        <div class="card border-light shadow-sm mb-3">
                            <div class="card-body">
                                <div class="fw-bold text-primary mb-3">[{{ $coa['kode_coa'] }}] {{ $coa['nama_coa'] }}</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 exportable-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Nomor</th>
                                                <th>Sumber transaksi</th>
                                                <th>Keterangan</th>
                                                <th class="text-end">Debit</th>
                                                <th class="text-end">Kredit</th>
                                                <th class="text-end">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($coa['rows'] as $row)
                                                <tr class="{{ $row['sumber_transaksi'] === 'SALDO AWAL' ? 'table-info fw-bold' : '' }}">
                                                    <td>{{ $row['tanggal'] }}</td>
                                                    <td>{{ $row['nomer'] }}</td>
                                                    <td>{{ $row['sumber_transaksi'] }}</td>
                                                    <td>{{ $row['keterangan'] }}</td>
                                                    <td class="text-end">{{ $row['debit'] > 0 ? number_format((float) $row['debit'], 0, ',', '.') : '' }}</td>
                                                    <td class="text-end">{{ $row['kredit'] > 0 ? number_format((float) $row['kredit'], 0, ',', '.') : '' }}</td>
                                                    <td class="text-end fw-bold">{{ number_format((float) $row['saldo_berjalan'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            Tidak ada data bukubesar untuk filter yang dipilih.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery('.coa-multiselect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function () {
                    return window.jQuery(this).data('placeholder');
                },
                closeOnSelect: false
            });
        });

        function exportTableToExcel() {
            if (!(window.XLSX && window.XLSX.utils)) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = '<table></table>';
            const exportTable = wrapper.querySelector('table');
            const sourceTables = document.querySelectorAll('.exportable-table');

            sourceTables.forEach(function (table, index) {
                const titleRow = exportTable.insertRow(-1);
                const titleCell = titleRow.insertCell(0);
                titleCell.textContent = table.closest('.card-body').querySelector('.text-primary')?.textContent?.trim() || `COA ${index + 1}`;

                Array.from(table.rows).forEach(function (row) {
                    const newRow = exportTable.insertRow(-1);
                    Array.from(row.cells).forEach(function (cell) {
                        const newCell = document.createElement(row.parentElement.tagName === 'THEAD' ? 'th' : 'td');
                        newCell.textContent = cell.textContent.trim();
                        newRow.appendChild(newCell);
                    });
                });

                exportTable.insertRow(-1);
            });

            const worksheet = XLSX.utils.table_to_sheet(exportTable, { raw: true });
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Bukubesar');
            XLSX.writeFile(workbook, 'Bukubesar_{{ $startDate }}_sampai_{{ $endDate }}.xlsx');
        }

        function printReport() {
            const printableArea = document.getElementById('printableArea');
            if (!printableArea) {
                return;
            }

            const originalContents = document.body.innerHTML;
            document.body.innerHTML = printableArea.innerHTML;
            window.print();
            document.body.innerHTML = originalContents;
            window.location.reload();
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
            .laporan-header__logo-image {
                max-width: 90px;
                max-height: 72px;
            }
        }
    </style>
@endpush
