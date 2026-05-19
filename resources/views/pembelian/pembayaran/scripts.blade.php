<script>
    document.addEventListener('DOMContentLoaded', function () {
        const supplierSelect = document.getElementById('select_supplier');
        const table = document.getElementById('table_data_detail');
        const tableBody = table?.querySelector('tbody');
        const totalHutangAwalInput = document.getElementById('input_total_hutang_awal');
        const totalHutangSisaInput = document.getElementById('input_total_hutang_sisa');
        const totalPembayaranInput = document.getElementById('input_total_pembayaran');
        const template = document.getElementById('detail-row-template');
        const invoiceAlert = document.getElementById('invoice_alert');

        if (!table || !tableBody || !totalHutangAwalInput || !totalHutangSisaInput || !totalPembayaranInput || !template) {
            return;
        }

        function initSelect2(scope = document) {
            if (window.initSelect2Fields) {
                window.initSelect2Fields(scope, '.select2-coa, .select2-supplier');
                return;
            }

            if (!(window.jQuery && window.jQuery.fn.select2)) {
                return;
            }

            window.jQuery(scope).find('.select2-coa, .select2-supplier').each(function () {
                const $select = window.jQuery(this);
                if ($select.data('select2')) {
                    return;
                }

                $select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: window.jQuery(document.body)
                });
            });
        }

        function parseAmount(value) {
            if (value === null || value === undefined || value === '') {
                return 0;
            }

            if (typeof value === 'number') {
                return Number.isFinite(value) ? Math.round(value) : 0;
            }

            let normalized = value.toString().trim();

            if (normalized === '') {
                return 0;
            }

            normalized = normalized.replace(/\s/g, '');

            const lastComma = normalized.lastIndexOf(',');
            const lastDot = normalized.lastIndexOf('.');

            if (lastComma !== -1 && lastDot !== -1) {
                if (lastComma > lastDot) {
                    normalized = normalized.replace(/\./g, '').replace(',', '.');
                } else {
                    normalized = normalized.replace(/,/g, '');
                }
            } else if (lastComma !== -1) {
                const decimalDigits = normalized.length - lastComma - 1;
                normalized = decimalDigits > 0 && decimalDigits <= 2
                    ? normalized.replace(/\./g, '').replace(',', '.')
                    : normalized.replace(/,/g, '');
            } else if (lastDot !== -1) {
                const decimalDigits = normalized.length - lastDot - 1;
                normalized = decimalDigits > 0 && decimalDigits <= 2
                    ? normalized.replace(/,/g, '')
                    : normalized.replace(/\./g, '');
            }

            normalized = normalized.replace(/[^\d.-]/g, '');

            const parsed = Number(normalized);

            return Number.isFinite(parsed) ? Math.round(parsed) : 0;
        }

        function formatIdInteger(value) {
            return parseAmount(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function reindexRows() {
            tableBody.querySelectorAll('tr').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(/rincian\[\d+\]/, `rincian[${index}]`);
                });
            });
        }

        function updateTotals() {
            let totalHutangAwal = 0;
            let totalHutangSisa = 0;
            let totalPembayaran = 0;

            tableBody.querySelectorAll('tr').forEach((row) => {
                totalHutangAwal += parseAmount(row.querySelector('.input-grandtotal')?.value);
                totalHutangSisa += parseAmount(row.querySelector('.input-sisa-tagihan-raw')?.value);

                const checked = row.querySelector('.check')?.checked ?? false;
                if (checked) {
                    totalPembayaran += parseAmount(row.querySelector('.input-nominal-bayar')?.value);
                }
            });

            totalHutangAwalInput.value = formatIdInteger(totalHutangAwal);
            totalHutangSisaInput.value = formatIdInteger(totalHutangSisa);
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

            clone.querySelector('.detail-nomor-faktur').textContent = `: ${item.nomer_faktur ?? '-'}`;
            clone.querySelector('.detail-tanggal-faktur').textContent = `: ${item.tanggal_faktur ?? '-'}`;
            clone.querySelector('.input-faktur-pembelian-id').value = item.id ?? item.faktur_pembelian_id ?? '';
            clone.querySelector('input[name$="[nomer_faktur]"]').value = item.nomer_faktur ?? '';
            clone.querySelector('input[name$="[tanggal_faktur]"]').value = item.tanggal_faktur ?? '';

            const grandTotal = parseAmount(item.grandtotal ?? 0);
            const sudahTerbayar = parseAmount(item.sudah_terbayar ?? 0);
            const nominalBayar = parseAmount(item.nominal_bayar ?? Math.max(grandTotal - sudahTerbayar, 0));
            const sisaTagihan = parseAmount(item.sisa_tagihan ?? Math.max(grandTotal - sudahTerbayar, 0));

            clone.querySelector('.input-grandtotal').value = grandTotal.toString();
            clone.querySelector('input[name$="[sudah_terbayar]"]').value = sudahTerbayar.toString();
            clone.querySelector('.input-sisa-tagihan-raw').value = sisaTagihan.toString();
            clone.querySelector('.input-grandtotal-display').value = formatIdInteger(grandTotal);
            clone.querySelector('.input-sisa-tagihan-display').value = formatIdInteger(sisaTagihan);
            clone.querySelector('.input-nominal-bayar').value = formatIdInteger(nominalBayar);
            clone.querySelector('.check').checked = checked;

            tableBody.appendChild(clone);
        }

        function renderRows(rows, checked = false) {
            clearRows();
            hideAlert();

            rows.forEach((item, index) => {
                const clone = template.content.cloneNode(true);
                populateRow(clone, index, item, checked);
            });

            if (rows.length > 0) {
                showTable();
            } else {
                hideTable();
                showAlert('Tidak ada invoice outstanding untuk supplier yang dipilih.', 'info');
            }

            updateTotals();
        }

        async function loadInvoicesBySupplier(supplierId) {
            if (!supplierId) {
                clearRows();
                hideAlert();
                hideTable();
                return;
            }

            const url = new URL('{{ route('pembelian.pembayaran.api.invoice-by-supplier') }}', window.location.origin);
            url.searchParams.set('id', supplierId);

            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Gagal memuat invoice supplier.');
            }

            const payload = await response.json();
            const invoices = payload?.data?.supplier?.faktur_pembelians ?? [];
            renderRows(invoices, false);
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

        if (supplierSelect) {
            supplierSelect.addEventListener('change', async function () {
                try {
                    await loadInvoicesBySupplier(this.value);
                } catch (error) {
                    console.error(error);
                    clearRows();
                    hideTable();
                    showAlert('Gagal memuat invoice outstanding. Silakan cek kembali data atau response endpoint.', 'danger');
                }
            });

            if (window.jQuery) {
                window.jQuery(supplierSelect).on('select2:select select2:clear', async function () {
                    try {
                        await loadInvoicesBySupplier(this.value);
                    } catch (error) {
                        console.error(error);
                        clearRows();
                        hideTable();
                        showAlert('Gagal memuat invoice outstanding. Silakan cek kembali data atau response endpoint.', 'danger');
                    }
                });
            }

            if (supplierSelect.value && tableBody.querySelectorAll('tr').length === 0) {
                loadInvoicesBySupplier(supplierSelect.value).catch((error) => {
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
                    input.value = parseAmount(input.value).toString();
                });

                totalPembayaranInput.value = parseAmount(totalPembayaranInput.value).toString();
                totalHutangAwalInput.value = parseAmount(totalHutangAwalInput.value).toString();
                totalHutangSisaInput.value = parseAmount(totalHutangSisaInput.value).toString();
            });
        });

        tableBody.querySelectorAll('.input-nominal-bayar').forEach((input) => {
            input.value = formatIdInteger(input.value);
        });

        initSelect2(document);
        updateTotals();
    });
</script>
