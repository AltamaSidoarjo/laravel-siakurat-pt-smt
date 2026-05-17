@extends('layouts.app')

@section('title', 'Deteksi Jurnal Tidak Balance')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Deteksi Jurnal Tidak Balance</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form method="get" action="">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Dari tanggal</label>
                                            <input type="date" name="startDate" id="startDate" class="form-control" value="{{ $startDate }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Sampai tanggal</label>
                                            <input type="date" name="endDate" id="endDate" class="form-control" value="{{ $endDate }}">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                            <button type="button" class="btn btn-warning" id="btnDeteksi">
                                                <i class="bi bi-search me-1"></i> Deteksi
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form id="formHapusMassal" method="post" action="{{ route('bridging.pendapatan.destroy-bulk') }}">
                                    @csrf

                                    <div class="alert alert-info fw-bold mb-3">
                                        Total data terpilih: <span id="selectedCount">0</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width: 40px;">
                                                        <input type="checkbox" id="checkAllRows">
                                                    </th>
                                                    <th>No rawat</th>
                                                    <th>Tanggal reg</th>
                                                    <th class="text-end">Total debit</th>
                                                    <th class="text-end">Total kredit</th>
                                                    <th class="text-end">Selisih</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyTidakBalance">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">Klik tombol Deteksi untuk memuat data.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <button type="submit" class="btn btn-danger mt-3">
                                        <i class="bi bi-trash me-1"></i> Hapus Massal
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const updateSelectedCount = () => {
                document.getElementById('selectedCount').textContent = document.querySelectorAll('.row-checkbox:checked').length;
            };

            const renderRows = (rows) => {
                const tbody = document.getElementById('tbodyTidakBalance');
                tbody.innerHTML = '';

                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-success py-3">Tidak ditemukan data jurnal tidak balance.</td></tr>';
                    updateSelectedCount();
                    return;
                }

                rows.forEach((item) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center">
                            <input type="checkbox" class="row-checkbox" name="selectedNoRawat[]" value="${item.no_rawat}">
                        </td>
                        <td>${item.no_rawat}</td>
                        <td>${item.tanggal_registrasi}</td>
                        <td class="text-end">${Number(item.total_debit).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end">${Number(item.total_kredit).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end">${Number(item.selisih).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    `;
                    tbody.appendChild(row);
                });

                updateSelectedCount();
            };

            document.getElementById('btnDeteksi')?.addEventListener('click', function () {
                const params = new URLSearchParams({
                    startDate: document.getElementById('startDate').value,
                    endDate: document.getElementById('endDate').value,
                });

                fetch(`{{ route('bridging.pendapatan.detect-tidak-balance') }}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                    .then((response) => response.json())
                    .then((response) => {
                        if (!response.success) {
                            window.alert(response.message || 'Gagal mendeteksi data tidak balance.');
                            return;
                        }

                        document.getElementById('checkAllRows').checked = false;
                        renderRows(response.data || []);
                    })
                    .catch(() => {
                        window.alert('Terjadi error saat deteksi data tidak balance.');
                    });
            });

            document.addEventListener('change', function (event) {
                if (event.target.matches('.row-checkbox')) {
                    updateSelectedCount();
                }
            });

            document.getElementById('checkAllRows')?.addEventListener('change', function () {
                document.querySelectorAll('.row-checkbox').forEach((checkbox) => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            document.getElementById('formHapusMassal')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;
                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu data untuk dihapus.');
                    return;
                }

                if (!window.confirm(`Apakah Anda yakin ingin menghapus ${total} data terpilih?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
