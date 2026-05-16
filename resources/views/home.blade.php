@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="row mb-4">
        <div class="col">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="alert alert-info" role="alert">
                        Layanan Informasi : Saat ini tidak ada informasi...
                    </div>

                    <h6>
                        Selamat datang di aplikasi {{ config('siakurat.app_name') }},
                        anda sedang login sebagai :
                        <strong>{{ session('auth.preview_user.username', 'preview-user') }}</strong>
                    </h6>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm">
        <h5 class="mb-3">Filter Tanggal</h5>
        <div class="row">
            <div class="col">
                <label for="dariTanggal">Dari</label>
                <input type="date" id="dariTanggal" class="form-control">
            </div>

            <div class="col">
                <label for="sampaiTanggal">Sampai</label>
                <input type="date" id="sampaiTanggal" class="form-control">
            </div>
        </div>
        <button class="btn btn-primary mt-3" type="button">Terapkan Filter</button>
    </div>

    <div class="row">
        @foreach ([
            'Kunjungan Harian',
            'Distribusi Poli',
            'Top Dokter',
            'Pendapatan Harian',
            'Komposisi Penjamin',
        ] as $chartTitle)
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">{{ $chartTitle }}</div>
                    <div class="card-body">
                        <div class="dashboard-chart-placeholder">
                            Placeholder chart
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
