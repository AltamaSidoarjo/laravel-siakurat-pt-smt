@extends('layouts.app')

@section('title', 'Setting Akun Obat & BHP')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Setting Akun Obat & BHP</span>
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
                            Pengaturan akun ini digunakan pada modul <strong>Bridging Pendapatan Obat</strong> (Tarik Obat & BHP serta Tarik Piutang Obat & BHP) untuk otomatisasi pencatatan Jurnal Umum, Faktur Penjualan, dan mutasi Buku Besar tanpa membaca tabel jurnal SIMRS.
                        </div>
                    </div>

                    <form method="post" action="{{ route('pengaturan.setting-obat.update') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <!-- Card Penjualan Bebas / Resep Obat -->
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    <i class="bi bi-capsule me-1"></i> Akun Penjualan Obat & BHP (Tarik Obat & BHP)
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="pendapatan_obat_coa_id" class="form-label fw-semibold">
                                                1. Akun Pendapatan Obat & BHP <span class="text-danger">*</span>
                                            </label>
                                            <select name="pendapatan_obat_coa_id" id="pendapatan_obat_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Pendapatan Obat --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('pendapatan_obat_coa_id', $setting?->pendapatan_obat_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun kredit pendapatan dari transaksi penjualan obat & BHP di kasir apotek (misal: 410000 Pendapatan Obat & BHP). Akun debit kas/bank diambil otomatis dari cara bayar billing apotek.
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="hpp_obat_coa_id" class="form-label fw-semibold">
                                                2. Akun HPP Obat & BHP <span class="text-danger">*</span>
                                            </label>
                                            <select name="hpp_obat_coa_id" id="hpp_obat_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun HPP Obat --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('hpp_obat_coa_id', $setting?->hpp_obat_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun debit Harga Pokok Penjualan yang dihitung dari total harga beli item obat terjual (misal: 510000 HPP Obat).
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="persediaan_obat_coa_id" class="form-label fw-semibold">
                                                3. Akun Persediaan Obat & BHP <span class="text-danger">*</span>
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
                                                Akun kredit persediaan obat yang berkurang akibat penjualan / piutang obat (misal: 115010 Persediaan Obat/Alkes).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Piutang Obat & BHP -->
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    <i class="bi bi-receipt me-1"></i> Akun Piutang Apotek (Tarik Piutang Obat & BHP)
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="piutang_obat_coa_id" class="form-label fw-semibold">
                                                4. Akun Piutang Obat & BHP <span class="text-danger">*</span>
                                            </label>
                                            <select name="piutang_obat_coa_id" id="piutang_obat_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Piutang Obat --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('piutang_obat_coa_id', $setting?->piutang_obat_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun debit piutang atas sisa piutang tagihan obat (misal: 117000 Piutang Obat & BHP).
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="kas_uang_muka_coa_id" class="form-label fw-semibold">
                                                5. Akun Kas / Uang Muka Piutang Obat <span class="text-danger">*</span>
                                            </label>
                                            <select name="kas_uang_muka_coa_id" id="kas_uang_muka_coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun Kas Uang Muka --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('kas_uang_muka_coa_id', $setting?->kas_uang_muka_coa_id) === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">
                                                Akun kas/bank penerimaan jika pasien/pembeli memberikan uang muka saat piutang dicatat (misal: 111010 Kas Kasir).
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
