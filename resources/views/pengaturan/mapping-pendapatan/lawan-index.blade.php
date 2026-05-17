@extends('layouts.app')

@section('title', 'Mapping Pendapatan - Lawan Pendapatan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Mapping Pendapatan - Lawan Pendapatan</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <div class="d-flex flex-column gap-3">
                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-end gap-3 align-items-center">
                                    <a href="{{ route('pengaturan.mapping-pendapatan.lawan.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </a>
                                    <a href="{{ route('pengaturan.mapping-pendapatan.index') }}" class="btn btn-info fw-bold text-white">
                                        <i class="bi bi-diagram-3-fill"></i> Mapping Tindakan
                                    </a>
                                    <a href="{{ route('pengaturan.mapping-pendapatan.umum.index') }}" class="btn btn-info fw-bold text-white">
                                        <i class="bi bi-list-stars"></i> Mapping Umum
                                    </a>
                                    <span class="btn btn-secondary fw-bold disabled">
                                        <i class="bi bi-arrow-left-right"></i> Mapping Lawan Pendapatan
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Kode COA SIMRS</th>
                                                <th>Nama COA SIMRS</th>
                                                <th>Kode COA</th>
                                                <th>Nama COA</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($mappings as $mapping)
                                                <tr>
                                                    <td>{{ $mapping->kode_coa_simrs }}</td>
                                                    <td>{{ $mapping->nama_coa_simrs }}</td>
                                                    <td>{{ $mapping->coa?->kode }}</td>
                                                    <td>{{ $mapping->coa?->nama }}</td>
                                                    <td class="text-center">
                                                        <form method="post" action="{{ route('pengaturan.mapping-pendapatan.lawan.destroy', $mapping) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash3"></i> Hapus
                                                            </button>
                                                        </form>
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
                    autoWidth: false,
                    scrollX: true,
                    dom: 'Bfrtip',
                    buttons: ['csv', 'excel', 'pdf', 'print'],
                    order: [[0, 'asc']]
                });
            }
        });
    </script>
@endpush
