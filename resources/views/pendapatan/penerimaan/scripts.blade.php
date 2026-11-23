<script>
    window.jQuery(function () {
        const pelangganSelect = document.getElementById('select_pelanggan');
        const table = document.getElementById('table_data_detail');
        const tableBody = table?.querySelector('tbody');
        const totalPiutangAwalInput = document.getElementById('input_total_piutang_awal');
        const totalPiutangSisaInput = document.getElementById('input_total_piutang_sisa');
        const totalRincianPiutangInput = document.getElementById('input_total_rincian_piutang');
        const hiddenJumlahPembayaran = document.getElementById('hidden_jumlah_pembayaran');
        const selisihTarifInput = document.getElementById('input_selisih_tarif');
        const nominalBankInput = document.getElementById('input_nominal_bank');
        const template = document.getElementById('detail-row-template');
        const invoiceAlert = document.getElementById('invoice_alert');
        const checkAllInvoices = document.getElementById('check_all_invoices');
        const inputDetailSearch = document.getElementById('input_detail_search');
        const btnClearDetailSearch = document.getElementById('btn_clear_detail_search');
        const detailSearchInfo = document.getElementById('detail_search_info');
        const detailSearchContainer = document.getElementById('detail_search_container');
        const noSearchResultsRow = document.getElementById('detail_no_search_results');

        if (!table || !tableBody || !totalPiutangAwalInput || !totalPiutangSisaInput || !totalRincianPiutangInput || !template) {
            return;
        }

        function initSelect2(scope = document) {
            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery(scope).find('.select2-coa, .select2-pelanggan').each(function () {
                const $select = window.jQuery(this);
                if ($select.data('select2')) {
                    return;
                }

                const placeholder = $select.find('option[value=""]').first().text().trim() || undefined;

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: window.jQuery(document.body),
                    placeholder: placeholder,
                    allowClear: !$select.prop('required'),
                });
            });
        }

        function parseIdInteger(value) {
            if (value === null || value === undefined || value === '') {
                return 0;
            }

            const cleaned = value.toString().replace(/[^\d-]/g, '');
            const parsed = parseInt(cleaned, 10);

            return Number.isNaN(parsed) ? 0 : parsed;
        }

        function formatIdInteger(value) {
            const num = parseIdInteger(value);
            const prefix = num < 0 ? '-' : '';
            return prefix + Math.abs(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function getSearchableRows() {
            return Array.from(tableBody.querySelectorAll('tr')).filter((row) => row.id !== 'detail_no_search_results');
        }

        function filterDetailRows(keyword = '') {
            const term = (keyword || '').toLowerCase().trim();
            const rows = getSearchableRows();
            const totalCount = rows.length;
            let matchCount = 0;

            rows.forEach((row) => {
                if (!term) {
                    row.classList.remove('d-none');
                    matchCount++;
                    return;
                }

                const nomor = row.querySelector('.detail-nomor-faktur')?.textContent?.toLowerCase() ?? '';
                const tanggal = row.querySelector('.detail-tanggal-faktur')?.textContent?.toLowerCase() ?? '';
                const pasien = row.querySelector('.detail-nama-pasien')?.textContent?.toLowerCase() ?? '';
                const norm = row.querySelector('.detail-norm')?.textContent?.toLowerCase() ?? '';

                const isMatch = nomor.includes(term) || tanggal.includes(term) || pasien.includes(term) || norm.includes(term);

                row.classList.toggle('d-none', !isMatch);
                if (isMatch) {
                    matchCount++;
                }
            });

            if (noSearchResultsRow) {
                noSearchResultsRow.classList.toggle('d-none', totalCount === 0 || matchCount > 0);
            }

            if (detailSearchInfo) {
                if (totalCount === 0) {
                    detailSearchInfo.textContent = '';
                } else if (term) {
                    detailSearchInfo.textContent = `Menampilkan ${matchCount} dari ${totalCount} faktur`;
                } else {
                    detailSearchInfo.textContent = `Menampilkan seluruh ${totalCount} faktur`;
                }
            }

            if (btnClearDetailSearch) {
                btnClearDetailSearch.classList.toggle('d-none', !term);
            }

            syncCheckAllInvoices();
        }

        function resetDetailSearch() {
            if (inputDetailSearch) {
                inputDetailSearch.value = '';
            }
            filterDetailRows('');
        }

        function reindexRows() {
            getSearchableRows().forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(/rincian\[\d+\]/, `rincian[${index}]`);
                });
            });
        }

        function updateNominalBank() {
            const totalRincian = parseIdInteger(totalRincianPiutangInput.value);
            const selisihTarif = parseIdInteger(selisihTarifInput?.value);
            const jumlahPembayaran = totalRincian + selisihTarif;

            if (hiddenJumlahPembayaran) {
                hiddenJumlahPembayaran.value = jumlahPembayaran.toString();
            }
            if (nominalBankInput) {
                nominalBankInput.value = formatIdInteger(jumlahPembayaran);
            }
        }

        function syncCheckAllInvoices() {
            if (!checkAllInvoices) {
                return;
            }

            const visibleRows = getSearchableRows().filter((row) => !row.classList.contains('d-none'));
            const visibleCheckboxes = visibleRows.map((row) => row.querySelector('.check')).filter(Boolean);
            const checkedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;

            checkAllInvoices.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
            checkAllInvoices.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
        }

        function updateTotals() {
            let totalPiutangAwal = 0;
            let totalPiutangSisa = 0;
            let totalRincian = 0;

            getSearchableRows().forEach((row) => {
                totalPiutangAwal += parseIdInteger(row.querySelector('.input-grandtotal')?.value);
                totalPiutangSisa += parseIdInteger(row.querySelector('.input-sisa-tagihan-raw')?.value);

                const checked = row.querySelector('.check')?.checked ?? false;
                if (checked) {
                    totalRincian += parseIdInteger(row.querySelector('.input-nominal-bayar')?.value);
                }
            });

            totalPiutangAwalInput.value = formatIdInteger(totalPiutangAwal);
            totalPiutangSisaInput.value = formatIdInteger(totalPiutangSisa);
            totalRincianPiutangInput.value = formatIdInteger(totalRincian);
            updateNominalBank();
            syncCheckAllInvoices();
        }

        function showTable() {
            table.classList.remove('d-none');
            if (detailSearchContainer) {
                detailSearchContainer.classList.remove('d-none');
            }
        }

        function hideTable() {
            table.classList.add('d-none');
            if (detailSearchContainer) {
                detailSearchContainer.classList.add('d-none');
            }
        }

        function showAlert(message, type = 'warning') {
            if (!invoiceAlert) {
                return;
            }

            invoiceAlert.className = `alert alert-${type} mb-3`;
            invoiceAlert.textContent = message;
            invoiceAlert.classList.remove('d-none');
        }

        function hideAlert() {
            if (!invoiceAlert) {
                return;
            }

            invoiceAlert.classList.add('d-none');
            invoiceAlert.textContent = '';
        }

        function clearRows() {
            tableBody.querySelectorAll('tr:not(#detail_no_search_results)').forEach((row) => row.remove());
            if (noSearchResultsRow) {
                noSearchResultsRow.classList.add('d-none');
            }
            updateTotals();
        }

        function populateRow(clone, index, item, checked = false) {
            clone.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__index__', index);
            });

            clone.querySelector('.detail-nomor-faktur').textContent = item.nomor_faktur ?? '-';
            clone.querySelector('.detail-tanggal-faktur').textContent = item.tanggal_faktur ?? '-';
            clone.querySelector('.detail-nama-pasien').textContent = item.nama_pasien ?? '-';
            clone.querySelector('.detail-norm').textContent = item.nomer_rekam_medis ?? '-';

            clone.querySelector('.input-faktur-penjualan-id').value = item.id ?? item.faktur_penjualan_id ?? '';
            clone.querySelector('input[name$="[nomor_faktur]"]').value = item.nomor_faktur ?? '';
            clone.querySelector('input[name$="[tanggal_faktur]"]').value = item.tanggal_faktur ?? '';
            clone.querySelector('input[name$="[nama_pasien]"]').value = item.nama_pasien ?? '';
            clone.querySelector('input[name$="[nomer_rekam_medis]"]').value = item.nomer_rekam_medis ?? '';

            const grandTotal = parseIdInteger(item.grandtotal ?? 0);
            const sudahTerbayar = parseIdInteger(item.sudah_terbayar ?? 0);
            const nominalBayar = parseIdInteger(item.nominal_bayar ?? Math.max(grandTotal - sudahTerbayar, 0));
            const sisaTagihan = parseIdInteger(item.sisa_tagihan ?? Math.max(grandTotal - sudahTerbayar, 0));

            clone.querySelector('.input-grandtotal').value = grandTotal.toString();
            clone.querySelector('input[name$="[sudah_terbayar]"]').value = sudahTerbayar.toString();
            clone.querySelector('.input-sisa-tagihan-raw').value = sisaTagihan.toString();
            clone.querySelector('.input-grandtotal-display').value = formatIdInteger(grandTotal);
            clone.querySelector('.input-sisa-tagihan-display').value = formatIdInteger(sisaTagihan);
            clone.querySelector('.input-nominal-bayar').value = formatIdInteger(nominalBayar);
            clone.querySelector('.check').checked = checked;

            tableBody.appendChild(clone);
        }

        function renderRows(rows) {
            resetDetailSearch();
            clearRows();
            hideAlert();

            rows.forEach((item, index) => {
                const clone = template.content.cloneNode(true);
                populateRow(clone, index, item, false);
            });

            if (rows.length > 0) {
                showTable();
                filterDetailRows('');
            } else {
                hideTable();
                showAlert('Tidak ada invoice outstanding untuk penjamin yang dipilih.', 'info');
            }

            updateTotals();
        }

        async function loadInvoicesByPelanggan(pelangganId) {
            if (!pelangganId) {
                clearRows();
                hideAlert();
                hideTable();
                return;
            }

            const url = new URL('{{ route('pendapatan.penerimaan.api.invoice-by-pelanggan') }}', window.location.origin);
            url.searchParams.set('id', pelangganId);

            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Gagal memuat invoice pelanggan.');
            }

            const payload = await response.json();
            const invoices = payload?.data?.pelanggan?.faktur_penjualans ?? [];
            renderRows(invoices);
        }

        tableBody.addEventListener('input', function (event) {
            if (event.target.classList.contains('input-nominal-bayar')) {
                event.target.value = event.target.value.replace(/[^\d]/g, '');
                updateTotals();
            }
        });

        tableBody.addEventListener('focusout', function (event) {
            if (event.target.classList.contains('input-nominal-bayar')) {
                event.target.value = formatIdInteger(event.target.value);
                updateTotals();
            }
        });

        tableBody.addEventListener('change', function (event) {
            if (event.target.classList.contains('check')) {
                updateTotals();
            }
        });

        if (checkAllInvoices) {
            checkAllInvoices.addEventListener('change', function () {
                const visibleRows = getSearchableRows().filter((row) => !row.classList.contains('d-none'));
                visibleRows.forEach((row) => {
                    const checkbox = row.querySelector('.check');
                    if (checkbox) {
                        checkbox.checked = this.checked;
                    }
                });

                updateTotals();
            });
        }

        if (inputDetailSearch) {
            inputDetailSearch.addEventListener('input', function () {
                filterDetailRows(this.value);
            });

            inputDetailSearch.addEventListener('search', function () {
                filterDetailRows(this.value);
            });
        }

        if (btnClearDetailSearch) {
            btnClearDetailSearch.addEventListener('click', function () {
                resetDetailSearch();
                inputDetailSearch?.focus();
            });
        }

        if (selisihTarifInput) {
            selisihTarifInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^\d-]/g, '').replace(/(?!^)-/g, '');
                updateNominalBank();
            });

            selisihTarifInput.addEventListener('focusout', function () {
                this.value = formatIdInteger(this.value);
                updateNominalBank();
            });
        }

        if (pelangganSelect) {
            pelangganSelect.addEventListener('change', async function () {
                try {
                    await loadInvoicesByPelanggan(this.value);
                } catch (error) {
                    console.error(error);
                    clearRows();
                    hideTable();
                    showAlert('Gagal memuat invoice outstanding. Silakan cek kembali data atau response endpoint.', 'danger');
                }
            });

            if (window.jQuery) {
                window.jQuery(pelangganSelect).on('select2:select select2:clear', async function () {
                    try {
                        await loadInvoicesByPelanggan(this.value);
                    } catch (error) {
                        console.error(error);
                        clearRows();
                        hideTable();
                        showAlert('Gagal memuat invoice outstanding. Silakan cek kembali data atau response endpoint.', 'danger');
                    }
                });
            }

            if (pelangganSelect.value && tableBody.querySelectorAll('tr').length === 0) {
                loadInvoicesByPelanggan(pelangganSelect.value).catch((error) => {
                    console.error(error);
                    clearRows();
                    hideTable();
                    showAlert('Gagal memuat invoice outstanding. Silakan cek kembali data atau response endpoint.', 'danger');
                });
            }
        }

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function () {
                reindexRows();

                getSearchableRows().forEach((row) => {
                    const input = row.querySelector('.input-nominal-bayar');
                    if (input) {
                        input.value = parseIdInteger(input.value).toString();
                    }
                });

                totalRincianPiutangInput.value = parseIdInteger(totalRincianPiutangInput.value).toString();
                totalPiutangAwalInput.value = parseIdInteger(totalPiutangAwalInput.value).toString();
                totalPiutangSisaInput.value = parseIdInteger(totalPiutangSisaInput.value).toString();

                if (selisihTarifInput) {
                    selisihTarifInput.value = parseIdInteger(selisihTarifInput.value).toString();
                }

                if (hiddenJumlahPembayaran) {
                    hiddenJumlahPembayaran.value = parseIdInteger(hiddenJumlahPembayaran.value).toString();
                }
            });
        });

        getSearchableRows().forEach((row) => {
            const input = row.querySelector('.input-nominal-bayar');
            if (input) {
                input.value = formatIdInteger(input.value);
            }
        });

        initSelect2(document);
        updateTotals();
        if (inputDetailSearch) {
            filterDetailRows(inputDetailSearch.value || '');
        }
    });
</script>
