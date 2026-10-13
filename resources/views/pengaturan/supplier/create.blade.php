@extends('layouts.app')

@section('title', 'Tambah Supplier')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Tambah Supplier</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.supplier.store') }}" class="d-flex flex-column gap-3">
        @csrf
        @include('pengaturan.supplier._form')
    </form>
@endsection
