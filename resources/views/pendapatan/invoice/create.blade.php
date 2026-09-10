@extends('layouts.app')
@section('title', 'Create Invoice Pendapatan')
@section('content')
    <div class="row mb-3"><div class="col"><div class="d-flex align-items-center gap-3 fs-3"><a href="{{ route('pendapatan.invoice.index') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a><span class="fw-bold">Create Invoice Pendapatan</span></div></div></div>
    <div class="card border-muhammadiyah mb-2"><div class="card-body">@include('partials.flash-message') @include('partials.validation-errors')<form action="{{ route('pendapatan.invoice.store') }}" method="post">@csrf @include('pendapatan.invoice._form')</form></div></div>
@endsection
@push('scripts') @include('pendapatan.invoice.scripts') @endpush
