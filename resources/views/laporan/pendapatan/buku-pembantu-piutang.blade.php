@extends('layouts.app')

@section('title', 'Buku Pembantu Piutang')

@php
    use Illuminate\Support\Carbon;

    $startDateCarbon = Carbon::parse($startDate);
    $endDateCarbon = Carbon::parse($endDate);
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Buku Pembantu Piutang</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah mb-2">
        <div class="card-body d-flex flex-column gap-3">
            @isset($errors)
                @include('partials.validation-errors')
            @endisset

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="{{ route('laporan.pendapatan.buku-pembantu-piutang') }}" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Dari tanggal</label>
                                <input type="date" name="startDate" class="form-control" value="{{ $startDate }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sampai tanggal</label>
                                <input type="date" name="endDate" class="form-control" value="{{ $endDate }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                                <select name="pelangganIds[]" id="pelangganSelect" class="form-select" multiple data-placeholder="Cari dan pilih pelanggan...">
                                    @foreach ($pelangganTerpilih as $pelanggan)
                                        <option value="{{ $pelanggan->id }}" selected>
                                            [{{ $pelanggan->kode_pelanggan }}] {{ $pelanggan->nama_pelanggan }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Wajib memilih minimal satu pelanggan.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Akun Piutang</label>
                                <select name="akunPiutang" id="akunPiutangSelect" class="form-select" data-placeholder="Semua akun">
                                    <option value="">Semua akun</option>
                                    <option value="tanpa-akun" @selected($akunPiutang === 'tanpa-akun')>Tanpa akun piutang</option>
                                    @if ($coaPiutangTerpilih)
                                        <option value="{{ $coaPiutangTerpilih->id }}" selected>
                                            [{{ $coaPiutangTerpilih->kode }}] {{ $coaPiutangTerpilih->nama }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status Saldo Akhir</label>
                                <select name="statusSaldo" class="form-select">
                                    <option value="semua" @selected($statusSaldo === 'semua')>Semua</option>
                                    <option value="masih-piutang" @selected($statusSaldo === 'masih-piutang')>Masih Piutang</option>
                                    <option value="lunas" @selected($statusSaldo === 'lunas')>Lunas</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end justify-content-end gap-2 flex-wrap">
                                <a href="{{ route('laporan.pendapatan.buku-pembantu-piutang') }}" class="btn btn-light">Reset</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-1"></i>Tampilkan
                                </button>
                                <button
                                    type="submit"
                                    class="btn btn-outline-success"
                                    formaction="{{ route('laporan.pendapatan.buku-pembantu-piutang.export-csv') }}"
                                    @disabled(! $hasSelection)
                                >
                                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if (! $hasSelection)
                <div class="card border-light shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                        <p class="mb-1 fw-semibold">Pilih minimal satu pelanggan untuk menampilkan laporan.</p>
                        <p class="mb-0">Pemilihan pelanggan diperlukan agar laporan tetap ringan dan fokus.</p>
                    </div>
                </div>
            @else
                <div class="card border-light shadow-sm">
                    <div class="card-body text-center">
                        @if ($logoRsUrl)
                            <img src="{{ $logoRsUrl }}" alt="Logo rumah sakit" class="report-logo mb-2">
                        @endif
                        <div class="fw-bold fs-5">{{ $namaRumahSakit }}</div>
                        <div class="fw-bold">BUKU PEMBANTU PIUTANG</div>
                        <div class="text-muted">
                            Periode {{ $startDateCarbon->translatedFormat('d F Y') }} s/d {{ $endDateCarbon->translatedFormat('d F Y') }}
                        </div>
                        <div class="text-muted small">{{ count($pelangganIds) }} pelanggan dipilih</div>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                    <div class="col">
                        <div class="card h-100 border-light shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Saldo Awal</div>
                                <div class="fw-bold fs-5">Rp {{ number_format($summary['saldo_awal'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-light shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Penambahan Piutang</div>
                                <div class="fw-bold fs-5 text-primary">Rp {{ number_format($summary['total_debit'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-light shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Pembayaran</div>
                                <div class="fw-bold fs-5 text-success">Rp {{ number_format($summary['total_kredit'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-light shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Saldo Akhir</div>
                                <div class="fw-bold fs-5">Rp {{ number_format($summary['saldo_akhir'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-light shadow-sm">
                    <div class="card-body">
                        <label for="transactionSearch" class="form-label">Cari transaksi</label>
                        <input type="search" id="transactionSearch" class="form-control" placeholder="Cari nomor faktur, pembayaran, pelanggan, pasien, akun, atau keterangan...">
                        <div class="text-muted small mt-2" id="searchResultInfo"></div>
                    </div>
                </div>

                <div id="customerCards" class="d-flex flex-column gap-3">
                    @forelse ($cards as $card)
                        <div class="card border-light shadow-sm customer-card" data-customer="{{ strtolower($card['kode_pelanggan'].' '.$card['nama_pelanggan']) }}">
                            <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold text-primary">
                                        [{{ $card['kode_pelanggan'] ?: '-' }}] {{ $card['nama_pelanggan'] }}
                                    </div>
                                    <div class="text-muted small">Akun: {{ implode(', ', $card['akun']) }}</div>
                                </div>
                                @if ($card['saldo_akhir'] > 0)
                                    <span class="badge bg-danger">Saldo: Rp {{ number_format($card['saldo_akhir'], 2, ',', '.') }}</span>
                                @elseif ($card['saldo_akhir'] < 0)
                                    <span class="badge bg-warning text-dark">Saldo Kredit: Rp {{ number_format(abs($card['saldo_akhir']), 2, ',', '.') }}</span>
                                @else
                                    <span class="badge bg-success">Lunas</span>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 ledger-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Nomor</th>
                                                <th>Jenis</th>
                                                <th>Ref. Faktur</th>
                                                <th>Keterangan</th>
                                                <th>COA</th>
                                                <th class="text-end">Debit</th>
                                                <th class="text-end">Kredit</th>
                                                <th class="text-end">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-info fw-semibold ledger-row">
                                                <td>{{ $startDateCarbon->format('d-m-Y') }}</td>
                                                <td>-</td>
                                                <td>Saldo Awal</td>
                                                <td>-</td>
                                                <td>Saldo s/d {{ $startDateCarbon->copy()->subDay()->format('d-m-Y') }}</td>
                                                <td>-</td>
                                                <td class="text-end">-</td>
                                                <td class="text-end">-</td>
                                                <td class="text-end">{{ number_format($card['saldo_awal'], 2, ',', '.') }}</td>
                                            </tr>
                                            @foreach ($card['rows'] as $row)
                                                <tr class="ledger-row">
                                                    <td class="text-nowrap">{{ Carbon::parse($row['tanggal'])->format('d-m-Y') }}</td>
                                                    <td class="text-nowrap">
                                                        @if ($row['jenis'] === 'Penerimaan' && $row['penerimaan_id'])
                                                            <a href="{{ route('pendapatan.penerimaan.print', $row['penerimaan_id']) }}" class="text-decoration-none fw-semibold">{{ $row['nomor'] }}</a>
                                                        @elseif ($row['faktur_id'])
                                                            <a href="{{ route('pendapatan.invoice.read', $row['faktur_id']) }}" class="text-decoration-none fw-semibold">{{ $row['nomor'] }}</a>
                                                        @else
                                                            {{ $row['nomor'] }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $row['jenis'] }}</td>
                                                    <td class="text-nowrap">{{ $row['referensi_faktur'] }}</td>
                                                    <td>{{ $row['keterangan'] }}</td>
                                                    <td>{{ $row['akun'] }}</td>
                                                    <td class="text-end">{{ $row['debit'] != 0 ? number_format($row['debit'], 2, ',', '.') : '-' }}</td>
                                                    <td class="text-end">{{ $row['kredit'] != 0 ? number_format($row['kredit'], 2, ',', '.') : '-' }}</td>
                                                    <td class="text-end fw-semibold">{{ number_format($row['saldo'], 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-secondary fw-bold">
                                            <tr>
                                                <td colspan="6" class="text-end">Total Periode / Saldo Akhir</td>
                                                <td class="text-end">{{ number_format($card['total_debit'], 2, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($card['total_kredit'], 2, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($card['saldo_akhir'], 2, ',', '.') }}</td>
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
                                <p class="mb-1 fw-semibold">Tidak ada data piutang sesuai filter.</p>
                                <p class="mb-0">Coba ubah periode, pelanggan, akun, atau status saldo akhir.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const $ = window.jQuery;

            if ($ && $.fn.select2) {
                $('#pelangganSelect').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    closeOnSelect: false,
                    placeholder: 'Cari dan pilih pelanggan...',
                    minimumInputLength: 1,
                    ajax: {
                        url: '{{ route('laporan.pendapatan.buku-pembantu-piutang.search-pelanggan') }}',
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term || '' }),
                        processResults: data => data,
                        cache: true
                    }
                });

                $('#akunPiutangSelect').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: true,
                    placeholder: 'Semua akun',
                    minimumInputLength: 0,
                    ajax: {
                        url: '{{ route('laporan.pendapatan.buku-pembantu-piutang.search-coa') }}',
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term || '' }),
                        processResults: data => data,
                        cache: true
                    }
                });
            }

            const searchInput = document.getElementById('transactionSearch');
            const resultInfo = document.getElementById('searchResultInfo');

            searchInput?.addEventListener('input', function () {
                const keyword = this.value.toLowerCase().trim();
                let visibleRows = 0;
                let totalRows = 0;

                document.querySelectorAll('.customer-card').forEach(function (card) {
                    const customerMatched = keyword !== '' && (card.dataset.customer || '').includes(keyword);
                    let cardHasMatch = keyword === '' || customerMatched;

                    card.querySelectorAll('tbody .ledger-row').forEach(function (row) {
                        totalRows++;
                        const matched = keyword === '' || customerMatched || row.textContent.toLowerCase().includes(keyword);
                        row.classList.toggle('d-none', !matched);
                        cardHasMatch = cardHasMatch || matched;

                        if (matched) {
                            visibleRows++;
                        }
                    });

                    card.classList.toggle('d-none', !cardHasMatch);
                });

                if (resultInfo) {
                    resultInfo.textContent = keyword === '' ? '' : `Menampilkan ${visibleRows} dari ${totalRows} baris.`;
                }
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .report-logo {
            display: inline-block;
            max-width: 100%;
            max-height: 70px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .ledger-table th,
        .ledger-table td {
            vertical-align: middle;
        }

        .ledger-table th {
            white-space: nowrap;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 38px;
        }
    </style>
@endpush
