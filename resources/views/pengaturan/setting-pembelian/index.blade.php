@extends('layouts.app')

@section('title', 'Setting Akun Pembelian')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Setting Akun Pembelian</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-10">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <div>
                            Pengaturan akun ini digunakan pada modul <strong>Bridging Pembelian</strong> (Pemesanan Obat & BHP serta Barang Non Medis) untuk otomatisasi pencatatan Faktur Pembelian dan mutasi Buku Besar tanpa membaca tabel jurnal SIMRS.
                        </div>
                    </div>

                    <form method="post" action="{{ route('pengaturan.setting-pembelian.update') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <!-- Card Pembelian Obat & BHP -->
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    <i class="bi bi-capsule me-1"></i> Akun Pembelian Obat & BHP
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="persediaan_obat_coa_id" class="form-label fw-semibold">
                                                1. Akun Persediaan Obat & BHP <span class="text-danger">*</span>
                                            </label>
                                            <select name="persediaan_obat_coa_id" id="persediaan_obat_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Persediaan Obat --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('persediaan_obat_coa_id', $setting?->persediaan_obat_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun debit persediaan untuk menampung DPP pembelian obat & BHP (misal: 115010 Persediaan Obat/Alkes).
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="hutang_obat_coa_id" class="form-label fw-semibold">
                                                2. Akun Hutang Pembelian Obat & BHP <span class="text-danger">*</span>
                                            </label>
                                            <select name="hutang_obat_coa_id" id="hutang_obat_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Hutang Obat --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('hutang_obat_coa_id', $setting?->hutang_obat_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun kredit kewajiban hutang atas tagihan faktur obat & BHP (misal: 211010 Hutang Usaha Medis/Obat).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Pembelian Barang Non Medis -->
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    <i class="bi bi-box-seam me-1"></i> Akun Pembelian Barang Non Medis
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="persediaan_non_medis_coa_id" class="form-label fw-semibold">
                                                3. Akun Persediaan / Beban Non Medis <span class="text-danger">*</span>
                                            </label>
                                            <select name="persediaan_non_medis_coa_id" id="persediaan_non_medis_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Non Medis --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('persediaan_non_medis_coa_id', $setting?->persediaan_non_medis_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun debit persediaan atau beban untuk menampung DPP pembelian barang non medis (misal: 115020 Persediaan Barang Non Medis).
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="hutang_non_medis_coa_id" class="form-label fw-semibold">
                                                4. Akun Hutang Pembelian Non Medis <span class="text-danger">*</span>
                                            </label>
                                            <select name="hutang_non_medis_coa_id" id="hutang_non_medis_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Hutang Non Medis --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('hutang_non_medis_coa_id', $setting?->hutang_non_medis_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun kredit kewajiban hutang atas tagihan faktur barang non medis (misal: 211210 Hutang Usaha Non Medis).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card PPN Masukan -->
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    <i class="bi bi-percent me-1"></i> Akun Pajak Pertambahan Nilai (PPN)
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="ppn_masukan_coa_id" class="form-label fw-semibold">
                                                5. Akun PPN Masukan <span class="text-danger">*</span>
                                            </label>
                                            <select name="ppn_masukan_coa_id" id="ppn_masukan_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun PPN Masukan --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('ppn_masukan_coa_id', $setting?->ppn_masukan_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun debit pajak masukan jika faktur pembelian memiliki nilai PPN &gt; 0 (misal: 116011 PPN Masukan).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('home') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-arrow-left"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold">
                                            <i class="bi bi-save"></i> Simpan Pengaturan
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

@push('scripts')
    <script>
        $(document).ready(function () {
            if (window.jQuery && window.jQuery.fn.select2) {
                $('.select2-basic').select2({
                    width: '100%',
                    theme: 'bootstrap-5'
                });
            }
        });
    </script>
@endpush
