<script>
    window.jQuery(function () {
        const $ = window.jQuery;
        if (!$) return;

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

            const $kelompok = $('#arus_kas_kelompok');
            let referensiKelompok = $kelompok.data('referensi') || {};
            if (typeof referensiKelompok === 'string') {
                try {
                    referensiKelompok = JSON.parse(referensiKelompok);
                } catch (e) {
                    referensiKelompok = {};
                }
            }

            $kelompok.select2({
                theme: 'bootstrap-5',
                width: '100%',
                tags: true,
                placeholder: 'Pilih atau ketik baru...',
                allowClear: true
            });

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
        }

        const toggleArusKas = () => window.jQuery('.arus-kas-field').toggle(window.jQuery('#tipe_coa').val() !== 'Kasbank');
        window.jQuery('#tipe_coa').on('change', toggleArusKas);
        toggleArusKas();
    });
</script>
