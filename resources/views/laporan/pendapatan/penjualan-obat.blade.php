@extends('layouts.app')

@section('title', 'Laporan Pendapatan Penjualan Obat')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Laporan Pendapatan Penjualan Obat</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
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
                            <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
                                <a href="{{ route('laporan.pendapatan.penjualan-obat') }}" class="btn btn-light">Reset</a>
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
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Jenis</th>
                                    <th>Gudang</th>
                                    <th>Rekening</th>
                                    <th>Keterangan</th>
                                    <th>Ongkir</th>
                                    <th>PPN</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('laporan.pendapatan.penjualan-obat.load-data') }}',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                dom: 'Bfrltip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                order: [[1, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                pageLength: 10,
                columns: [
                    { data: 'nomer_transaksi', name: 'nomer_transaksi' },
                    { data: 'tanggal', name: 'tanggal' },
                    { data: 'nama_pelanggan', name: 'nama_pelanggan' },
                    { data: 'jenis_jual', name: 'jenis_jual' },
                    { data: 'kode_gudang', name: 'kode_gudang' },
                    { data: 'nama_rekening', name: 'nama_rekening' },
                    { data: 'keterangan', name: 'keterangan' },
                    { data: 'ongkir', name: 'ongkir', className: 'text-end' },
                    { data: 'ppn', name: 'ppn', className: 'text-end' },
                    { data: 'grandtotal', name: 'grandtotal', className: 'text-end' }
                ],
                language: {
                    emptyTable: 'Belum ada data pendapatan penjualan obat pada rentang ini.'
                }
            });
        });
    </script>
@endpush
