@extends('layouts.app')

@section('title', 'Arus Kas (Metode Tidak Langsung)')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Arus Kas (Metode Tidak Langsung)</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('laporan.keuangan.arus-kas', ['startDate' => $startDate, 'endDate' => $endDate]) }}">
                            <i class="bi bi-list-ul me-1"></i> Metode Langsung
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('laporan.keuangan.arus-kas-tidak-langsung', ['startDate' => $startDate, 'endDate' => $endDate]) }}">
                            <i class="bi bi-calculator me-1"></i> Metode Tidak Langsung
                        </a>
                    </li>
                </ul>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                </button>
            </div>

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
                            <div class="fw-bold laporan-header__title-report">Laporan Arus Kas (Metode Tidak Langsung)</div>
                            <div class="text-muted">Untuk periode yang berakhir {{ \Carbon\Carbon::parse($endDate)->format('d-m-Y') }}</div>
                            <div class="text-muted small">(dalam Rupiah)</div>
                        </div>
                    </div>

                    @php
                        $formatArusKas = static function (float $value): string {
                            $formatted = number_format(abs($value), 0, ',', '.');
                            return $value < 0 ? '('.$formatted.')' : $formatted;
                        };
                    @endphp

                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle laporan-arus-kas">
                            <thead class="table-light">
                                <tr>
                                    <th>Uraian</th>
                                    <th class="text-end" style="width: 25%;">{{ \Carbon\Carbon::parse($periode['berjalan']['end'])->format('d-m-Y') }}</th>
                                    <th class="text-end" style="width: 25%;">{{ \Carbon\Carbon::parse($periode['pembanding']['end'])->format('d-m-Y') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- 1. AKTIVITAS OPERASI --}}
                                <tr class="table-light fw-bold">
                                    <td colspan="3">ARUS KAS DARI AKTIVITAS OPERASI</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold">Laba (Rugi) Bersih Periode Berjalan</td>
                                    <td class="text-end fw-semibold">{{ $formatArusKas((float) $berjalan['laba_bersih']) }}</td>
                                    <td class="text-end fw-semibold">{{ $formatArusKas((float) $pembanding['laba_bersih']) }}</td>
                                </tr>

                                {{-- Penyesuaian Non-Kas --}}
                                <tr class="fst-italic text-muted">
                                    <td colspan="3" class="ps-3">Penyesuaian untuk merekonsiliasi laba bersih menjadi kas bersih dari aktivitas operasi:</td>
                                </tr>
                                <tr>
                                    <td class="ps-4">Beban Penyusutan & Amortisasi</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['penyesuaian_non_kas']['subtotal']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['penyesuaian_non_kas']['subtotal']) }}</td>
                                </tr>

                                {{-- Modal Kerja --}}
                                <tr class="fst-italic text-muted">
                                    <td colspan="3" class="ps-3">Perubahan dalam modal kerja:</td>
                                </tr>
                                @foreach (['piutang', 'persediaan', 'aset_lancar_lainnya', 'utang_usaha', 'liabilitas_pendek_lainnya'] as $key)
                                    @php
                                        $itemB = $berjalan['modal_kerja']['items']->get($key);
                                        $itemP = $pembanding['modal_kerja']['items']->get($key);
                                        $labelRow = $itemB['label'] ?? ($itemP['label'] ?? ucwords(str_replace('_', ' ', $key)));
                                        $nilaiB = (float) ($itemB['dampak_kas'] ?? 0);
                                        $nilaiP = (float) ($itemP['dampak_kas'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="ps-4">{{ $labelRow }}</td>
                                        <td class="text-end">{{ $formatArusKas($nilaiB) }}</td>
                                        <td class="text-end">{{ $formatArusKas($nilaiP) }}</td>
                                    </tr>
                                @endforeach

                                <tr class="table-light fw-bold">
                                    <td>Kas Bersih yang Diperoleh dari (Digunakan untuk) Aktivitas Operasi</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['kas_operasi']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['kas_operasi']) }}</td>
                                </tr>

                                {{-- 2. AKTIVITAS INVESTASI --}}
                                <tr class="table-light fw-bold">
                                    <td colspan="3">ARUS KAS DARI AKTIVITAS INVESTASI</td>
                                </tr>
                                @php
                                    $invGroupsB = $berjalan['investasi']['kelompok']->keyBy('kelompok');
                                    $invGroupsP = $pembanding['investasi']['kelompok']->keyBy('kelompok');
                                    $invGroupNames = $invGroupsB->keys()->merge($invGroupsP->keys())->unique()->sort();
                                @endphp
                                @forelse ($invGroupNames as $name)
                                    <tr>
                                        <td class="ps-4">{{ $name }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) ($invGroupsB->get($name)['nilai'] ?? 0)) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) ($invGroupsP->get($name)['nilai'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="ps-4 text-muted">Tidak ada arus kas investasi</td>
                                        <td class="text-end">0</td>
                                        <td class="text-end">0</td>
                                    </tr>
                                @endforelse
                                <tr class="table-light fw-bold">
                                    <td>Kas Bersih yang Diperoleh dari (Digunakan untuk) Aktivitas Investasi</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['investasi']['subtotal']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['investasi']['subtotal']) }}</td>
                                </tr>

                                {{-- 3. AKTIVITAS PENDANAAN --}}
                                <tr class="table-light fw-bold">
                                    <td colspan="3">ARUS KAS DARI AKTIVITAS PENDANAAN</td>
                                </tr>
                                @php
                                    $pendGroupsB = $berjalan['pendanaan']['kelompok']->keyBy('kelompok');
                                    $pendGroupsP = $pembanding['pendanaan']['kelompok']->keyBy('kelompok');
                                    $pendGroupNames = $pendGroupsB->keys()->merge($pendGroupsP->keys())->unique()->sort();
                                @endphp
                                @forelse ($pendGroupNames as $name)
                                    <tr>
                                        <td class="ps-4">{{ $name }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) ($pendGroupsB->get($name)['nilai'] ?? 0)) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) ($pendGroupsP->get($name)['nilai'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="ps-4 text-muted">Tidak ada arus kas pendanaan</td>
                                        <td class="text-end">0</td>
                                        <td class="text-end">0</td>
                                    </tr>
                                @endforelse
                                <tr class="table-light fw-bold">
                                    <td>Kas Bersih yang Diperoleh dari (Digunakan untuk) Aktivitas Pendanaan</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['pendanaan']['subtotal']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['pendanaan']['subtotal']) }}</td>
                                </tr>

                                {{-- REKONSILIASI SALDO KAS --}}
                                <tr class="table-light fw-bold">
                                    <td colspan="3">REKONSILIASI KAS DAN SETARA KAS</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Kenaikan (Penurunan) Bersih Kas dan Setara Kas</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['kenaikan_penurunan']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['kenaikan_penurunan']) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3">Kas dan Setara Kas pada Awal Periode</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['kas_awal']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['kas_awal']) }}</td>
                                </tr>
                                <tr class="table-info fw-bold">
                                    <td>Kas dan Setara Kas pada Akhir Periode</td>
                                    <td class="text-end">{{ $formatArusKas((float) $berjalan['kas_akhir']) }}</td>
                                    <td class="text-end">{{ $formatArusKas((float) $pembanding['kas_akhir']) }}</td>
                                </tr>

                                @if (abs((float) $berjalan['selisih_rekonsiliasi']) >= 0.005 || abs((float) $pembanding['selisih_rekonsiliasi']) >= 0.005)
                                    <tr class="table-warning">
                                        <td>Penyesuaian / Selisih Rekonsiliasi Kas</td>
                                        <td class="text-end">{{ $formatArusKas((float) $berjalan['selisih_rekonsiliasi']) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $pembanding['selisih_rekonsiliasi']) }}</td>
                                    </tr>
                                    <tr class="table-success fw-bold">
                                        <td>Saldo Kas dan Setara Kas Riil (Buku Besar)</td>
                                        <td class="text-end">{{ $formatArusKas((float) $berjalan['kas_akhir_riil']) }}</td>
                                        <td class="text-end">{{ $formatArusKas((float) $pembanding['kas_akhir_riil']) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- Rincian Akun Modal Kerja --}}
                    <div class="accordion mt-4" id="accordionModalKerja">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingModalKerja">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseModalKerja" aria-expanded="false" aria-controls="collapseModalKerja">
                                    <i class="bi bi-info-circle me-2"></i> Rincian Perubahan Akun Modal Kerja Periode Berjalan
                                </button>
                            </h2>
                            <div id="collapseModalKerja" class="accordion-collapse collapse" aria-labelledby="headingModalKerja" data-bs-parent="#accordionModalKerja">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Kelompok</th>
                                                    <th>Kode COA</th>
                                                    <th>Nama Akun</th>
                                                    <th class="text-end">Total Debit</th>
                                                    <th class="text-end">Total Kredit</th>
                                                    <th class="text-end">Dampak Kas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $hasDetail = false; @endphp
                                                @foreach ($berjalan['modal_kerja']['items'] as $groupKey => $group)
                                                    @foreach ($group['detail_akun'] as $acc)
                                                        @php $hasDetail = true; @endphp
                                                        <tr>
                                                            <td><span class="badge bg-secondary">{{ $group['nama'] }}</span></td>
                                                            <td><code>{{ $acc['kode'] }}</code></td>
                                                            <td>{{ $acc['nama'] }}</td>
                                                            <td class="text-end">{{ number_format($acc['debit'], 0, ',', '.') }}</td>
                                                            <td class="text-end">{{ number_format($acc['kredit'], 0, ',', '.') }}</td>
                                                            <td class="text-end fw-semibold">{{ $formatArusKas((float) $acc['dampak_kas']) }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                                @if (! $hasDetail)
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">Tidak ada mutasi akun modal kerja pada periode ini.</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
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
