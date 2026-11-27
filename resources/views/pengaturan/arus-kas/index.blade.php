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
    .section-header {
        cursor: pointer;
        user-select: none;
    }
    .section-header[aria-expanded="false"] .section-collapse-chevron {
        transform: rotate(-90deg);
    }
    .section-header[aria-expanded="true"] .section-collapse-chevron {
        transform: rotate(0deg);
    }
    .section-collapse-chevron {
        transition: transform 0.2s ease-in-out;
    }
    .btn-xs {
        padding: 0.15rem 0.45rem;
        font-size: 0.75rem;
        line-height: 1.25;
    }
    .modal-dialog-scrollable .modal-content {
        max-height: calc(100vh - 3.5rem);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .modal-dialog-scrollable .modal-header,
    .modal-dialog-scrollable .modal-footer {
        flex-shrink: 0;
    }
    .modal-dialog-scrollable .modal-body {
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        min-height: 0;
    }
    @media (max-width: 576px) {
        .modal-dialog-scrollable .modal-content {
            max-height: calc(100dvh - 1.5rem);
        }
    }
</style>
@endpush

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('pengaturan.arus-kas', 'create') ?? false;
        $canUpdate = $user?->hasModuleAccess('pengaturan.arus-kas', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('pengaturan.arus-kas', 'delete') ?? false;
        $canLaporanKeuangan = $user?->hasModuleAccess('laporan.keuangan', 'view') ?? false;
    @endphp

    <div class="d-flex flex-column gap-3">
        <!-- Header Card -->
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center justify-content-between g-3">
                    <div class="col-12 col-lg-7">
                        <div class="d-flex align-items-center gap-3">
                            <a href="{{ route('home') }}"
                               class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs flex-shrink-0"
                               style="width: 38px; height: 38px;"
                               title="Kembali ke Dashboard">
                                <i class="bi bi-arrow-left fs-5"></i>
                            </a>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <h4 class="mb-0 fw-bold text-dark">Pengaturan Arus Kas</h4>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                        Master &amp; Pemetaan
                                    </span>
                                </div>
                                <p class="text-muted mb-0 small mt-1" style="max-width: 580px;">
                                    Standardisasi master pos kelompok arus kas (metode langsung) dan pemetaan pos laporan arus kas per bagian akun COA serta daftar pengecualian (metode tidak langsung).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto ms-lg-auto">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if ($canLaporanKeuangan)
                                @if ($activeMetode === 'langsung')
                                    <a href="{{ route('laporan.keuangan.arus-kas') }}"
                                       id="btnLaporanArusKas"
                                       class="btn btn-sm btn-outline-primary fw-semibold text-nowrap d-inline-flex align-items-center gap-1.5 shadow-xs"
                                       title="Buka Laporan Arus Kas Metode Langsung">
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                        <span>Laporan Arus Kas</span>
                                    </a>
                                @else
                                    <a href="{{ route('laporan.keuangan.arus-kas-tidak-langsung') }}"
                                       id="btnLaporanArusKas"
                                       class="btn btn-sm btn-outline-primary fw-semibold text-nowrap d-inline-flex align-items-center gap-1.5 shadow-xs"
                                       title="Buka Laporan Arus Kas Metode Tidak Langsung">
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                        <span>Laporan Arus Kas</span>
                                    </a>
                                @endif
                            @endif

                            <div class="dropdown">
                                <button type="button"
                                        id="btnBantuanDropdown"
                                        class="btn btn-sm btn-outline-success fw-semibold dropdown-toggle d-inline-flex align-items-center gap-1.5 shadow-xs"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        title="Pusat Panduan &amp; Bantuan Interaktif">
                                    <i class="bi bi-lightbulb text-success"></i>
                                    <span>Bantuan &amp; Panduan</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="btnBantuanDropdown">
                                    <li>
                                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2" id="btnStartTour">
                                            <i class="bi bi-play-circle-fill text-primary fs-6"></i>
                                            <div>
                                                <div class="fw-semibold">Mulai Tur Panduan</div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">Sorot &amp; jelajahi fitur halaman ini</small>
                                            </div>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                id="btnOpenModalPanduan"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalPanduanArusKas">
                                            <i class="bi bi-book-half text-success fs-6"></i>
                                            <div>
                                                <div class="fw-semibold">Panduan &amp; Glosarium</div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">Struktur pos, COA &amp; aturan eliminasi</small>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.flash-message')
        @include('partials.validation-errors')

        <!-- Unmapped Mutated Neraca Warning Banner (Khusus Metode Tidak Langsung) -->
        @if ($activeMetode === 'tidak_langsung' && $unmappedAccounts->isNotEmpty())
            <div id="bannerUnmappedTidakLangsung" class="alert alert-warning border-warning-subtle shadow-sm mb-0" role="alert">
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

        <!-- Unmapped Cash Counterpart Warning Banner (Khusus Metode Langsung) -->
        @if ($activeMetode === 'langsung' && $unmappedLangsungAccounts->isNotEmpty())
            <div id="bannerUnmappedLangsung" class="alert alert-warning border-warning-subtle shadow-sm mb-0" role="alert">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning-emphasis mt-0.5"></i>
                        <div>
                            <div class="fw-bold text-dark">
                                Terdapat {{ $unmappedLangsungAccounts->count() }} akun lawan transaksi Kas/Bank tahun berjalan yang belum dipetakan.
                            </div>
                            <div class="small text-muted">
                                Akun berikut memiliki mutasi pada buku besar berpasangan dengan akun Kas/Bank namun belum dipetakan ke pos kelompok arus kas langsung.
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-warning text-dark fw-semibold text-nowrap"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#unmappedLangsungAccountsCollapse"
                            aria-expanded="false"
                            aria-controls="unmappedLangsungAccountsCollapse">
                        <i class="bi bi-chevron-down me-1"></i> Lihat Daftar Akun
                    </button>
                </div>

                <div class="collapse mt-3" id="unmappedLangsungAccountsCollapse">
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
                                @foreach ($unmappedLangsungAccounts as $account)
                                    <tr>
                                        <td class="px-3 py-2 fw-semibold text-dark">
                                            {{ $account['kode'] }} - {{ $account['nama'] }}
                                            @if ((int) $account['status_aktif'] !== 1)
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">Nonaktif</span>
                                            @endif
                                            @if (($account['trx_count'] ?? 0) > 0)
                                                <span class="text-muted fw-normal ms-1">({{ $account['trx_count'] }} trx)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-muted">{{ $account['tipe_coa'] !== '' ? $account['tipe_coa'] : '-' }}</td>
                                        <td class="px-3 py-2 text-end font-monospace">{{ number_format((float) $account['debit'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-end font-monospace">{{ number_format((float) $account['kredit'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-end font-monospace fw-semibold">{{ number_format((float) $account['dampak_kas'], 2, ',', '.') }}</td>
                                        @if ($canCreate || $canUpdate)
                                            <td class="px-3 py-2 text-center text-nowrap">
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-primary py-0 px-2 btn-quick-map-langsung"
                                                        data-coa-id="{{ $account['coa_id'] }}"
                                                        data-coa-label="{{ $account['kode'] }} - {{ $account['nama'] }}">
                                                    <i class="bi bi-plus-circle me-1"></i> Petakan
                                                </button>
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

        <!-- Card Utama: 2 Tab Utama -->
        <div class="card border-0 shadow-sm bg-white">
            <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-3 px-md-4">
                <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-x-auto overflow-y-hidden custom-scrollbar border-bottom-0 pb-1" id="mainArusKasTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a id="tabLinkLangsung"
                           class="nav-link fw-semibold px-3 py-2.5 text-nowrap {{ $activeMetode === 'langsung' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', ['metode' => 'langsung']) }}">
                            <i class="bi bi-arrow-down-up me-1.5"></i> Pengaturan Arus Kas Metode Langsung
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1.5">{{ $referensiTabCounts['semua'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a id="tabLinkTidakLangsung"
                           class="nav-link fw-semibold px-3 py-2.5 text-nowrap {{ $activeMetode === 'tidak_langsung' ? 'active text-primary' : 'text-muted' }}"
                           href="{{ route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']) }}">
                            <i class="bi bi-diagram-3 me-1.5"></i> Pengaturan Arus Kas Metode Tidak Langsung
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1.5">{{ $tidakLangsungTabCounts['semua'] + $tidakLangsungTabCounts['pengecualian'] }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3 p-md-4">
                @if ($activeMetode === 'langsung')
                    @include('pengaturan.arus-kas.partials._tab-langsung')
                @else
                    @include('pengaturan.arus-kas.partials._tab-tidak-langsung')
                @endif
            </div>
        </div>
    </div>

    @include('pengaturan.arus-kas.partials._modal-panduan')
    @include('pengaturan.arus-kas.partials._modals')
@endsection

@include('pengaturan.arus-kas.partials._scripts')
