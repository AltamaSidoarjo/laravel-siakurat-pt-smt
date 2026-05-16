<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.querySelector('#table_data_detail tbody');
        const totalDebitInput = document.getElementById('input_total_debit');
        const totalKreditInput = document.getElementById('input_total_kredit');
        const balanceStatus = document.getElementById('balance-status');
        const submitButtons = document.querySelectorAll('.btn_submit');
        let detailIndex = tableBody.querySelectorAll('tr').length;

        function initSelect2(scope = document) {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(scope).find('.select2-coa').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: window.jQuery('#table_data_detail')
                });
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
                totalDebit += parseIdInteger(row.querySelector('.input-debit')?.value);
                totalKredit += parseIdInteger(row.querySelector('.input-kredit')?.value);
            });

            totalDebitInput.value = formatIdInteger(totalDebit);
            totalKreditInput.value = formatIdInteger(totalKredit);

            const balanced = totalDebit === totalKredit && totalDebit > 0 && totalKredit > 0;
            submitButtons.forEach((button) => {
                button.disabled = !balanced;
            });

            balanceStatus.classList.toggle('text-success', balanced);
            balanceStatus.classList.toggle('text-danger', !balanced);
            balanceStatus.textContent = balanced
                ? '✅ Jurnal seimbang'
                : '⚠️ Debit dan Kredit belum seimbang';
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

        function createCoaOptions() {
            return `@foreach ($coaOptions as $coa)<option value="{{ $coa->id }}">{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach`;
        }

        function addRow() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="align-middle">
                    <select name="rincian[${detailIndex}][coa_id]" class="form-select select2-coa" required>
                        <option value="">Pilih Akun</option>
                        ${createCoaOptions()}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="text" name="rincian[${detailIndex}][debit]" value="0" class="form-control text-end input-debit" required>
                </td>
                <td class="align-middle">
                    <input type="text" name="rincian[${detailIndex}][kredit]" value="0" class="form-control text-end input-kredit" required>
                </td>
                <td class="align-middle">
                    <input type="text" name="rincian[${detailIndex}][catatan]" class="form-control">
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-light text-danger btn-delete-detail">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            `;

            tableBody.appendChild(row);
            detailIndex += 1;

            row.querySelectorAll('.input-debit, .input-kredit').forEach((input) => {
                input.value = formatIdInteger(parseIdInteger(input.value));
                attachInputEvents(input);
            });

            initSelect2(row);
            updateBalance();
        }

        document.getElementById('btn_add_detail')?.addEventListener('click', addRow);

        tableBody.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.btn-delete-detail');
            if (!deleteButton) return;

            deleteButton.closest('tr')?.remove();
            updateBalance();
        });

        tableBody.querySelectorAll('.input-debit, .input-kredit').forEach((input) => {
            attachInputEvents(input);
            input.value = formatIdInteger(parseIdInteger(input.value));
        });

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function () {
                tableBody.querySelectorAll('.input-debit, .input-kredit').forEach((input) => {
                    input.value = parseIdInteger(input.value).toString();
                });

                totalDebitInput.value = parseIdInteger(totalDebitInput.value).toString();
                totalKreditInput.value = parseIdInteger(totalKreditInput.value).toString();
            });
        });

        initSelect2(document);
        updateBalance();
    });
</script>
