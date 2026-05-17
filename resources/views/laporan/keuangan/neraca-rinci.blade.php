@extends('layouts.app')

@section('title', 'Neraca Rinci')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Neraca Rinci</span>
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
                            <div class="col-md-4">
                                <label class="form-label">Tipe COA</label>
                                <select name="tipeCoa" class="form-select">
                                    <option value="">Semua tipe</option>
                                    @foreach ($tipeCoaOptions as $tipe)
                                        <option value="{{ $tipe }}" @selected($selectedTipeCoa === $tipe)>{{ $tipe }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
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
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama COA</th>
                                    <th>Tipe COA</th>
                                    <th class="text-end">Total Debit</th>
                                    <th class="text-end">Total Kredit</th>
                                    <th class="text-end">Saldo Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $row['kode_coa'] }}</td>
                                        <td>{{ $row['nama_coa'] }}</td>
                                        <td>{{ $row['tipe_coa'] }}</td>
                                        <td class="text-end">{{ number_format((float) $row['total_debit'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) $row['total_kredit'], 0, ',', '.') }}</td>
                                        <td class="text-end fw-bold">{{ number_format((float) $row['saldo_akhir'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Tidak ada data neraca rinci.</td>
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
