@php
    $oldModalTarget = old('_modal_target');
    $oldPosId = old('_pos_id');
    $isOldPosModal = $errors->any() && $oldModalTarget === 'modalPosArusKas';
    $isOldPosEdit = $isOldPosModal && is_numeric($oldPosId) && (int) $oldPosId > 0;
    $posFormAction = $isOldPosEdit
        ? route('pengaturan.arus-kas.update', (int) $oldPosId)
        : route('pengaturan.arus-kas.store');

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
    <!-- Modal Create / Edit Pos Arus Kas -->
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

                            <div class="col-md-4">
                                <label for="posKodePos" class="form-label small fw-semibold">
                                    Kode Pos
                                </label>
                                <input type="text"
                                       name="kode_pos"
                                       id="posKodePos"
                                       class="form-control form-control-sm font-monospace"
                                       maxlength="80"
                                       placeholder="Otomatis jika kosong"
                                       value="{{ $isOldPosModal ? old('kode_pos') : '' }}">
                            </div>

                            <div class="col-md-2">
                                <label for="posUrutan" class="form-label small fw-semibold">
                                    Urutan <span class="text-danger">*</span>
                                </label>
                                <input type="number"
                                       name="urutan"
                                       id="posUrutan"
                                       class="form-control form-control-sm"
                                       required
                                       min="1"
                                       value="{{ $isOldPosModal ? old('urutan', 1) : 1 }}">
                            </div>

                            <div class="col-12">
                                <div class="row g-2 p-2.5 bg-light rounded-3 border m-0">
                                    <div class="col-12 col-sm-7">
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="pakai_prefix_perubahan" value="0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="pakai_prefix_perubahan"
                                                   value="1"
                                                   id="posPakaiPrefix"
                                                   @checked($isOldPosModal ? (bool) old('pakai_prefix_perubahan') : false)>
                                            <label class="form-check-label small fw-semibold" for="posPakaiPrefix" id="posPakaiPrefixLabel">
                                                Prefix Perubahan (Kenaikan/Penurunan)
                                            </label>
                                        </div>
                                        <div class="text-muted small mt-0.5" id="posPrefixHelp" style="font-size: 0.75rem;">
                                            Disesuaikan otomatis dengan mutasi saldo akun Neraca.
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-5 d-flex align-items-center">
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="is_active"
                                                   value="1"
                                                   id="posIsActive"
                                                   @checked($isOldPosModal ? (bool) old('is_active', true) : true)>
                                            <label class="form-check-label small fw-semibold" for="posIsActive">
                                                Status Aktif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Parent COA Quick-Select -->
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <label for="parentCoaSelect" class="form-label small fw-semibold mb-1">
                                        Pilih Cepat Berdasarkan Akun Induk (Parent COA)
                                    </label>
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <div class="flex-grow-1">
                                            <select id="parentCoaSelect" class="form-select form-select-sm">
                                                <option value="">-- Pilih Akun Induk (Parent COA) --</option>
                                                @foreach ($parentCoas as $parent)
                                                    <option value="{{ $parent->id }}"
                                                            data-tipe="{{ $parent->tipe_coa }}"
                                                            data-status="{{ (int) $parent->status_aktif }}">
                                                        {{ $parent->kode }} - {{ $parent->nama }}{{ (int) $parent->status_aktif !== 1 ? ' [Nonaktif]' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button"
                                                id="btnAddParentLeaves"
                                                class="btn btn-sm btn-outline-primary fw-semibold text-nowrap">
                                            <i class="bi bi-diagram-3 me-1"></i> Tambah Semua Child COA
                                        </button>
                                    </div>
                                    <div id="parentLeavesFeedback" class="small mt-2 d-none"></div>
                                </div>
                            </div>

                            <!-- Leaf COA Multi-Select -->
                            <div class="col-12">
                                <label for="coaIdsSelect" class="form-label small fw-semibold">
                                    Akun COA Terpetakan
                                </label>
                                <input type="hidden" name="coa_ids" value="">
                                <select name="coa_ids[]"
                                        id="coaIdsSelect"
                                        class="form-select form-select-sm"
                                        multiple="multiple">
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
                                                @disabled($disabled)
                                                @selected(! $disabled && in_array($coaId, $initialPosCoaIds, true))>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small">
                                    Hanya akun detail (leaf) yang kompatibel dengan sifat pos yang dapat dipilih. Akun yang sudah dipakai pos lain atau dikecualikan otomatis dinonaktifkan.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top px-4 py-2 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSubmitPosArusKas" class="btn btn-sm btn-primary fw-bold px-3">
                            <i class="bi bi-check-circle me-1"></i>
                            <span id="btnSubmitPosArusKasLabel">{{ $isOldPosEdit ? 'Perbarui Pos Arus Kas' : 'Simpan Pos Arus Kas' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
@endif

@if ($canCreate)
    <!-- Modal Tambah Pengecualian Arus Kas -->
    <div class="modal fade" id="modalPengecualianArusKas" tabindex="-1" aria-labelledby="modalPengecualianArusKasLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content border-0 shadow" id="formPengecualianArusKas" method="POST" action="{{ route('pengaturan.arus-kas.pengecualian.store') }}">
                @csrf
                <input type="hidden" name="_modal_target" value="modalPengecualianArusKas">

                <div class="modal-header bg-light border-bottom px-4 py-3 flex-shrink-0">
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalPengecualianArusKasLabel">
                        <i class="bi bi-slash-circle text-danger me-1"></i> Tambah Akun Pengecualian Arus Kas
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
                        <div class="row g-3">
                            <!-- Parent COA Quick-Select for Pengecualian -->
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <label for="pengecualianParentCoaSelect" class="form-label small fw-semibold mb-1">
                                        Pilih Cepat Berdasarkan Akun Induk (Parent COA)
                                    </label>
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <div class="flex-grow-1">
                                            <select id="pengecualianParentCoaSelect" class="form-select form-select-sm">
                                                <option value="">-- Pilih Akun Induk (Parent COA) --</option>
                                                @foreach ($parentCoas as $parent)
                                                    <option value="{{ $parent->id }}"
                                                            data-tipe="{{ $parent->tipe_coa }}"
                                                            data-status="{{ (int) $parent->status_aktif }}">
                                                        {{ $parent->kode }} - {{ $parent->nama }}{{ (int) $parent->status_aktif !== 1 ? ' [Nonaktif]' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button"
                                                id="btnAddPengecualianParentLeaves"
                                                class="btn btn-sm btn-outline-danger fw-semibold text-nowrap">
                                            <i class="bi bi-diagram-3 me-1"></i> Tambah Semua Child COA
                                        </button>
                                    </div>
                                    <div id="pengecualianParentLeavesFeedback" class="small mt-2 d-none"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="pengecualianCoaIdsSelect" class="form-label small fw-semibold">
                                    Akun Neraca Dikecualikan <span class="text-danger">*</span>
                                </label>
                                <select name="coa_ids[]"
                                        id="pengecualianCoaIdsSelect"
                                        class="form-select form-select-sm"
                                        multiple="multiple"
                                        required>
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

                            <div class="col-12">
                                <label for="pengecualianAlasan" class="form-label small fw-semibold">
                                    Alasan Pengecualian
                                </label>
                                <textarea name="alasan"
                                          id="pengecualianAlasan"
                                          class="form-control form-control-sm"
                                          rows="2"
                                          maxlength="255"
                                          placeholder="Contoh: Akun kontra akumulasi penyusutan yang sudah disesuaikan pada pos Penyesuaian Laba">{{ $isOldPengecualianModal ? old('alasan') : '' }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top px-4 py-2 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                            <i class="bi bi-check-circle me-1"></i> Simpan Pengecualian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Modal Detail Akun COA Terpetakan -->
<div class="modal fade" id="modalDetailCoaPos" tabindex="-1" aria-labelledby="modalDetailCoaPosLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom px-4 py-3 flex-shrink-0">
                <div>
                    <h5 class="modal-title fw-bold text-dark fs-6" id="modalDetailCoaPosLabel">
                        <i class="bi bi-diagram-2 text-primary me-1"></i> Akun COA Terpetakan
                    </h5>
                    <div class="small text-muted mt-0.5" id="detailCoaPosSubTitle">Pos: -</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3 custom-scrollbar">
                <div class="mb-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               id="inputSearchDetailCoa"
                               class="form-control border-start-0"
                               placeholder="Cari kode atau nama akun...">
                    </div>
                </div>
                <div id="detailCoaEmptyAlert" class="alert alert-secondary py-2 px-3 small text-center d-none mb-0">
                    Tidak ada akun yang cocok dengan kata kunci.
                </div>
                <ul class="list-group list-group-flush border rounded-3" id="detailCoaListContainer">
                    <!-- Populated dynamically via JS -->
                </ul>
            </div>
            <div class="modal-footer bg-light border-top px-4 py-2 flex-shrink-0 justify-content-between">
                <span class="small text-muted fw-semibold" id="detailCoaTotalCount">Total: 0 Akun</span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
