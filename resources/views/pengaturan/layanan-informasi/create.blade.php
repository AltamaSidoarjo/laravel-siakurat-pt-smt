@extends('layouts.app')

@section('title', 'Tambah Layanan Informasi')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Tambah Layanan Informasi</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.layanan-informasi.store') }}" class="d-flex flex-column gap-3">
        @csrf
        @include('pengaturan.layanan-informasi._form')
    </form>
@endsection
