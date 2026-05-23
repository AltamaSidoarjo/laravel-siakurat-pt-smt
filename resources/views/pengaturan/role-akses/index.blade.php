@extends('layouts.app')

@section('title', 'Role Akses')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Role Akses</span>
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
                                <div class="d-flex justify-content-end gap-3 align-items-center">
                                    <a href="{{ route('pengaturan.role-akses.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah Role
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nama</th>
                                                <th>Kode</th>
                                                <th>Deskripsi</th>
                                                <th>User</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($roles as $role)
                                                <tr>
                                                    <td>
                                                        {{ $role->nama }}
                                                        @if ($role->is_system)
                                                            <span class="badge bg-secondary ms-1">System</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $role->kode }}</td>
                                                    <td>{{ $role->deskripsi ?: '-' }}</td>
                                                    <td>{{ $role->users_count }}</td>
                                                    <td class="text-center">
                                                        @if (! $role->is_system)
                                                            <a href="{{ route('pengaturan.role-akses.edit', $role) }}" class="btn btn-warning btn-sm">
                                                                <i class="bi bi-pencil-square"></i> Edit
                                                            </a>
                                                        @else
                                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                                <i class="bi bi-lock-fill"></i> Terkunci
                                                            </button>
                                                        @endif
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
        $(document).ready(function () {
            if (!(window.jQuery && window.jQuery.fn.DataTable)) {
                return;
            }

            $('#datatable').DataTable({
                autoWidth: false,
                scrollX: true,
                dom: 'Bfrtip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                order: [[0, 'asc']]
            });
        });
    </script>
@endpush
