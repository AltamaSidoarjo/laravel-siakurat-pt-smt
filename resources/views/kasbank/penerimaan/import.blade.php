@extends('layouts.app')

@section('title', 'Import Kasbank Penerimaan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('kasbank.penerimaan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Import Kasbank Penerimaan</span>
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
                            Unduh Template
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Unduh template XLSX terlebih dahulu. Template berisi baris contoh, sheet
                                <strong>Petunjuk</strong>, dan sheet <strong>Master COA</strong> (hanya akun transaksi/leaf aktif).
                                Hapus baris contoh sebelum mengunggah.
                            </p>
                            <a href="{{ route('kasbank.penerimaan.import.template') }}" class="btn btn-outline-success fw-bold">
                                <i class="bi bi-file-earmark-arrow-down-fill"></i> Unduh Template XLSX
                            </a>
                        </div>
                    </div>

                    <div class="card border-light shadow-sm">
                        <div class="card-header fw-bold bg-light">
                            Unggah File
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('kasbank.penerimaan.import.store') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label for="file" class="form-label">File XLSX</label>
                                    <input
                                        type="file"
                                        name="file"
                                        id="file"
                                        class="form-control"
                                        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                                        required
                                    >
                                    <div class="form-text">
                                        Format XLSX/XLS, maksimal 10 MB. Batas upload PHP {{ $phpUploadMaxFileSize }}, batas post {{ $phpPostMaxSize }}.
                                        Jika ada satu baris tidak valid, seluruh import dibatalkan.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="bi bi-upload"></i> Import
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
