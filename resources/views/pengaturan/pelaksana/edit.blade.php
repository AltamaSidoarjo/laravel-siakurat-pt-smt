@extends('layouts.app')

@section('title', 'Edit Pelaksana')

@section('content')
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Edit Pelaksana</div></div></div>
    @include('partials.validation-errors')
    <form method="post" action="{{ route('pengaturan.pelaksana.update', $pelaksana) }}" class="d-flex flex-column gap-3">
        @csrf
        @method('PUT')
        @include('pengaturan.pelaksana._form')
    </form>
@endsection
