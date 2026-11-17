@extends('layouts.app')

@section('title', 'Tambah Template Kasbank Penerimaan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('kasbank.penerimaan-template.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Tambah Template Kasbank Penerimaan</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form action="{{ route('kasbank.penerimaan-template.store') }}" method="post">
                        @csrf
                        @include('kasbank.penerimaan-template._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('kasbank.penerimaan-template.scripts')
@endpush
