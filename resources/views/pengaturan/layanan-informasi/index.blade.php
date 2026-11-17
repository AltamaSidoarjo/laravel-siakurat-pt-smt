@extends('layouts.app')

@section('title', 'Layanan Informasi')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.layanan-informasi', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.layanan-informasi', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.layanan-informasi', 'delete') ?? false;
    @endphp
    <div class="row mb-3"><div class="col"><div class="fs-3 fw-bold">Layanan Informasi</div></div></div>
    <div class="card border-muhammadiyah mb-2"><div class="card-body">
        @include('partials.flash-message')
        <div class="d-flex flex-column gap-3">
            <div class="card border-light shadow-sm"><div class="card-body">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari judul atau isi informasi..."></div>
                    <div class="col-auto"><button class="btn btn-primary">Cari</button></div>
                    @if ($search !== '')<div class="col-auto"><a href="{{ route('pengaturan.layanan-informasi.index') }}" class="btn btn-light">Reset</a></div>@endif
                    @if ($canCreate)<div class="col-auto ms-auto"><a href="{{ route('pengaturan.layanan-informasi.create') }}" class="btn btn-success fw-bold"><i class="bi bi-plus-circle-fill"></i> Tambah Informasi</a></div>@endif
                </form>
            </div></div>
            <div class="card border-light shadow-sm"><div class="card-body"><div class="table-responsive">
                <table class="table table-sm table-striped table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">Urutan</th>
                            <th>Judul Informasi</th>
                            <th style="width: 120px;">Tipe</th>
                            <th>Periode Tayang</th>
                            <th class="text-center" style="width: 110px;">Popup Modal</th>
                            <th class="text-center" style="width: 100px;">Status</th>
                            <th class="text-center" style="width: 170px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($informasiList as $item)
                        <tr>
                            <td class="text-center">{{ $item->urutan }}</td>
                            <td>
                                <strong>{{ $item->judul }}</strong>
                                <div class="text-muted small text-truncate" style="max-width: 380px;">{{ \Illuminate\Support\Str::limit(strip_tags($item->isi), 90) }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $item->badge_class }}">
                                    <i class="bi {{ $item->icon_class }} me-1"></i> {{ $item->tipe_label }}
                                </span>
                            </td>
                            <td>
                                @if ($item->tanggal_mulai && $item->tanggal_selesai)
                                    {{ $item->tanggal_mulai->format('d/m/Y') }} s/d {{ $item->tanggal_selesai->format('d/m/Y') }}
                                @elseif ($item->tanggal_mulai)
                                    Mulai {{ $item->tanggal_mulai->format('d/m/Y') }}
                                @elseif ($item->tanggal_selesai)
                                    Sampai {{ $item->tanggal_selesai->format('d/m/Y') }}
                                @else
                                    <span class="text-muted">Selamanya</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($item->tampilkan_popup)
                                    <span class="badge bg-primary"><i class="bi bi-window-stack me-1"></i> Ya</span>
                                @else
                                    <span class="badge bg-light text-muted">Hanya Banner</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $item->status_aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $item->status_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($canUpdate)
                                    <a href="{{ route('pengaturan.layanan-informasi.edit', $item) }}" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                @endif
                                @if ($canDelete)
                                    <form method="post" action="{{ route('pengaturan.layanan-informasi.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Hapus informasi ini?')">
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
                        <tr><td colspan="7" class="text-center text-muted">Data layanan informasi belum ada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>{{ $informasiList->onEachSide(1)->links('pagination::bootstrap-5') }}</div></div>
        </div>
    </div></div>
@endsection
