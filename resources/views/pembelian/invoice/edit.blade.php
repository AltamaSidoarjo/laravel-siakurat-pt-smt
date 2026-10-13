@extends('layouts.app')

@section('title', 'Edit Invoice Pembelian')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pembelian.invoice.read', $invoicePembelian) }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
                <span class="fw-bold">Edit Invoice Pembelian</span>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')
                    <form method="post" action="{{ route('pembelian.invoice.update', $invoicePembelian) }}">
                        @csrf
                        @method('PUT')
                        @include('pembelian.invoice._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('pembelian.invoice.scripts')
@endpush
