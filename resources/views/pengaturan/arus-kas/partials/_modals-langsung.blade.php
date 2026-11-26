@php
    $leafCoas = $langsungCoaOptionsData['leafCoas'] ?? collect();
    $parentCoas = $langsungCoaOptionsData['parentCoas'] ?? collect();
    $mappedByCoaId = $langsungCoaOptionsData['mappedByCoaId'] ?? [];
    $groupedActiveReferensi = $activeReferensiOptions->groupBy('aktivitas');

    $oldModalTarget = old('_modal_target');
    $isOldTambahReferensi = $errors->any() && $oldModalTarget === 'modalTambahReferensi';
    $isOldEditReferensi = $errors->any() && $oldModalTarget === 'modalEditReferensi';
    $isOldQuickMap = $errors->any() && $oldModalTarget === 'modalQuickMapLangsung';

    $oldReferensiId = old('_referensi_id');
    $oldReferensiAktivitas = old('_referensi_aktivitas');
    $oldReferensiGroupKey = old('_referensi_group_key');
    $editReferensiAction = $oldReferensiId
        ? route('pengaturan.arus-kas.langsung.update', $oldReferensiId)
        : '';

    $oldCoaIds = array_values(array_filter(
        (array) old('coa_ids', []),
        fn ($val) => is_numeric($val)
    ));
    $oldCoaIds = array_map('intval', $oldCoaIds);
@endphp

@if ($canCreate)
    <!-- Modal Tambah Pos Referensi (Metode Langsung) -->
    <div class="modal fade" id="modalTambahReferensi" tabindex="-1" aria-labelledby="modalTambahReferensiLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('pengaturan.arus-kas.langsung.store') }}" id="formTambahReferensi">
                    @csrf
                    <input type="hidden" name="_modal_target" value="modalTambahReferensi">

                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalTambahReferensiLabel">
                            <i class="bi bi-plus-circle-fill text-success me-1"></i> Tambah Pos Referensi Arus Kas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        @if ($isOldTambahReferensi)
                            <div id="modalTambahReferensiValidationAlert" class="alert alert-danger py-2 px-3 small mb-3">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="createAktivitas" class="form-label small fw-semibold">Aktivitas <span class="text-danger">*</span></label>
                                <select name="aktivitas" id="createAktivitas" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Aktivitas --</option>
                                    <option value="operasi" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'operasi')>Operasi</option>
                                    <option value="investasi" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'investasi')>Investasi</option>
                                    <option value="pendanaan" @selected(old('aktivitas', in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) ? $aktivitas : '') === 'pendanaan')>Pendanaan</option>
                                </select>
                                <div class="form-text small">Kategori aktivitas arus kas standar.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="createNama" class="form-label small fw-semibold">Nama Kelompok Arus Kas <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="createNama" class="form-control form-control-sm" required maxlength="150" placeholder="Contoh: Penerimaan dari Pelanggan" value="{{ old('nama') }}">
                                <div class="form-text small">Maksimal 150 karakter. Spasi berlebih dirapikan.</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="status_aktif" value="0">
                                <input class="form-check-input" type="checkbox" name="status_aktif" value="1" id="createStatusAktif" @checked(old('status_aktif', '1') == '1')>
                                <label class="form-check-label small fw-semibold" for="createStatusAktif">Status Aktif</label>
                            </div>
                            <div class="form-text small">Pos yang aktif dapat dipilih saat konfigurasi pemetaan akun COA.</div>
                        </div>

                        {{-- Panel Pemetaan Akun COA --}}
                        <div class="border rounded-3 p-3 bg-light mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">
                                    <i class="bi bi-diagram-3 me-1 text-primary"></i> Pemetaan Akun COA Terkait (Leaf)
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" id="createCoaCounterBadge">
                                    0 akun dipilih
                                </span>
                            </div>

                            {{-- Sub-Akun Expander via Parent COA --}}
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-12 col-md">
                                    <select id="createParentCoaSelect" class="form-select form-select-sm" style="width: 100%;">
                                        <option value="">-- Pilih Akun Induk COA untuk Expand Sub-Akun --</option>
                                        @foreach ($parentCoas as $pCoa)
                                            <option value="{{ $pCoa->id }}">{{ $pCoa->kode }} - {{ $pCoa->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-auto">
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btnCreateAddParentLeaves">
                                        <i class="bi bi-plus me-1"></i> + Sub-Akun
                                    </button>
                                </div>
                            </div>
                            <div id="createParentLeavesFeedback" class="small mb-2 d-none"></div>

                            {{-- Quick Filter Input --}}
                            <div class="mb-2">
                                <input type="text" id="filterCreateCoaOptionsInput" class="form-control form-control-sm" placeholder="Ketik untuk memfilter akun COA...">
                            </div>

                            {{-- Sentinel hidden input directly before multi-select --}}
                            <input type="hidden" name="coa_ids" value="">
                            <select name="coa_ids[]" id="createCoaIdsSelect" class="form-select form-select-sm" multiple size="7">
                                @foreach ($leafCoas as $coa)
                                    @php
                                        $coaId = (int) $coa->id;
                                        $isMapped = isset($mappedByCoaId[$coaId]);
                                        $mappingInfo = $isMapped ? $mappedByCoaId[$coaId] : null;
                                        $currentGroupKey = $isMapped ? ($mappingInfo['aktivitas'] . '||' . $mappingInfo['kelompok']) : '';
                                        $currentLabel = $isMapped ? ' (Saat ini: [' . ucfirst($mappingInfo['aktivitas']) . '] ' . $mappingInfo['kelompok'] . ')' : '';
                                        $isSelected = in_array($coaId, $oldCoaIds, true);
                                    @endphp
                                    <option value="{{ $coaId }}"
                                            data-current-group-key="{{ $currentGroupKey }}"
                                            data-current-label="{{ $currentLabel }}"
                                            data-kode="{{ $coa->kode }}"
                                            data-nama="{{ $coa->nama }}"
                                            data-base-label="{{ $coa->kode }} - {{ $coa->nama }}{{ (int) $coa->status_aktif !== 1 ? ' [Nonaktif]' : '' }}"
                                            @selected($isSelected)>
                                        {{ $coa->kode }} - {{ $coa->nama }}{{ (int) $coa->status_aktif !== 1 ? ' [Nonaktif]' : '' }}{{ $currentLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted mt-1">
                                Gunakan Ctrl/Cmd + Klik untuk memilih banyak akun sekaligus. Akun yang dipilih akan otomatis terpetakan ke pos ini.
                            </div>
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
@endif

@if ($canUpdate)
    <!-- Modal Edit Pos Referensi (Metode Langsung) -->
    <div class="modal fade" id="modalEditReferensi" tabindex="-1" aria-labelledby="modalEditReferensiLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow border-0">
                <form id="formEditReferensi" method="POST" action="{{ $editReferensiAction }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal_target" value="modalEditReferensi">
                    <input type="hidden" name="_referensi_id" id="editReferensiId" value="{{ $oldReferensiId }}">
                    <input type="hidden" name="_referensi_aktivitas" id="editReferensiAktivitas" value="{{ $oldReferensiAktivitas }}">
                    <input type="hidden" name="_referensi_group_key" id="editOriginalGroupKey" value="{{ $oldReferensiGroupKey }}">

                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalEditReferensiLabel">
                            <i class="bi bi-pencil-square text-primary me-1"></i> Edit Pos Referensi Arus Kas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        @if ($isOldEditReferensi)
                            <div id="modalEditReferensiValidationAlert" class="alert alert-danger py-2 px-3 small mb-3">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold">Aktivitas</label>
                                <input type="text" id="editAktivitasLabel" class="form-control form-control-sm bg-light text-uppercase fw-semibold" value="{{ $oldReferensiAktivitas }}" readonly disabled>
                                <div class="form-text small text-muted">Aktivitas tidak dapat diubah (immutabilitas).</div>
                            </div>

                            <div class="col-12 col-md-5">
                                <label for="editNama" class="form-label small fw-semibold">Nama Kelompok Arus Kas <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="editNama" class="form-control form-control-sm" required maxlength="150" placeholder="Contoh: Penerimaan dari Pelanggan" value="{{ old('nama') }}">
                                <div class="form-text small">Perubahan nama otomatis meng-cascade akun COA terkait.</div>
                            </div>

                            <div class="col-12 col-md-3">
                                <label for="editStatusAktif" class="form-label small fw-semibold">Status Aktif <span class="text-danger">*</span></label>
                                <select name="status_aktif" id="editStatusAktif" class="form-select form-select-sm" required>
                                    <option value="1" @selected(old('status_aktif', '1') == '1')>Aktif</option>
                                    <option value="0" @selected(old('status_aktif') == '0')>Nonaktif</option>
                                </select>
                                <div class="form-text small">Jika nonaktif, pos tidak dapat dipilih lagi.</div>
                            </div>
                        </div>

                        {{-- Panel Pemetaan Akun COA --}}
                        <div class="border rounded-3 p-3 bg-light mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">
                                    <i class="bi bi-diagram-3 me-1 text-primary"></i> Pemetaan Akun COA Terkait (Leaf)
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" id="editCoaCounterBadge">
                                    0 akun dipilih
                                </span>
                            </div>

                            {{-- Sub-Akun Expander via Parent COA --}}
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-12 col-md">
                                    <select id="editParentCoaSelect" class="form-select form-select-sm" style="width: 100%;">
                                        <option value="">-- Pilih Akun Induk COA untuk Expand Sub-Akun --</option>
                                        @foreach ($parentCoas as $pCoa)
                                            <option value="{{ $pCoa->id }}">{{ $pCoa->kode }} - {{ $pCoa->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-auto">
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btnEditAddParentLeaves">
                                        <i class="bi bi-plus me-1"></i> + Sub-Akun
                                    </button>
                                </div>
                            </div>
                            <div id="editParentLeavesFeedback" class="small mb-2 d-none"></div>

                            {{-- Quick Filter Input --}}
                            <div class="mb-2">
                                <input type="text" id="filterEditCoaOptionsInput" class="form-control form-control-sm" placeholder="Ketik untuk memfilter akun COA...">
                            </div>

                            {{-- Sentinel hidden input directly before multi-select --}}
                            <input type="hidden" name="coa_ids" value="">
                            <select name="coa_ids[]" id="editCoaIdsSelect" class="form-select form-select-sm" multiple size="7">
                                @foreach ($leafCoas as $coa)
                                    @php
                                        $coaId = (int) $coa->id;
                                        $isMapped = isset($mappedByCoaId[$coaId]);
                                        $mappingInfo = $isMapped ? $mappedByCoaId[$coaId] : null;
                                        $currentGroupKey = $isMapped ? ($mappingInfo['aktivitas'] . '||' . $mappingInfo['kelompok']) : '';
                                        $currentLabel = $isMapped ? ' (Saat ini: [' . ucfirst($mappingInfo['aktivitas']) . '] ' . $mappingInfo['kelompok'] . ')' : '';
                                        $isSelected = in_array($coaId, $oldCoaIds, true);
                                    @endphp
                                    <option value="{{ $coaId }}"
                                            data-current-group-key="{{ $currentGroupKey }}"
                                            data-current-label="{{ $currentLabel }}"
                                            data-kode="{{ $coa->kode }}"
                                            data-nama="{{ $coa->nama }}"
                                            data-base-label="{{ $coa->kode }} - {{ $coa->nama }}{{ (int) $coa->status_aktif !== 1 ? ' [Nonaktif]' : '' }}"
                                            @selected($isSelected)>
                                        {{ $coa->kode }} - {{ $coa->nama }}{{ (int) $coa->status_aktif !== 1 ? ' [Nonaktif]' : '' }}{{ $currentLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted mt-1">
                                Gunakan Ctrl/Cmd + Klik untuk memilih banyak akun sekaligus. Mengosongkan pilihan akan menghapus pemetaan akun dari pos ini.
                            </div>
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
@endif

@if ($canUpdate || $canCreate)
    <!-- Modal Quick Map (Metode Langsung) -->
    <div class="modal fade" id="modalQuickMapLangsung" tabindex="-1" aria-labelledby="modalQuickMapLangsungLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form id="formQuickMapLangsung" method="POST" action="{{ route('pengaturan.arus-kas.langsung.quick-map') }}">
                    @csrf
                    <input type="hidden" name="_modal_target" value="modalQuickMapLangsung">
                    <input type="hidden" name="coa_id" id="quickMapCoaId" value="{{ old('coa_id') }}">

                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalQuickMapLangsungLabel">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Petakan Cepat Akun ke Pos Arus Kas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        @if ($isOldQuickMap)
                            <div id="modalQuickMapValidationAlert" class="alert alert-danger py-2 px-3 small mb-3">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Akun COA</label>
                            <div class="fw-bold fs-6 text-primary p-2 bg-light rounded border" id="quickMapCoaLabel">
                                @php
                                    $oldQuickMapCoa = old('coa_id') ? $leafCoas->firstWhere('id', (int) old('coa_id')) : null;
                                @endphp
                                {{ $oldQuickMapCoa ? ($oldQuickMapCoa->kode . ' - ' . $oldQuickMapCoa->nama) : (old('coa_id') ? ('COA #' . old('coa_id')) : '-') }}
                            </div>
                        </div>

                        @if ($canUpdate)
                            <div class="mb-3">
                                <label for="quickMapReferensiSelect" class="form-label small fw-semibold">Pilih Pos Arus Kas Tujuan <span class="text-danger">*</span></label>
                                <select name="referensi_id" id="quickMapReferensiSelect" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Pos Referensi --</option>
                                    @foreach (['operasi' => 'Aktivitas Operasi', 'investasi' => 'Aktivitas Investasi', 'pendanaan' => 'Aktivitas Pendanaan'] as $aktKey => $aktLabel)
                                        @if (isset($groupedActiveReferensi[$aktKey]) && $groupedActiveReferensi[$aktKey]->isNotEmpty())
                                            <optgroup label="{{ $aktLabel }}">
                                                @foreach ($groupedActiveReferensi[$aktKey] as $refOpt)
                                                    <option value="{{ $refOpt->id }}" @selected(old('referensi_id') == $refOpt->id)>
                                                        {{ $refOpt->nama }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="form-text small">Pilih pos aktif tujuan untuk akun ini.</div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer bg-light px-4 py-2 border-top d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <div class="d-flex gap-2">
                            @if ($canCreate)
                                <button type="button" class="btn btn-sm btn-outline-success fw-semibold" id="btnQuickMapCreatePosBaru">
                                    <i class="bi bi-plus-circle me-1"></i> Buat Pos Baru dengan Akun Ini
                                </button>
                            @endif
                            @if ($canUpdate)
                                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                                    <i class="bi bi-check-circle me-1"></i> Simpan Pemetaan
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Modal Detail COA Terpetakan (Metode Langsung) - Selalu dirender untuk semua level hak akses -->
<div class="modal fade" id="modalDetailCoaLangsung" tabindex="-1" aria-labelledby="modalDetailCoaLangsungLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalDetailCoaLangsungLabel">
                        <i class="bi bi-diagram-3-fill text-primary me-1"></i> Detail Akun COA Terpetakan
                    </h5>
                    <div class="small text-muted" id="modalDetailLangsungSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <h6 class="small fw-bold text-uppercase text-muted tracking-wider mb-2">Akun Detail (Leaf) Terpetakan</h6>
                    <ul class="list-group list-group-flush border rounded-3 small" id="modalDetailLangsungLeafList"></ul>
                </div>

                <div id="modalDetailLangsungParentSection" class="mt-3 d-none">
                    <h6 class="small fw-bold text-uppercase text-secondary tracking-wider mb-1">
                        <i class="bi bi-info-circle me-1"></i> Akun Induk Master COA (Template Pewarisan)
                    </h6>
                    <div class="small text-muted mb-2">
                        Akun induk berikut memiliki tag kelompok ini di Master COA sebagai template pewarisan untuk sub-akun baru.
                    </div>
                    <ul class="list-group list-group-flush border rounded-3 small" id="modalDetailLangsungParentList"></ul>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2 border-top">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@if ($canCreate || $canUpdate)
    <!-- Modal Konfirmasi Pemindahan Akun COA (Metode Langsung) -->
    <div class="modal fade" id="modalKonfirmasiPindahCoaLangsung" tabindex="-1" aria-labelledby="modalKonfirmasiPindahCoaLangsungLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-warning-subtle border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalKonfirmasiPindahCoaLangsungLabel">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Konfirmasi Pemindahan Akun COA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-dark mb-2">
                        Beberapa akun COA yang Anda pilih saat ini sudah terpetakan pada pos kelompok arus kas lain. Menyimpan perubahan ini akan <strong>memindahkan pemetaan akun</strong> tersebut ke pos ini:
                    </p>
                    <ul class="list-group list-group-flush border rounded-3 small mb-3" id="konfirmasiPindahCoaList"></ul>
                    <p class="small text-muted mb-0">
                        Apakah Anda yakin ingin memindahkan akun-akun tersebut ke pos ini?
                    </p>
                </div>
                <div class="modal-footer bg-light px-4 py-2 border-top">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal / Periksa Kembali</button>
                    <button type="button" class="btn btn-sm btn-primary fw-bold px-3" id="btnConfirmPindahCoaLangsung">
                        <i class="bi bi-check-circle me-1"></i> Ya, Pindahkan & Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
