@extends('layouts.app')

@section('title', 'Neraca Standard')

@php
    $selisih = (float) $subtotalAktiva - (float) $subtotalPasivaEkuitas;
    $isBalance = abs($selisih) <= 0.01;
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Neraca Standard</span>
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
                            <div class="fw-bold laporan-header__title-report">Neraca</div>
                            <div class="text-muted">Per {{ $perDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="neraca-standard-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Deskripsi</th>
                                    <th>Tipe</th>
                                    <th class="text-end">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr class="{{ $row['is_root'] ? 'table-light fw-bold' : '' }}">
                                        <td>{{ $row['kode_coa'] }}</td>
                                        <td style="padding-left: {{ max(0, ($row['level'] - 1) * 18) }}px;">
                                            @if (!$row['is_root'] && $row['has_children'])
                                                <a href="{{ route('laporan.keuangan.neraca-per-parent-coa', ['coaId' => $row['coa_id'], 'perDate' => $perDate]) }}" class="text-decoration-none">
                                                    {{ $row['nama_coa'] }}
                                                </a>
                                            @else
                                                {{ $row['nama_coa'] }}
                                            @endif
                                        </td>
                                        <td>{{ $row['tipe_coa'] }}</td>
                                        <td class="text-end">
                                            {{ $row['is_root'] || $row['display_saldo'] === null ? '-' : number_format((float) $row['display_saldo'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data neraca.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-bordered mb-0" id="neraca-standard-summary-table">
                            <tbody>
                                <tr class="table-light fw-bold">
                                    <td>Subtotal Aktiva</td>
                                    <td class="text-end">{{ number_format((float) $subtotalAktiva, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light fw-bold">
                                    <td>Subtotal Pasiva</td>
                                    <td class="text-end">{{ number_format((float) $subtotalPasiva, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light fw-bold">
                                    <td>Subtotal Ekuitas</td>
                                    <td class="text-end">{{ number_format((float) $subtotalEkuitas, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light fw-bold">
                                    <td>Subtotal Pasiva + Ekuitas</td>
                                    <td class="text-end">{{ number_format((float) $subtotalPasivaEkuitas, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="{{ $isBalance ? 'table-success' : 'table-danger' }} fw-bold">
                                    <td>Status Neraca</td>
                                    <td class="text-end">{{ $isBalance ? 'BALANCE' : 'TIDAK BALANCE' }} | Selisih {{ number_format((float) $selisih, 0, ',', '.') }}</td>
                                </tr>
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
        function exportTableToExcel() {
            if (!(window.XLSX && window.XLSX.utils)) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = '<table></table>';
            const exportTable = wrapper.querySelector('table');
            const sourceTables = [
                document.getElementById('neraca-standard-table'),
                document.getElementById('neraca-standard-summary-table')
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
            XLSX.utils.book_append_sheet(workbook, worksheet, 'NeracaStandard');
            XLSX.writeFile(workbook, 'Neraca_Standard_{{ $perDate }}.xlsx');
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
