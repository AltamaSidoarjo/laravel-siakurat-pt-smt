@extends('layouts.app')

@section('title', 'Pengaturan Arus Kas')

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
        $canCreate = $user?->hasModuleAccess('pengaturan.arus-kas', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.arus-kas', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.arus-kas', 'delete') ?? false;
        $canReferensiArusKas = $user?->hasModuleAccess('pengaturan.referensi-kelompok-arus-kas', 'view') ?? false;

        $searchLower = mb_strtolower($search);

        $filteredPosList = $posList
            ->when(
                $activeTab !== 'semua' && $activeTab !== 'pengecualian',
                fn ($collection) => $collection->where('kelompok_bagian', $activeTab)
            )
            ->when($searchLower !== '', function ($collection) use ($searchLower) {
                return $collection->filter(function ($pos) use ($searchLower): bool {
                    if (str_contains(mb_strtolower((string) $pos->nama_pos), $searchLower)) {
                        return true;
                    }
                    if (str_contains(mb_strtolower((string) $pos->kode_pos), $searchLower)) {
                        return true;
                    }

                    return $pos->coas->contains(function ($coa) use ($searchLower): bool {
                        return str_contains(mb_strtolower((string) $coa->kode), $searchLower)
                            || str_contains(mb_strtolower((string) $coa->nama), $searchLower);
                    });
                });
            })
            ->values();

        $filteredPengecualian = $pengecualianList
            ->when($searchLower !== '', function ($collection) use ($searchLower) {
                return $collection->filter(function ($item) use ($searchLower): bool {
                    $coa = $item->coa;
                    if (str_contains(mb_strtolower((string) ($item->alasan ?? '')), $searchLower)) {
                        return true;
                    }
                    if ($coa === null) {
                        return false;
                    }

                    return str_contains(mb_strtolower((string) $coa->kode), $searchLower)
                        || str_contains(mb_strtolower((string) $coa->nama), $searchLower);
                });
            })
            ->values();

        $bagianBadgeClasses = [
            'penyesuaian_laba' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
            'modal_kerja' => 'bg-primary-subtle text-primary border-primary-subtle',
            'investasi' => 'bg-success-subtle text-success border-success-subtle',
            'pendanaan' => 'bg-info-subtle text-info-emphasis border-info-subtle',
        ];
    @endphp

    <div class="d-flex flex-column gap-3">
        <!-- Header Card -->
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('home') }}"
                           class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs"
                           style="width: 38px; height: 38px;"
                           title="Kembali ke Dashboard">
                            <i class="bi bi-arrow-left fs-5"></i>
                        </a>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h4 class="mb-0 fw-bold text-dark">Pengaturan Arus Kas</h4>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                    Metode Tidak Langsung
                                </span>
                            </div>
                            <p class="text-muted mb-0 small mt-1">
                                Pemetaan pos laporan arus kas metode tidak langsung per bagian (Penyesuaian Laba, Modal Kerja, Investasi, Pendanaan), akun COA terkait, serta daftar pengecualian akun kontra.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if ($canReferensiArusKas)
                            <a href="{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}"
                               class="btn btn-sm btn-outline-secondary fw-semibold text-nowrap">
                                <i class="bi bi-diagram-3 me-1"></i> Referensi Kelompok Arus Kas (Langsung)
                            </a>
                        @endif

                        <div class="btn-group btn-group-sm" role="group" aria-label="Mode Tampilan Pengaturan Arus Kas">
                            <button type="button"
                                    id="btnModeTab"
                                    class="btn btn-primary fw-semibold active"
                                    data-view-mode="tab">
                                <i class="bi bi-table me-1"></i> Mode Tab
                            </button>
                            <button type="button"
                                    id="btnModeStruktur"
                                    class="btn btn-outline-primary fw-semibold"
                                    data-view-mode="struktur">
                                <i class="bi bi-list-nested me-1"></i> Mode Struktur Laporan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.flash-message')
        @include('partials.validation-errors')

        <!-- Unmapped Mutated Neraca Warning Banner -->
        @if ($unmappedAccounts->isNotEmpty())
            <div class="alert alert-warning border-warning-subtle shadow-sm mb-0" role="alert">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning-emphasis mt-0.5"></i>
                        <div>
                            <div class="fw-bold text-dark">
                                Terdapat {{ $unmappedAccounts->count() }} akun Neraca bermutasi tahun berjalan yang belum dipetakan atau dikecualikan.
                            </div>
                            <div class="small text-muted">
                                Akun berikut memiliki mutasi pada buku besar namun belum masuk ke pos aktif maupun daftar pengecualian.
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-warning text-dark fw-semibold text-nowrap"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#unmappedAccountsCollapse"
                            aria-expanded="false"
                            aria-controls="unmappedAccountsCollapse">
                        <i class="bi bi-chevron-down me-1"></i> Lihat Daftar Akun
                    </button>
                </div>

                <div class="collapse mt-3" id="unmappedAccountsCollapse">
                    <div class="table-responsive bg-white rounded-3 border">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-2">Akun COA</th>
                                    <th class="px-3 py-2">Tipe COA</th>
                                    <th class="px-3 py-2 text-end">Debit</th>
                                    <th class="px-3 py-2 text-end">Kredit</th>
                                    <th class="px-3 py-2 text-end">Dampak Kas</th>
                                    @if ($canCreate || $canUpdate)
                                        <th class="px-3 py-2 text-center">Tindakan Cepat</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unmappedAccounts as $account)
                                    <tr>
                                        <td class="px-3 py-2 fw-semibold text-dark">
                                            {{ $account['kode'] }} - {{ $account['nama'] }}
                                            @if ((int) $account['status_aktif'] !== 1)
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-muted">{{ $account['tipe_coa'] !== '' ? $account['tipe_coa'] : '-' }}</td>
                                        <td class="px-3 py-2 text-end font-monospace">{{ number_format((float) $account['debit'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-end font-monospace">{{ number_format((float) $account['kredit'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-end font-monospace fw-semibold">{{ number_format((float) $account['dampak_kas'], 2, ',', '.') }}</td>
                                        @if ($canCreate || $canUpdate)
                                            <td class="px-3 py-2 text-center text-nowrap">
                                                @if ($canCreate)
                                                    <button type="button"
                                                            class="btn btn-xs btn-outline-primary py-0 px-2 btn-quick-map-unmapped"
                                                            data-coa-id="{{ $account['coa_id'] }}"
                                                            data-coa-label="{{ $account['kode'] }} - {{ $account['nama'] }}">
                                                        <i class="bi bi-plus-circle me-1"></i> Petakan
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-xs btn-outline-secondary py-0 px-2 ms-1 btn-quick-exclude-unmapped"
                                                            data-coa-id="{{ $account['coa_id'] }}"
                                                            data-coa-label="{{ $account['kode'] }} - {{ $account['nama'] }}">
                                                        <i class="bi bi-slash-circle me-1"></i> Kecualikan
                                                    </button>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- Mode Tab Pane -->
        <div id="viewModeTab" class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-4">
                <ul class="nav nav-tabs card-header-tabs" id="arusKasTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'semua' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['search' => $search])) }}">
                            <i class="bi bi-collection me-1"></i> Semua Pos
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ $tabCounts['semua'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'penyesuaian_laba' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['tab' => 'penyesuaian_laba', 'search' => $search])) }}">
                            <i class="bi bi-sliders me-1"></i> Penyesuaian Laba
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill ms-1">{{ $tabCounts['penyesuaian_laba'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'modal_kerja' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['tab' => 'modal_kerja', 'search' => $search])) }}">
                            <i class="bi bi-arrow-left-right me-1"></i> Modal Kerja
                            <span class="badge bg-primary-subtle text-primary rounded-pill ms-1">{{ $tabCounts['modal_kerja'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'investasi' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['tab' => 'investasi', 'search' => $search])) }}">
                            <i class="bi bi-graph-up-arrow me-1"></i> Investasi
                            <span class="badge bg-success-subtle text-success rounded-pill ms-1">{{ $tabCounts['investasi'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'pendanaan' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['tab' => 'pendanaan', 'search' => $search])) }}">
                            <i class="bi bi-bank me-1"></i> Pendanaan
                            <span class="badge bg-info-subtle text-info-emphasis rounded-pill ms-1">{{ $tabCounts['pendanaan'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link fw-semibold px-3 py-2.5 {{ $activeTab === 'pengecualian' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', array_filter(['tab' => 'pengecualian', 'search' => $search])) }}">
                            <i class="bi bi-slash-circle me-1"></i> Akun Dikecualikan
                            <span class="badge bg-danger-subtle text-danger rounded-pill ms-1">{{ $tabCounts['pengecualian'] }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3 p-md-4">
                <!-- Filter & Action Toolbar -->
                <div class="bg-light p-3 rounded-3 border mb-3" id="filterToolbar">
                    <form method="GET" action="{{ route('pengaturan.arus-kas.index') }}" class="row g-2 align-items-center">
                        @if ($activeTab !== 'semua')
                            <input type="hidden" name="tab" value="{{ $activeTab }}">
                        @endif

                        <div class="col-12 col-md-6 col-lg-7">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search"
                                       name="search"
                                       value="{{ $search }}"
                                       class="form-control border-start-0"
                                       placeholder="Cari nama pos, kode pos, atau kode/nama akun COA...">
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-5 d-flex flex-wrap gap-2 justify-content-md-end">
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3">
                                <i class="bi bi-filter me-1"></i> Filter
                            </button>
                            @if ($search !== '' || $activeTab !== 'semua')
                                <a href="{{ route('pengaturan.arus-kas.index', $activeTab !== 'semua' ? ['tab' => $activeTab] : []) }}"
                                   class="btn btn-sm btn-light border fw-semibold">
                                    Reset
                                </a>
                            @endif

                            @if ($canCreate)
                                @if ($activeTab === 'pengecualian')
                                    <button type="button"
                                            class="btn btn-sm btn-danger fw-bold text-nowrap ms-auto btn-open-create-pengecualian"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalPengecualianArusKas">
                                        <i class="bi bi-plus-circle me-1"></i> Tambah Pengecualian
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-sm btn-success fw-bold text-nowrap ms-auto btn-open-create-pos"
                                            data-kelompok-bagian="{{ in_array($activeTab, \App\Models\SettingArusKas::KELOMPOK_BAGIAN, true) ? $activeTab : 'penyesuaian_laba' }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalPosArusKas">
                                        <i class="bi bi-plus-circle me-1"></i> Tambah Pos
                                    </button>
                                @endif
                            @endif
                        </div>
                    </form>
                </div>

                @if ($activeTab === 'pengecualian')
                    <div class="table-responsive border rounded-3 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase text-secondary">
                                    <th class="py-2.5 px-3 text-center" style="width: 55px;">No</th>
                                    <th class="py-2.5 px-3" style="min-width: 260px;">Akun COA</th>
                                    <th class="py-2.5 px-3" style="width: 160px;">Tipe COA</th>
                                    <th class="py-2.5 px-3" style="min-width: 240px;">Alasan Pengecualian</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 95px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filteredPengecualian as $item)
                                    <tr>
                                        <td class="text-center fw-bold text-muted">{{ $loop->iteration }}</td>
                                        <td>
                                            @if ($item->coa)
                                                <span class="fw-semibold text-dark">{{ $item->coa->kode }} - {{ $item->coa->nama }}</span>
                                                @if ((int) $item->coa->status_aktif !== 1)
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">Nonaktif</span>
                                                @endif
                                            @else
                                                <span class="text-muted">COA #{{ $item->coa_id }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $item->coa?->tipe_coa ?? '-' }}</td>
                                        <td class="text-muted">{{ $item->alasan ?? '-' }}</td>
                                        <td class="text-center text-nowrap">
                                            @if ($canDelete)
                                                <form method="POST"
                                                      action="{{ route('pengaturan.arus-kas.pengecualian.destroy', $item) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Hapus akun ini dari daftar pengecualian?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2" title="Hapus Pengecualian">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada akun yang dikecualikan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-responsive border rounded-3 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase text-secondary">
                                    <th class="py-2.5 px-3 text-center" style="width: 65px;">Urutan</th>
                                    <th class="py-2.5 px-3" style="min-width: 220px;">Pos Arus Kas</th>
                                    <th class="py-2.5 px-3" style="width: 170px;">Bagian</th>
                                    <th class="py-2.5 px-3" style="width: 180px;">Sifat Pos</th>
                                    <th class="py-2.5 px-3" style="min-width: 260px;">Akun COA Terpetakan</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 140px;">Status</th>
                                    <th class="py-2.5 px-3 text-center" style="width: 110px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filteredPosList as $pos)
                                    <tr>
                                        <td class="text-center fw-bold text-muted">{{ $pos->urutan }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $pos->nama_pos }}</div>
                                            <div class="small text-muted font-monospace">
                                                {{ $pos->kode_pos }}
                                                @if ($pos->pakai_prefix_perubahan)
                                                    <span class="badge bg-light text-secondary border ms-1">Prefix Perubahan</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge border px-2 py-1 rounded-pill fw-semibold {{ $bagianBadgeClasses[$pos->kelompok_bagian] ?? 'bg-secondary-subtle text-secondary border-secondary-subtle' }}">
                                                {{ $kelompokBagianLabels[$pos->kelompok_bagian] ?? $pos->kelompok_bagian }}
                                            </span>
                                        </td>
                                        <td class="small text-dark">
                                            {{ $sifatPosLabels[$pos->sifat_pos] ?? $pos->sifat_pos }}
                                        </td>
                                        <td>
                                            @if ($pos->coas->isNotEmpty())
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach ($pos->coas as $coa)
                                                        <span class="badge bg-light text-dark border fw-normal">
                                                            {{ $coa->kode }} - {{ $coa->nama }}
                                                            @if ((int) $coa->status_aktif !== 1)
                                                                <span class="text-muted">[Nonaktif]</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted small fst-italic">Belum ada COA dipetakan</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                                @if ($pos->is_active)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-semibold">
                                                        Aktif
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill fw-semibold">
                                                        Nonaktif
                                                    </span>
                                                @endif

                                                @if ($canUpdate)
                                                    <form method="POST" action="{{ route('pengaturan.arus-kas.toggle', $pos) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit"
                                                                class="btn btn-sm btn-outline-secondary p-1 py-0 rounded-circle border-0"
                                                                title="Ubah Status Aktif">
                                                            <i class="bi {{ $pos->is_active ? 'bi-toggle-on text-success fs-5' : 'bi-toggle-off text-muted fs-5' }}"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @if ($canUpdate)
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary py-0.5 px-2 btn-edit-pos"
                                                        data-id="{{ $pos->id }}"
                                                        data-kode-pos="{{ $pos->kode_pos }}"
                                                        data-nama-pos="{{ $pos->nama_pos }}"
                                                        data-kelompok-bagian="{{ $pos->kelompok_bagian }}"
                                                        data-sifat-pos="{{ $pos->sifat_pos }}"
                                                        data-pakai-prefix="{{ $pos->pakai_prefix_perubahan ? 1 : 0 }}"
                                                        data-urutan="{{ $pos->urutan }}"
                                                        data-is-active="{{ $pos->is_active ? 1 : 0 }}"
                                                        data-coa-ids='@json($pos->coas->pluck("id")->values())'
                                                        title="Edit Pos Arus Kas">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            @endif

                                            @if ($canDelete)
                                                <form method="POST"
                                                      action="{{ route('pengaturan.arus-kas.destroy', $pos) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus pos arus kas ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger py-0.5 px-2 ms-1"
                                                            title="Hapus Pos Arus Kas">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Data pos arus kas belum ada atau tidak sesuai filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Mode Struktur Laporan Pane -->
        <div id="viewModeStruktur" class="d-none flex-column gap-3">
            @foreach (\App\Models\SettingArusKas::KELOMPOK_BAGIAN as $bagianKey)
                @php
                    $sectionItems = $posList->where('kelompok_bagian', $bagianKey)->values();
                @endphp
                <div class="card border-0 shadow-sm bg-white" data-section-bagian="{{ $bagianKey }}">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-dark fs-6">{{ $kelompokBagianLabels[$bagianKey] ?? $bagianKey }}</h5>
                            <span class="badge border rounded-pill {{ $bagianBadgeClasses[$bagianKey] ?? 'bg-secondary-subtle text-secondary' }}">
                                {{ $sectionItems->count() }} Pos
                            </span>
                        </div>
                        @if ($canCreate)
                            <button type="button"
                                    class="btn btn-sm btn-success fw-semibold btn-open-create-pos"
                                    data-kelompok-bagian="{{ $bagianKey }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalPosArusKas">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Pos
                            </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-sm">
                                <thead class="table-light border-bottom">
                                    <tr class="text-nowrap small text-uppercase text-secondary">
                                        <th class="py-2 px-3 text-center" style="width: 110px;">Urutan</th>
                                        <th class="py-2 px-3" style="min-width: 220px;">Pos Arus Kas</th>
                                        <th class="py-2 px-3" style="width: 180px;">Sifat Pos</th>
                                        <th class="py-2 px-3" style="min-width: 240px;">Akun COA Terpetakan</th>
                                        <th class="py-2 px-3 text-center" style="width: 120px;">Status</th>
                                        <th class="py-2 px-3 text-center" style="width: 110px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="section-pos-tbody" data-kelompok-bagian="{{ $bagianKey }}">
                                    @forelse ($sectionItems as $pos)
                                        <tr data-pos-id="{{ $pos->id }}">
                                            <td class="text-center text-nowrap">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <span class="fw-bold text-muted me-1">{{ $pos->urutan }}</span>
                                                    @if ($canUpdate)
                                                        <button type="button"
                                                                class="btn btn-sm btn-light border py-0 px-1.5 btn-reorder-pos"
                                                                data-direction="up"
                                                                data-kelompok-bagian="{{ $bagianKey }}"
                                                                data-pos-id="{{ $pos->id }}"
                                                                @disabled($loop->first)
                                                                title="Naikkan Urutan">↑</button>
                                                        <button type="button"
                                                                class="btn btn-sm btn-light border py-0 px-1.5 btn-reorder-pos"
                                                                data-direction="down"
                                                                data-kelompok-bagian="{{ $bagianKey }}"
                                                                data-pos-id="{{ $pos->id }}"
                                                                @disabled($loop->last)
                                                                title="Turunkan Urutan">↓</button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $pos->nama_pos }}</div>
                                                <div class="small text-muted font-monospace">
                                                    {{ $pos->kode_pos }}
                                                    @if ($pos->pakai_prefix_perubahan)
                                                        <span class="badge bg-light text-secondary border ms-1">Prefix Perubahan</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="small">{{ $sifatPosLabels[$pos->sifat_pos] ?? $pos->sifat_pos }}</td>
                                            <td>
                                                @if ($pos->coas->isNotEmpty())
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach ($pos->coas as $coa)
                                                            <span class="badge bg-light text-dark border fw-normal">
                                                                {{ $coa->kode }} - {{ $coa->nama }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted small fst-italic">Belum ada COA dipetakan</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                                    @if ($pos->is_active)
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-semibold">Aktif</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill fw-semibold">Nonaktif</span>
                                                    @endif
                                                    @if ($canUpdate)
                                                        <form method="POST" action="{{ route('pengaturan.arus-kas.toggle', $pos) }}" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary p-1 py-0 rounded-circle border-0" title="Ubah Status Aktif">
                                                                <i class="bi {{ $pos->is_active ? 'bi-toggle-on text-success fs-5' : 'bi-toggle-off text-muted fs-5' }}"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                @if ($canUpdate)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary py-0.5 px-2 btn-edit-pos"
                                                            data-id="{{ $pos->id }}"
                                                            data-kode-pos="{{ $pos->kode_pos }}"
                                                            data-nama-pos="{{ $pos->nama_pos }}"
                                                            data-kelompok-bagian="{{ $pos->kelompok_bagian }}"
                                                            data-sifat-pos="{{ $pos->sifat_pos }}"
                                                            data-pakai-prefix="{{ $pos->pakai_prefix_perubahan ? 1 : 0 }}"
                                                            data-urutan="{{ $pos->urutan }}"
                                                            data-is-active="{{ $pos->is_active ? 1 : 0 }}"
                                                            data-coa-ids='@json($pos->coas->pluck("id")->values())'
                                                            title="Edit Pos Arus Kas">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                @endif
                                                @if ($canDelete)
                                                    <form method="POST"
                                                          action="{{ route('pengaturan.arus-kas.destroy', $pos) }}"
                                                          class="d-inline"
                                                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus pos arus kas ini?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2 ms-1" title="Hapus Pos Arus Kas">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted small">
                                                Belum ada pos pada bagian {{ $kelompokBagianLabels[$bagianKey] ?? $bagianKey }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Akun Dikecualikan Section in Struktur Mode -->
            <div class="card border-0 shadow-sm bg-white" data-section-bagian="pengecualian">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 fw-bold text-dark fs-6">Akun Dikecualikan</h5>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                            {{ $pengecualianList->count() }} Akun
                        </span>
                    </div>
                    @if ($canCreate)
                        <button type="button"
                                class="btn btn-sm btn-danger fw-semibold btn-open-create-pengecualian"
                                data-bs-toggle="modal"
                                data-bs-target="#modalPengecualianArusKas">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Pengecualian
                        </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase text-secondary">
                                    <th class="py-2 px-3 text-center" style="width: 55px;">No</th>
                                    <th class="py-2 px-3">Akun COA</th>
                                    <th class="py-2 px-3">Tipe COA</th>
                                    <th class="py-2 px-3">Alasan Pengecualian</th>
                                    <th class="py-2 px-3 text-center" style="width: 95px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pengecualianList as $item)
                                    <tr>
                                        <td class="text-center fw-bold text-muted">{{ $loop->iteration }}</td>
                                        <td class="fw-semibold text-dark">
                                            {{ $item->coa ? "{$item->coa->kode} - {$item->coa->nama}" : "COA #{$item->coa_id}" }}
                                        </td>
                                        <td class="text-muted">{{ $item->coa?->tipe_coa ?? '-' }}</td>
                                        <td class="text-muted">{{ $item->alasan ?? '-' }}</td>
                                        <td class="text-center text-nowrap">
                                            @if ($canDelete)
                                                <form method="POST"
                                                      action="{{ route('pengaturan.arus-kas.pengecualian.destroy', $item) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Hapus akun ini dari daftar pengecualian?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0.5 px-2" title="Hapus Pengecualian">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            Belum ada akun yang dikecualikan.
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

    @include('pengaturan.arus-kas.partials._modals')
@endsection

@include('pengaturan.arus-kas.partials._scripts')
