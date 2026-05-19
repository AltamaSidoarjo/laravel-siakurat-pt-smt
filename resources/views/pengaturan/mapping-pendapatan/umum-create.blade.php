@extends('layouts.app')

@section('title', 'Create Mapping Pendapatan Umum')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.mapping-pendapatan.umum.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create Mapping Pendapatan Umum</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.mapping-pendapatan.umum.store') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    Header
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="nama" class="form-label">Nama</label>
                                            <select name="nama" id="nama" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Nama --</option>
                                                @foreach ($nameOptions as $name)
                                                    <option value="{{ $name }}" @selected(old('nama') === $name)>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="kode_penjamin" class="form-label">Penjamin</label>
                                            <select name="kode_penjamin" id="kode_penjamin" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Penjamin --</option>
                                                @foreach ($penjaminOptions as $penjamin)
                                                    <option value="{{ $penjamin['kd_pj'] }}" @selected(old('kode_penjamin') === $penjamin['kd_pj'])>
                                                        {{ $penjamin['kd_pj'] }} | {{ $penjamin['png_jawab'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="coa_id" class="form-label">Akun (COA)</label>
                                            <select name="coa_id" id="coa_id" class="form-select select2-basic" required>
                                                <option value="">-- Pilih Akun --</option>
                                                @foreach ($coaOptions as $coa)
                                                    <option value="{{ $coa->id }}" @selected((string) old('coa_id') === (string) $coa->id)>
                                                        {{ $coa->kode }} | {{ $coa->nama }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('pengaturan.mapping-pendapatan.umum.index') }}" class="btn btn-light fw-bold">
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.initSelect2Fields) {
                window.initSelect2Fields(document, '.select2-basic');
                return;
            }

            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery('.select2-basic').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: window.jQuery(document.body)
            });
        });
    </script>
@endpush
