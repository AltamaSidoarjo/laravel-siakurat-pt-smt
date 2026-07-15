@extends('layouts.app')

@section('title', 'Konversi File')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Konversi File</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card border-muhammadiyah">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <div class="card border-light shadow-sm mb-3">
                        <div class="card-header fw-bold bg-success-subtle text-success">
                            CSV ke XLSX
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('pengaturan.konversi-file.csv-ke-xlsx') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label for="source_file" class="form-label">File CSV</label>
                                    <input
                                        type="file"
                                        name="source_file"
                                        id="source_file"
                                        class="form-control"
                                        accept=".csv,.txt,text/csv,text/plain"
                                        required
                                    >
                                    <div class="form-text">
                                        Upload file CSV atau TXT dengan ukuran maksimal 10 MB. Batas upload PHP aktif saat ini {{ $phpUploadMaxFileSize }}, dan batas post {{ $phpPostMaxSize }}. Hasil konversi akan langsung diunduh sebagai XLSX.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="bi bi-file-earmark-arrow-down-fill"></i> Konversi ke XLSX
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-light shadow-sm">
                        <div class="card-header fw-bold bg-light">
                            Pengembangan Selanjutnya
                        </div>
                        <div class="card-body">
                            <p class="mb-0 text-muted">
                                Menu ini disiapkan sebagai pusat konversi format file. Converter lain dapat ditambahkan di halaman ini pada pengembangan berikutnya.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
