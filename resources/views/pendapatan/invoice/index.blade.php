@extends('layouts.app')

@section('title', 'Invoice Pendapatan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Invoice Pendapatan</span>
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
                                            <a href="{{ route('pendapatan.invoice.index') }}" class="btn btn-light w-100">Reset</a>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-funnel me-1"></i>Filter
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
                                                <th>Nomor</th>
                                                <th>Tanggal</th>
                                                <th>Dokter</th>
                                                <th>No. RM</th>
                                                <th>Pasien</th>
                                                <th>Poli</th>
                                                <th>Penjamin</th>
                                                <th>Nominal</th>
                                                <th>Sudah bayar</th>
                                                <th>Kurang bayar</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
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

            window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                scrollCollapse: true,
                dom: 'Bfrltip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                lengthMenu: [
                    [10, 25, 50, 100, 1000],
                    [10, 25, 50, 100, 1000]
                ],
                pageLength: 10,
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route('pendapatan.invoice.load-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    {
                        data: 'nomor_faktur',
                        name: 'nomor_faktur',
                        render: function (data, type, row) {
                            return `<a href="${row.nomer_link}" class="text-primary fw-bold">${data}</a>`;
                        }
                    },
                    {
                        data: 'tanggal_faktur',
                        name: 'tanggal_faktur'
                    },
                    {
                        data: 'nama_dokter',
                        name: 'nama_dokter'
                    },
                    {
                        data: 'nomer_rekam_medis',
                        name: 'nomer_rekam_medis'
                    },
                    {
                        data: 'nama_pasien',
                        name: 'nama_pasien'
                    },
                    {
                        data: 'nama_poli',
                        name: 'nama_poli'
                    },
                    {
                        data: 'nama_penjamin',
                        name: 'nama_penjamin'
                    },
                    {
                        data: 'nominal',
                        name: 'grandtotal',
                        className: 'text-end'
                    },
                    {
                        data: 'sudah_bayar',
                        name: 'sudah_terbayar',
                        className: 'text-end'
                    },
                    {
                        data: 'kurang_bayar',
                        name: 'kurang_bayar',
                        className: 'text-end',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_text',
                        name: 'status_proses',
                        className: 'text-center',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            if (data === 'Sudah Lunas') {
                                return '<span class="badge bg-success">Sudah Lunas</span>';
                            }

                            return '<span class="badge bg-danger">Belum Lunas</span>';
                        }
                    }
                ]
            });
        });
    </script>
@endpush
