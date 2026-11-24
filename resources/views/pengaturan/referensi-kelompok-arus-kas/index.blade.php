@extends('layouts.app')

@section('title', 'Referensi Kelompok Arus Kas')

@push('styles')
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>
@endpush

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'delete') ?? false;
    @endphp

    <div class="d-flex flex-column gap-3">
        <!-- Header -->
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('home') }}" class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px;" title="Kembali ke Dashboard">
                            <i class="bi bi-arrow-left fs-5"></i>
                        </a>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="mb-0 fw-bold text-dark">Referensi Kelompok Arus Kas</h4>
                                <span class="badge bg-success-subtle text-success border border-success-subtle small">Master Data</span>
                            </div>
                            <p class="text-muted mb-0 small mt-1">
                                Standardisasi master pos kelompok arus kas per aktivitas (Operasi, Investasi, Pendanaan) dan sinkronisasi ke bagan akun (COA).
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.flash-message')
        @include('partials.validation-errors')

        <!-- Navigation Tabs & Content Card -->
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-4">
                <ul class="nav nav-tabs card-header-tabs" id="referensiTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $aktivitas === 'semua' || empty($aktivitas) ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['search' => $search])) }}">
                            <i class="bi bi-collection me-1.5"></i> Semua Pos
                            @if(isset($tabCounts['semua']))
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ $tabCounts['semua'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $aktivitas === 'operasi' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'operasi', 'search' => $search])) }}">
                            <i class="bi bi-activity me-1.5"></i> Aktivitas Operasi
                            @if(isset($tabCounts['operasi']))
                                <span class="badge bg-primary-subtle text-primary rounded-pill ms-1">{{ $tabCounts['operasi'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $aktivitas === 'investasi' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'investasi', 'search' => $search])) }}">
                            <i class="bi bi-graph-up-arrow me-1.5"></i> Aktivitas Investasi
                            @if(isset($tabCounts['investasi']))
                                <span class="badge bg-success-subtle text-success rounded-pill ms-1">{{ $tabCounts['investasi'] }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $aktivitas === 'pendanaan' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', array_filter(['aktivitas' => 'pendanaan', 'search' => $search])) }}">
                            <i class="bi bi-bank me-1.5"></i> Aktivitas Pendanaan
                            @if(isset($tabCounts['pendanaan']))
                                <span class="badge bg-info-subtle text-info rounded-pill ms-1">{{ $tabCounts['pendanaan'] }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3 p-md-4">
                <!-- Toolbar Filter Pencarian -->
                <div class="bg-light p-3 rounded-3 border mb-3" id="filterToolbar">
                    <form method="GET" action="{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}" class="row g-2 align-items-center">
                        @if($aktivitas && in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true))
                            <input type="hidden" name="aktivitas" value="{{ $aktivitas }}">
                        @endif

                        <div class="col-12 col-md-7 col-lg-8">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" name="search" value="{{ $search }}" class="form-control border-start-0" placeholder="Cari nama kelompok arus kas...">
                            </div>
                        </div>

                        <div class="col-12 col-md-5 col-lg-4 d-flex flex-wrap gap-2 justify-content-md-end">
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3">
                                <i class="bi bi-filter me-1"></i> Filter
                            </button>
                            @if($search !== '' || ($aktivitas !== 'semua' && !empty($aktivitas)))
                                <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index', $aktivitas !== 'semua' && !empty($aktivitas) ? ['aktivitas' => $aktivitas] : []) }}" class="btn btn-sm btn-light border fw-semibold">
                                    Reset
                                </a>
                            @endif

                            @if ($canCreate)
                                <button type="button" class="btn btn-sm btn-success fw-bold text-nowrap ms-auto" data-bs-toggle="modal" data-bs-target="#modalTambahReferensi">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Pos Referensi
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="table-responsive border rounded-3 custom-scrollbar" id="tableReferensiContainer">
                    <table class="table table-hover align-middle mb-0 text-sm">
                        <thead class="table-light border-bottom">
                            <tr class="text-nowrap small text-uppercase tracking-wider text-secondary">
                                <th class="py-2.5 px-3 text-center" style="width: 50px;">No</th>
                                <th class="py-2.5 px-3" style="width: 140px;">Aktivitas</th>
                                <th class="py-2.5 px-3" style="min-width: 260px;">Nama Kelompok Arus Kas</th>
                                <th class="py-2.5 px-3 text-center" style="width: 150px;">Status</th>
                                <th class="py-2.5 px-3 text-center" style="width: 160px;">COA Terkait</th>
                                <th class="py-2.5 px-3 text-center" style="width: 110px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($referensiList as $item)
                                <tr>
                                    <td class="text-center align-middle fw-bold text-muted">
                                        {{ $loop->iteration + ($referensiList->currentPage() - 1) * $referensiList->perPage() }}
                                    </td>
                                    <td class="align-middle">
                                        @if ($item->aktivitas === 'operasi')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                Operasi
                                            </span>
                                        @elseif ($item->aktivitas === 'investasi')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-semibold">
                                                Investasi
                                            </span>
                                        @elseif ($item->aktivitas === 'pendanaan')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill fw-semibold">
                                                Pendanaan
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                {{ ucfirst($item->aktivitas) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <div class="fw-semibold text-dark">{{ $item->nama }}</div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1.5">
                                            @if ($item->status_aktif)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-semibold">
                                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Aktif
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                    Nonaktif
                                                </span>
                                            @endif

                                            @if ($canUpdate)
                                                <form method="POST" action="{{ route('pengaturan.referensi-kelompok-arus-kas.toggle', $item) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary p-1 py-0 rounded-circle border-0" title="Ubah Status Aktif">
                                                        <i class="bi {{ $item->status_aktif ? 'bi-toggle-on text-success fs-5' : 'bi-toggle-off text-muted fs-5' }}"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        @if (($item->coa_count ?? 0) > 0)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                <i class="bi bi-diagram-3 me-1"></i> {{ $item->coa_count }} Akun
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1 rounded-pill">
                                                0 Akun
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle text-nowrap">
                                        @if ($canUpdate)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary py-0.5 px-2 btn-edit-referensi"
                                                    data-id="{{ $item->id }}"
                                                    data-aktivitas="{{ $item->aktivitas }}"
                                                    data-nama="{{ $item->nama }}"
                                                    data-status="{{ $item->status_aktif ? 1 : 0 }}"
                                                    title="Edit Pos Referensi">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endif

                                        @if ($canDelete)
                                            <form method="POST" action="{{ route('pengaturan.referensi-kelompok-arus-kas.destroy', $item) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('{{ ($item->coa_count ?? 0) > 0 ? "PERINGATAN: Pos ini sedang digunakan oleh {$item->coa_count} akun COA dan penghapusan akan ditolak sistem. Tetap lanjutkan?" : "Apakah Anda yakin ingin menghapus pos referensi ini?" }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger py-0.5 px-2 ms-1"
                                                        @if(($item->coa_count ?? 0) > 0)
                                                            title="Pos ini sedang digunakan oleh {{ $item->coa_count }} akun COA"
                                                        @else
                                                            title="Hapus Pos Referensi"
                                                        @endif>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        Data referensi kelompok arus kas belum ada atau tidak sesuai filter.
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
                                <input type="hidden" name="status_aktif" value="0">
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
                    form.action = '{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}/' + id;

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
