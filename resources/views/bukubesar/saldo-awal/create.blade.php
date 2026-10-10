@extends('layouts.app')

@section('title', 'Setup Saldo Awal')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.saldo-awal.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Setup Saldo Awal Baru</span>
            </div>
            <div class="text-muted small">
                Tentukan tanggal cut-off neraca dan masukkan saldo debit/kredit masing-masing akun.
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form action="{{ route('bukubesar.saldo-awal.store') }}" method="post" id="form-saldo-awal">
                        @csrf
                        @include('bukubesar.saldo-awal._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('bukubesar.saldo-awal.scripts')
@endpush

