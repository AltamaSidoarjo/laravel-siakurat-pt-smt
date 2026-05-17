@extends('layouts.app')

@section('title', 'Neraca Per Parent COA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.neraca-standard', ['perDate' => $perDate]) }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Neraca Per Parent COA</span>
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
                    <div class="text-center mb-4">
                        @if ($logoRsUrl)
                            <div class="mb-2">
                                <img src="{{ $logoRsUrl }}" alt="Logo RS" style="max-height: 80px; object-fit: contain;">
                            </div>
                        @endif
                        <div class="fw-bold" style="font-size: 20px;">{{ $namaRumahSakit }}</div>
                        <div class="fw-bold" style="font-size: 18px;">Neraca Per Parent COA</div>
                        <div class="text-muted">Per {{ $perDate }}</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
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
                                    <tr class="{{ $row['level'] === 0 ? 'table-light fw-bold' : '' }}">
                                        <td>{{ $row['kode_coa'] }}</td>
                                        <td style="padding-left: {{ $row['level'] * 18 }}px;">{{ $row['nama_coa'] }}</td>
                                        <td>{{ $row['tipe_coa'] }}</td>
                                        <td class="text-end">{{ number_format((float) $row['saldo'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Parent COA tidak ditemukan atau tidak memiliki data.</td>
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
