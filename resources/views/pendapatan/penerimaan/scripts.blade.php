<script>
    document.addEventListener('DOMContentLoaded', function () {
        const pelangganSelect = document.getElementById('select_pelanggan');
        const table = document.getElementById('table_data_detail');
        const tableBody = table?.querySelector('tbody');
        const totalPiutangAwalInput = document.getElementById('input_total_piutang_awal');
        const totalPiutangSisaInput = document.getElementById('input_total_piutang_sisa');
        const totalPembayaranInput = document.getElementById('input_total_pembayaran');
        const template = document.getElementById('detail-row-template');
        const invoiceAlert = document.getElementById('invoice_alert');

        if (!table || !tableBody || !totalPiutangAwalInput || !totalPiutangSisaInput || !totalPembayaranInput || !template) {
            return;
        }

        function initSelect2(scope = document) {
            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery(scope).find('.select2-coa, .select2-pelanggan').each(function () {
                const $select = window.jQuery(this);
                const $dropdownParent = $select.closest('.modal').length
                    ? $select.closest('.modal')
                    : window.jQuery(document.body);

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $dropdownParent
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
            return parseIdInteger(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function reindexRows() {
            tableBody.querySelectorAll('tr').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(/rincian\[\d+\]/, `rincian[${index}]`);
                });
            });
        }

        function updateTotals() {
            let totalPiutangAwal = 0;
            let totalPiutangSisa = 0;
            let totalPembayaran = 0;

            tableBody.querySelectorAll('tr').forEach((row) => {
                totalPiutangAwal += parseIdInteger(row.querySelector('.input-grandtotal')?.value);
                totalPiutangSisa += parseIdInteger(row.querySelector('.input-sisa-tagihan-raw')?.value);

                const checked = row.querySelector('.check')?.checked ?? false;
                if (checked) {
                    totalPembayaran += parseIdInteger(row.querySelector('.input-nominal-bayar')?.value);
                }
            });

            totalPiutangAwalInput.value = formatIdInteger(totalPiutangAwal);
            totalPiutangSisaInput.value = formatIdInteger(totalPiutangSisa);
            totalPembayaranInput.value = formatIdInteger(totalPembayaran);
        }

        function showTable() {
            table.classList.remove('d-none');
        }

        function hideTable() {
            table.classList.add('d-none');
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
            tableBody.innerHTML = '';
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
            clearRows();
            hideAlert();

            rows.forEach((item, index) => {
                const clone = template.content.cloneNode(true);
                populateRow(clone, index, item, false);
            });

            if (rows.length > 0) {
                showTable();
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

                tableBody.querySelectorAll('.input-nominal-bayar').forEach((input) => {
                    input.value = parseIdInteger(input.value).toString();
                });

                totalPembayaranInput.value = parseIdInteger(totalPembayaranInput.value).toString();
                totalPiutangAwalInput.value = parseIdInteger(totalPiutangAwalInput.value).toString();
                totalPiutangSisaInput.value = parseIdInteger(totalPiutangSisaInput.value).toString();
            });
        });

        tableBody.querySelectorAll('.input-nominal-bayar').forEach((input) => {
            input.value = formatIdInteger(input.value);
        });

        initSelect2(document);
        updateTotals();
    });
</script>
