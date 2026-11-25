@php
    $coaOptionsPayload = [
        'leafCoas' => ($coaOptionsData['leafCoas'] ?? collect())
            ->map(fn ($coa): array => [
                'id' => (int) $coa->id,
                'parent_coa' => $coa->parent_coa !== null ? (int) $coa->parent_coa : null,
                'kode' => (string) $coa->kode,
                'nama' => (string) $coa->nama,
                'tipe_coa' => (string) ($coa->tipe_coa ?? ''),
                'status_aktif' => (int) $coa->status_aktif,
            ])
            ->values()
            ->all(),
        'mappedByPos' => $coaOptionsData['mappedByPos'] ?? [],
        'excludedCoaIds' => array_values(array_map('intval', $coaOptionsData['excludedCoaIds'] ?? [])),
        'tipeLabaRugi' => \App\Models\SettingArusKas::TIPE_LABA_RUGI,
        'sifatLabaRugi' => \App\Models\SettingArusKas::SIFAT_LABA_RUGI,
    ];

    $maxUrutanByBagian = [];
    foreach (\App\Models\SettingArusKas::KELOMPOK_BAGIAN as $bagianKey) {
        $maxUrutanByBagian[$bagianKey] = (int) ($posList->where('kelompok_bagian', $bagianKey)->max('urutan') ?? 0);
    }

    $oldModalState = [
        'hasErrors' => $errors->any(),
        'target' => (string) old('_modal_target', ''),
        'posId' => old('_pos_id') !== null && old('_pos_id') !== '' ? (int) old('_pos_id') : null,
        'sifatPos' => (string) old('sifat_pos', 'penambah_laba'),
        'coaIds' => array_values(array_map('intval', (array) old('coa_ids', []))),
    ];
@endphp

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const VIEW_MODE_STORAGE_KEY = 'siakurat.pengaturan_arus_kas.view_mode';

        // ---------------------------------------------------------------------
        // 1. Dual-Mode View Switcher (Mode Tab vs Mode Struktur Laporan)
        // ---------------------------------------------------------------------
        const viewModeTabEl = document.getElementById('viewModeTab');
        const viewModeStrukturEl = document.getElementById('viewModeStruktur');
        const modeButtons = document.querySelectorAll('[data-view-mode]');

        const applyViewMode = (mode, persist = true) => {
            const normalizedMode = mode === 'struktur' ? 'struktur' : 'tab';

            if (viewModeTabEl && viewModeStrukturEl) {
                if (normalizedMode === 'struktur') {
                    viewModeTabEl.classList.add('d-none');
                    viewModeStrukturEl.classList.remove('d-none');
                    viewModeStrukturEl.classList.add('d-flex');
                } else {
                    viewModeTabEl.classList.remove('d-none');
                    viewModeStrukturEl.classList.add('d-none');
                    viewModeStrukturEl.classList.remove('d-flex');
                }
            }

            modeButtons.forEach((btn) => {
                const isActive = btn.getAttribute('data-view-mode') === normalizedMode;
                btn.classList.toggle('btn-primary', isActive);
                btn.classList.toggle('active', isActive);
                btn.classList.toggle('btn-outline-primary', !isActive);
            });

            if (persist) {
                try {
                    window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, normalizedMode);
                } catch (err) {
                    // Ignore storage failures in restricted browsers
                }
            }
        };

        let savedViewMode = 'tab';
        try {
            savedViewMode = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY) || 'tab';
        } catch (err) {
            savedViewMode = 'tab';
        }
        applyViewMode(savedViewMode, false);

        modeButtons.forEach((btn) => {
            btn.addEventListener('click', function () {
                applyViewMode(this.getAttribute('data-view-mode'), true);
            });
        });

        @if ($canCreate || $canUpdate)
        const COA_META = @json($coaOptionsPayload);
        const MAX_URUTAN_BY_BAGIAN = @json($maxUrutanByBagian);
        const OLD_MODAL_STATE = @json($oldModalState);
        const PARENT_LEAVES_URL = '{{ route('pengaturan.arus-kas.parent-coa-leaves') }}';
        const STORE_POS_URL = '{{ route('pengaturan.arus-kas.store') }}';
        const UPDATE_POS_URL_TEMPLATE = '{{ route('pengaturan.arus-kas.update', ['settingArusKas' => '__POS_ID__']) }}';
        const REORDER_URL = '{{ route('pengaturan.arus-kas.reorder') }}';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        const $ = window.jQuery;
        const excludedIdSet = new Set((COA_META.excludedCoaIds || []).map((id) => Number(id)));
        const tipeLabaRugiSet = new Set(COA_META.tipeLabaRugi || []);
        const sifatLabaRugiSet = new Set(COA_META.sifatLabaRugi || []);
        const leafCoaById = new Map((COA_META.leafCoas || []).map((coa) => [Number(coa.id), coa]));

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        // ---------------------------------------------------------------------
        // 2. Shared Select2 & COA Filtering Helpers
        // ---------------------------------------------------------------------
        const initSelect2InModal = (selector, modalSelector, placeholderText, isMultiple = false) => {
            if (!($ && $.fn && $.fn.select2)) {
                return null;
            }
            const $el = $(selector);
            const $modal = $(modalSelector);
            if (!$el.length || !$modal.length) {
                return null;
            }
            if ($el.data('select2')) {
                return $el;
            }

            $el.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $modal,
                placeholder: placeholderText,
                allowClear: true,
                closeOnSelect: !isMultiple,
            });

            return $el;
        };

        const isCoaCompatibleWithMode = (coa, modeOrSifat) => {
            const isLabaRugiCoa = tipeLabaRugiSet.has(String(coa.tipe_coa || ''));
            if (sifatLabaRugiSet.has(String(modeOrSifat || ''))) {
                return isLabaRugiCoa && Number(coa.status_aktif) === 1;
            }
            return !isLabaRugiCoa;
        };

        const formatCoaOptionState = (coa, ignorePosId = null) => {
            const coaId = Number(coa.id);
            let label = `${coa.kode} - ${coa.nama}`;
            if (Number(coa.status_aktif) !== 1) {
                label += ' [Nonaktif]';
            }

            let disabled = false;
            if (excludedIdSet.has(coaId)) {
                label += ' (Dikecualikan)';
                disabled = true;
            } else if (COA_META.mappedByPos && COA_META.mappedByPos[coaId]) {
                const holder = COA_META.mappedByPos[coaId];
                if (ignorePosId === null || Number(holder.pos_id) !== Number(ignorePosId)) {
                    const holderStatus = holder.is_active ? '' : ' [Nonaktif]';
                    label += ` (Dipakai: ${holder.nama_pos}${holderStatus})`;
                    disabled = true;
                }
            }

            return { label, disabled };
        };

        const populateCoaSelect = (selectEl, { modeOrSifat, ignorePosId = null, selectedIds = [] }) => {
            if (!selectEl) {
                return;
            }

            const selectedSet = new Set((selectedIds || []).map((id) => Number(id)));
            selectEl.innerHTML = '';

            (COA_META.leafCoas || []).forEach((coa) => {
                if (!isCoaCompatibleWithMode(coa, modeOrSifat)) {
                    return;
                }

                const coaId = Number(coa.id);
                const { label, disabled } = formatCoaOptionState(coa, ignorePosId);

                const option = document.createElement('option');
                option.value = String(coaId);
                option.textContent = label;
                option.disabled = disabled;
                option.selected = !disabled && selectedSet.has(coaId);
                selectEl.appendChild(option);
            });

            if ($ && $.fn && $.fn.select2) {
                $(selectEl).trigger('change');
            }
        };

        const getSelectedValues = (selectEl) => {
            if (!selectEl) {
                return [];
            }
            if ($ && $(selectEl).data('select2')) {
                return ($(selectEl).val() || []).map((val) => Number(val));
            }
            return Array.from(selectEl.selectedOptions).map((opt) => Number(opt.value));
        };

        const setSelectedValues = (selectEl, ids) => {
            if (!selectEl) {
                return;
            }
            const idSet = new Set((ids || []).map((id) => String(id)));
            Array.from(selectEl.options).forEach((opt) => {
                if (!opt.disabled) {
                    opt.selected = idSet.has(opt.value);
                }
            });
            if ($ && $(selectEl).data('select2')) {
                $(selectEl).val(Array.from(idSet)).trigger('change');
            }
        };

        const resetFeedback = (feedbackEl) => {
            if (!feedbackEl) {
                return;
            }
            feedbackEl.className = 'small mt-2 d-none';
            feedbackEl.innerHTML = '';
        };

        const renderParentLeavesFeedback = (feedbackEl, eligible, skipped) => {
            if (!feedbackEl) {
                return;
            }

            const eligibleCount = eligible.length;
            const skippedCount = skipped.length;

            if (eligibleCount === 0 && skippedCount === 0) {
                feedbackEl.className = 'small mt-2 alert alert-secondary py-2 px-3 mb-0';
                feedbackEl.textContent = 'Tidak ditemukan akun child (detail) di bawah akun induk yang dipilih.';
                return;
            }

            let html = '';
            if (eligibleCount > 0) {
                html += `<div class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>${eligibleCount} akun COA eligible berhasil ditambahkan ke pilihan.</div>`;
            } else {
                html += `<div class="fw-semibold text-warning-emphasis"><i class="bi bi-info-circle me-1"></i>Tidak ada akun COA eligible yang dapat ditambahkan.</div>`;
            }

            if (skippedCount > 0) {
                const maxShown = 6;
                const itemsHtml = skipped.slice(0, maxShown).map((item) => {
                    return `<li><span class="fw-semibold">${escapeHtml(item.kode)} - ${escapeHtml(item.nama)}</span>: ${escapeHtml(item.reason)}</li>`;
                }).join('');
                const remainingNote = skippedCount > maxShown
                    ? `<li class="text-muted">...dan ${skippedCount - maxShown} akun lainnya.</li>`
                    : '';

                html += `
                    <div class="mt-1 text-muted">
                        <span class="fw-semibold">${skippedCount} akun dilewati:</span>
                        <ul class="mb-0 ps-3 mt-1">${itemsHtml}${remainingNote}</ul>
                    </div>
                `;
            }

            feedbackEl.className = `small mt-2 alert ${eligibleCount > 0 ? 'alert-light border' : 'alert-warning'} py-2 px-3 mb-0`;
            feedbackEl.innerHTML = html;
        };

        const bindParentLeavesAction = ({
            buttonEl,
            parentSelectEl,
            targetSelectEl,
            feedbackEl,
            getSifatPos,
            getIgnorePosId,
        }) => {
            if (!buttonEl || !parentSelectEl || !targetSelectEl) {
                return;
            }

            buttonEl.addEventListener('click', async () => {
                const parentId = parentSelectEl.value;
                if (!parentId) {
                    if (feedbackEl) {
                        feedbackEl.className = 'small mt-2 alert alert-warning py-2 px-3 mb-0';
                        feedbackEl.textContent = 'Pilih akun induk (parent COA) terlebih dahulu.';
                    }
                    return;
                }

                const originalHtml = buttonEl.innerHTML;
                buttonEl.disabled = true;
                buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memuat...';

                try {
                    const url = new URL(PARENT_LEAVES_URL, window.location.origin);
                    url.searchParams.set('parent_id', parentId);
                    const sifatPos = getSifatPos();
                    if (sifatPos) {
                        url.searchParams.set('sifat_pos', sifatPos);
                    }
                    const ignorePosId = getIgnorePosId();
                    if (ignorePosId) {
                        url.searchParams.set('ignore_pos_id', String(ignorePosId));
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Gagal memuat daftar child COA.');
                    }

                    const payload = await response.json();
                    const eligible = Array.isArray(payload.eligible) ? payload.eligible : [];
                    const skipped = Array.isArray(payload.skipped) ? payload.skipped : [];

                    const existingIds = getSelectedValues(targetSelectEl).map((id) => String(id));
                    const mergedSet = new Set(existingIds);
                    eligible.forEach((item) => {
                        const idStr = String(item.id);
                        const matchingOpt = Array.from(targetSelectEl.options).find((opt) => opt.value === idStr);
                        if (matchingOpt && !matchingOpt.disabled) {
                            mergedSet.add(idStr);
                        }
                    });

                    setSelectedValues(targetSelectEl, Array.from(mergedSet));
                    renderParentLeavesFeedback(feedbackEl, eligible, skipped);
                } catch (err) {
                    if (feedbackEl) {
                        feedbackEl.className = 'small mt-2 alert alert-danger py-2 px-3 mb-0';
                        feedbackEl.textContent = err.message || 'Terjadi kesalahan saat mengambil daftar child COA.';
                    }
                } finally {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = originalHtml;
                }
            });
        };

        // ---------------------------------------------------------------------
        // 3. Modal Pos Arus Kas Initialization & Handlers
        // ---------------------------------------------------------------------
        const modalPosEl = document.getElementById('modalPosArusKas');
        const formPosEl = document.getElementById('formPosArusKas');
        const posMethodInput = document.getElementById('posFormMethod');
        const posIdInput = document.getElementById('posFormId');
        const modalPosTitleEl = document.getElementById('modalPosArusKasLabel');
        const btnSubmitPosLabelEl = document.getElementById('btnSubmitPosArusKasLabel');
        const posBagianSelect = document.getElementById('posKelompokBagian');
        const posSifatSelect = document.getElementById('posSifatPos');
        const posNamaInput = document.getElementById('posNamaPos');
        const posKodeInput = document.getElementById('posKodePos');
        const posUrutanInput = document.getElementById('posUrutan');
        const posPakaiPrefixInput = document.getElementById('posPakaiPrefix');
        const posIsActiveInput = document.getElementById('posIsActive');
        const parentCoaSelectEl = document.getElementById('parentCoaSelect');
        const btnAddParentLeavesEl = document.getElementById('btnAddParentLeaves');
        const parentLeavesFeedbackEl = document.getElementById('parentLeavesFeedback');
        const coaIdsSelectEl = document.getElementById('coaIdsSelect');

        initSelect2InModal('#parentCoaSelect', '#modalPosArusKas', 'Pilih Akun Induk (Parent COA)...', false);
        initSelect2InModal('#coaIdsSelect', '#modalPosArusKas', 'Pilih satu atau lebih akun COA...', true);

        const defaultSifatForBagian = (bagian) => {
            switch (bagian) {
                case 'penyesuaian_laba':
                    return 'penambah_laba';
                case 'pendanaan':
                    return 'liabilitas';
                case 'modal_kerja':
                case 'investasi':
                default:
                    return 'aset';
            }
        };

        const inferNeracaSifatFromCoa = (coaId) => {
            const coa = leafCoaById.get(Number(coaId));
            const tipe = String(coa?.tipe_coa || '').toLowerCase();
            if (tipe.includes('hutang') || tipe.includes('kewajiban') || tipe.includes('liabilitas')) {
                return 'liabilitas';
            }
            if (tipe.includes('ekuitas') || tipe.includes('modal')) {
                return 'ekuitas';
            }
            return 'aset';
        };

        const syncPrefixToggleState = () => {
            if (!posSifatSelect || !posPakaiPrefixInput) {
                return;
            }
            const isLabaRugi = sifatLabaRugiSet.has(posSifatSelect.value);
            if (isLabaRugi) {
                posPakaiPrefixInput.checked = false;
                posPakaiPrefixInput.disabled = true;
            } else {
                posPakaiPrefixInput.disabled = false;
            }
        };

        const getCurrentIgnorePosId = () => {
            const rawId = posIdInput ? posIdInput.value : '';
            return rawId !== '' && Number(rawId) > 0 ? Number(rawId) : null;
        };

        const openPosModalForCreate = ({ kelompokBagian = 'penyesuaian_laba', sifatPos = null, preselectedCoaIds = [] } = {}) => {
            if (!modalPosEl || !formPosEl) {
                return;
            }

            const oldAlert = document.getElementById('modalPosValidationAlert');
            if (oldAlert) {
                oldAlert.classList.add('d-none');
            }

            formPosEl.action = STORE_POS_URL;
            if (posMethodInput) {
                posMethodInput.disabled = true;
                posMethodInput.value = 'POST';
            }
            if (posIdInput) {
                posIdInput.value = '';
            }
            if (modalPosTitleEl) {
                modalPosTitleEl.innerHTML = '<i class="bi bi-plus-circle text-success me-1"></i> Tambah Pos Arus Kas';
            }
            if (btnSubmitPosLabelEl) {
                btnSubmitPosLabelEl.textContent = 'Simpan Pos Arus Kas';
            }

            const resolvedBagian = kelompokBagian || 'penyesuaian_laba';
            const resolvedSifat = sifatPos || defaultSifatForBagian(resolvedBagian);

            if (posBagianSelect) posBagianSelect.value = resolvedBagian;
            if (posSifatSelect) posSifatSelect.value = resolvedSifat;
            if (posNamaInput) posNamaInput.value = '';
            if (posKodeInput) posKodeInput.value = '';
            if (posUrutanInput) {
                const nextUrutan = Number(MAX_URUTAN_BY_BAGIAN[resolvedBagian] || 0) + 1;
                posUrutanInput.value = String(Math.max(1, nextUrutan));
            }
            if (posPakaiPrefixInput) posPakaiPrefixInput.checked = false;
            if (posIsActiveInput) posIsActiveInput.checked = true;

            if (parentCoaSelectEl) {
                parentCoaSelectEl.value = '';
                if ($ && $(parentCoaSelectEl).data('select2')) {
                    $(parentCoaSelectEl).val('').trigger('change');
                }
            }
            resetFeedback(parentLeavesFeedbackEl);
            syncPrefixToggleState();

            populateCoaSelect(coaIdsSelectEl, {
                modeOrSifat: resolvedSifat,
                ignorePosId: null,
                selectedIds: preselectedCoaIds,
            });
        };

        const openPosModalForEdit = (triggerEl) => {
            if (!modalPosEl || !formPosEl || !triggerEl) {
                return;
            }

            const oldAlert = document.getElementById('modalPosValidationAlert');
            if (oldAlert) {
                oldAlert.classList.add('d-none');
            }

            const posId = Number(triggerEl.getAttribute('data-id'));
            const kodePos = triggerEl.getAttribute('data-kode-pos') || '';
            const namaPos = triggerEl.getAttribute('data-nama-pos') || '';
            const kelompokBagian = triggerEl.getAttribute('data-kelompok-bagian') || 'penyesuaian_laba';
            const sifatPos = triggerEl.getAttribute('data-sifat-pos') || 'penambah_laba';
            const pakaiPrefix = triggerEl.getAttribute('data-pakai-prefix') === '1';
            const urutan = triggerEl.getAttribute('data-urutan') || '1';
            const isActive = triggerEl.getAttribute('data-is-active') === '1';

            let coaIds = [];
            try {
                coaIds = JSON.parse(triggerEl.getAttribute('data-coa-ids') || '[]');
            } catch (err) {
                coaIds = [];
            }

            formPosEl.action = UPDATE_POS_URL_TEMPLATE.replace('__POS_ID__', String(posId));
            if (posMethodInput) {
                posMethodInput.disabled = false;
                posMethodInput.value = 'PUT';
            }
            if (posIdInput) {
                posIdInput.value = String(posId);
            }
            if (modalPosTitleEl) {
                modalPosTitleEl.innerHTML = '<i class="bi bi-pencil-square text-primary me-1"></i> Edit Pos Arus Kas';
            }
            if (btnSubmitPosLabelEl) {
                btnSubmitPosLabelEl.textContent = 'Perbarui Pos Arus Kas';
            }

            if (posBagianSelect) posBagianSelect.value = kelompokBagian;
            if (posSifatSelect) posSifatSelect.value = sifatPos;
            if (posNamaInput) posNamaInput.value = namaPos;
            if (posKodeInput) posKodeInput.value = kodePos;
            if (posUrutanInput) posUrutanInput.value = urutan;
            if (posPakaiPrefixInput) posPakaiPrefixInput.checked = pakaiPrefix;
            if (posIsActiveInput) posIsActiveInput.checked = isActive;

            if (parentCoaSelectEl) {
                parentCoaSelectEl.value = '';
                if ($ && $(parentCoaSelectEl).data('select2')) {
                    $(parentCoaSelectEl).val('').trigger('change');
                }
            }
            resetFeedback(parentLeavesFeedbackEl);
            syncPrefixToggleState();

            populateCoaSelect(coaIdsSelectEl, {
                modeOrSifat: sifatPos,
                ignorePosId: posId,
                selectedIds: coaIds,
            });

            window.bootstrap.Modal.getOrCreateInstance(modalPosEl).show();
        };

        if (posSifatSelect) {
            posSifatSelect.addEventListener('change', function () {
                const currentSelected = getSelectedValues(coaIdsSelectEl);
                syncPrefixToggleState();
                resetFeedback(parentLeavesFeedbackEl);
                populateCoaSelect(coaIdsSelectEl, {
                    modeOrSifat: this.value,
                    ignorePosId: getCurrentIgnorePosId(),
                    selectedIds: currentSelected,
                });
            });
        }

        document.querySelectorAll('.btn-open-create-pos').forEach((btn) => {
            btn.addEventListener('click', function () {
                openPosModalForCreate({
                    kelompokBagian: this.getAttribute('data-kelompok-bagian') || 'penyesuaian_laba',
                });
            });
        });

        document.querySelectorAll('.btn-edit-pos').forEach((btn) => {
            btn.addEventListener('click', function () {
                openPosModalForEdit(this);
            });
        });

        bindParentLeavesAction({
            buttonEl: btnAddParentLeavesEl,
            parentSelectEl: parentCoaSelectEl,
            targetSelectEl: coaIdsSelectEl,
            feedbackEl: parentLeavesFeedbackEl,
            getSifatPos: () => (posSifatSelect ? posSifatSelect.value : 'aset'),
            getIgnorePosId: getCurrentIgnorePosId,
        });

        // ---------------------------------------------------------------------
        // 4. Modal Pengecualian Arus Kas Initialization & Handlers
        // ---------------------------------------------------------------------
        const modalPengecualianEl = document.getElementById('modalPengecualianArusKas');
        const pengecualianParentEl = document.getElementById('pengecualianParentCoaSelect');
        const btnAddPengecualianLeavesEl = document.getElementById('btnAddPengecualianParentLeaves');
        const pengecualianFeedbackEl = document.getElementById('pengecualianParentLeavesFeedback');
        const pengecualianCoaSelectEl = document.getElementById('pengecualianCoaIdsSelect');
        const pengecualianAlasanEl = document.getElementById('pengecualianAlasan');

        initSelect2InModal('#pengecualianParentCoaSelect', '#modalPengecualianArusKas', 'Pilih Akun Induk (Parent COA)...', false);
        initSelect2InModal('#pengecualianCoaIdsSelect', '#modalPengecualianArusKas', 'Pilih akun Neraca yang dikecualikan...', true);

        const openPengecualianModal = (preselectedCoaIds = []) => {
            if (!modalPengecualianEl) {
                return;
            }
            const oldAlert = document.getElementById('modalPengecualianValidationAlert');
            if (oldAlert) {
                oldAlert.classList.add('d-none');
            }
            if (pengecualianParentEl) {
                pengecualianParentEl.value = '';
                if ($ && $(pengecualianParentEl).data('select2')) {
                    $(pengecualianParentEl).val('').trigger('change');
                }
            }
            if (pengecualianAlasanEl) {
                pengecualianAlasanEl.value = '';
            }
            resetFeedback(pengecualianFeedbackEl);
            populateCoaSelect(pengecualianCoaSelectEl, {
                modeOrSifat: 'pengecualian',
                ignorePosId: null,
                selectedIds: preselectedCoaIds,
            });
        };

        document.querySelectorAll('.btn-open-create-pengecualian').forEach((btn) => {
            btn.addEventListener('click', function () {
                openPengecualianModal([]);
            });
        });

        bindParentLeavesAction({
            buttonEl: btnAddPengecualianLeavesEl,
            parentSelectEl: pengecualianParentEl,
            targetSelectEl: pengecualianCoaSelectEl,
            feedbackEl: pengecualianFeedbackEl,
            getSifatPos: () => 'pengecualian',
            getIgnorePosId: () => null,
        });

        // ---------------------------------------------------------------------
        // 5. Unmapped Mutated Neraca Banner Quick-Action Hooks
        // ---------------------------------------------------------------------
        document.querySelectorAll('.btn-quick-map-unmapped').forEach((btn) => {
            btn.addEventListener('click', function () {
                const coaId = Number(this.getAttribute('data-coa-id'));
                if (!coaId || !modalPosEl) {
                    return;
                }
                const inferredSifat = inferNeracaSifatFromCoa(coaId);
                const targetBagian = inferredSifat === 'ekuitas' ? 'pendanaan' : 'modal_kerja';

                openPosModalForCreate({
                    kelompokBagian: targetBagian,
                    sifatPos: inferredSifat,
                    preselectedCoaIds: [coaId],
                });
                window.bootstrap.Modal.getOrCreateInstance(modalPosEl).show();
            });
        });

        document.querySelectorAll('.btn-quick-exclude-unmapped').forEach((btn) => {
            btn.addEventListener('click', function () {
                const coaId = Number(this.getAttribute('data-coa-id'));
                if (!coaId || !modalPengecualianEl) {
                    return;
                }
                openPengecualianModal([coaId]);
                window.bootstrap.Modal.getOrCreateInstance(modalPengecualianEl).show();
            });
        });

        @if ($canUpdate)
        // ---------------------------------------------------------------------
        // 6. Struktur Laporan Row Reorder (↑ / ↓)
        // ---------------------------------------------------------------------
        const refreshSectionOrderControls = (tbody) => {
            const rows = Array.from(tbody.querySelectorAll('tr[data-pos-id]'));
            rows.forEach((tr, index) => {
                const urutanSpan = tr.querySelector('td:first-child span.fw-bold');
                if (urutanSpan) {
                    urutanSpan.textContent = String(index + 1);
                }
                const editBtn = tr.querySelector('.btn-edit-pos');
                if (editBtn) {
                    editBtn.setAttribute('data-urutan', String(index + 1));
                }
                const upBtn = tr.querySelector('.btn-reorder-pos[data-direction="up"]');
                const downBtn = tr.querySelector('.btn-reorder-pos[data-direction="down"]');
                if (upBtn) {
                    upBtn.disabled = index === 0;
                }
                if (downBtn) {
                    downBtn.disabled = index === rows.length - 1;
                }
            });
        };

        document.querySelectorAll('.btn-reorder-pos').forEach((btn) => {
            btn.addEventListener('click', async function () {
                const direction = this.getAttribute('data-direction');
                const kelompokBagian = this.getAttribute('data-kelompok-bagian');
                const row = this.closest('tr[data-pos-id]');
                const tbody = this.closest('.section-pos-tbody');

                if (!row || !tbody || !kelompokBagian) {
                    return;
                }

                const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
                if (!sibling || !sibling.hasAttribute('data-pos-id')) {
                    return;
                }

                const originalOrder = Array.from(tbody.querySelectorAll('tr[data-pos-id]'));
                if (direction === 'up') {
                    tbody.insertBefore(row, sibling);
                } else {
                    tbody.insertBefore(sibling, row);
                }

                const orderedIds = Array.from(tbody.querySelectorAll('tr[data-pos-id]'))
                    .map((tr) => Number(tr.getAttribute('data-pos-id')));

                const sectionButtons = tbody.querySelectorAll('.btn-reorder-pos');
                sectionButtons.forEach((button) => {
                    button.disabled = true;
                });

                try {
                    const response = await fetch(REORDER_URL, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            kelompok_bagian: kelompokBagian,
                            ordered_ids: orderedIds,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Gagal memperbarui urutan pos arus kas.');
                    }

                    refreshSectionOrderControls(tbody);
                } catch (err) {
                    originalOrder.forEach((tr) => tbody.appendChild(tr));
                    refreshSectionOrderControls(tbody);
                    window.alert(err.message || 'Gagal memperbarui urutan pos arus kas.');
                }
            });
        });
        @endif

        // ---------------------------------------------------------------------
        // 7. Auto-reopen Modal on Validation Errors ($errors->any())
        // ---------------------------------------------------------------------
        if (OLD_MODAL_STATE.hasErrors && window.bootstrap && window.bootstrap.Modal) {
            if (OLD_MODAL_STATE.target === 'modalPengecualianArusKas' && modalPengecualianEl) {
                populateCoaSelect(pengecualianCoaSelectEl, {
                    modeOrSifat: 'pengecualian',
                    ignorePosId: null,
                    selectedIds: OLD_MODAL_STATE.coaIds || [],
                });
                window.bootstrap.Modal.getOrCreateInstance(modalPengecualianEl).show();
            } else if (OLD_MODAL_STATE.target === 'modalPosArusKas' && modalPosEl) {
                syncPrefixToggleState();
                populateCoaSelect(coaIdsSelectEl, {
                    modeOrSifat: OLD_MODAL_STATE.sifatPos || 'penambah_laba',
                    ignorePosId: OLD_MODAL_STATE.posId,
                    selectedIds: OLD_MODAL_STATE.coaIds || [],
                });
                window.bootstrap.Modal.getOrCreateInstance(modalPosEl).show();
            }
        }
        @endif
    });
</script>
@endpush
