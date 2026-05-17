@extends('layouts.app')

@section('title', 'Preferensi')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Preferensi</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.preferensi.update') }}" enctype="multipart/form-data">
                        @csrf

                        <input type="hidden" name="id" value="{{ old('id', $preferensi->id) }}">
                        <input type="hidden" name="logo_perusahaan" value="{{ old('logo_perusahaan', $preferensi->logo_perusahaan) }}">

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    Header
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="nama_perusahaan" class="form-label">Nama Perusahaan</label>
                                            <input
                                                type="text"
                                                name="nama_perusahaan"
                                                id="nama_perusahaan"
                                                class="form-control"
                                                value="{{ old('nama_perusahaan', $preferensi->nama_perusahaan) }}"
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label for="ttd_kabag" class="form-label">TTD Kabag</label>
                                            <input
                                                type="text"
                                                name="ttd_kabag"
                                                id="ttd_kabag"
                                                class="form-control"
                                                value="{{ old('ttd_kabag', $preferensi->ttd_kabag) }}"
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label for="ttd_direktur" class="form-label">TTD Direktur</label>
                                            <input
                                                type="text"
                                                name="ttd_direktur"
                                                id="ttd_direktur"
                                                class="form-control"
                                                value="{{ old('ttd_direktur', $preferensi->ttd_direktur) }}"
                                            >
                                        </div>
                                        <div class="col-12">
                                            <label for="logo_file" class="form-label">Logo Perusahaan</label>

                                            @if (filled(old('logo_perusahaan', $preferensi->logo_perusahaan)))
                                                <div class="mb-3">
                                                    <div class="text-muted small mb-2">Logo saat ini</div>
                                                    <img
                                                        src="{{ old('logo_perusahaan', $preferensi->logo_perusahaan) }}"
                                                        alt="Logo Perusahaan"
                                                        class="img-thumbnail"
                                                        style="max-height: 90px; object-fit: contain;"
                                                    >
                                                </div>
                                            @endif

                                            <input
                                                type="file"
                                                name="logo_file"
                                                id="logo_file"
                                                class="form-control"
                                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                            >
                                            <div class="form-text">
                                                Kosongkan jika tidak ingin mengganti logo. Format yang diizinkan: PNG, JPG, JPEG, WEBP. Ukuran maksimal 2 MB.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('home') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold">
                                            <i class="bi bi-check-circle-fill"></i> Simpan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
