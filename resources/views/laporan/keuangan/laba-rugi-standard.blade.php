@extends('layouts.app')

@section('title', 'Laba Rugi Standard')

@php
    $rowsCollection = collect($rows);
    $viewRows = $rowsCollection
        ->filter(fn (array $item) => ($item['sort_level'] ?? 0) === 0
            || ($item['has_children'] ?? false)
            || abs((float) $item['nominal']) > 0.01
            || abs((float) $item['rba_nominal']) > 0.01)
        ->values();

    $isPendapatan = fn (array $item) => str_contains(strtolower((string) ($item['tipe_coa'] ?? '')), 'pendapatan');
    $isBiaya = fn (array $item) => str_contains(strtolower((string) ($item['tipe_coa'] ?? '')), 'beban')
        || str_contains(strtolower((string) ($item['tipe_coa'] ?? '')), 'biaya');
    $visibleChildren = $viewRows->where('sort_level', '>', 0)->values();
    $labaRugi = (float) $visibleChildren->sum(fn (array $item) => $isPendapatan($item) ? (float) $item['nominal'] : ($isBiaya($item) ? (float) $item['nominal'] * -1 : 0));
    $labaRugiRba = (float) $visibleChildren->sum(fn (array $item) => $isPendapatan($item) ? (float) $item['rba_nominal'] : ($isBiaya($item) ? (float) $item['rba_nominal'] * -1 : 0));
    $formatPersentase = function (float $capaian, float $rba): string {
        if (abs($rba) <= 0.01) {
            return abs($capaian) <= 0.01 ? '0%' : '-';
        }

        return number_format(($capaian / $rba) * 100, 0, ',', '.').'%';
    };
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laba Rugi Standard</span>
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
                                <label class="form-label">Dari tanggal</label>
                                <input type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sampai tanggal</label>
                                <input type="date" name="endDate" class="form-control" value="{{ $endDate }}">
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
                            <div class="fw-bold laporan-header__title-report">Laba Rugi Standard</div>
                            <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Deskripsi</th>
                                    <th class="text-end">RBA</th>
                                    <th class="text-end">Capaian</th>
                                    <th class="text-end">Selisih</th>
                                    <th class="text-end">% Capaian</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($viewRows as $index => $row)
                                    @php
                                        $isRoot = $row['sort_level'] === 0;
                                        $indentLevel = max(((int) ($row['level'] ?? 0)) - 1, 0);
                                        $selisih = (float) $row['nominal'] - (float) $row['rba_nominal'];
                                    @endphp
                                    <tr class="{{ $isRoot ? 'table-light fw-bold' : '' }}">
                                        <td>
                                            <div class="{{ $isRoot ? '' : 'laporan-row-code laporan-row-code--child' }}">
                                                {{ $row['kode'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <div
                                                class="laporan-row-label{{ $isRoot ? '' : ' laporan-row-label--child' }}"
                                            >
                                                @if (!$isRoot && $row['has_children'])
                                                    <a href="{{ route('laporan.keuangan.laba-rugi-per-parent-coa', ['coaId' => $row['coa_id'], 'startDate' => $startDate, 'endDate' => $endDate]) }}" class="text-decoration-none">
                                                        {{ $row['deskripsi'] }}
                                                    </a>
                                                @else
                                                    {{ $row['deskripsi'] }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $row['rba_nominal'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) $row['nominal'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($selisih, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ $formatPersentase((float) $row['nominal'], (float) $row['rba_nominal']) }}</td>
                                    </tr>
                                    @php
                                        $nextRow = $viewRows[$index + 1] ?? null;
                                    @endphp
                                    @if (!$isRoot && ($nextRow === null || $nextRow['sort_level'] === 0))
                                        @php
                                            $rootChildRows = $viewRows
                                                ->take($index + 1)
                                                ->reverse()
                                                ->takeWhile(fn (array $item) => $item['sort_level'] !== 0)
                                                ->values();
                                            $subtotalNominal = (float) $rootChildRows->sum('nominal');
                                            $subtotalRba = (float) $rootChildRows->sum('rba_nominal');
                                            $subtotalSelisih = $subtotalNominal - $subtotalRba;
                                        @endphp
                                        <tr class="table-light fw-bold">
                                            <td></td>
                                            <td class="text-end">Subtotal</td>
                                            <td class="text-end">{{ number_format($subtotalRba, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotalNominal, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotalSelisih, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ $formatPersentase($subtotalNominal, $subtotalRba) }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Tidak ada data untuk ditampilkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($viewRows->isNotEmpty())
                                <tfoot>
                                    <tr class="fw-bold {{ $labaRugi >= 0 ? 'table-success' : 'table-danger' }}">
                                        <td></td>
                                        <td class="text-end">Laba (Rugi)</td>
                                        <td class="text-end">{{ number_format($labaRugiRba, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($labaRugi, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($labaRugi - $labaRugiRba, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ $formatPersentase($labaRugi, $labaRugiRba) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
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
            const table = document.getElementById('datatable');
            if (!table || !(window.XLSX && window.XLSX.utils)) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = table.outerHTML;
            const tableClone = wrapper.querySelector('table');
            if (!tableClone) {
                return;
            }

            tableClone.querySelectorAll('a').forEach(function (anchor) {
                anchor.replaceWith(document.createTextNode(anchor.textContent));
            });

            const worksheet = XLSX.utils.table_to_sheet(tableClone, { raw: true });
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'LabaRugi');

            const filename = 'LabaRugi_{{ $startDate }}_sampai_{{ $endDate }}.xlsx';
            XLSX.writeFile(workbook, filename);
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

        .laporan-row-label {
            display: block;
        }

        .laporan-row-code {
            display: block;
        }

        .laporan-row-code--child {
            margin-left: 1.5rem;
        }

        .laporan-row-label--child {
            margin-left: 1.5rem;
        }

        @media print {
            .laporan-header__logo-image {
                max-width: 90px;
                max-height: 72px;
            }
        }
    </style>
@endpush
