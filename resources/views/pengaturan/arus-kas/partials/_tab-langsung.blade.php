{{-- Toolbar Filter Metode Langsung --}}
<div class="bg-light p-3 rounded-3 border mb-3" id="filterToolbarLangsung">
    <form method="GET" action="{{ route('pengaturan.arus-kas.index') }}" id="formFilterLangsung">
        <input type="hidden" name="metode" value="langsung">

        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4 col-lg-3">
                <select name="aktivitas" id="selectAktivitasLangsung" class="form-select form-select-sm">
                    <option value="semua" @selected($aktivitas === 'semua' || empty($aktivitas))>
                        Semua Aktivitas ({{ $referensiTabCounts['semua'] }})
                    </option>
                    <option value="operasi" @selected($aktivitas === 'operasi')>
                        Aktivitas Operasi ({{ $referensiTabCounts['operasi'] }})
                    </option>
                    <option value="investasi" @selected($aktivitas === 'investasi')>
                        Aktivitas Investasi ({{ $referensiTabCounts['investasi'] }})
                    </option>
                    <option value="pendanaan" @selected($aktivitas === 'pendanaan')>
                        Aktivitas Pendanaan ({{ $referensiTabCounts['pendanaan'] }})
                    </option>
                </select>
            </div>

            <div class="col-12 col-md flex-grow-1">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white text-muted border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           name="search"
                           id="searchLangsungInput"
                           value="{{ $search }}"
                           class="form-control border-start-0 {{ $search !== '' ? 'border-end-0' : '' }}"
                           placeholder="Cari nama kelompok arus kas... (Tekan Enter)">
                    @if ($search !== '')
                        <a href="{{ route('pengaturan.arus-kas.index', array_filter(['metode' => 'langsung', 'aktivitas' => $aktivitas !== 'semua' ? $aktivitas : null])) }}"
                           class="input-group-text bg-white text-muted border-start-0 text-decoration-none"
                           title="Reset pencarian"
                           id="btnResetSearchLangsung">
                            <i class="bi bi-x-circle-fill text-secondary"></i>
                        </a>
                    @endif
                </div>
            </div>

            @if ($canCreate)
                <div class="col-12 col-md-auto ms-auto">
                    <button type="button"
                            class="btn btn-sm btn-success fw-bold text-nowrap w-100"
                            id="btnOpenCreateReferensi"
                            data-bs-toggle="modal"
                            data-bs-target="#modalTambahReferensi">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Pos Referensi
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

{{-- Data Table Metode Langsung --}}
<div class="table-responsive border rounded-3 custom-scrollbar" id="tableReferensiContainer">
    <table class="table table-hover align-middle mb-0 text-sm">
        <thead class="table-light border-bottom">
            <tr class="text-nowrap small text-uppercase tracking-wider text-secondary">
                <th class="py-2.5 px-3 text-center" style="width: 50px;">No</th>
                <th class="py-2.5 px-3" style="width: 140px;">Aktivitas</th>
                <th class="py-2.5 px-3" style="min-width: 260px;">Nama Kelompok Arus Kas</th>
                <th class="py-2.5 px-3 text-center" style="width: 120px;">Status</th>
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
                        @if ($item->status_aktif)
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                Nonaktif
                            </span>
                        @endif
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
                            <form method="POST" action="{{ route('pengaturan.arus-kas.langsung.destroy', $item) }}"
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
