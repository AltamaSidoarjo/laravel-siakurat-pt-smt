<script>
    document.addEventListener('DOMContentLoaded', function () {
        const body = document.querySelector('#invoice-detail-table tbody');
        const template = document.getElementById('invoice-detail-template');
        let nextIndex = body?.querySelectorAll('tr').length ?? 0;

        function initDetailSelect2(scope) {
            window.initSelect2Fields?.(scope, '.select2-detail');
        }

        function calculate() {
            let total = 0;
            body?.querySelectorAll('tr').forEach(row => {
                const subtotal = (parseFloat(row.querySelector('.detail-quantity')?.value) || 0) * (parseFloat(row.querySelector('.detail-price')?.value) || 0);
                row.querySelector('.detail-subtotal').value = subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                total += subtotal;
            });
            document.getElementById('invoice-grandtotal').value = total.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        body?.addEventListener('input', event => {
            if (event.target.matches('.detail-quantity, .detail-price')) calculate();
        });
        body?.addEventListener('click', event => {
            const button = event.target.closest('.remove-detail');
            if (!button || body.querySelectorAll('tr').length <= 1) return;
            button.closest('tr').remove();
            calculate();
        });
        document.getElementById('add-invoice-detail')?.addEventListener('click', function () {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[data-name]').forEach(input => {
                input.name = `rincian[${nextIndex}][${input.dataset.name}]`;
                input.removeAttribute('data-name');
            });
            const row = fragment.querySelector('tr');
            body.appendChild(fragment);
            nextIndex++;
            initDetailSelect2(row);
            calculate();
        });
        calculate();
    });
</script>
