@extends('layouts.app')

@section('title', 'Tagihan Pembelian Obat & BHP SIMRS')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pembelian.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Tagihan Pembelian Obat & BHP SIMRS</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <div class="d-flex flex-column gap-3">
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
                                        <div class="col-12 d-flex justify-content-end gap-2">
                                            <a href="{{ route('bridging.pembelian.tarik-obat') }}" class="btn btn-light">Reset</a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form id="formImportObat" method="post" action="{{ route('bridging.pembelian.process-import-obat') }}">
                                    @csrf

                                    <div class="alert alert-info fw-bold mb-3">
                                        Total data terpilih: <span id="selectedCount">0</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width: 40px;">
                                                        <input type="checkbox" id="checkAll">
                                                    </th>
                                                    <th>Nomer</th>
                                                    <th>No. order</th>
                                                    <th>Tanggal</th>
                                                    <th>Tgl barang datang</th>
                                                    <th>Tgl jth tempo</th>
                                                    <th>Supplier</th>
                                                    <th>Kode bangsal</th>
                                                    <th>Status</th>
                                                    <th>Grandtotal</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Import ke:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisInvoicePembelian" value="InvoicePembelian" checked>
                                            <label class="form-check-label" for="jenisInvoicePembelian">Invoice Pembelian</label>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Metode tanggal pengakuan:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="metodeTanggalPengakuan" id="metodeTanggalInvoice" value="TanggalInvoice" checked>
                                            <label class="form-check-label" for="metodeTanggalInvoice">Tanggal Invoice SIMRS</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="metodeTanggalPengakuan" id="metodeTanggalBarangDatang" value="TanggalBarangDatang">
                                            <label class="form-check-label" for="metodeTanggalBarangDatang">Tanggal Barang Datang</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-3">
                                        <i class="bi bi-send me-1"></i> Kirim Data
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
            if (!(window.jQuery && window.jQuery.fn.DataTable)) {
                return;
            }

            const updateSelectedCount = () => {
                document.getElementById('selectedCount').textContent = document.querySelectorAll('.row-checkbox:checked').length;
            };

            const formatNominal = (value) => Number(value).toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                order: [[3, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                ajax: {
                    url: '{{ route('bridging.pembelian.load-tagihan-obat') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    {
                        data: 'no_faktur',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<input type="checkbox" class="row-checkbox" name="selectedNoTransaksi[]" value="${data}">`;
                        }
                    },
                    { data: 'no_faktur', name: 'no_faktur' },
                    { data: 'no_order', name: 'no_order' },
                    { data: 'tgl_faktur', name: 'tgl_faktur' },
                    { data: 'tgl_pesan', name: 'tgl_pesan' },
                    { data: 'tgl_tempo', name: 'tgl_tempo' },
                    { data: 'nama_suplier', name: 'nama_suplier' },
                    { data: 'kd_bangsal', name: 'kd_bangsal' },
                    { data: 'status', name: 'status' },
                    {
                        data: 'tagihan',
                        name: 'tagihan',
                        className: 'text-end',
                        render: function (data) {
                            return formatNominal(data);
                        }
                    }
                ]
            });

            table.on('draw', function () {
                document.getElementById('checkAll').checked = false;
                updateSelectedCount();
            });

            document.addEventListener('change', function (event) {
                if (event.target.matches('.row-checkbox')) {
                    updateSelectedCount();
                }
            });

            document.getElementById('checkAll')?.addEventListener('change', function () {
                document.querySelectorAll('.row-checkbox').forEach((checkbox) => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            document.getElementById('formImportObat')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;
                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu transaksi untuk diproses.');
                    return;
                }

                const metodeTanggal = document.querySelector('input[name="metodeTanggalPengakuan"]:checked')?.value ?? 'TanggalInvoice';

                if (!window.confirm(`Apakah Anda yakin ingin mengirim ${total} data ke proses Invoice Pembelian dengan metode tanggal ${metodeTanggal}?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
