@extends('layouts.app')

@section('title', 'Referensi Kelompok Arus Kas')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'delete') ?? false;
    @endphp

    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Referensi Kelompok Arus Kas</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <div class="d-flex flex-column gap-3 mt-2">
                        <!-- Filter & Search Toolbar -->
                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-auto mb-2 mb-md-0">
                                        <div class="btn-group" role="group" aria-label="Filter Aktivitas">
                                            <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['search' => $search])) }}"
                                               class="btn btn-sm {{ $aktivitas === 'semua' || empty($aktivitas) ? 'btn-primary' : 'btn-outline-primary' }}">
                                                Semua
                                                @if(isset($tabCounts['semua']))
                                                    <span class="badge text-bg-light ms-1">{{ $tabCounts['semua'] }}</span>
                                                @endif
                                            </a>
                                            <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'operasi', 'search' => $search])) }}"
                                               class="btn btn-sm {{ $aktivitas === 'operasi' ? 'btn-primary' : 'btn-outline-primary' }}">
                                                Operasi
                                                @if(isset($tabCounts['operasi']))
                                                    <span class="badge text-bg-light ms-1">{{ $tabCounts['operasi'] }}</span>
                                                @endif
                                            </a>
                                            <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'investasi', 'search' => $search])) }}"
                                               class="btn btn-sm {{ $aktivitas === 'investasi' ? 'btn-success' : 'btn-outline-success' }}">
                                                Investasi
                                                @if(isset($tabCounts['investasi']))
                                                    <span class="badge text-bg-light ms-1">{{ $tabCounts['investasi'] }}</span>
                                                @endif
                                            </a>
                                            <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'pendanaan', 'search' => $search])) }}"
                                               class="btn btn-sm {{ $aktivitas === 'pendanaan' ? 'btn-info text-dark' : 'btn-outline-info text-dark' }}">
                                                Pendanaan
                                                @if(isset($tabCounts['pendanaan']))
                                                    <span class="badge text-bg-light ms-1">{{ $tabCounts['pendanaan'] }}</span>
                                                @endif
                                            </a>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md">
                                        <form method="get" action="{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}" class="d-flex gap-2">
                                            @if($aktivitas && in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true))
                                                <input type="hidden" name="aktivitas" value="{{ $aktivitas }}">
                                            @endif
                                            <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari nama kelompok arus kas...">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-search"></i> Cari
                                            </button>
                                            @if($search !== '' || ($aktivitas !== 'semua' && !empty($aktivitas)))
                                                <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}" class="btn btn-sm btn-light border">
                                                    Reset
                                                </a>
                                            @endif
                                        </form>
                                    </div>

                                    @if ($canCreate)
                                        <div class="col-12 col-md-auto ms-auto text-end">
                                            <button type="button" class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahReferensi">
                                                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Pos Referensi
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 50px;">No</th>
                                                <th style="width: 140px;">Aktivitas</th>
                                                <th>Nama Kelompok Arus Kas</th>
                                                <th class="text-center" style="width: 160px;">Status Aktif</th>
                                                <th class="text-center" style="width: 150px;">Jumlah COA Terkait</th>
                                                <th class="text-center" style="width: 160px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($referensiList as $item)
                                                <tr>
                                                    <td class="text-center">
                                                        {{ $loop->iteration + ($referensiList->currentPage() - 1) * $referensiList->perPage() }}
                                                    </td>
                                                    <td>
                                                        @if ($item->aktivitas === 'operasi')
                                                            <span class="badge bg-primary">Operasi</span>
                                                        @elseif ($item->aktivitas === 'investasi')
                                                            <span class="badge bg-success">Investasi</span>
                                                        @elseif ($item->aktivitas === 'pendanaan')
                                                            <span class="badge bg-info text-dark">Pendanaan</span>
                                                        @else
                                                            <span class="badge bg-secondary">{{ ucfirst($item->aktivitas) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="fw-semibold">
                                                        {{ $item->nama }}
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                                            @if ($item->status_aktif)
                                                                <span class="badge bg-success">Aktif</span>
                                                            @else
                                                                <span class="badge bg-secondary">Nonaktif</span>
                                                            @endif

                                                            @if ($canUpdate)
                                                                <form method="POST" action="{{ route('pengaturan.referensi-kelompok-arus-kas.toggle', $item) }}" class="d-inline">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button type="submit" class="btn btn-xs btn-outline-secondary p-1 py-0" title="Ubah Status">
                                                                        <i class="bi {{ $item->status_aktif ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' }} fs-6"></i>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        @if (($item->coa_count ?? 0) > 0)
                                                            <span class="badge bg-primary rounded-pill px-2 py-1">
                                                                {{ $item->coa_count }} Akun
                                                            </span>
                                                        @else
                                                            <span class="text-muted small">0 Akun</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            @if ($canUpdate)
                                                                <button type="button"
                                                                        class="btn btn-warning btn-sm btn-edit-referensi"
                                                                        data-id="{{ $item->id }}"
                                                                        data-aktivitas="{{ $item->aktivitas }}"
                                                                        data-nama="{{ $item->nama }}"
                                                                        data-status="{{ $item->status_aktif ? 1 : 0 }}"
                                                                        title="Edit Pos Referensi">
                                                                    <i class="bi bi-pencil-square"></i> Edit
                                                                </button>
                                                            @endif

                                                            @if ($canDelete)
                                                                <form method="POST" action="{{ route('pengaturan.referensi-kelompok-arus-kas.destroy', $item) }}"
                                                                      class="d-inline"
                                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus pos referensi ini?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                            class="btn btn-danger btn-sm"
                                                                            @if(($item->coa_count ?? 0) > 0)
                                                                                title="Pos ini sedang digunakan oleh {{ $item->coa_count }} akun COA"
                                                                            @else
                                                                                title="Hapus Pos Referensi"
                                                                            @endif>
                                                                        <i class="bi bi-trash3"></i> Hapus
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                                        Data referensi kelompok arus kas belum ada.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    {{ $referensiList->links('pagination::bootstrap-5') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Referensi -->
    <div class="modal fade" id="modalTambahReferensi" tabindex="-1" aria-labelledby="modalTambahReferensiLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('pengaturan.referensi-kelompok-arus-kas.store') }}">
                    @csrf
                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalTambahReferensiLabel">
                            <i class="bi bi-plus-circle-fill text-success me-1"></i> Tambah Pos Referensi Arus Kas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="createAktivitas" class="form-label small fw-semibold">Aktivitas <span class="text-danger">*</span></label>
                            <select name="aktivitas" id="createAktivitas" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Aktivitas --</option>
                                <option value="operasi" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'operasi')>Operasi</option>
                                <option value="investasi" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'investasi')>Investasi</option>
                                <option value="pendanaan" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'pendanaan')>Pendanaan</option>
                            </select>
                            <div class="form-text small">Pilih kategori aktivitas arus kas standar.</div>
                        </div>

                        <div class="mb-3">
                            <label for="createNama" class="form-label small fw-semibold">Nama Kelompok Arus Kas <span class="text-danger">*</span></label>
                            <input type="text" name="nama" id="createNama" class="form-control form-control-sm" required maxlength="150" placeholder="Contoh: Pembayaran Rekening Listrik" value="{{ old('nama') }}">
                            <div class="form-text small">Maksimal 150 karakter. Spasi berlebih akan dirapikan otomatis.</div>
                        </div>

                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status_aktif" value="1" id="createStatusAktif" @checked(old('status_aktif', '1') == '1')>
                                <label class="form-check-label small fw-semibold" for="createStatusAktif">Status Aktif</label>
                            </div>
                            <div class="form-text small">Pos yang aktif dapat dipilih saat konfigurasi COA.</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-2 border-top">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                            <i class="bi bi-check-circle me-1"></i> Simpan Pos Referensi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Referensi -->
    <div class="modal fade" id="modalEditReferensi" tabindex="-1" aria-labelledby="modalEditReferensiLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form id="formEditReferensi" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalEditReferensiLabel">
                            <i class="bi bi-pencil-square text-primary me-1"></i> Edit Pos Referensi Arus Kas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Aktivitas</label>
                            <input type="text" id="editAktivitasLabel" class="form-control form-control-sm bg-light text-uppercase fw-semibold" readonly disabled>
                            <div class="form-text small text-muted">Aktivitas tidak dapat diubah (immutabilitas aktivitas).</div>
                        </div>

                        <div class="mb-3">
                            <label for="editNama" class="form-label small fw-semibold">Nama Kelompok Arus Kas <span class="text-danger">*</span></label>
                            <input type="text" name="nama" id="editNama" class="form-control form-control-sm" required maxlength="150" placeholder="Contoh: Pembayaran Rekening Listrik">
                            <div class="form-text small">Perubahan nama akan otomatis meng-update akun COA yang menggunakan pos ini (cascade).</div>
                        </div>

                        <div class="mb-2">
                            <label for="editStatusAktif" class="form-label small fw-semibold">Status Aktif <span class="text-danger">*</span></label>
                            <select name="status_aktif" id="editStatusAktif" class="form-select form-select-sm" required>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                            <div class="form-text small">Jika nonaktif, pos tidak dapat dipilih lagi saat konfigurasi COA baru.</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-2 border-top">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                            <i class="bi bi-check-circle me-1"></i> Perbarui Pos Referensi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEditEl = document.getElementById('modalEditReferensi');
        if (modalEditEl) {
            const modalEdit = new bootstrap.Modal(modalEditEl);
            document.querySelectorAll('.btn-edit-referensi').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const aktivitas = this.getAttribute('data-aktivitas');
                    const nama = this.getAttribute('data-nama');
                    const status = this.getAttribute('data-status');

                    const form = document.getElementById('formEditReferensi');
                    form.action = '{{ url('/pengaturan/referensi-kelompok-arus-kas') }}/' + id;

                    document.getElementById('editAktivitasLabel').value = aktivitas;
                    document.getElementById('editNama').value = nama;
                    document.getElementById('editStatusAktif').value = status;

                    modalEdit.show();
                });
            });
        }
    });
</script>
@endpush
