@php
    if ($activeMetode === 'langsung') {
        $oldLangsungModalState = [
            'hasErrors' => $errors->any(),
            'target' => (string) old('_modal_target', ''),
            'referensiId' => old('_referensi_id') !== null && old('_referensi_id') !== '' ? (int) old('_referensi_id') : null,
            'coaIds' => array_values(array_map('intval', array_filter((array) old('coa_ids', []), 'is_numeric'))),
        ];
    } else {
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
    }
@endphp

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ---------------------------------------------------------------------
        // 0. Pusat Panduan & Tur Interaktif Placeholder Alert
        // ---------------------------------------------------------------------
        const btnStartTour = document.getElementById('btnStartTour');
        const tourAlert = document.getElementById('tourPlaceholderAlert');
        if (btnStartTour && tourAlert) {
            btnStartTour.addEventListener('click', function () {
                tourAlert.classList.remove('d-none');
                tourAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }

        // ---------------------------------------------------------------------
        // Shared Pure JavaScript Utilities
        // ---------------------------------------------------------------------
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const initSelect2InModal = (selector, modalSelector, placeholderText) => {
            const $ = window.jQuery;
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
            });

            return $el;
        };

        const filterSelectOptions = (selectEl, rawQuery) => {
            if (!selectEl) {
                return;
            }
            const needle = String(rawQuery || '').trim().toLowerCase();
            Array.from(selectEl.options).forEach((opt) => {
                const text = String(opt.textContent || '').toLowerCase();
                const value = String(opt.value || '').toLowerCase();
                const matches = needle === '' || text.includes(needle) || value.includes(needle);
                opt.hidden = !matches;
                opt.style.display = matches ? '' : 'none';
            });
        };

        const bindSelectFilterInput = (inputEl, selectEl) => {
            if (!inputEl || !selectEl) {
                return;
            }
            inputEl.addEventListener('input', function () {
                filterSelectOptions(selectEl, this.value);
            });
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        };

        const getSelectedValues = (selectEl) => {
            if (!selectEl) {
                return [];
            }
            return Array.from(selectEl.selectedOptions).map((opt) => Number(opt.value));
        };

        const updateCoaCounterBadge = (selectEl, badgeEl = null) => {
            if (!selectEl) {
                return;
            }
            const count = Array.from(selectEl.selectedOptions).length;
            const targetBadge = badgeEl || (selectEl.id === 'coaIdsSelect' ? document.getElementById('coaCounterBadge') : null);
            if (targetBadge) {
                targetBadge.textContent = `${count} akun dipilih`;
            }
        };

        const setSelectedValues = (selectEl, ids, badgeEl = null) => {
            if (!selectEl) {
                return;
            }
            const idSet = new Set((ids || []).map((id) => String(id)));
            Array.from(selectEl.options).forEach((opt) => {
                if (!opt.disabled) {
                    opt.selected = idSet.has(opt.value);
                }
            });
            updateCoaCounterBadge(selectEl, badgeEl);
        };

        @if ($activeMetode === 'langsung')
        // ---------------------------------------------------------------------
        // METODE LANGSUNG: Filters, Modals, Detail COA, Parent Expansion & Quick Map
        // ---------------------------------------------------------------------
        const selectAktivitas = document.getElementById('selectAktivitasLangsung');
        const formFilterLangsung = document.getElementById('formFilterLangsung');
        if (selectAktivitas && formFilterLangsung) {
            selectAktivitas.addEventListener('change', function () {
                formFilterLangsung.submit();
            });
        }

        const searchLangsungInput = document.getElementById('searchLangsungInput');
        if (searchLangsungInput && formFilterLangsung) {
            searchLangsungInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    formFilterLangsung.submit();
                }
            });
        }

        // Detail COA Modal Handler (Available to all users including viewers)
        const modalDetailCoaEl = document.getElementById('modalDetailCoaLangsung');
        const detailCoaSubtitleEl = document.getElementById('modalDetailLangsungSubtitle');
        const detailLeafListEl = document.getElementById('modalDetailLangsungLeafList');
        const detailParentSectionEl = document.getElementById('modalDetailLangsungParentSection');
        const detailParentListEl = document.getElementById('modalDetailLangsungParentList');

        document.querySelectorAll('.btn-show-langsung-coas').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const posName = this.getAttribute('data-pos-name') || '';
                const aktivitas = this.getAttribute('data-pos-aktivitas') || '';
                let leafCoas = [];
                let parentCoas = [];
                try {
                    leafCoas = JSON.parse(this.getAttribute('data-leaf-coas') || '[]');
                } catch (e) {
                    leafCoas = [];
                }
                try {
                    parentCoas = JSON.parse(this.getAttribute('data-parent-coas') || '[]');
                } catch (e) {
                    parentCoas = [];
                }

                if (detailCoaSubtitleEl) {
                    const aktLabel = aktivitas.charAt(0).toUpperCase() + aktivitas.slice(1);
                    detailCoaSubtitleEl.textContent = `Pos: ${posName} (${aktLabel}) - Total ${leafCoas.length} Akun Leaf, ${parentCoas.length} Akun Induk`;
                }

                if (detailLeafListEl) {
                    detailLeafListEl.innerHTML = '';
                    if (leafCoas.length === 0) {
                        detailLeafListEl.innerHTML = '<li class="list-group-item text-muted text-center py-3">Tidak ada akun detail (leaf) yang terpetakan.</li>';
                    } else {
                        leafCoas.forEach(function (c) {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3';
                            const statusBadge = Number(c.status_aktif) !== 1
                                ? '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small ms-1">Nonaktif</span>'
                                : '';
                            li.innerHTML = `
                                <div class="me-2">
                                    <span class="font-monospace fw-semibold text-primary me-2">${escapeHtml(c.kode)}</span>
                                    <span class="text-dark">${escapeHtml(c.nama)}</span>
                                    ${statusBadge}
                                </div>
                            `;
                            detailLeafListEl.appendChild(li);
                        });
                    }
                }

                if (detailParentSectionEl && detailParentListEl) {
                    detailParentListEl.innerHTML = '';
                    if (parentCoas.length > 0) {
                        detailParentSectionEl.classList.remove('d-none');
                        parentCoas.forEach(function (p) {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3';
                            const statusBadge = Number(p.status_aktif) !== 1
                                ? '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small ms-1">Nonaktif</span>'
                                : '';
                            li.innerHTML = `
                                <div class="me-2">
                                    <span class="font-monospace fw-semibold text-secondary me-2">${escapeHtml(p.kode)}</span>
                                    <span class="text-dark">${escapeHtml(p.nama)}</span>
                                    ${statusBadge}
                                </div>
                            `;
                            detailParentListEl.appendChild(li);
                        });
                    } else {
                        detailParentSectionEl.classList.add('d-none');
                    }
                }

                if (modalDetailCoaEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalDetailCoaEl).show();
                }
            });
        });

        @if ($canCreate || $canUpdate)
        let pendingFormSubmit = null;
        const modalKonfirmasiEl = document.getElementById('modalKonfirmasiPindahCoaLangsung');
        const konfirmasiListEl = document.getElementById('konfirmasiPindahCoaList');
        const btnConfirmPindah = document.getElementById('btnConfirmPindahCoaLangsung');

        if (btnConfirmPindah) {
            btnConfirmPindah.addEventListener('click', function () {
                if (pendingFormSubmit) {
                    pendingFormSubmit.dataset.confirmedMove = 'true';
                    if (modalKonfirmasiEl && window.bootstrap && window.bootstrap.Modal) {
                        const modalInstance = window.bootstrap.Modal.getInstance(modalKonfirmasiEl);
                        if (modalInstance) modalInstance.hide();
                    }
                    pendingFormSubmit.submit();
                }
            });
        }

        const PARENT_LEAVES_LANGSUNG_URL = '{{ route('pengaturan.arus-kas.langsung.parent-coa-leaves') }}';

        const setupLangsungCoaPanel = (cfg) => {
            const {
                modalSelector,
                formEl,
                parentSelectEl,
                btnAddParentLeaves,
                feedbackEl,
                filterInputEl,
                coaSelectEl,
                counterBadgeEl,
                getCurrentGroupKey,
                getIgnoreReferensiId,
            } = cfg;

            if (!formEl || !coaSelectEl) {
                return;
            }

            if (parentSelectEl) {
                initSelect2InModal(parentSelectEl, modalSelector, '-- Pilih Akun Induk COA --');
            }

            if (filterInputEl) {
                bindSelectFilterInput(filterInputEl, coaSelectEl);
            }

            coaSelectEl.addEventListener('change', function () {
                updateCoaCounterBadge(coaSelectEl, counterBadgeEl);
            });

            if (btnAddParentLeaves && parentSelectEl && feedbackEl) {
                btnAddParentLeaves.addEventListener('click', function () {
                    const $ = window.jQuery;
                    const parentId = ($ && $(parentSelectEl).val()) || parentSelectEl.value;
                    if (!parentId) {
                        feedbackEl.className = 'small mb-2 alert alert-warning py-2 px-3';
                        feedbackEl.textContent = 'Silakan pilih akun induk terlebih dahulu.';
                        feedbackEl.classList.remove('d-none');
                        return;
                    }

                    btnAddParentLeaves.disabled = true;
                    btnAddParentLeaves.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';

                    let url = `${PARENT_LEAVES_LANGSUNG_URL}?parent_id=${encodeURIComponent(parentId)}`;
                    const ignoreId = getIgnoreReferensiId ? getIgnoreReferensiId() : null;
                    if (ignoreId) {
                        url += `&ignore_referensi_id=${encodeURIComponent(ignoreId)}`;
                    }

                    fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        const eligible = data.eligible || [];
                        const reassignable = data.reassignable || [];
                        const skipped = data.skipped || [];

                        // Add eligible to select
                        const eligibleSet = new Set(eligible.map(e => String(e.id)));
                        Array.from(coaSelectEl.options).forEach(opt => {
                            if (eligibleSet.has(String(opt.value))) {
                                opt.selected = true;
                            }
                        });

                        updateCoaCounterBadge(coaSelectEl, counterBadgeEl);

                        // Render feedback
                        let html = '';
                        if (eligible.length > 0) {
                            html += `<div class="fw-semibold text-success mb-1"><i class="bi bi-check-circle me-1"></i>${eligible.length} akun eligible berhasil ditambahkan ke pilihan.</div>`;
                        } else {
                            html += `<div class="fw-semibold text-muted mb-1"><i class="bi bi-info-circle me-1"></i>Tidak ada akun eligible baru yang dapat ditambahkan langsung.</div>`;
                        }

                        if (reassignable.length > 0) {
                            const reassignIds = JSON.stringify(reassignable.map(r => r.id));
                            html += `
                                <div class="mt-2 p-2 bg-warning-subtle rounded border border-warning-subtle text-dark">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>${reassignable.length} akun sudah terpetakan di pos lain:</span>
                                        <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-include-reassignable" data-reassign-ids='${escapeHtml(reassignIds)}'>
                                            + Sertakan ${reassignable.length} Akun dari Pos Lain
                                        </button>
                                    </div>
                                    <ul class="mb-0 ps-3 small">
                                        ${reassignable.slice(0, 5).map(r => `<li>${escapeHtml(r.kode)} - ${escapeHtml(r.nama)} (Pos: [${escapeHtml(r.current_aktivitas)}] ${escapeHtml(r.current_kelompok)})</li>`).join('')}
                                        ${reassignable.length > 5 ? `<li class="text-muted">...dan ${reassignable.length - 5} akun lainnya.</li>` : ''}
                                    </ul>
                                </div>
                            `;
                        }

                        if (skipped.length > 0) {
                            html += `
                                <div class="mt-2 text-muted small">
                                    <span class="fw-semibold">${skipped.length} akun dilewati:</span>
                                    <ul class="mb-0 ps-3">
                                        ${skipped.slice(0, 5).map(s => `<li>${escapeHtml(s.kode)} - ${escapeHtml(s.nama)}: ${escapeHtml(s.reason)}</li>`).join('')}
                                        ${skipped.length > 5 ? `<li>...dan ${skipped.length - 5} akun lainnya.</li>` : ''}
                                    </ul>
                                </div>
                            `;
                        }

                        feedbackEl.className = 'small mb-2 alert alert-light border py-2 px-3';
                        feedbackEl.innerHTML = html;
                        feedbackEl.classList.remove('d-none');

                        feedbackEl.querySelectorAll('.btn-include-reassignable').forEach(b => {
                            b.addEventListener('click', function () {
                                const ids = JSON.parse(this.getAttribute('data-reassign-ids') || '[]');
                                const idSet = new Set(ids.map(String));
                                Array.from(coaSelectEl.options).forEach(opt => {
                                    if (idSet.has(String(opt.value))) {
                                        opt.selected = true;
                                    }
                                });
                                updateCoaCounterBadge(coaSelectEl, counterBadgeEl);
                                this.disabled = true;
                                this.textContent = 'Sudah Disertakan';
                            });
                        });
                    })
                    .catch(err => {
                        feedbackEl.className = 'small mb-2 alert alert-danger py-2 px-3';
                        feedbackEl.textContent = 'Gagal memuat sub-akun. Silakan coba kembali.';
                        feedbackEl.classList.remove('d-none');
                    })
                    .finally(() => {
                        btnAddParentLeaves.disabled = false;
                        btnAddParentLeaves.innerHTML = '<i class="bi bi-plus me-1"></i> + Sub-Akun';
                    });
                });
            }

            formEl.addEventListener('submit', function (e) {
                if (formEl.dataset.confirmedMove === 'true') {
                    return;
                }

                const currentGroupKey = (getCurrentGroupKey ? getCurrentGroupKey() : '').toLowerCase();
                const movingOptions = [];

                Array.from(coaSelectEl.selectedOptions).forEach(opt => {
                    const optKey = (opt.getAttribute('data-current-group-key') || '').toLowerCase();
                    if (optKey !== '' && optKey !== currentGroupKey) {
                        movingOptions.push(opt);
                    }
                });

                if (movingOptions.length > 0) {
                    e.preventDefault();
                    if (konfirmasiListEl) {
                        konfirmasiListEl.innerHTML = '';
                        movingOptions.forEach(opt => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item py-2 px-3';
                            const kode = opt.getAttribute('data-kode') || '';
                            const nama = opt.getAttribute('data-nama') || '';
                            const fullText = opt.textContent || '';
                            const currentInfo = fullText.includes('(Saat ini:')
                                ? fullText.substring(fullText.indexOf('(Saat ini:'))
                                : '';
                            li.innerHTML = `
                                <strong>${escapeHtml(kode)} - ${escapeHtml(nama)}</strong>
                                <div class="text-danger small">${escapeHtml(currentInfo)}</div>
                            `;
                            konfirmasiListEl.appendChild(li);
                        });
                    }

                    pendingFormSubmit = formEl;
                    if (modalKonfirmasiEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalKonfirmasiEl).show();
                    }
                }
            });
        };
        @endif

        @if ($canCreate)
        setupLangsungCoaPanel({
            modalSelector: '#modalTambahReferensi',
            formEl: document.getElementById('formTambahReferensi'),
            parentSelectEl: document.getElementById('createParentCoaSelect'),
            btnAddParentLeaves: document.getElementById('btnCreateAddParentLeaves'),
            feedbackEl: document.getElementById('createParentLeavesFeedback'),
            filterInputEl: document.getElementById('filterCreateCoaOptionsInput'),
            coaSelectEl: document.getElementById('createCoaIdsSelect'),
            counterBadgeEl: document.getElementById('createCoaCounterBadge'),
            getCurrentGroupKey: () => {
                const akt = document.getElementById('createAktivitas')?.value || '';
                const nama = document.getElementById('createNama')?.value.trim() || '';
                return akt && nama ? `${akt}||${nama}` : '';
            },
            getIgnoreReferensiId: () => null,
        });

        const btnOpenCreate = document.getElementById('btnOpenCreateReferensi');
        if (btnOpenCreate) {
            btnOpenCreate.addEventListener('click', function () {
                const form = document.getElementById('formTambahReferensi');
                if (form) {
                    form.reset();
                    form.dataset.confirmedMove = 'false';
                }
                const selectCoa = document.getElementById('createCoaIdsSelect');
                const badge = document.getElementById('createCoaCounterBadge');
                if (selectCoa) {
                    Array.from(selectCoa.options).forEach(opt => opt.selected = false);
                    updateCoaCounterBadge(selectCoa, badge);
                }
                const filterInput = document.getElementById('filterCreateCoaOptionsInput');
                if (filterInput) {
                    filterInput.value = '';
                    filterSelectOptions(selectCoa, '');
                }
                const parentSelect = document.getElementById('createParentCoaSelect');
                if (parentSelect) {
                    const $ = window.jQuery;
                    if ($ && $.fn && $.fn.select2 && $(parentSelect).data('select2')) {
                        $(parentSelect).val('').trigger('change');
                    } else {
                        parentSelect.value = '';
                    }
                }
                const feedbackEl = document.getElementById('createParentLeavesFeedback');
                if (feedbackEl) {
                    feedbackEl.className = 'small mb-2 d-none';
                    feedbackEl.innerHTML = '';
                }
            });
        }
        @endif

        @if ($canUpdate)
        setupLangsungCoaPanel({
            modalSelector: '#modalEditReferensi',
            formEl: document.getElementById('formEditReferensi'),
            parentSelectEl: document.getElementById('editParentCoaSelect'),
            btnAddParentLeaves: document.getElementById('btnEditAddParentLeaves'),
            feedbackEl: document.getElementById('editParentLeavesFeedback'),
            filterInputEl: document.getElementById('filterEditCoaOptionsInput'),
            coaSelectEl: document.getElementById('editCoaIdsSelect'),
            counterBadgeEl: document.getElementById('editCoaCounterBadge'),
            getCurrentGroupKey: () => {
                return document.getElementById('editOriginalGroupKey')?.value || '';
            },
            getIgnoreReferensiId: () => {
                return document.getElementById('editReferensiId')?.value || null;
            },
        });

        const UPDATE_LANGSUNG_URL_TEMPLATE = '{{ route('pengaturan.arus-kas.langsung.update', ['referensi' => '__ID__']) }}';
        const modalEditEl = document.getElementById('modalEditReferensi');
        const modalEdit = modalEditEl && window.bootstrap && window.bootstrap.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(modalEditEl)
            : null;

        document.querySelectorAll('.btn-edit-referensi').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const aktivitas = this.getAttribute('data-aktivitas');
                const nama = this.getAttribute('data-nama');
                const status = this.getAttribute('data-status');
                let coaIds = [];
                try {
                    coaIds = JSON.parse(this.getAttribute('data-coa-ids') || '[]');
                } catch (e) {
                    coaIds = [];
                }

                const form = document.getElementById('formEditReferensi');
                if (form) {
                    form.action = UPDATE_LANGSUNG_URL_TEMPLATE.replace('__ID__', id);
                    form.dataset.confirmedMove = 'false';
                }

                const editIdEl = document.getElementById('editReferensiId');
                if (editIdEl) editIdEl.value = id;

                const editAktEl = document.getElementById('editReferensiAktivitas');
                if (editAktEl) editAktEl.value = aktivitas;

                const editLabelEl = document.getElementById('editAktivitasLabel');
                if (editLabelEl) editLabelEl.value = aktivitas;

                const editNamaEl = document.getElementById('editNama');
                if (editNamaEl) editNamaEl.value = nama;

                const editStatusEl = document.getElementById('editStatusAktif');
                if (editStatusEl) editStatusEl.value = status;

                const groupKey = (aktivitas + '||' + nama).toLowerCase();
                const editGroupKeyEl = document.getElementById('editOriginalGroupKey');
                if (editGroupKeyEl) editGroupKeyEl.value = groupKey;

                const editCoaSelect = document.getElementById('editCoaIdsSelect');
                const editCounterBadge = document.getElementById('editCoaCounterBadge');
                if (editCoaSelect) {
                    Array.from(editCoaSelect.options).forEach(function (opt) {
                        const optGroupKey = (opt.getAttribute('data-current-group-key') || '').toLowerCase();
                        const baseLabel = opt.getAttribute('data-base-label') || opt.textContent;
                        const currentLabel = opt.getAttribute('data-current-label') || '';
                        if (optGroupKey === groupKey) {
                            opt.textContent = baseLabel;
                        } else if (optGroupKey !== '') {
                            opt.textContent = baseLabel + currentLabel;
                        }
                    });

                    setSelectedValues(editCoaSelect, coaIds, editCounterBadge);
                }

                const filterInput = document.getElementById('filterEditCoaOptionsInput');
                if (filterInput) {
                    filterInput.value = '';
                    filterSelectOptions(editCoaSelect, '');
                }

                const parentSelect = document.getElementById('editParentCoaSelect');
                if (parentSelect) {
                    const $ = window.jQuery;
                    if ($ && $.fn && $.fn.select2 && $(parentSelect).data('select2')) {
                        $(parentSelect).val('').trigger('change');
                    } else {
                        parentSelect.value = '';
                    }
                }

                const feedbackEl = document.getElementById('editParentLeavesFeedback');
                if (feedbackEl) {
                    feedbackEl.className = 'small mb-2 d-none';
                    feedbackEl.innerHTML = '';
                }

                if (modalEdit) modalEdit.show();
            });
        });
        @endif

        @if ($canUpdate || $canCreate)
        const modalQuickMapEl = document.getElementById('modalQuickMapLangsung');
        const modalQuickMap = modalQuickMapEl && window.bootstrap && window.bootstrap.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(modalQuickMapEl)
            : null;

        document.querySelectorAll('.btn-quick-map-langsung').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const coaId = this.getAttribute('data-coa-id');
                const coaLabel = this.getAttribute('data-coa-label');

                const coaIdEl = document.getElementById('quickMapCoaId');
                if (coaIdEl) coaIdEl.value = coaId;

                const coaLabelEl = document.getElementById('quickMapCoaLabel');
                if (coaLabelEl) coaLabelEl.textContent = coaLabel;

                const selectRef = document.getElementById('quickMapReferensiSelect');
                if (selectRef) selectRef.value = '';

                if (modalQuickMap) modalQuickMap.show();
            });
        });

        @if ($canCreate)
        const btnQuickMapCreate = document.getElementById('btnQuickMapCreatePosBaru');
        if (btnQuickMapCreate) {
            btnQuickMapCreate.addEventListener('click', function () {
                const coaId = document.getElementById('quickMapCoaId')?.value;
                if (modalQuickMap) modalQuickMap.hide();

                const modalTambahEl = document.getElementById('modalTambahReferensi');
                if (modalTambahEl && window.bootstrap && window.bootstrap.Modal) {
                    const form = document.getElementById('formTambahReferensi');
                    if (form) {
                        form.reset();
                        form.dataset.confirmedMove = 'false';
                    }
                    const selectCoa = document.getElementById('createCoaIdsSelect');
                    const badge = document.getElementById('createCoaCounterBadge');
                    if (selectCoa) {
                        Array.from(selectCoa.options).forEach(opt => {
                            opt.selected = (String(opt.value) === String(coaId));
                        });
                        updateCoaCounterBadge(selectCoa, badge);
                    }
                    window.bootstrap.Modal.getOrCreateInstance(modalTambahEl).show();
                }
            });
        }
        @endif
        @endif

        @if ($errors->any())
            const oldTarget = '{{ old('_modal_target') }}';
            if (oldTarget === 'modalTambahReferensi') {
                const modalTambahEl = document.getElementById('modalTambahReferensi');
                if (modalTambahEl && window.bootstrap && window.bootstrap.Modal) {
                    const selectCoa = document.getElementById('createCoaIdsSelect');
                    const badge = document.getElementById('createCoaCounterBadge');
                    if (selectCoa) updateCoaCounterBadge(selectCoa, badge);
                    window.bootstrap.Modal.getOrCreateInstance(modalTambahEl).show();
                }
            } else if (oldTarget === 'modalEditReferensi') {
                const modalEditEl = document.getElementById('modalEditReferensi');
                if (modalEditEl && window.bootstrap && window.bootstrap.Modal) {
                    const selectCoa = document.getElementById('editCoaIdsSelect');
                    const badge = document.getElementById('editCoaCounterBadge');
                    if (selectCoa) updateCoaCounterBadge(selectCoa, badge);
                    window.bootstrap.Modal.getOrCreateInstance(modalEditEl).show();
                }
            } else if (oldTarget === 'modalQuickMapLangsung') {
                const modalQuickEl = document.getElementById('modalQuickMapLangsung');
                if (modalQuickEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalQuickEl).show();
                }
            }
        @endif

        @else
        // ---------------------------------------------------------------------
        // METODE TIDAK LANGSUNG: Switcher, Table/Struktur, COA Pickers & Reorder
        // ---------------------------------------------------------------------
        const VIEW_MODE_STORAGE_KEY = 'siakurat.pengaturan_arus_kas.view_mode';

        const viewModeTabEl = document.getElementById('viewModeTab');
        const viewModeStrukturEl = document.getElementById('viewModeStruktur');
        const subBarModeTabEl = document.getElementById('subBarModeTab');
        const subBarModeStrukturEl = document.getElementById('subBarModeStruktur');
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

            if (subBarModeTabEl && subBarModeStrukturEl) {
                if (normalizedMode === 'struktur') {
                    subBarModeTabEl.classList.add('d-none');
                    subBarModeStrukturEl.classList.remove('d-none');
                } else {
                    subBarModeTabEl.classList.remove('d-none');
                    subBarModeStrukturEl.classList.add('d-none');
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
                    // Ignore storage failures
                }
            }
        };

        let savedViewMode = 'tab';
        const urlParams = new URLSearchParams(window.location.search);
        const hasBagianOrSearchParam = urlParams.has('bagian') || urlParams.has('search');

        try {
            savedViewMode = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY) || 'tab';
        } catch (err) {
            savedViewMode = 'tab';
        }

        if (hasBagianOrSearchParam) {
            savedViewMode = 'tab';
        }

        applyViewMode(savedViewMode, false);

        modeButtons.forEach((btn) => {
            btn.addEventListener('click', function () {
                applyViewMode(this.getAttribute('data-view-mode'), true);
            });
        });

        // 1b. Mode Struktur Laporan: Expand All / Collapse All Sections
        const btnExpandAllSections = document.getElementById('btnExpandAllSections');
        const btnCollapseAllSections = document.getElementById('btnCollapseAllSections');

        if (btnExpandAllSections) {
            btnExpandAllSections.addEventListener('click', function () {
                document.querySelectorAll('#viewModeStruktur .collapse').forEach((el) => {
                    if (window.bootstrap && window.bootstrap.Collapse) {
                        window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
                    } else {
                        el.classList.add('show');
                    }
                });
            });
        }

        if (btnCollapseAllSections) {
            btnCollapseAllSections.addEventListener('click', function () {
                document.querySelectorAll('#viewModeStruktur .collapse').forEach((el) => {
                    if (window.bootstrap && window.bootstrap.Collapse) {
                        window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
                    } else {
                        el.classList.remove('show');
                    }
                });
            });
        }

        // 1c. Modal Detail COA Terpetakan
        const modalDetailCoaEl = document.getElementById('modalDetailCoaPos');
        const detailCoaListEl = document.getElementById('modalDetailCoaList');
        const detailCoaSubtitleEl = document.getElementById('modalDetailPosSubtitle');

        document.querySelectorAll('.btn-show-coas').forEach((btn) => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const posName = this.getAttribute('data-pos-name') || '';
                const posKode = this.getAttribute('data-pos-kode') || '';
                let coas = [];
                try {
                    coas = JSON.parse(this.getAttribute('data-coas') || '[]');
                } catch (err) {
                    coas = [];
                }

                if (detailCoaSubtitleEl) {
                    detailCoaSubtitleEl.textContent = `Pos: ${posName} (${posKode}) - Total ${coas.length} Akun COA`;
                }

                if (detailCoaListEl) {
                    detailCoaListEl.innerHTML = '';
                    if (coas.length === 0) {
                        detailCoaListEl.innerHTML = '<li class="list-group-item text-muted text-center py-3">Tidak ada akun COA terpetakan.</li>';
                    } else {
                        coas.forEach((c) => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3';
                            const statusBadge = Number(c.status_aktif) !== 1
                                ? '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small ms-1">Nonaktif</span>'
                                : '';
                            li.innerHTML = `
                                <div class="me-2">
                                    <span class="font-monospace fw-semibold text-primary me-2">${escapeHtml(c.kode)}</span>
                                    <span class="text-dark">${escapeHtml(c.nama)}</span>
                                    ${statusBadge}
                                </div>
                            `;
                            detailCoaListEl.appendChild(li);
                        });
                    }
                }

                if (modalDetailCoaEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalDetailCoaEl).show();
                }
            });
        });

        // 1d. Toolbar Bagian Dropdown & Search Input
        const selectBagianTidakLangsungEl = document.getElementById('selectBagianTidakLangsung');
        const formFilterTidakLangsungEl = document.getElementById('formFilterTidakLangsung');
        if (selectBagianTidakLangsungEl && formFilterTidakLangsungEl) {
            selectBagianTidakLangsungEl.addEventListener('change', function () {
                formFilterTidakLangsungEl.submit();
            });
        }

        const searchTidakLangsungInputEl = document.getElementById('searchTidakLangsungInput');
        if (searchTidakLangsungInputEl && formFilterTidakLangsungEl) {
            searchTidakLangsungInputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    formFilterTidakLangsungEl.submit();
                }
            });
        }

        @if ($canCreate || $canUpdate)
        const COA_META = @json($coaOptionsPayload);
        const MAX_URUTAN_BY_BAGIAN = @json($maxUrutanByBagian);
        const OLD_MODAL_STATE = @json($oldModalState);
        const PARENT_LEAVES_URL = '{{ route('pengaturan.arus-kas.tidak-langsung.parent-coa-leaves') }}';
        const STORE_POS_URL = '{{ route('pengaturan.arus-kas.tidak-langsung.store') }}';
        const UPDATE_POS_URL_TEMPLATE = '{{ route('pengaturan.arus-kas.tidak-langsung.update', ['settingArusKas' => '__POS_ID__']) }}';
        const REORDER_URL = '{{ route('pengaturan.arus-kas.tidak-langsung.reorder') }}';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        const $ = window.jQuery;
        const excludedIdSet = new Set((COA_META.excludedCoaIds || []).map((id) => Number(id)));
        const tipeLabaRugiSet = new Set(COA_META.tipeLabaRugi || []);
        const sifatLabaRugiSet = new Set(COA_META.sifatLabaRugi || []);
        const leafCoaById = new Map((COA_META.leafCoas || []).map((coa) => [Number(coa.id), coa]));

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

        const coaCounterBadgeEl = document.getElementById('coaCounterBadge');
        const filterCoaOptionsInputEl = document.getElementById('filterCoaOptionsInput');
        const filterPengecualianCoaInputEl = document.getElementById('filterPengecualianCoaInput');

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

            if (selectEl.id === 'coaIdsSelect') {
                updateCoaCounterBadge(selectEl);
                if (filterCoaOptionsInputEl && filterCoaOptionsInputEl.value !== '') {
                    filterSelectOptions(selectEl, filterCoaOptionsInputEl.value);
                }
            } else if (selectEl.id === 'pengecualianCoaIdsSelect' && filterPengecualianCoaInputEl && filterPengecualianCoaInputEl.value !== '') {
                filterSelectOptions(selectEl, filterPengecualianCoaInputEl.value);
            }
        };

        const resetFeedback = (feedbackEl) => {
            if (!feedbackEl) {
                return;
            }
            feedbackEl.className = 'small text-muted mb-2 d-none';
            feedbackEl.innerHTML = '';
        };

        const renderParentLeavesFeedback = (feedbackEl, eligible, skipped) => {
            if (!feedbackEl) {
                return;
            }

            const eligibleCount = eligible.length;
            const skippedCount = skipped.length;

            if (eligibleCount === 0 && skippedCount === 0) {
                feedbackEl.className = 'small text-muted mb-2 alert alert-secondary py-2 px-3';
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

            feedbackEl.className = `small mb-2 alert ${eligibleCount > 0 ? 'alert-light border' : 'alert-warning'} py-2 px-3`;
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
                        feedbackEl.className = 'small text-muted mb-2 alert alert-warning py-2 px-3';
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
                        feedbackEl.className = 'small text-muted mb-2 alert alert-danger py-2 px-3';
                        feedbackEl.textContent = err.message || 'Terjadi kesalahan saat mengambil daftar child COA.';
                    }
                } finally {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = originalHtml;
                }
            });
        };

        // 3. Modal Pos Arus Kas
        const modalPosEl = document.getElementById('modalPosArusKas');
        const formPosEl = document.getElementById('formPosArusKas');
        const posMethodInput = document.getElementById('posFormMethod');
        const posIdInput = document.getElementById('posFormId');
        const modalPosTitleEl = document.getElementById('modalPosArusKasLabel');
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

        initSelect2InModal('#parentCoaSelect', '#modalPosArusKas', '-- Pilih Induk COA untuk Tambah Cepat --');

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
            if (filterCoaOptionsInputEl) {
                filterCoaOptionsInputEl.value = '';
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
            if (filterCoaOptionsInputEl) {
                filterCoaOptionsInputEl.value = '';
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

        if (coaIdsSelectEl) {
            coaIdsSelectEl.addEventListener('change', function () {
                updateCoaCounterBadge(this);
            });
            updateCoaCounterBadge(coaIdsSelectEl);
        }
        bindSelectFilterInput(filterCoaOptionsInputEl, coaIdsSelectEl);

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

        // 4. Modal Pengecualian Arus Kas
        const modalPengecualianEl = document.getElementById('modalPengecualianArusKas');
        const pengecualianParentEl = document.getElementById('pengecualianParentCoaSelect');
        const btnAddPengecualianLeavesEl = document.getElementById('btnAddPengecualianParentLeaves');
        const pengecualianFeedbackEl = document.getElementById('pengecualianParentLeavesFeedback');
        const pengecualianCoaSelectEl = document.getElementById('pengecualianCoaIdsSelect');
        const pengecualianAlasanEl = document.getElementById('pengecualianAlasan');

        initSelect2InModal('#pengecualianParentCoaSelect', '#modalPengecualianArusKas', '-- Pilih Induk COA untuk Memilih Cepat --');

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
            if (filterPengecualianCoaInputEl) {
                filterPengecualianCoaInputEl.value = '';
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

        bindSelectFilterInput(filterPengecualianCoaInputEl, pengecualianCoaSelectEl);

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

        // 5. Unmapped Mutated Neraca Quick-Action Hooks
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
        // 6. Struktur Laporan Row Reorder
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

        // 7. Auto-reopen Modal on Validation Errors ($errors->any())
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
        @endif
    });
</script>
@endpush
