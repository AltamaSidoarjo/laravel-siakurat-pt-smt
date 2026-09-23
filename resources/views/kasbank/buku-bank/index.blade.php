@extends('layouts.app')

@section('title', 'Buku Bank')

@php
    use Illuminate\Support\Carbon;

    $start = Carbon::parse($startDate);
    $end = Carbon::parse($endDate);
@endphp

@section('content')
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="fs-3 fw-bold">Buku Bank</div>
        </div>
    </div>

    <div class="card border-muhammadiyah mb-3">
        <div class="card-body">
            <form method="get" action="{{ route('kasbank.buku-bank.index') }}" id="filterForm" class="no-print">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="startDate" class="form-label">Dari tanggal</label>
                        <input id="startDate" type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <label for="endDate" class="form-label">Sampai tanggal</label>
                        <input id="endDate" type="date" name="endDate" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4">
                        <label for="coaSelect" class="form-label">COA Kasbank <small class="text-muted">(minimal satu)</small></label>
                        <select id="coaSelect" name="coaIds[]" class="form-select select2" multiple data-placeholder="Cari akun kas/bank...">
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
                        <a href="{{ route('kasbank.buku-bank.index') }}" class="btn btn-outline-secondary" title="Reset filter">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 no-print">
                <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button type="button" class="btn btn-success" id="exportExcel">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                </button>
            </div>
        </div>
    </div>

    <div class="text-center mb-3 print-header">
        @if ($namaRumahSakit)
            <div class="fw-bold fs-5">{{ $namaRumahSakit }}</div>
        @endif
        <div class="fw-bold fs-5">Buku Bank</div>
        <div class="text-muted">Periode {{ $start->translatedFormat('d F Y') }} s/d {{ $end->translatedFormat('d F Y') }}</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger no-print">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (count($selectedCoaIds) === 0)
        <div class="card border-light shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-bank fs-1 d-block mb-3"></i>
                <p class="mb-0">Pilih minimal satu COA Kasbank untuk menampilkan transaksi.</p>
            </div>
        </div>
    @else
        <div class="card border-light shadow-sm mb-3 no-print">
            <div class="card-body">
                <label for="transactionSearch" class="form-label">Cari transaksi</label>
                <input id="transactionSearch" type="search" class="form-control" placeholder="Cari nomor, sumber transaksi, atau keterangan...">
                <small id="searchInfo" class="text-muted"></small>
            </div>
        </div>

        <div id="bookContent">
            @forelse ($rowsByCoa as $coa)
                <div class="card border-light shadow-sm mb-3 coa-card" data-coa="{{ $coa['kode_coa'] }} {{ $coa['nama_coa'] }}">
                    <div class="card-body">
                        <h5 class="text-center text-primary">[{{ $coa['kode_coa'] }}] {{ $coa['nama_coa'] }}</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0 exportable-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nomor</th>
                                        <th>Sumber Transaksi</th>
                                        <th>Keterangan</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Kredit</th>
                                        <th class="text-end">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($coa['rows'] as $row)
                                        <tr class="transaction-row {{ $row['sumber_transaksi'] === 'SALDO AWAL' ? 'table-info fw-bold' : '' }}"
                                            data-search="{{ \Illuminate\Support\Str::lower($row['nomer'].' '.$row['sumber_transaksi'].' '.$row['keterangan']) }}">
                                            <td class="text-nowrap">{{ Carbon::parse($row['tanggal'])->translatedFormat('d M Y') }}</td>
                                            <td>{{ $row['nomer'] }}</td>
                                            <td>{{ $row['sumber_transaksi'] }}</td>
                                            <td>{{ $row['keterangan'] }}</td>
                                            <td class="text-end" data-value="{{ $row['debit'] }}">{{ $row['debit'] > 0 ? number_format($row['debit'], 0, ',', '.') : '' }}</td>
                                            <td class="text-end" data-value="{{ $row['kredit'] }}">{{ $row['kredit'] > 0 ? number_format($row['kredit'], 0, ',', '.') : '' }}</td>
                                            <td class="text-end fw-bold" data-value="{{ $row['saldo_berjalan'] }}">{{ number_format($row['saldo_berjalan'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-light shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        <p class="mb-0">Tidak ada transaksi Buku Bank untuk filter yang dipilih.</p>
                    </div>
                </div>
            @endforelse
        </div>
    @endif
@endsection

@push('styles')
    <style>
        @media print {
            .no-print, .sidebar, .navbar, .app-header { display: none !important; }
            .card { border: 0 !important; box-shadow: none !important; break-inside: avoid; }
            .table { font-size: 10px; }
            body { background: #fff !important; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('transactionSearch');
            search?.addEventListener('input', function () {
                const keyword = this.value.trim().toLocaleLowerCase('id');
                let visible = 0;
                document.querySelectorAll('.transaction-row').forEach(row => {
                    const show = row.dataset.search.includes(keyword);
                    row.classList.toggle('d-none', !show);
                    if (show) visible++;
                });
                document.querySelectorAll('.coa-card').forEach(card => {
                    card.classList.toggle('d-none', !card.querySelector('.transaction-row:not(.d-none)'));
                });
                document.getElementById('searchInfo').textContent = keyword ? `${visible} baris ditemukan` : '';
            });

            document.getElementById('exportExcel')?.addEventListener('click', function () {
                if (typeof XLSX === 'undefined') {
                    alert('Library export Excel tidak tersedia.');
                    return;
                }

                const rows = [
                    [@json($namaRumahSakit ?: '')],
                    ['Buku Bank'],
                    [@json('Periode '.$start->format('d-m-Y').' s/d '.$end->format('d-m-Y'))],
                    []
                ];

                document.querySelectorAll('.coa-card:not(.d-none)').forEach(card => {
                    rows.push([card.dataset.coa]);
                    rows.push(['Tanggal', 'Nomor', 'Sumber Transaksi', 'Keterangan', 'Debit', 'Kredit', 'Saldo']);
                    card.querySelectorAll('.transaction-row:not(.d-none)').forEach(row => {
                        const cells = row.querySelectorAll('td');
                        rows.push([
                            cells[0].textContent.trim(), cells[1].textContent.trim(), cells[2].textContent.trim(),
                            cells[3].textContent.trim(), Number(cells[4].dataset.value),
                            Number(cells[5].dataset.value), Number(cells[6].dataset.value)
                        ]);
                    });
                    rows.push([]);
                });

                if (rows.length === 4) {
                    alert('Tidak ada data yang dapat diexport.');
                    return;
                }

                const worksheet = XLSX.utils.aoa_to_sheet(rows);
                worksheet['!cols'] = [{ wch: 14 }, { wch: 20 }, { wch: 24 }, { wch: 45 }, { wch: 18 }, { wch: 18 }, { wch: 18 }];
                const workbook = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(workbook, worksheet, 'Buku Bank');
                XLSX.writeFile(workbook, @json('buku-bank-'.$start->format('Ymd').'-'.$end->format('Ymd').'.xlsx'));
            });
        });
    </script>
@endpush
