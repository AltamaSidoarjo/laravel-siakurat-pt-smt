@extends('layouts.app')

@section('title', 'Master Pelaksana')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.master-pelaksana', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.master-pelaksana', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.master-pelaksana', 'delete') ?? false;
    @endphp
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Master Pelaksana</div></div></div>
    <div class="card border-muhammadiyah mb-2"><div class="card-body">
        @include('partials.flash-message')
        <div class="d-flex flex-column gap-3">
            <div class="card border-light shadow-sm"><div class="card-body">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari kode atau nama pelaksana"></div>
                    <div class="col-auto"><button class="btn btn-primary">Cari</button></div>
                    @if ($search !== '')<div class="col-auto"><a href="{{ route('pengaturan.pelaksana.index') }}" class="btn btn-light">Reset</a></div>@endif
                    @if ($canCreate)<div class="col-auto ms-auto"><a href="{{ route('pengaturan.pelaksana.create') }}" class="btn btn-success fw-bold"><i class="bi bi-plus-circle-fill"></i> Tambah</a></div>@endif
                </form>
            </div></div>
            <div class="card border-light shadow-sm"><div class="card-body"><div class="table-responsive">
                <table class="table table-sm table-striped table-bordered table-hover">
                    <thead class="table-light"><tr><th>No. Proyek</th><th>Nama Pelaksana</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($pelaksanas as $pelaksana)
                        <tr>
                            <td>{{ $pelaksana->no_proyek }}</td><td>{{ $pelaksana->nama_pelaksana }}</td>
                            <td><span class="badge {{ $pelaksana->status_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $pelaksana->status_aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-center">
                                @if ($canUpdate)<a href="{{ route('pengaturan.pelaksana.edit', $pelaksana) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i> Edit</a>@endif
                                @if ($canDelete)<form method="post" action="{{ route('pengaturan.pelaksana.destroy', $pelaksana) }}" class="d-inline" onsubmit="return confirm('Hapus pelaksana ini?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> Hapus</button></form>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Data pelaksana tidak ditemukan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>{{ $pelaksanas->onEachSide(1)->links('pagination::bootstrap-5') }}</div></div>
        </div>
    </div></div>
@endsection
