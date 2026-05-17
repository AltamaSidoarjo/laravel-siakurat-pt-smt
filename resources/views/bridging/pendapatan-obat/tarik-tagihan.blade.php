@extends('layouts.app')

@section('title', 'Tagihan Jual Obat SIMRS')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pendapatan-obat.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Tagihan Jual Obat SIMRS</span>
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
                                        <div class="col-md-4">
                                            <label class="form-label">Dari tanggal</label>
                                            <input type="date" name="startDate" class="form-control" value="{{ $startDate }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Sampai tanggal</label>
                                            <input type="date" name="endDate" class="form-control" value="{{ $endDate }}">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end gap-2">
                                            <a href="{{ route('bridging.pendapatan-obat.tarik-tagihan') }}" class="btn btn-light w-100">Reset</a>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form id="formImport" method="post" action="{{ route('bridging.pendapatan-obat.process-import') }}">
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
                                                    <th>Tanggal</th>
                                                    <th>Nama</th>
                                                    <th>Jenis jual</th>
                                                    <th>Keterangan</th>
                                                    <th>Ongkir</th>
                                                    <th>PPn</th>
                                                    <th>Grandtotal</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Import ke:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisJurnalUmum" value="JurnalUmum" checked>
                                            <label class="form-check-label" for="jenisJurnalUmum">Jurnal Umum</label>
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

            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                scrollCollapse: true,
                order: [[2, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                ajax: {
                    url: '{{ route('bridging.pendapatan-obat.load-tagihan-simrs') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    {
                        data: 'nomer_transaksi',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<input type="checkbox" class="row-checkbox" name="selectedNoTransaksi[]" value="${data}">`;
                        }
                    },
                    { data: 'nomer_transaksi', name: 'nomer_transaksi' },
                    { data: 'tanggal', name: 'tanggal' },
                    { data: 'nama_pelanggan', name: 'nama_pelanggan' },
                    { data: 'jenis_jual', name: 'jenis_jual' },
                    { data: 'keterangan', name: 'keterangan' },
                    {
                        data: 'ongkir',
                        name: 'ongkir',
                        className: 'text-end',
                        render: function (data) {
                            return Number(data).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        }
                    },
                    {
                        data: 'ppn',
                        name: 'ppn',
                        className: 'text-end',
                        render: function (data) {
                            return Number(data).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        }
                    },
                    {
                        data: 'grandtotal',
                        name: 'grandtotal',
                        className: 'text-end',
                        render: function (data) {
                            return Number(data).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
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

            document.getElementById('formImport')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;
                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu transaksi untuk diproses.');
                    return;
                }

                if (!window.confirm(`Apakah Anda yakin ingin mengirim ${total} data ke proses Jurnal Umum?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
