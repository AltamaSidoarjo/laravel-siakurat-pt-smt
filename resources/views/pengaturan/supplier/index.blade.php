@extends('layouts.app')

@section('title', 'Master Supplier')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.supplier', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.supplier', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.supplier', 'delete') ?? false;
    @endphp
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Master Supplier</div></div></div>
    <div class="card border-muhammadiyah mb-2"><div class="card-body">
        @include('partials.flash-message')
        <div class="d-flex flex-column gap-3">
            <div class="card border-light shadow-sm"><div class="card-body">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari kode, nama, kategori, atau email supplier"></div>
                    <div class="col-auto"><button class="btn btn-primary">Cari</button></div>
                    @if ($search !== '')<div class="col-auto"><a href="{{ route('pengaturan.supplier.index') }}" class="btn btn-light">Reset</a></div>@endif
                    @if ($canCreate)<div class="col-auto ms-auto"><a href="{{ route('pengaturan.supplier.create') }}" class="btn btn-success fw-bold"><i class="bi bi-plus-circle-fill"></i> Tambah</a></div>@endif
                </form>
            </div></div>
            <div class="card border-light shadow-sm"><div class="card-body"><div class="table-responsive">
                <table class="table table-sm table-striped table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Supplier</th>
                            <th>Kategori</th>
                            <th>Email</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 170px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->kode_supplier }}</strong></td>
                            <td>{{ $supplier->nama_supplier }}</td>
                            <td>{{ $supplier->kategori_supplier ?: '-' }}</td>
                            <td>{{ $supplier->email_supplier ?: '-' }}</td>
                            <td>{{ $supplier->alamat_supplier ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $supplier->status_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $supplier->status_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($canUpdate)
                                    <a href="{{ route('pengaturan.supplier.edit', $supplier) }}" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                @endif
                                @if ($canDelete)
                                    <form method="post" action="{{ route('pengaturan.supplier.destroy', $supplier) }}" class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash3"></i> Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Data supplier tidak ditemukan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>{{ $suppliers->onEachSide(1)->links('pagination::bootstrap-5') }}</div></div>
        </div>
    </div></div>
@endsection
