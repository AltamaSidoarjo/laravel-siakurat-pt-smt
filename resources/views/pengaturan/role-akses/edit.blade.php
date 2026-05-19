@extends('layouts.app')

@section('title', 'Edit Role Akses')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.role-akses.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Edit Role Akses</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.role-akses.update', $role) }}">
                        @csrf
                        @method('PUT')
                        @include('pengaturan.role-akses._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
