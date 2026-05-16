@extends('layouts.app')

@section('title', 'COA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">COA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @include('partials.flash-message')
                        @include('partials.validation-errors')

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-3 align-items-center">
                                    <a href="{{ route('bukubesar.coa.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                                        <thead>
                                            <tr class="fs-6">
                                                <th class="text-start" style="width: 15%;">Nomer</th>
                                                <th>Nama</th>
                                                <th style="width: 15%;">Tipe</th>
                                                <th style="width: 20px" class="text-center">#</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $coa)
                                                @php
                                                    $level = (int) ($coa->level ?? 0);
                                                    $indentPx = max(0, $level) * 16;
                                                    $isParent = (int) ($coa->is_parent ?? 0) === 1;
                                                @endphp
                                                <tr>
                                                    <td class="text-start">
                                                        <span style="display:inline-block; margin-left: {{ $indentPx }}px;" class="{{ $isParent ? 'fw-bold' : '' }}">
                                                            {{ $coa->kode }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style="display:inline-block; margin-left: {{ $indentPx }}px;" class="{{ $isParent ? 'fw-bold' : '' }}">
                                                            {{ $coa->nama }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $coa->tipe_coa }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('bukubesar.coa.edit', $coa->id) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && window.jQuery.fn.DataTable) {
                window.jQuery('#datatable').DataTable({
                    dom: 'Bfrtip',
                    buttons: ['csv', 'excel', 'pdf', 'print'],
                    paging: false,
                    ordering: false,
                    info: false
                });
            }
        });
    </script>
@endpush
