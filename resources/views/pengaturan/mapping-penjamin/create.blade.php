@extends('layouts.app')

@section('title', 'Create Mapping Penjamin')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.mapping-penjamin.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create Mapping Penjamin</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.mapping-penjamin.store') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    Header
                                </div>
                                <div class="card-body">
                                    @if ($apiError)
                                        <div class="alert alert-danger mb-0">{{ $apiError }}</div>
                                    @elseif ($penjaminOptions->isEmpty())
                                        <div class="alert alert-info mb-0">
                                            Semua penjamin Billing API sudah memiliki mapping akun piutang.
                                        </div>
                                    @elseif ($coaOptions->isEmpty())
                                        <div class="alert alert-info mb-0">
                                            Tidak ada COA piutang aktif dan postable yang dapat dipilih.
                                        </div>
                                    @else
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="penjamin_id" class="form-label">Penjamin</label>
                                                <select name="penjamin_id" id="penjamin_id" class="form-select select2-basic" required>
                                                    <option value="">-- Pilih Penjamin --</option>
                                                    @foreach ($penjaminOptions as $penjamin)
                                                        <option value="{{ $penjamin['id'] }}" @selected((string) old('penjamin_id') === (string) $penjamin['id'])>
                                                            {{ $penjamin['nama'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="coa_id" class="form-label">Akun Piutang (COA)</label>
                                                <select name="coa_id" id="coa_id" class="form-select select2-basic" required>
                                                    <option value="">-- Pilih Akun Piutang --</option>
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
                                        <a href="{{ route('pengaturan.mapping-penjamin.index') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold" @disabled($apiError || $penjaminOptions->isEmpty() || $coaOptions->isEmpty())>
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
        $(document).ready(function () {
            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery('.select2-basic').each(function () {
                const $select = window.jQuery(this);
                const placeholder = $select.find('option[value=""]').first().text().trim() || undefined;

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: window.jQuery(document.body),
                    placeholder: placeholder,
                    allowClear: !$select.prop('required'),
                });
            });
        });
    </script>
@endpush
