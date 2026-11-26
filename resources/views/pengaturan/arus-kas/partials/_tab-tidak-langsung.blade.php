@php
    $searchLower = mb_strtolower($search);

    $filteredPosList = $posList
        ->when(
            $activeBagian !== 'semua' && $activeBagian !== 'pengecualian',
            fn ($collection) => $collection->where('kelompok_bagian', $activeBagian)
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

{{-- Top Control Bar: Sub-bar Mode Info/Struktur Controls & View Mode Switcher --}}
<div id="tidakLangsungControlBar" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div id="subBarModeTab" class="small text-muted">
            <i class="bi bi-table me-1"></i> Kelola daftar pos laporan arus kas per bagian, pemetaan akun COA, dan akun dikecualikan.
        </div>
        <div id="subBarModeStruktur" class="d-none">
            <div class="btn-group bg-white" role="group" aria-label="Kontrol Struktur Laporan">
                <button type="button" class="btn btn-sm fw-bold btn-light border" id="btnExpandAllSections">
                    <i class="bi bi-arrows-expand me-1"></i> Buka Semua
                </button>
                <button type="button" class="btn btn-sm fw-bold btn-light border" id="btnCollapseAllSections">
                    <i class="bi bi-arrows-collapse me-1"></i> Tutup Semua
                </button>
            </div>
        </div>
    </div>

    <div class="btn-group btn-group-sm flex-nowrap flex-shrink-0 ms-auto" role="group" aria-label="Mode Tampilan Pengaturan Arus Kas">
        <button type="button"
                id="btnModeTab"
                class="btn btn-primary fw-semibold active text-nowrap"
                data-view-mode="tab">
            <i class="bi bi-table me-1"></i> Mode Tab
        </button>
        <button type="button"
                id="btnModeStruktur"
                class="btn btn-outline-primary fw-semibold text-nowrap"
                data-view-mode="struktur">
            <i class="bi bi-list-nested me-1"></i> Mode Struktur Laporan
        </button>
    </div>
</div>

<!-- Mode Tab Pane -->
<div id="viewModeTab">
    <!-- Filter & Action Toolbar -->
    <div class="bg-light p-3 rounded-3 border mb-3" id="filterToolbarTidakLangsung">
        <form method="GET" action="{{ route('pengaturan.arus-kas.index') }}" id="formFilterTidakLangsung">
            <input type="hidden" name="metode" value="tidak_langsung">

            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4 col-lg-3">
                    <select name="bagian" id="selectBagianTidakLangsung" class="form-select form-select-sm">
                        <option value="semua" @selected($activeBagian === 'semua' || empty($activeBagian))>
                            Semua Pos ({{ $tidakLangsungTabCounts['semua'] }})
                        </option>
                        <option value="penyesuaian_laba" @selected($activeBagian === 'penyesuaian_laba')>
                            {{ $kelompokBagianLabels['penyesuaian_laba'] ?? 'Penyesuaian Laba' }} ({{ $tidakLangsungTabCounts['penyesuaian_laba'] }})
                        </option>
                        <option value="modal_kerja" @selected($activeBagian === 'modal_kerja')>
                            {{ $kelompokBagianLabels['modal_kerja'] ?? 'Modal Kerja Operasi' }} ({{ $tidakLangsungTabCounts['modal_kerja'] }})
                        </option>
                        <option value="investasi" @selected($activeBagian === 'investasi')>
                            {{ $kelompokBagianLabels['investasi'] ?? 'Aktivitas Investasi' }} ({{ $tidakLangsungTabCounts['investasi'] }})
                        </option>
                        <option value="pendanaan" @selected($activeBagian === 'pendanaan')>
                            {{ $kelompokBagianLabels['pendanaan'] ?? 'Aktivitas Pendanaan' }} ({{ $tidakLangsungTabCounts['pendanaan'] }})
                        </option>
                        <option value="pengecualian" @selected($activeBagian === 'pengecualian')>
                            Akun Dikecualikan ({{ $tidakLangsungTabCounts['pengecualian'] }})
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
                               id="searchTidakLangsungInput"
                               value="{{ $search }}"
                               class="form-control border-start-0 {{ $search !== '' ? 'border-end-0' : '' }}"
                               placeholder="Cari nama pos, kode pos, atau kode/nama akun COA... (Tekan Enter)">
                        @if ($search !== '')
                            <a href="{{ route('pengaturan.arus-kas.index', array_filter(['metode' => 'tidak_langsung', 'bagian' => $activeBagian !== 'semua' ? $activeBagian : null])) }}"
                               class="input-group-text bg-white text-muted border-start-0 text-decoration-none"
                               title="Reset pencarian"
                               id="btnResetSearchTidakLangsung">
                                <i class="bi bi-x-circle-fill text-secondary"></i>
                            </a>
                        @endif
                    </div>
                </div>

                @if ($canCreate)
                    <div class="col-12 col-md-auto ms-auto">
                        @if ($activeBagian === 'pengecualian')
                            <button type="button"
                                    class="btn btn-sm btn-danger fw-bold text-nowrap btn-open-create-pengecualian w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalPengecualianArusKas">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Pengecualian
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-sm btn-success fw-bold text-nowrap btn-open-create-pos w-100"
                                    data-kelompok-bagian="{{ in_array($activeBagian, \App\Models\SettingArusKas::KELOMPOK_BAGIAN, true) ? $activeBagian : 'penyesuaian_laba' }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalPosArusKas">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Pos
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </form>
    </div>

    @if ($activeBagian === 'pengecualian')
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
                                          action="{{ route('pengaturan.arus-kas.tidak-langsung.pengecualian.destroy', $item) }}"
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
                                @if ($search !== '')
                                    Tidak ada akun dikecualikan yang cocok dengan pencarian.
                                @else
                                    Belum ada akun yang dikecualikan.
                                @endif
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
                        <th class="py-2.5 px-3" style="min-width: 250px;">Pos Arus Kas</th>
                        <th class="py-2.5 px-3" style="width: 170px;">Bagian</th>
                        <th class="py-2.5 px-3" style="width: 180px;">Sifat Pos</th>
                        <th class="py-2.5 px-3 text-center" style="width: 110px;">Status</th>
                        <th class="py-2.5 px-3 text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredPosList as $pos)
                        <tr>
                            <td class="text-center fw-bold text-muted">{{ $pos->urutan }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $pos->nama_pos }}</div>
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    <span class="small text-muted font-monospace">{{ $pos->kode_pos }}</span>
                                    @if ($pos->pakai_prefix_perubahan)
                                        <span class="badge bg-light text-secondary border">Prefix Perubahan</span>
                                    @endif
                                    @if ($pos->coas->isNotEmpty())
                                        @php
                                            $coasJson = json_encode($pos->coas->map(fn ($c) => [
                                                'kode' => $c->kode,
                                                'nama' => $c->nama,
                                                'status_aktif' => (int) $c->status_aktif,
                                                'label' => "{$c->kode} - {$c->nama}",
                                            ])->values());
                                        @endphp
                                        <button type="button"
                                                class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-show-coas text-nowrap"
                                                data-pos-name="{{ $pos->nama_pos }}"
                                                data-pos-kode="{{ $pos->kode_pos }}"
                                                data-coas="{{ $coasJson }}"
                                                title="Lihat Daftar COA Terpetakan">
                                            <i class="bi bi-diagram-2 me-1"></i> {{ $pos->coas->count() }} Akun COA
                                        </button>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0 px-1.5 rounded-pill small">
                                            <i class="bi bi-exclamation-circle me-1"></i> 0 Akun
                                        </span>
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
                            <td class="text-center">
                                @if ($pos->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                        Aktif
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                        Nonaktif
                                    </span>
                                @endif
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
                                          action="{{ route('pengaturan.arus-kas.tidak-langsung.destroy', $pos) }}"
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
                            <td colspan="6" class="text-center py-5 text-muted">
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

<!-- Mode Struktur Laporan Pane -->
<div id="viewModeStruktur" class="d-none flex-column gap-3">

    @foreach (\App\Models\SettingArusKas::KELOMPOK_BAGIAN as $bagianKey)
        @php
            $sectionItems = $posList->where('kelompok_bagian', $bagianKey)->values();
        @endphp
        <div class="card border-0 shadow-sm bg-white" data-section-bagian="{{ $bagianKey }}">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 section-header"
                 data-bs-toggle="collapse"
                 data-bs-target="#collapseBagian-{{ $bagianKey }}"
                 aria-expanded="true"
                 aria-controls="collapseBagian-{{ $bagianKey }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-chevron-down text-muted section-collapse-chevron fs-6"></i>
                    <h5 class="mb-0 fw-bold text-dark fs-6">{{ $kelompokBagianLabels[$bagianKey] ?? $bagianKey }}</h5>
                    <span class="badge border rounded-pill {{ $bagianBadgeClasses[$bagianKey] ?? 'bg-secondary-subtle text-secondary' }}">
                        {{ $sectionItems->count() }} Pos
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
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
            </div>
            <div class="collapse show" id="collapseBagian-{{ $bagianKey }}">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-light border-bottom">
                                <tr class="text-nowrap small text-uppercase text-secondary">
                                    <th class="py-2 px-3 text-center" style="width: 110px;">Urutan</th>
                                    <th class="py-2 px-3" style="min-width: 250px;">Pos Arus Kas</th>
                                    <th class="py-2 px-3" style="width: 180px;">Sifat Pos</th>
                                    <th class="py-2 px-3 text-center" style="width: 100px;">Status</th>
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
                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                <span class="small text-muted font-monospace">{{ $pos->kode_pos }}</span>
                                                @if ($pos->pakai_prefix_perubahan)
                                                    <span class="badge bg-light text-secondary border ms-1">Prefix Perubahan</span>
                                                @endif
                                                @if ($pos->coas->isNotEmpty())
                                                    @php
                                                        $coasJsonStruktur = json_encode($pos->coas->map(fn ($c) => [
                                                            'kode' => $c->kode,
                                                            'nama' => $c->nama,
                                                            'status_aktif' => (int) $c->status_aktif,
                                                            'label' => "{$c->kode} - {$c->nama}",
                                                        ])->values());
                                                    @endphp
                                                    <button type="button"
                                                            class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-show-coas text-nowrap"
                                                            data-pos-name="{{ $pos->nama_pos }}"
                                                            data-pos-kode="{{ $pos->kode_pos }}"
                                                            data-coas="{{ $coasJsonStruktur }}"
                                                            title="Lihat Daftar COA Terpetakan">
                                                        <i class="bi bi-diagram-2 me-1"></i> {{ $pos->coas->count() }} Akun COA
                                                    </button>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0 px-1.5 rounded-pill small">
                                                        <i class="bi bi-exclamation-circle me-1"></i> 0 Akun
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="small">{{ $sifatPosLabels[$pos->sifat_pos] ?? $pos->sifat_pos }}</td>
                                        <td class="text-center">
                                            @if ($pos->is_active)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">Aktif</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold">Nonaktif</span>
                                            @endif
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
                                                      action="{{ route('pengaturan.arus-kas.tidak-langsung.destroy', $pos) }}"
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
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            Belum ada pos pada bagian {{ $kelompokBagianLabels[$bagianKey] ?? $bagianKey }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Akun Dikecualikan Section in Struktur Mode -->
    <div class="card border-0 shadow-sm bg-white" data-section-bagian="pengecualian">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 section-header"
             data-bs-toggle="collapse"
             data-bs-target="#collapseBagian-pengecualian"
             aria-expanded="true"
             aria-controls="collapseBagian-pengecualian">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-chevron-down text-muted section-collapse-chevron fs-6"></i>
                <h5 class="mb-0 fw-bold text-dark fs-6">Akun Dikecualikan</h5>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                    {{ $pengecualianList->count() }} Akun
                </span>
            </div>
            <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
                @if ($canCreate)
                    <button type="button"
                            class="btn btn-sm btn-danger fw-semibold btn-open-create-pengecualian"
                            data-bs-toggle="modal"
                            data-bs-target="#modalPengecualianArusKas">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Pengecualian
                    </button>
                @endif
            </div>
        </div>
        <div class="collapse show" id="collapseBagian-pengecualian">
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
                                                  action="{{ route('pengaturan.arus-kas.tidak-langsung.pengecualian.destroy', $item) }}"
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
