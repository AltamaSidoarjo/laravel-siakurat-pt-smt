<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.querySelector('#table_data_detail tbody');
        const totalInput = document.getElementById('input_total');
        let detailIndex = tableBody.querySelectorAll('tr').length;

        function initSelect2(scope = document) {
            if (window.initSelect2Fields) {
                window.initSelect2Fields(scope, '.select2-coa');
                return;
            }

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
                    });
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

        function attachInputEvents(input) {
            input.addEventListener('input', function () {
                this.value = this.value.toString().replace(/[^\d]/g, '');
                updateTotal();
            });

            input.addEventListener('focus', function () {
                this.value = parseIdInteger(this.value).toString();
            });

            input.addEventListener('blur', function () {
                this.value = formatIdInteger(parseIdInteger(this.value));
                updateTotal();
            });
        }

        function createCoaOptions() {
            return `@foreach ($coaOptions as $coa)<option value="{{ $coa->id }}">{{ $coa->kode }} - {{ $coa->nama }}</option>@endforeach`;
        }

        function reindexRows() {
            let nextIndex = 0;

            tableBody.querySelectorAll('tr').forEach((row) => {
                row.querySelector('select').setAttribute('name', `rincian[${nextIndex}][coa_id]`);
                row.querySelector('.input-nominal').setAttribute('name', `rincian[${nextIndex}][nominal]`);
                row.querySelector('.input-catatan').setAttribute('name', `rincian[${nextIndex}][catatan]`);
                nextIndex += 1;
            });

            detailIndex = nextIndex;
        }

        function updateTotal() {
            let total = 0;

            tableBody.querySelectorAll('.input-nominal').forEach((input) => {
                total += parseIdInteger(input.value);
            });

            totalInput.value = formatIdInteger(total);
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
                    <input type="text" name="rincian[${detailIndex}][nominal]" value="0" class="form-control text-end input-nominal" required>
                </td>
                <td class="align-middle">
                    <input type="text" name="rincian[${detailIndex}][catatan]" class="form-control input-catatan">
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-light text-danger btn-delete-detail">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            `;

            tableBody.appendChild(row);
            detailIndex += 1;

            row.querySelectorAll('.input-nominal').forEach((input) => {
                input.value = formatIdInteger(parseIdInteger(input.value));
                attachInputEvents(input);
            });

            initSelect2(row);
            updateTotal();
        }

        document.getElementById('btn_add_detail')?.addEventListener('click', addRow);

        tableBody.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.btn-delete-detail');
            if (!deleteButton) {
                return;
            }

            deleteButton.closest('tr')?.remove();
            reindexRows();
            updateTotal();
        });

        tableBody.querySelectorAll('.input-nominal').forEach((input) => {
            attachInputEvents(input);
            input.value = formatIdInteger(parseIdInteger(input.value));
        });

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function () {
                reindexRows();

                tableBody.querySelectorAll('.input-nominal').forEach((input) => {
                    input.value = parseIdInteger(input.value).toString();
                });

                totalInput.value = parseIdInteger(totalInput.value).toString();
            });
        });

        initSelect2(document);
        updateTotal();
    });
</script>
