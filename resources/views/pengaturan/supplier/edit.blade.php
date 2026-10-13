@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Edit Supplier</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.supplier.update', $supplier) }}" class="d-flex flex-column gap-3">
        @csrf
        @method('PUT')
        @include('pengaturan.supplier._form')
    </form>
@endsection
