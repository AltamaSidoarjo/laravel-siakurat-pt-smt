@extends('layouts.app')

@section('title', 'Laba Rugi Detil')

@php
    $totals = collect($rows)->groupBy('tipe_coa')->map->sum('nominal');
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laba Rugi Detil</span>
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
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
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
                            <div class="fw-bold laporan-header__title-report">Laba Rugi Detil</div>
                            <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="laba-rugi-detil-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Deskripsi</th>
                                    <th>Tipe</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $row['kode'] }}</td>
                                        <td>{{ $row['deskripsi'] }}</td>
                                        <td>{{ $row['tipe_coa'] }}</td>
                                        <td class="text-end">{{ number_format((float) $row['nominal'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada data untuk periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (count($rows) > 0)
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered mb-0" id="laba-rugi-detil-summary-table">
                                <tbody>
                                    @foreach ($totals as $tipe => $nominal)
                                        <tr class="table-light fw-bold">
                                            <td>{{ $tipe }}</td>
                                            <td class="text-end">{{ number_format((float) $nominal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
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
                document.getElementById('laba-rugi-detil-table'),
                document.getElementById('laba-rugi-detil-summary-table')
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
            XLSX.utils.book_append_sheet(workbook, worksheet, 'LabaRugiDetil');
            XLSX.writeFile(workbook, 'Laba_Rugi_Detil_{{ $startDate }}_sampai_{{ $endDate }}.xlsx');
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
