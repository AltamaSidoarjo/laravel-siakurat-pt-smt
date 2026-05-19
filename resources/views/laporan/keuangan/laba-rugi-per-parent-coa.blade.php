@extends('layouts.app')

@section('title', 'Laba Rugi Per Parent COA')

@php
    $viewRows = collect($rows)->filter(fn (array $item) => $item['has_children'] || abs((float) $item['nominal']) > 0.01 || abs((float) $item['rba_nominal']) > 0.01)->values();
    $leafRows = $viewRows->filter(fn (array $item) => !($item['has_children'] ?? false))->values();
    $totalNominal = (float) $leafRows->sum('nominal');
    $totalRba = (float) $leafRows->sum('rba_nominal');
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
                <a href="{{ route('laporan.keuangan.laba-rugi-standard', ['startDate' => $startDate, 'endDate' => $endDate]) }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laba Rugi Per Parent COA</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="alert alert-info">
                Parent COA: <strong>{{ $parentCoa?->kode }} - {{ $parentCoa?->nama }}</strong>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="">
                        <input type="hidden" name="coaId" value="{{ $parentCoa?->id }}">
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
                    <div class="text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="mb-2">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" style="max-height: 80px; object-fit: contain;">
                            </div>
                        @endif
                        <div class="fw-bold" style="font-size: 20px;">{{ $namaRumahSakit }}</div>
                        <div class="fw-bold" style="font-size: 18px;">Laba Rugi Per Parent COA</div>
                        <div class="text-muted">Periode {{ $startDate }} s/d {{ $endDate }}</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Deskripsi</th>
                                    <th class="text-end">RBA</th>
                                    <th class="text-end">Capaian</th>
                                    <th class="text-end">% Capaian</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($viewRows as $row)
                                    @php
                                        $isParent = $row['has_children'];
                                    @endphp
                                    <tr>
                                        <td>{{ $row['kode'] }}</td>
                                        <td>
                                            @if ($row['has_children'])
                                                <a href="{{ route('laporan.keuangan.laba-rugi-per-parent-coa', ['coaId' => $row['coa_id'], 'startDate' => $startDate, 'endDate' => $endDate]) }}" class="text-decoration-none">
                                                    {{ $row['deskripsi'] }}
                                                </a>
                                            @else
                                                {{ $row['deskripsi'] }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $isParent ? '' : number_format((float) $row['rba_nominal'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ $isParent ? '' : number_format((float) $row['nominal'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ $isParent ? '' : $formatPersentase((float) $row['nominal'], (float) $row['rba_nominal']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tidak ada data untuk ditampilkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($viewRows->isNotEmpty())
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td></td>
                                        <td class="text-end">Total</td>
                                        <td class="text-end">{{ number_format($totalRba, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($totalNominal, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ $formatPersentase($totalNominal, $totalRba) }}</td>
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
