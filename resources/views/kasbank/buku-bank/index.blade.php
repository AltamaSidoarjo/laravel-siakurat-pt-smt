@extends('layouts.app')

@section('title', 'Buku Bank')

@php
    use Illuminate\Support\Carbon;

    $startDateCarbon = Carbon::parse($startDate);
    $endDateCarbon = Carbon::parse($endDate);
@endphp

@section('content')
    <div class="row mb-3 no-print">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
                <span class="fw-bold">Buku Bank</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah mb-2">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm no-print">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form method="get" action="{{ route('kasbank.buku-bank.index') }}" id="filterForm">
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
                                <label class="form-label">Akun Kas/Bank <small class="text-muted">(kosong = semua)</small></label>
                                <select name="coaIds[]" id="coaSelect" class="form-select" multiple>
                                    @foreach ($coaOptions as $coa)
                                        <option value="{{ $coa->id }}" @selected(in_array($coa->id, $selectedCoaIds, true))>
                                            [{{ $coa->kode }}] {{ $coa->nama }}{{ (int) $coa->status_aktif !== 1 ? ' (Nonaktif)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                                <a href="{{ route('kasbank.buku-bank.index') }}" class="btn btn-outline-secondary" title="Reset">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                        <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportBukuBank()">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                        </button>
                    </div>
                </div>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body text-center laporan-header">
                    @if ($namaRumahSakit)
                        <div class="fw-bold" id="namaRsExport" style="font-size: 20px;">{{ $namaRumahSakit }}</div>
                    @endif
                    <div class="fw-bold" id="judulExport" style="font-size: 18px;">Buku Bank</div>
                    <div class="text-muted" id="periodeExport">
                        Periode {{ $startDateCarbon->translatedFormat('d F Y') }} s/d {{ $endDateCarbon->translatedFormat('d F Y') }}
                    </div>
                </div>
            </div>

            <div class="card border-light shadow-sm no-print">
                <div class="card-body">
                    <label for="globalSearch" class="form-label">Cari transaksi</label>
                    <input type="text" id="globalSearch" class="form-control" placeholder="Cari nomor, sumber transaksi, atau keterangan...">
                    <small class="text-muted" id="searchResultInfo"></small>
                </div>
            </div>

            @forelse ($rowsByCoa as $coa)
                @php($footerSaldo = collect($coa['rows'])->last()['saldo_berjalan'] ?? 0)
                <div class="card border-light shadow-sm coa-card">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="fw-bold text-primary coa-title">
                                [{{ $coa['kode_coa'] }}] {{ $coa['nama_coa'] }}
                                @if ($coa['status_aktif'] !== 1)
                                    <span class="badge text-bg-secondary no-print">Nonaktif</span>
                                @endif
                            </div>
                        </div>
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
                                        <tr class="coa-row {{ $row['sumber_transaksi'] === 'SALDO AWAL' ? 'table-info fw-bold' : '' }}">
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
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="6" class="text-end">Saldo Akhir</td>
                                        <td class="text-end">{{ number_format($footerSaldo, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-light shadow-sm">
                    <div class="card-body text-center text-muted py-5">Tidak ada akun Kasbank yang sesuai dengan filter.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery('#coaSelect').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Semua akun Kas/Bank',
                    allowClear: true,
                    width: '100%'
                });
            }

            const search = document.getElementById('globalSearch');
            search?.addEventListener('input', function () {
                const keyword = search.value.toLowerCase().trim();
                let visible = 0;
                let total = 0;

                document.querySelectorAll('.coa-card').forEach(function (card) {
                    let cardVisible = false;
                    card.querySelectorAll('.coa-row').forEach(function (row) {
                        total++;
                        const matched = keyword === '' || row.textContent.toLowerCase().includes(keyword);
                        row.style.display = matched ? '' : 'none';
                        if (matched) {
                            visible++;
                            cardVisible = true;
                        }
                    });
                    card.style.display = keyword !== '' && !cardVisible ? 'none' : '';
                });

                document.getElementById('searchResultInfo').textContent = keyword === ''
                    ? ''
                    : `Menampilkan ${visible} dari ${total} baris.`;
            });
        });

        function exportBukuBank() {
            if (!(window.XLSX && window.XLSX.utils)) {
                alert('Library XLSX belum termuat.');
                return;
            }

            const rows = [
                [(document.getElementById('namaRsExport')?.textContent || '').trim()],
                ['Buku Bank'],
                [(document.getElementById('periodeExport')?.textContent || '').trim()],
                []
            ];
            let exported = false;

            document.querySelectorAll('.coa-card').forEach(function (card) {
                if (card.style.display === 'none') {
                    return;
                }
                rows.push([(card.querySelector('.coa-title')?.textContent || '').trim()]);
                rows.push(['Tanggal', 'Nomor', 'Sumber Transaksi', 'Keterangan', 'Debit', 'Kredit', 'Saldo']);

                card.querySelectorAll('tbody .coa-row').forEach(function (row) {
                    if (row.style.display === 'none') {
                        return;
                    }
                    const cells = row.querySelectorAll('td');
                    rows.push([
                        cells[0]?.textContent.trim() || '',
                        cells[1]?.textContent.trim() || '',
                        cells[2]?.textContent.trim() || '',
                        cells[3]?.textContent.trim() || '',
                        Number(cells[4]?.dataset.value || 0),
                        Number(cells[5]?.dataset.value || 0),
                        Number(cells[6]?.dataset.value || 0)
                    ]);
                    exported = true;
                });
                rows.push([]);
            });

            if (!exported) {
                alert('Tidak ada data yang dapat diekspor.');
                return;
            }

            const worksheet = XLSX.utils.aoa_to_sheet(rows);
            worksheet['!cols'] = [{ wch: 16 }, { wch: 20 }, { wch: 24 }, { wch: 42 }, { wch: 16 }, { wch: 16 }, { wch: 16 }];
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Buku Bank');
            XLSX.writeFile(workbook, 'Buku_Bank_{{ $startDateCarbon->format('Ymd') }}_{{ $endDateCarbon->format('Ymd') }}.xlsx');
        }
    </script>
@endpush

@push('styles')
    <style>
        @media print {
            .no-print, .sidebar, .navbar, .select2-container { display: none !important; }
            body, .card { background: #fff !important; }
            .card { border: 0 !important; box-shadow: none !important; }
            .coa-card { page-break-inside: avoid; }
            thead { display: table-header-group; }
            .table { font-size: 11px; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
@endpush
