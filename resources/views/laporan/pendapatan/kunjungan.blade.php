@extends('layouts.app')

@section('title', 'Laporan Pendapatan Kunjungan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laporan Pendapatan Kunjungan</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Dari tanggal</label>
                                <input type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sampai tanggal</label>
                                <input type="date" name="endDate" class="form-control" value="{{ $endDate }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Poli</label>
                                <input type="text" name="poli" class="form-control" value="{{ $poli }}" placeholder="Cari poli">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Penjamin</label>
                                <input type="text" name="penjamin" class="form-control" value="{{ $penjamin }}" placeholder="Cari penjamin">
                            </div>
                            <div class="col-md-12 d-flex justify-content-end gap-2">
                                <a href="{{ route('laporan.pendapatan.kunjungan') }}" class="btn btn-light">Reset</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#exportCsvModal">
                                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                            <thead>
                                <tr>
                                    <th>No. Billing</th>
                                    <th>Tanggal Registrasi</th>
                                    <th>Pasien</th>
                                    <th>Status Layanan</th>
                                    <th>Dokter</th>
                                    <th>Poli</th>
                                    <th>Penjamin</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="alert alert-success fw-bold mt-3" id="grandTotalInfo">
                        Grand Total Tagihan: <span id="grandTotalValue">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="exportCsvModal" tabindex="-1" aria-labelledby="exportCsvModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="get" action="{{ route('laporan.pendapatan.kunjungan.export-csv') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportCsvModalLabel">Export CSV Pendapatan Kunjungan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Dari tanggal</label>
                                <input type="date" name="startDate" class="form-control" value="{{ $startDate }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sampai tanggal</label>
                                <input type="date" name="endDate" class="form-control" value="{{ $endDate }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download me-1"></i> Download CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('laporan.pendapatan.kunjungan.load-data') }}',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                        d.poli = document.querySelector('[name="poli"]').value;
                        d.penjamin = document.querySelector('[name="penjamin"]').value;
                    }
                },
                dom: 'frltip',
                order: [[1, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                pageLength: 10,
                columns: [
                    { data: 'nomer_billing', name: 'nomer_billing' },
                    { data: 'tanggal_reg', name: 'tanggal_reg' },
                    { data: 'nama_pasien', name: 'nama_pasien' },
                    { data: 'status_layanan', name: 'status_layanan' },
                    { data: 'dokter', name: 'dokter' },
                    { data: 'poli', name: 'poli' },
                    { data: 'penjamin', name: 'penjamin' },
                    { data: 'total_tagihan', name: 'total_tagihan', className: 'text-end' }
                ],
                language: {
                    emptyTable: 'Belum ada data pendapatan kunjungan pada rentang ini.'
                }
            });

            table.on('xhr', function (e, settings, json) {
                if (json && json.grandTotal !== undefined) {
                    const formatted = Number(json.grandTotal).toLocaleString('id-ID');
                    document.getElementById('grandTotalValue').textContent = 'Rp ' + formatted;
                }
            });
        });
    </script>
@endpush
