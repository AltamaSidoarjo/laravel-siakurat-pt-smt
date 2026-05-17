@extends('layouts.app')

@section('title', 'Arus Kas')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Arus Kas</span>
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
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="laporan-header text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="laporan-header__logo">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" class="laporan-header__logo-image">
                            </div>
                        @endif
                        <div class="laporan-header__identity">
                            <div class="fw-bold laporan-header__title-rs">{{ $namaRumahSakit }}</div>
                            <div class="fw-bold laporan-header__title-report">Laporan Arus Kas</div>
                            <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Kategori</th>
                                    <th class="text-end">Kas Masuk</th>
                                    <th class="text-end">Kas Keluar</th>
                                    <th class="text-end">Kas Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summaryRows as $row)
                                    <tr>
                                        <td>{{ $row['kategori'] }}</td>
                                        <td class="text-end">{{ number_format((float) $row['total_kas_masuk'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) $row['total_kas_keluar'], 0, ',', '.') }}</td>
                                        <td class="text-end fw-bold">{{ number_format((float) $row['total_kas_bersih'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td>Kas Awal</td>
                                    <td colspan="3" class="text-end">{{ number_format((float) $kasAwal, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light fw-bold">
                                    <td>Kenaikan / Penurunan Kas</td>
                                    <td colspan="3" class="text-end">{{ number_format((float) $kenaikanPenurunan, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-success fw-bold">
                                    <td>Kas Akhir</td>
                                    <td colspan="3" class="text-end">{{ number_format((float) $kasAkhir, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nomor</th>
                                    <th>Sumber</th>
                                    <th>Kategori</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Kas Masuk</th>
                                    <th class="text-end">Kas Keluar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($detailRows as $row)
                                    <tr>
                                        <td>{{ $row['tanggal'] }}</td>
                                        <td>{{ $row['nomer'] }}</td>
                                        <td>{{ $row['sumber_transaksi'] }}</td>
                                        <td>{{ $row['kategori_arus_kas'] }}</td>
                                        <td>{{ $row['keterangan'] }}</td>
                                        <td class="text-end">{{ $row['kas_masuk'] > 0 ? number_format((float) $row['kas_masuk'], 0, ',', '.') : '' }}</td>
                                        <td class="text-end">{{ $row['kas_keluar'] > 0 ? number_format((float) $row['kas_keluar'], 0, ',', '.') : '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Tidak ada data arus kas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

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
