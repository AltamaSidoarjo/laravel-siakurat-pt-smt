@if ($activeMetode === 'langsung')
    @php
        $oldModalTarget = old('_modal_target');
        $isOldTambahReferensi = $errors->any() && $oldModalTarget === 'modalTambahReferensi';
        $isOldEditReferensi = $errors->any() && $oldModalTarget === 'modalEditReferensi';
        $oldReferensiId = old('_referensi_id');
        $oldReferensiAktivitas = old('_referensi_aktivitas');
        $editReferensiAction = $oldReferensiId
            ? route('pengaturan.arus-kas.langsung.update', $oldReferensiId)
            : '';
    @endphp

    @if ($canCreate)
        <!-- Modal Tambah Pos Referensi (Metode Langsung) -->
        <div class="modal fade" id="modalTambahReferensi" tabindex="-1" aria-labelledby="modalTambahReferensiLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
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
    @endif

    @if ($canUpdate)
        <!-- Modal Edit Pos Referensi (Metode Langsung) -->
        <div class="modal fade" id="modalEditReferensi" tabindex="-1" aria-labelledby="modalEditReferensiLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow border-0">
                    <form id="formEditReferensi" method="POST" action="{{ $editReferensiAction }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_modal_target" value="modalEditReferensi">
                        <input type="hidden" name="_referensi_id" id="editReferensiId" value="{{ $oldReferensiId }}">
                        <input type="hidden" name="_referensi_aktivitas" id="editReferensiAktivitas" value="{{ $oldReferensiAktivitas }}">

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

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Aktivitas</label>
                                <input type="text" id="editAktivitasLabel" class="form-control form-control-sm bg-light text-uppercase fw-semibold" value="{{ $oldReferensiAktivitas }}" readonly disabled>
                                <div class="form-text small text-muted">Aktivitas tidak dapat diubah (immutabilitas aktivitas).</div>
                            </div>

                            <div class="mb-3">
                                <label for="editNama" class="form-label small fw-semibold">Nama Kelompok Arus Kas <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="editNama" class="form-control form-control-sm" required maxlength="150" placeholder="Contoh: Pembayaran Rekening Listrik" value="{{ old('nama') }}">
                                <div class="form-text small">Perubahan nama akan otomatis meng-update akun COA yang menggunakan pos ini (cascade).</div>
                            </div>

                            <div class="mb-2">
                                <label for="editStatusAktif" class="form-label small fw-semibold">Status Aktif <span class="text-danger">*</span></label>
                                <select name="status_aktif" id="editStatusAktif" class="form-select form-select-sm" required>
                                    <option value="1" @selected(old('status_aktif', '1') == '1')>Aktif</option>
                                    <option value="0" @selected(old('status_aktif') == '0')>Nonaktif</option>
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
    @endif
@else
    @php
        $oldModalTarget = old('_modal_target');
        $oldPosId = old('_pos_id');
        $isOldPosModal = $errors->any() && $oldModalTarget === 'modalPosArusKas';
        $isOldPosEdit = $isOldPosModal && is_numeric($oldPosId) && (int) $oldPosId > 0;
        $posFormAction = $isOldPosEdit
            ? route('pengaturan.arus-kas.tidak-langsung.update', (int) $oldPosId)
            : route('pengaturan.arus-kas.tidak-langsung.store');

        $isOldPengecualianModal = $errors->any() && $oldModalTarget === 'modalPengecualianArusKas';

        $leafCoas = $coaOptionsData['leafCoas'] ?? collect();
        $parentCoas = $coaOptionsData['parentCoas'] ?? collect();
        $mappedByPos = $coaOptionsData['mappedByPos'] ?? [];
        $excludedSet = array_flip(array_map('intval', $coaOptionsData['excludedCoaIds'] ?? []));

        $initialPosSifat = $isOldPosModal
            ? (string) old('sifat_pos', 'penambah_laba')
            : 'penambah_laba';
        $initialIgnorePosId = $isOldPosEdit ? (int) $oldPosId : null;
        $initialPosCoaIds = $isOldPosModal
            ? array_map('intval', (array) old('coa_ids', []))
            : [];
        $initialPengecualianCoaIds = $isOldPengecualianModal
            ? array_map('intval', (array) old('coa_ids', []))
            : [];

        $isInitialPosLabaRugi = in_array($initialPosSifat, \App\Models\SettingArusKas::SIFAT_LABA_RUGI, true);
    @endphp

    @if ($canCreate || $canUpdate)
        <!-- Modal Create / Edit Pos Arus Kas (Metode Tidak Langsung) -->
        <div class="modal fade" id="modalPosArusKas" tabindex="-1" aria-labelledby="modalPosArusKasLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content border-0 shadow" id="formPosArusKas" method="POST" action="{{ $posFormAction }}">
                    @csrf
                    <input type="hidden" name="_method" id="posFormMethod" value="PUT" @disabled(! $isOldPosEdit)>
                    <input type="hidden" name="_modal_target" value="modalPosArusKas">
                    <input type="hidden" name="_pos_id" id="posFormId" value="{{ $isOldPosEdit ? (int) $oldPosId : '' }}">

                    <div class="modal-header bg-light border-bottom px-4 py-3 flex-shrink-0">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalPosArusKasLabel">
                            @if ($isOldPosEdit)
                                <i class="bi bi-pencil-square text-primary me-1"></i> Edit Pos Arus Kas
                            @else
                                <i class="bi bi-plus-circle text-success me-1"></i> Tambah Pos Arus Kas
                            @endif
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body p-4 custom-scrollbar">
                        @if ($isOldPosModal)
                            <div id="modalPosValidationAlert" class="alert alert-danger py-2 px-3 small mb-3">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="posKelompokBagian" class="form-label small fw-semibold">
                                    Kelompok Bagian <span class="text-danger">*</span>
                                </label>
                                <select name="kelompok_bagian" id="posKelompokBagian" class="form-select form-select-sm" required>
                                    @foreach ($kelompokBagianLabels as $bagianKey => $bagianLabel)
                                        <option value="{{ $bagianKey }}" @selected(($isOldPosModal ? old('kelompok_bagian') : 'penyesuaian_laba') === $bagianKey)>
                                            {{ $bagianLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="posSifatPos" class="form-label small fw-semibold">
                                    Sifat Pos <span class="text-danger">*</span>
                                </label>
                                <select name="sifat_pos" id="posSifatPos" class="form-select form-select-sm" required>
                                    @foreach ($sifatPosLabels as $sifatKey => $sifatLabel)
                                        <option value="{{ $sifatKey }}" @selected($initialPosSifat === $sifatKey)>
                                            {{ $sifatLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small" id="posSifatHint">
                                    Penambah/Pengurang Laba memfilter akun Laba Rugi aktif; Aset/Liabilitas/Ekuitas memfilter akun Neraca non-Kasbank.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="posNamaPos" class="form-label small fw-semibold">
                                    Nama Pos <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       name="nama_pos"
                                       id="posNamaPos"
                                       class="form-control form-control-sm"
                                       required
                                       maxlength="150"
                                       placeholder="Contoh: Piutang Usaha"
                                       value="{{ $isOldPosModal ? old('nama_pos') : '' }}">
                            </div>

                            <div class="col-md-3">
                                <label for="posKodePos" class="form-label small fw-semibold">Kode Pos (Opsional)</label>
                                <input type="text"
                                       name="kode_pos"
                                       id="posKodePos"
                                       class="form-control form-control-sm font-monospace"
                                       maxlength="50"
                                       placeholder="Otomatis jika kosong"
                                       value="{{ $isOldPosModal ? old('kode_pos') : '' }}">
                            </div>

                            <div class="col-md-3">
                                <label for="posUrutan" class="form-label small fw-semibold">
                                    Urutan <span class="text-danger">*</span>
                                </label>
                                <input type="number"
                                       name="urutan"
                                       id="posUrutan"
                                       class="form-control form-control-sm"
                                       min="1"
                                       required
                                       value="{{ $isOldPosModal ? old('urutan', 1) : 1 }}">
                            </div>

                            <div class="col-12">
                                <div class="row g-2">
                                    <div class="col-md-6" id="wrapperPrefixPerubahan">
                                        <div class="form-check form-switch mt-1">
                                            <input type="hidden" name="pakai_prefix_perubahan" value="0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   role="switch"
                                                   id="posPakaiPrefix"
                                                   name="pakai_prefix_perubahan"
                                                   value="1"
                                                   @checked($isOldPosModal ? (bool) old('pakai_prefix_perubahan', true) : true)>
                                            <label class="form-check-label small fw-semibold" for="posPakaiPrefix">
                                                Gunakan Prefix Perubahan (Kenaikan / Penurunan)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-1">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   role="switch"
                                                   id="posIsActive"
                                                   name="is_active"
                                                   value="1"
                                                   @checked($isOldPosModal ? (bool) old('is_active', true) : true)>
                                            <label class="form-check-label small fw-semibold" for="posIsActive">
                                                Pos Aktif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pemetaan Akun COA -->
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                        <label class="form-label small fw-bold mb-0 text-dark">
                                            <i class="bi bi-diagram-3 me-1 text-primary"></i> Pemetaan Akun COA Terkait
                                        </label>
                                        <span class="small text-muted" id="coaCounterBadge">0 akun dipilih</span>
                                    </div>

                                    <!-- Quick Parent Picker -->
                                    <div class="row g-2 align-items-center mb-2">
                                        <div class="col-sm-8">
                                            <select class="form-select form-select-sm" id="parentCoaSelect">
                                                <option value="">-- Pilih Induk COA untuk Tambah Cepat --</option>
                                                @foreach ($parentCoas as $parent)
                                                    <option value="{{ $parent->id }}">
                                                        {{ $parent->kode }} - {{ $parent->nama }} ({{ $parent->tipe_coa ?? '-' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-4">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-semibold" id="btnAddParentLeaves">
                                                <i class="bi bi-plus-circle me-1"></i> Tambah Sub-Akun
                                            </button>
                                        </div>
                                    </div>
                                    <div id="parentLeavesFeedback" class="small text-muted mb-2 d-none"></div>

                                    <div class="mb-2">
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               id="filterCoaOptionsInput"
                                               placeholder="Ketik untuk memfilter daftar akun COA di bawah...">
                                    </div>

                                    <select name="coa_ids[]"
                                            id="coaIdsSelect"
                                            class="form-select form-select-sm custom-scrollbar"
                                            multiple
                                            size="7"
                                            style="font-family: monospace; font-size: 0.8rem;">
                                        @foreach ($leafCoas as $coa)
                                            @php
                                                $coaId = (int) $coa->id;
                                                $isCoaLabaRugi = in_array((string) $coa->tipe_coa, \App\Models\SettingArusKas::TIPE_LABA_RUGI, true);
                                                $compatible = $isInitialPosLabaRugi
                                                    ? ($isCoaLabaRugi && (int) $coa->status_aktif === 1)
                                                    : ! $isCoaLabaRugi;

                                                if (! $compatible) {
                                                    continue;
                                                }

                                                $label = "{$coa->kode} - {$coa->nama}";
                                                if ((int) $coa->status_aktif !== 1) {
                                                    $label .= ' [Nonaktif]';
                                                }

                                                $disabled = false;
                                                if (isset($excludedSet[$coaId])) {
                                                    $label .= ' (Dikecualikan)';
                                                    $disabled = true;
                                                } elseif (isset($mappedByPos[$coaId])) {
                                                    $holder = $mappedByPos[$coaId];
                                                    if ($initialIgnorePosId === null || (int) $holder['pos_id'] !== $initialIgnorePosId) {
                                                        $holderSuffix = $holder['is_active'] ? '' : ' [Nonaktif]';
                                                        $label .= " (Dipakai: {$holder['nama_pos']}{$holderSuffix})";
                                                        $disabled = true;
                                                    }
                                                }
                                            @endphp
                                            <option value="{{ $coaId }}"
                                                    data-parent-id="{{ $coa->parent_coa ?? '' }}"
                                                    data-tipe-coa="{{ $coa->tipe_coa ?? '' }}"
                                                    data-status-aktif="{{ $coa->status_aktif }}"
                                                    @disabled($disabled)
                                                    @selected(! $disabled && in_array($coaId, $initialPosCoaIds, true))>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small mt-1">
                                        Gunakan <kbd>Ctrl</kbd> + Klik (atau <kbd>Cmd</kbd> + Klik pada Mac) untuk memilih beberapa akun sekaligus.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light px-4 py-2 border-top flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3" id="btnSubmitPosArusKas">
                            <i class="bi bi-check-circle me-1"></i> <span id="btnSubmitPosArusKasLabel">{{ $isOldPosEdit ? 'Perbarui Pos Arus Kas' : 'Simpan Pos Arus Kas' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canCreate)
        <!-- Modal Tambah Pengecualian Akun (Metode Tidak Langsung) -->
        <div class="modal fade" id="modalPengecualianArusKas" tabindex="-1" aria-labelledby="modalPengecualianArusKasLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content border-0 shadow" id="formPengecualianArusKas" method="POST" action="{{ route('pengaturan.arus-kas.tidak-langsung.pengecualian.store') }}">
                    @csrf
                    <input type="hidden" name="_modal_target" value="modalPengecualianArusKas">

                    <div class="modal-header bg-light border-bottom px-4 py-3 flex-shrink-0">
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalPengecualianArusKasLabel">
                            <i class="bi bi-slash-circle text-danger me-1"></i> Tambah Akun Pengecualian
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body p-4 custom-scrollbar">
                        @if ($isOldPengecualianModal)
                            <div id="modalPengecualianValidationAlert" class="alert alert-danger py-2 px-3 small mb-3">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Pilih Induk COA (Opsional)</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select form-select-sm" id="pengecualianParentCoaSelect">
                                    <option value="">-- Pilih Induk COA untuk Memilih Cepat --</option>
                                    @foreach ($parentCoas as $parent)
                                        <option value="{{ $parent->id }}">
                                            {{ $parent->kode }} - {{ $parent->nama }} ({{ $parent->tipe_coa ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btnAddPengecualianParentLeaves">
                                    Pilih Semua
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="pengecualianCoaIdsSelect" class="form-label small fw-semibold">
                                Akun Neraca <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control form-control-sm mb-2"
                                   id="filterPengecualianCoaInput"
                                   placeholder="Filter akun Neraca...">
                            <select name="coa_ids[]"
                                    id="pengecualianCoaIdsSelect"
                                    class="form-select form-select-sm custom-scrollbar"
                                    multiple
                                    size="8"
                                    required
                                    style="font-family: monospace; font-size: 0.8rem;">
                                @foreach ($leafCoas as $coa)
                                    @php
                                        $coaId = (int) $coa->id;
                                        $isCoaLabaRugi = in_array((string) $coa->tipe_coa, \App\Models\SettingArusKas::TIPE_LABA_RUGI, true);
                                        if ($isCoaLabaRugi) {
                                            continue;
                                        }

                                        $label = "{$coa->kode} - {$coa->nama}";
                                        if ((int) $coa->status_aktif !== 1) {
                                            $label .= ' [Nonaktif]';
                                        }

                                        $disabled = false;
                                        if (isset($excludedSet[$coaId])) {
                                            $label .= ' (Dikecualikan)';
                                            $disabled = true;
                                        } elseif (isset($mappedByPos[$coaId])) {
                                            $holder = $mappedByPos[$coaId];
                                            $holderSuffix = $holder['is_active'] ? '' : ' [Nonaktif]';
                                            $label .= " (Dipakai: {$holder['nama_pos']}{$holderSuffix})";
                                            $disabled = true;
                                        }
                                    @endphp
                                    <option value="{{ $coaId }}"
                                            data-parent-id="{{ $coa->parent_coa ?? '' }}"
                                            @disabled($disabled)
                                            @selected(! $disabled && in_array($coaId, $initialPengecualianCoaIds, true))>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">
                                Khusus akun Neraca non-Kasbank (mis. akumulasi penyusutan atau akun kontra) agar tidak muncul pada peringatan akun belum dipetakan.
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="pengecualianAlasan" class="form-label small fw-semibold">Alasan Pengecualian (Opsional)</label>
                            <input type="text"
                                   name="alasan"
                                   id="pengecualianAlasan"
                                   class="form-control form-control-sm"
                                   maxlength="255"
                                   placeholder="Contoh: Akun kontra aset tetap (penyusutan diakui di Penyesuaian Laba)"
                                   value="{{ $isOldPengecualianModal ? old('alasan') : '' }}">
                        </div>
                    </div>

                    <div class="modal-footer bg-light px-4 py-2 border-top flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                            <i class="bi bi-slash-circle me-1"></i> Simpan Pengecualian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Detail COA Terpetakan per Pos (Metode Tidak Langsung) -->
    <div class="modal fade" id="modalDetailCoaPos" tabindex="-1" aria-labelledby="modalDetailCoaPosLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom px-4 py-3 flex-shrink-0">
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6" id="modalDetailCoaPosLabel">Daftar Akun COA Terpetakan</h5>
                        <div class="small text-muted" id="modalDetailPosSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-0 custom-scrollbar">
                    <ul class="list-group list-group-flush small" id="modalDetailCoaList"></ul>
                </div>
                <div class="modal-footer bg-light px-4 py-2 border-top flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endif
