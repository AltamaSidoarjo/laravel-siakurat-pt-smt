@extends('layouts.app')

@section('title', 'Neraca Saldo')

@php
    $totalDebit = collect($rows)->sum('saldo_debit');
    $totalKredit = collect($rows)->sum('saldo_kredit');
    $selisih = $totalDebit - $totalKredit;
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Neraca Saldo</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Per tanggal</label>
                                <input type="date" name="perDate" class="form-control" value="{{ $perDate }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-flex gap-2 w-100">
                                    <button type="button" class="btn btn-success flex-fill" onclick="printReport()">
                                        <i class="bi bi-printer me-1"></i> Print
                                    </button>
                                    <button type="button" class="btn btn-outline-success flex-fill" onclick="exportTableToExcel()">
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
                            <div class="fw-bold laporan-header__title-report">Neraca Saldo</div>
                            <div class="text-muted">Per {{ $perDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="neraca-saldo-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Akun</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Kredit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $row['kode_coa'] }}</td>
                                        <td>{{ $row['nama_coa'] }}</td>
                                        <td class="text-end">{{ $row['saldo_debit'] > 0 ? number_format((float) $row['saldo_debit'], 0, ',', '.') : '' }}</td>
                                        <td class="text-end">{{ $row['saldo_kredit'] > 0 ? number_format((float) $row['saldo_kredit'], 0, ',', '.') : '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data neraca saldo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (count($rows) > 0)
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered mb-0" id="neraca-saldo-summary-table">
                                <tbody>
                                    <tr class="table-light fw-bold">
                                        <td>Total Debit</td>
                                        <td class="text-end">{{ number_format((float) $totalDebit, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="table-light fw-bold">
                                        <td>Total Kredit</td>
                                        <td class="text-end">{{ number_format((float) $totalKredit, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="{{ abs($selisih) <= 0.01 ? 'table-success' : 'table-danger' }} fw-bold">
                                        <td>Status</td>
                                        <td class="text-end">{{ abs($selisih) <= 0.01 ? 'Seimbang' : 'Selisih '.number_format((float) $selisih, 0, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function exportTableToExcel() {
            if (!(window.XLSX && window.XLSX.utils)) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = '<table></table>';
            const exportTable = wrapper.querySelector('table');
            const sourceTables = [
                document.getElementById('neraca-saldo-table'),
                document.getElementById('neraca-saldo-summary-table')
            ].filter(Boolean);

            sourceTables.forEach(function (table, index) {
                Array.from(table.rows).forEach(function (row) {
                    const newRow = exportTable.insertRow(-1);
                    Array.from(row.cells).forEach(function (cell) {
                        const newCell = document.createElement(row.parentElement.tagName === 'THEAD' ? 'th' : 'td');
                        newCell.textContent = cell.textContent.trim();
                        newRow.appendChild(newCell);
                    });
                });

                if (index < sourceTables.length - 1) {
                    exportTable.insertRow(-1);
                }
            });

            const worksheet = XLSX.utils.table_to_sheet(exportTable, { raw: true });
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'NeracaSaldo');
            XLSX.writeFile(workbook, 'Neraca_Saldo_{{ $perDate }}.xlsx');
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

        @media print {
            .laporan-header__logo-image {
                max-width: 90px;
                max-height: 72px;
            }
        }
    </style>
@endpush
