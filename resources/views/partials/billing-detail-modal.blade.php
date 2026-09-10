<div class="modal fade" id="billingDetailModal" tabindex="-1" aria-labelledby="billingDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="billingDetailModalLabel">Detail Data Billing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="billingDetailBody"></tbody>
                    </table>
                </div>

                <div id="billingAccountDetailSection" class="mt-4" hidden>
                    <h6 class="fw-bold">Rincian Akun Billing</h6>
                    <div id="billingAccountDetailLoading" class="text-muted">Memuat rincian billing...</div>
                    <div id="billingAccountDetailError" class="alert alert-warning mb-0" hidden></div>
                    <div id="billingAccountDetailTable" class="table-responsive" hidden>
                        <table class="table table-sm table-striped table-bordered mb-2">
                            <thead>
                                <tr>
                                    <th>Kode Akun</th>
                                    <th>Job</th>
                                    <th class="text-end">Biaya</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="billingAccountDetailBody"></tbody>
                        </table>
                        <div class="text-end fw-bold">
                            Total Rincian: <span id="billingAccountDetailTotal">Rp 0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        let activeRequest = null;
        let requestSequence = 0;

        const formatNumber = (value) => Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        });

        const renderAccountRows = (rows) => {
            const body = document.getElementById('billingAccountDetailBody');
            body.replaceChildren();

            rows.forEach((item) => {
                const row = document.createElement('tr');
                const values = [
                    item.akun || '-',
                    item.job || '-',
                    item.biaya === null ? '-' : `Rp ${formatNumber(item.biaya)}`,
                    item.jumlah === null ? '-' : formatNumber(item.jumlah),
                    item.subtotal === null ? '-' : `Rp ${formatNumber(item.subtotal)}`,
                ];

                values.forEach((value, index) => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    cell.classList.toggle('text-end', index >= 2);
                    row.appendChild(cell);
                });

                body.appendChild(row);
            });
        };

        const loadAccountDetail = async (query) => {
            const sequence = ++requestSequence;
            const section = document.getElementById('billingAccountDetailSection');
            const loading = document.getElementById('billingAccountDetailLoading');
            const error = document.getElementById('billingAccountDetailError');
            const table = document.getElementById('billingAccountDetailTable');

            activeRequest?.abort();
            activeRequest = new AbortController();
            section.hidden = false;
            loading.hidden = false;
            error.hidden = true;
            table.hidden = true;
            document.getElementById('billingAccountDetailBody').replaceChildren();

            try {
                const url = new URL(@json(route('bridging.pendapatan.load-billing-account-detail')), window.location.href);
                Object.entries(query).forEach(([key, value]) => url.searchParams.set(key, value));

                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                const payload = await response.json().catch(() => ({}));

                if (sequence !== requestSequence) {
                    return;
                }

                if (!response.ok) {
                    throw new Error(payload.message || 'Rincian billing gagal dimuat.');
                }

                if (!Array.isArray(payload.data) || payload.data.length === 0) {
                    throw new Error('Rincian akun billing tidak ditemukan.');
                }

                renderAccountRows(payload.data);
                document.getElementById('billingAccountDetailTotal').textContent = `Rp ${formatNumber(payload.grandTotal || 0)}`;
                table.hidden = false;
            } catch (exception) {
                if (exception.name === 'AbortError' || sequence !== requestSequence) {
                    return;
                }

                error.textContent = exception.message || 'Rincian billing gagal dimuat.';
                error.hidden = false;
            } finally {
                if (sequence === requestSequence) {
                    loading.hidden = true;
                }
            }
        };

        const show = (data, fields, detailQuery) => {
            const body = document.getElementById('billingDetailBody');
            body.replaceChildren();

            fields.forEach(([label, key, formatter]) => {
                const rawValue = data?.[key];
                const hasValue = rawValue !== null && rawValue !== undefined && String(rawValue).trim() !== '';
                const value = hasValue ? (formatter ? formatter(rawValue) : String(rawValue)) : '-';
                const row = document.createElement('tr');
                const labelCell = document.createElement('th');
                const valueCell = document.createElement('td');

                labelCell.className = 'bg-light';
                labelCell.style.width = '35%';
                labelCell.textContent = label;
                valueCell.textContent = value;
                row.append(labelCell, valueCell);
                body.appendChild(row);
            });

            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('billingDetailModal')).show();
            loadAccountDetail(detailQuery);
        };

        document.getElementById('billingDetailModal').addEventListener('hidden.bs.modal', () => {
            requestSequence++;
            activeRequest?.abort();
        });

        window.billingDetailModal = { show };
    })();
</script>
