@php
    $isEdit = isset($coa);
    $hasChild = $hasChild ?? false;
@endphp

<script>
    window.jQuery(function () {
        const $ = window.jQuery;
        if (!$) return;

        const $kelompok = $('#arus_kas_kelompok');
        let referensiKelompok = $kelompok.data('referensi') || {};
        if (typeof referensiKelompok === 'string') {
            try {
                referensiKelompok = JSON.parse(referensiKelompok);
            } catch (e) {
                referensiKelompok = {};
            }
        }

        const updateKelompokOptions = (aktivitas, selectedToPreserve) => {
            const items = (aktivitas && referensiKelompok[aktivitas]) ? referensiKelompok[aktivitas] : [];
            const targetValue = (typeof selectedToPreserve !== 'undefined') ? selectedToPreserve : $kelompok.val();

            $kelompok.empty();
            $kelompok.append(new Option('Pilih atau ketik baru...', '', false, false));

            let hasTargetInItems = false;
            items.forEach((name) => {
                const isSelected = targetValue && (name.toLowerCase() === String(targetValue).toLowerCase());
                if (isSelected) {
                    hasTargetInItems = true;
                }
                $kelompok.append(new Option(name, name, isSelected, isSelected));
            });

            if (targetValue && !hasTargetInItems) {
                $kelompok.append(new Option(targetValue, targetValue, true, true));
            }

            $kelompok.trigger('change');
        };

        if ($.fn.select2) {
            $('#status_aktif').select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: Infinity
            });
            $('#parent_id, #tipe_coa, #arus_kas_aktivitas').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            $kelompok.select2({
                theme: 'bootstrap-5',
                width: '100%',
                tags: true,
                placeholder: 'Pilih atau ketik baru...',
                allowClear: true
            });

            updateKelompokOptions($('#arus_kas_aktivitas').val(), $kelompok.val());

            $('#arus_kas_aktivitas').on('change', function () {
                updateKelompokOptions($(this).val(), '');
            });

            $kelompok.on('select2:selecting', function (e) {
                const data = e.params.args ? e.params.args.data : e.params.data;
                const selectedText = (data ? (data.text || data.id) : '').trim();
                if (!selectedText) {
                    return;
                }

                const currentAktivitas = $('#arus_kas_aktivitas').val();
                const presetList = (currentAktivitas && referensiKelompok[currentAktivitas]) ? referensiKelompok[currentAktivitas] : [];
                const isPreset = presetList.some((item) => item.toLowerCase() === selectedText.toLowerCase());

                if (!isPreset) {
                    const confirmed = window.confirm('Pos kelompok arus kas ini belum terdaftar di master referensi. Apakah Anda yakin ingin menambahkan pos baru ini?');
                    if (!confirmed) {
                        e.preventDefault();
                    }
                }
            });
        } else {
            $('#arus_kas_aktivitas').on('change', function () {
                updateKelompokOptions($(this).val(), '');
            });
        }

        const toggleArusKas = () => window.jQuery('.arus-kas-field').toggle(window.jQuery('#tipe_coa').val() !== 'Kasbank');
        window.jQuery('#tipe_coa').on('change', toggleArusKas);
        toggleArusKas();

        @if (! $isEdit)
            $('#parent_id').on('change', function () {
                const $selectedOption = $(this).find('option:selected');
                const aktivitas = $selectedOption.attr('data-aktivitas') || '';
                const kelompok = $selectedOption.attr('data-kelompok') || '';

                $('#arus_kas_aktivitas').val(aktivitas).trigger('change');
                if (kelompok) {
                    updateKelompokOptions(aktivitas, kelompok);
                }
            });
        @endif

        @if ($isEdit && $hasChild)
            const $cascadeWrapper = $('#cascade_wrapper');
            const $cascadeCheckbox = $('#cascade_to_children');
            const $cascadeMode = $('#cascade_mode');
            const $form = $cascadeCheckbox.closest('form');
            const previewUrl = @json(route('bukubesar.coa.cascade-preview', $coa->id));

            const toggleCascadeAvailability = () => {
                const isKasbank = String($('#tipe_coa').val() || '').toLowerCase() === 'kasbank';
                const hasAktivitas = Boolean($('#arus_kas_aktivitas').val());
                const allowed = !isKasbank && hasAktivitas;

                $cascadeWrapper.toggle(allowed);
                $cascadeCheckbox.prop('disabled', !allowed);

                if (!allowed) {
                    $cascadeCheckbox.prop('checked', false);
                    $cascadeMode.val('');
                }
            };

            $('#tipe_coa, #arus_kas_aktivitas').on('change', function () {
                $cascadeMode.val('');
                toggleCascadeAvailability();
            });

            $('#arus_kas_kelompok, #cascade_to_children').on('change', function () {
                $cascadeMode.val('');
            });

            toggleCascadeAvailability();

            const submitWithCascadeMode = (mode) => {
                $cascadeMode.val(mode);
                const modalEl = document.getElementById('cascadeConfirmModal');
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                $form.trigger('submit');
            };

            $('#btnCascadeFillEmpty').on('click', function () {
                submitWithCascadeMode('fill_empty');
            });

            $('#btnCascadeOverwriteAll').on('click', function () {
                submitWithCascadeMode('overwrite_all');
            });

            $form.on('submit', function (e) {
                if (!$cascadeCheckbox.is(':checked') || $cascadeMode.val()) {
                    return;
                }

                e.preventDefault();

                $.ajax({
                    url: previewUrl,
                    method: 'GET',
                    dataType: 'json',
                    headers: {
                        'Accept': 'application/json'
                    }
                }).done(function (data) {
                    const existingCount = Number(data.existing_count || 0);
                    if (existingCount === 0) {
                        $cascadeMode.val('overwrite_all');
                        $form.trigger('submit');
                        return;
                    }

                    $('#modal_total_descendants').text(data.total_descendants ?? 0);
                    $('#modal_empty_count').text(data.empty_count ?? 0);
                    $('#modal_existing_count').text(existingCount);

                    const $list = $('#modal_existing_list').empty();
                    const samples = Array.isArray(data.existing_samples) ? data.existing_samples : [];
                    samples.forEach(function (item) {
                        const aktivitasLabel = item.aktivitas ? item.aktivitas : '-';
                        const kelompokLabel = item.kelompok ? item.kelompok : '-';
                        const $li = $('<li class="list-group-item py-1 px-2"></li>');
                        $li.text(`${item.kode} - ${item.nama} (${aktivitasLabel}: ${kelompokLabel})`);
                        $list.append($li);
                    });

                    if (existingCount > samples.length) {
                        const remaining = existingCount - samples.length;
                        const $more = $('<li class="list-group-item py-1 px-2 text-muted fst-italic"></li>');
                        $more.text(`...dan ${remaining} sub-akun lainnya`);
                        $list.append($more);
                    }

                    const modalEl = document.getElementById('cascadeConfirmModal');
                    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else if ($.fn.modal) {
                        $('#cascadeConfirmModal').modal('show');
                    }
                }).fail(function () {
                    window.alert('Gagal memuat pratinjau sub-akun. Silakan coba lagi.');
                });
            });
        @endif
    });
</script>
