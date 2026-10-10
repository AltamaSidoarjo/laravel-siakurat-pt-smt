<script>
    window.jQuery(function () {
        const tableBody = document.querySelector('#table_saldo_awal_detail tbody');
        const totalDebitInput = document.getElementById('input_total_debit');
        const totalKreditInput = document.getElementById('input_total_kredit');
        const selisihInput = document.getElementById('input_selisih');
        const balanceStatus = document.getElementById('balance-status');
        const btnSubmitLock = document.getElementById('btn-submit-lock');
        const btnAddRowTop = document.getElementById('btn-add-row');
        const btnAddRowBottom = document.getElementById('btn-add-row-bottom');

        if (!tableBody || !totalDebitInput || !totalKreditInput || !selisihInput || !balanceStatus) {
            return;
        }

        let detailIndex = tableBody.querySelectorAll('tr').length;

        function initSelect2(scope = document) {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(scope).find('.select2-coa').each(function () {
                    const $select = window.jQuery(this);
                    if ($select.data('select2')) {
                        return;
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: window.jQuery(document.body),
                        placeholder: '-- Pilih Akun COA --',
                        allowClear: true,
                    });

                    // Update tipe coa column
                    $select.on('change', function () {
                        updateTipeCoa(this);
                    });
                });
            }
        }

        function updateTipeCoa(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const tipe = selectedOption ? (selectedOption.getAttribute('data-tipe') || '-') : '-';
            const row = selectElement.closest('tr');
            if (row) {
                const tipeCol = row.querySelector('.col-tipe-coa');
                if (tipeCol) {
                    tipeCol.textContent = tipe;
                }
            }
        }

        function parseIdInteger(value) {
            if (!value) return 0;
            const cleaned = value.toString().trim().replace(/[^\d]/g, '');
            const parsed = parseInt(cleaned, 10);
            return Number.isNaN(parsed) ? 0 : parsed;
        }

        function formatIdInteger(value) {
            const number = Number.isNaN(value) || value === null ? 0 : value;
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function sanitizeTypingInteger(value) {
            let cleaned = (value ?? '').toString().replace(/[^\d]/g, '');
            if (cleaned === '') cleaned = '0';
            return cleaned;
        }

        function updateBalance() {
            let totalDebit = 0;
            let totalKredit = 0;

            tableBody.querySelectorAll('tr').forEach((row) => {
                const debitVal = parseIdInteger(row.querySelector('.input-debit')?.value);
                const kreditVal = parseIdInteger(row.querySelector('.input-kredit')?.value);
                totalDebit += debitVal;
                totalKredit += kreditVal;
            });

            const selisih = Math.abs(totalDebit - totalKredit);

            totalDebitInput.value = 'Rp ' + formatIdInteger(totalDebit);
            totalKreditInput.value = 'Rp ' + formatIdInteger(totalKredit);
            selisihInput.value = 'Rp ' + formatIdInteger(selisih);

            const balanced = (totalDebit === totalKredit) && (totalDebit > 0);

            if (btnSubmitLock) {
                btnSubmitLock.disabled = !balanced;
                if (!balanced) {
                    btnSubmitLock.title = 'Hanya dapat dikunci dan diposting jika Total Debit = Total Kredit (Selisih Rp 0)';
                } else {
                    btnSubmitLock.title = 'Kunci dan posting saldo awal ke Buku Besar';
                }
            }

            if (balanced) {
                balanceStatus.className = 'fw-bold text-success';
                balanceStatus.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> SEIMBANG (BALANCE)';
                selisihInput.classList.remove('text-danger');
                selisihInput.classList.add('text-success');
            } else {
                balanceStatus.className = 'fw-bold text-danger';
                balanceStatus.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> BELUM SEIMBANG';
                selisihInput.classList.remove('text-success');
                selisihInput.classList.add('text-danger');
            }
        }

        function attachInputEvents(input) {
            input.addEventListener('input', function () {
                this.value = sanitizeTypingInteger(this.value);
                updateBalance();
            });

            input.addEventListener('focus', function () {
                this.value = parseIdInteger(this.value).toString();
            });

            input.addEventListener('blur', function () {
                this.value = formatIdInteger(parseIdInteger(this.value));
                updateBalance();
            });
        }

        function renumberRows() {
            tableBody.querySelectorAll('tr').forEach((row, idx) => {
                const numCol = row.querySelector('.row-number');
                if (numCol) {
                    numCol.textContent = idx + 1;
                }
            });
        }

        function createCoaOptions() {
            return `@foreach ($coaOptions as $coa)<option value="{{ $coa->id }}" data-tipe="{{ $coa->tipe_coa }}">{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach`;
        }

        function addRow() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-center row-number"></td>
                <td>
                    <select name="rincian[${detailIndex}][coa_id]" class="form-select select2-coa" required>
                        <option value="">-- Pilih Akun COA --</option>
                        ${createCoaOptions()}
                    </select>
                </td>
                <td class="text-center text-muted small col-tipe-coa">-</td>
                <td>
                    <input type="text" name="rincian[${detailIndex}][debit]" value="0" class="form-control text-end input-debit" required>
                </td>
                <td>
                    <input type="text" name="rincian[${detailIndex}][kredit]" value="0" class="form-control text-end input-kredit" required>
                </td>
                <td>
                    <input type="text" name="rincian[${detailIndex}][catatan]" value="" class="form-control" placeholder="Opsional">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" title="Hapus Baris">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;

            tableBody.appendChild(row);
            detailIndex++;

            renumberRows();
            initSelect2(row);
            row.querySelectorAll('.input-debit, .input-kredit').forEach(attachInputEvents);
            updateBalance();
        }

        // Attach events to existing inputs
        tableBody.querySelectorAll('.input-debit, .input-kredit').forEach(attachInputEvents);
        tableBody.querySelectorAll('.select2-coa').forEach(updateTipeCoa);

        // Delete row click delegation
        tableBody.addEventListener('click', function (e) {
            const deleteBtn = e.target.closest('.btn-delete-row');
            if (!deleteBtn) return;

            const row = deleteBtn.closest('tr');
            if (tableBody.querySelectorAll('tr').length <= 1) {
                // If only 1 row, just reset values
                const select = row.querySelector('.select2-coa');
                if (select) {
                    window.jQuery(select).val('').trigger('change');
                }
                row.querySelector('.input-debit').value = '0';
                row.querySelector('.input-kredit').value = '0';
                row.querySelector('input[name*="[catatan]"]').value = '';
                updateBalance();
                return;
            }

            row.remove();
            renumberRows();
            updateBalance();
        });

        if (btnAddRowTop) {
            btnAddRowTop.addEventListener('click', addRow);
        }
        if (btnAddRowBottom) {
            btnAddRowBottom.addEventListener('click', addRow);
        }

        initSelect2();
        updateBalance();
    });
</script>

