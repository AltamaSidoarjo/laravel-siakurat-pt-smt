@extends('layouts.app')

@section('title', 'Create Mapping General')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.mapping-general.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create Mapping General</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.mapping-general.store') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    Header
                                </div>
                                <div class="card-body">
                                    @if ($rekeningOptions->isEmpty())
                                        <div class="alert alert-info mb-0">
                                            Semua rekening SIMRS sudah termapping di Mapping General.
                                        </div>
                                    @else
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="kode_rekening" class="form-label">COA SIMRS</label>
                                                <select name="kode_rekening" id="kode_rekening" class="form-select select2-basic" required>
                                                    <option value="">-- Pilih COA SIMRS --</option>
                                                    @foreach ($rekeningOptions as $rekening)
                                                        <option value="{{ $rekening['kode_rekening'] }}" @selected(old('kode_rekening') === $rekening['kode_rekening'])>
                                                            {{ $rekening['kode_rekening'] }} | {{ $rekening['nama_rekening'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
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
                                    @endif
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('pengaturan.mapping-general.index') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold" @disabled($rekeningOptions->isEmpty())>
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
