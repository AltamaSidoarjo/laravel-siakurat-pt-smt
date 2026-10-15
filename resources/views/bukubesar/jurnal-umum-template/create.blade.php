@extends('layouts.app')

@section('title', 'Tambah Template Jurnal Umum')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.jurnal-umum-template.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Tambah Template Jurnal Umum</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form action="{{ route('bukubesar.jurnal-umum-template.store') }}" method="post">
                        @csrf
                        @include('bukubesar.jurnal-umum-template._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('bukubesar.jurnal-umum-template.scripts')
@endpush
