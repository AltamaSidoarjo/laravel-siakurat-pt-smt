@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-light">
                <div class="card-body p-4">
                    <h4 class="mb-2">{{ $title }}</h4>
                    <p class="text-muted mb-0">
                        Halaman placeholder untuk menu <strong>{{ $groupLabel }}</strong> pada rebuild Laravel.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
