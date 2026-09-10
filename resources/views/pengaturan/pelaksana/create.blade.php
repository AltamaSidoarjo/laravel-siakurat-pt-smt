@extends('layouts.app')

@section('title', 'Tambah Pelaksana')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Tambah Pelaksana</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.pelaksana.store') }}" class="d-flex flex-column gap-3">
        @csrf
        @include('pengaturan.pelaksana._form')
    </form>
@endsection
