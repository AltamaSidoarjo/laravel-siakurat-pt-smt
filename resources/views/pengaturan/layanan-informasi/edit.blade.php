@extends('layouts.app')

@section('title', 'Edit Layanan Informasi')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Edit Layanan Informasi</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.layanan-informasi.update', $informasi) }}" class="d-flex flex-column gap-3">
        @csrf
        @method('PUT')
        @include('pengaturan.layanan-informasi._form')
    </form>
@endsection
