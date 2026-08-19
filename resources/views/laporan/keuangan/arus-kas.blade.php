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
                            <div class="text-muted">Untuk periode yang berakhir {{ \Carbon\Carbon::parse($endDate)->format('d-m-Y') }}</div>
                            <div class="text-muted small">(dalam Rupiah)</div>
                        </div>
                    </div>

                    @php
                        $formatArusKas = static function (float $value): string {
                            $formatted = number_format(abs($value), 0, ',', '.');
                            return $value < 0 ? '('.$formatted.')' : $formatted;
                        };
                        $activityLabels = [
                            'operasi' => 'ARUS KAS DARI AKTIVITAS OPERASI',
                            'investasi' => 'ARUS KAS DARI AKTIVITAS INVESTASI',
                            'pendanaan' => 'ARUS KAS DARI AKTIVITAS PENDANAAN',
                        ];
                    @endphp

                    @if ($arusKasBerjalan['akun_belum_dipetakan']->isNotEmpty() || abs($arusKasBerjalan['belum_dipetakan']) >= 0.005)
                        <div class="alert alert-warning">
                            <div class="fw-bold">Mapping arus kas belum lengkap.</div>
                            <div>Perbarui mapping COA berikut agar laporan dapat diklasifikasikan seluruhnya:</div>
                            @if ($arusKasBerjalan['akun_belum_dipetakan']->isNotEmpty())
                                <ul class="mb-0 mt-2">
                                    @foreach ($arusKasBerjalan['akun_belum_dipetakan'] as $account)
                                        <li>{{ $account['kode'] }} - {{ $account['nama'] }} ({{ $account['tipe_coa'] }})</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle laporan-arus-kas">
                            <thead class="table-light">
                                <tr>
                                    <th>Uraian</th>
                                    <th class="text-end">{{ \Carbon\Carbon::parse($periode['berjalan']['end'])->format('d-m-Y') }}</th>
                                    <th class="text-end">{{ \Carbon\Carbon::parse($periode['pembanding']['end'])->format('d-m-Y') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activityLabels as $activity => $label)
                                    <tr class="table-light fw-bold"><td colspan="3">{{ $label }}</td></tr>
                                    @php
                                        $currentGroups = $arusKasBerjalan['bagian'][$activity]['kelompok']->keyBy('kelompok');
                                        $comparisonGroups = $arusKasPembanding['bagian'][$activity]['kelompok']->keyBy('kelompok');
                                        $groupNames = $currentGroups->keys()->merge($comparisonGroups->keys())->unique()->sort();
                                    @endphp
                                    @forelse ($groupNames as $groupName)
                                        <tr>
                                            <td class="ps-4">{{ $groupName }}</td>
                                            <td class="text-end">{{ $formatArusKas((float) ($currentGroups->get($groupName)['nilai'] ?? 0)) }}</td>
                                            <td class="text-end">{{ $formatArusKas((float) ($comparisonGroups->get($groupName)['nilai'] ?? 0)) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="ps-4 text-muted">Tidak ada arus kas</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                                    @endforelse
                                    <tr class="fw-bold">
                                        <td>Kas neto dari aktivitas {{ $activity }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasBerjalan['bagian'][$activity]['subtotal']) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasPembanding['bagian'][$activity]['subtotal']) }}</td>
                                    </tr>
                                @endforeach

                                @if (abs($arusKasBerjalan['belum_dipetakan']) >= 0.005 || abs($arusKasPembanding['belum_dipetakan']) >= 0.005)
                                    <tr class="table-warning fw-bold">
                                        <td>Arus kas belum dipetakan</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasBerjalan['belum_dipetakan']) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasPembanding['belum_dipetakan']) }}</td>
                                    </tr>
                                @endif

                                @foreach ([
                                    'kenaikan_penurunan' => 'Kenaikan (penurunan) neto kas dan setara kas',
                                    'kas_awal' => 'Kas dan setara kas awal periode',
                                    'pengaruh_kurs' => 'Pengaruh perubahan kurs',
                                    'kas_akhir' => 'Kas dan setara kas akhir periode',
                                ] as $key => $label)
                                    <tr class="{{ $key === 'kas_akhir' ? 'table-success' : 'table-light' }} fw-bold">
                                        <td>{{ $label }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasBerjalan[$key]) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $arusKasPembanding[$key]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h5 class="fw-bold">Lampiran Rincian Transaksi</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nomor</th>
                                    <th>Akun Kas</th>
                                    <th>Akun Lawan</th>
                                    <th>Aktivitas</th>
                                    <th>Kelompok</th>
                                    <th>Keterangan</th>
                                    <th>Status</th>
                                    <th class="text-end">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($detailRows as $row)
                                    <tr>
                                        <td>{{ $row['tanggal'] }}</td>
                                        <td>{{ $row['nomer'] }}</td>
                                        <td>{{ $row['akun_kas'] }}</td>
                                        <td>{{ $row['kode_akun_lawan'] }} {{ $row['akun_lawan'] }}</td>
                                        <td class="text-uppercase">{{ str_replace('_', ' ', $row['aktivitas']) }}</td>
                                        <td>{{ $row['kelompok'] }}</td>
                                        <td>{{ $row['keterangan'] }}</td>
                                        <td>{{ $row['status_mapping'] }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $row['nilai']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">Tidak ada data arus kas.</td>
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
