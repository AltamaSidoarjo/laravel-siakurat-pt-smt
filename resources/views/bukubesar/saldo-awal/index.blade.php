@extends('layouts.app')

@section('title', 'Saldo Awal Akun')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Saldo Awal Akun (Opening Balance)</span>
            </div>
            <div class="text-muted small">
                Pencatatan posisi neraca awal per tanggal cut-off sesuai standar akuntansi.
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
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <h5 class="mb-1 text-primary fw-bold">
                                            <i class="bi bi-journal-check me-2"></i>Daftar Saldo Awal
                                        </h5>
                                        <p class="mb-0 text-muted small">
                                            Saldo awal yang telah berstatus <strong>Terkunci (Locked)</strong> secara otomatis diposting ke Buku Besar sebagai saldo pembuka periode.
                                        </p>
                                    </div>
                                    <a href="{{ route('bukubesar.saldo-awal.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill me-1"></i> Setup Saldo Awal Baru
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 50px;">No</th>
                                                <th>Nomor</th>
                                                <th>Tanggal Cut-Off</th>
                                                <th>Keterangan</th>
                                                <th class="text-end">Total Debit</th>
                                                <th class="text-end">Total Kredit</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center" style="min-width: 150px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($saldoAwalList as $index => $item)
                                                @php
                                                    $isBalanced = $item->isBalanced();
                                                    $isLocked = $item->isLocked();
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $index + 1 }}</td>
                                                    <td class="fw-bold">
                                                        <a href="{{ route('bukubesar.saldo-awal.edit', $item) }}" class="text-decoration-none">
                                                            {{ $item->nomer }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-calendar3 me-1"></i>
                                                            {{ optional($item->tanggal_cutoff)->format('d/m/Y') }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $item->keterangan ?: '-' }}</td>
                                                    <td class="text-end fw-semibold text-success">
                                                        Rp {{ number_format((float) $item->total_debit, 0, ',', '.') }}
                                                    </td>
                                                    <td class="text-end fw-semibold text-primary">
                                                        Rp {{ number_format((float) $item->total_kredit, 0, ',', '.') }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($isLocked)
                                                            <span class="badge bg-success" title="Terkunci & Diposting ke Buku Besar">
                                                                <i class="bi bi-lock-fill me-1"></i> Terkunci (Posted)
                                                            </span>
                                                        @else
                                                            <span class="badge bg-warning text-dark" title="Masih berupa draft">
                                                                <i class="bi bi-pencil-fill me-1"></i> Draft
                                                            </span>
                                                            @if (! $isBalanced)
                                                                <div class="small text-danger mt-1">⚠️ Belum Seimbang</div>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="{{ route('bukubesar.saldo-awal.edit', $item) }}" class="btn btn-outline-primary" title="{{ $isLocked ? 'Lihat Detail' : 'Edit Saldo' }}">
                                                                <i class="bi {{ $isLocked ? 'bi-eye' : 'bi-pencil' }}"></i> {{ $isLocked ? 'Lihat' : 'Edit' }}
                                                            </a>

                                                            @if (! $isLocked && $isBalanced)
                                                                <form action="{{ route('bukubesar.saldo-awal.lock', $item) }}" method="post" class="d-inline" onsubmit="return confirm('Kunci dan posting saldo awal ini ke Buku Besar?');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-outline-success" title="Kunci & Posting">
                                                                        <i class="bi bi-lock"></i> Kunci
                                                                    </button>
                                                                </form>
                                                            @endif

                                                            @if ($isLocked)
                                                                <form action="{{ route('bukubesar.saldo-awal.unlock', $item) }}" method="post" class="d-inline" onsubmit="return confirm('Buka kunci saldo awal ini? Catatan di Buku Besar akan ditarik kembali hingga dikunci ulang.');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-outline-warning" title="Buka Kunci (Unlock)">
                                                                        <i class="bi bi-unlock"></i> Buka Kunci
                                                                    </button>
                                                                </form>
                                                            @endif

                                                            @if (! $isLocked)
                                                                <form action="{{ route('bukubesar.saldo-awal.destroy', $item) }}" method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data saldo awal ini?');">
                                                                    @csrf
                                                                    @method('delete')
                                                                    <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-muted">
                                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                        Belum ada data Saldo Awal yang dibuat.<br>
                                                        Klik tombol <strong>"Setup Saldo Awal Baru"</strong> untuk memasukkan saldo awal pembukuan.
                                                    </td>
                                                </tr>
                                            @endforelse
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

