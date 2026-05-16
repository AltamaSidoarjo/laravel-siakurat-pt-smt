@extends('layouts.app')

@section('title', 'Create COA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.coa.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create COA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form action="{{ route('bukubesar.coa.store') }}" method="post">
                        @csrf
                        @include('bukubesar.coa._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery('#status_aktif').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumResultsForSearch: Infinity
                });
                window.jQuery('#parent_id, #tipe_coa').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
        });
    </script>
@endpush
