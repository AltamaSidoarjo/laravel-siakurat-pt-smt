@extends('layouts.app')

@section('title', 'Detail Saldo Awal')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.saldo-awal.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">
                    {{ $saldoAwal->isLocked() ? 'Detail Saldo Awal (Terkunci)' : 'Edit Saldo Awal (Draft)' }}
                </span>
            </div>
            <div class="text-muted small">
                Nomor: <strong>{{ $saldoAwal->nomer }}</strong> &bull; Cut-off: <strong>{{ optional($saldoAwal->tanggal_cutoff)->format('d F Y') }}</strong>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form action="{{ route('bukubesar.saldo-awal.update', $saldoAwal) }}" method="post" id="form-saldo-awal">
                        @csrf
                        @method('put')
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

