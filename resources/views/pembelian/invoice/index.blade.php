@extends('layouts.app')

@section('title', 'Invoice Pembelian')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Invoice Pembelian</span>
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
                                @include('partials.flash-message')
                                @include('partials.validation-errors')

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

                                        <hr>

                                        <div class="d-flex justify-content-end mt-0">
                                            <a href="{{ route('pembelian.invoice.index') }}" class="btn btn-light me-2">Reset</a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-success ms-2" data-bs-toggle="modal" data-bs-target="#exportCsvModal"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
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
                                                <th>Nomer faktur</th>
                                                <th>Tanggal faktur</th>
                                                <th>Tgl jth tempo</th>
                                                <th>Supplier</th>
                                                <th>Kode bangsal</th>
                                                <th>Kategori faktur</th>
                                                <th>Grandtotal</th>
                                                <th>Sudah terbayar</th>
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
    @include('partials.export-csv-modal', ['exportRoute' => route('pembelian.invoice.export-csv'), 'exportTitle' => 'Invoice Pembelian', 'startDate' => $startDate, 'endDate' => $endDate])
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
                dom: 'frltip',
                ajax: {
                    url: '{{ route('pembelian.invoice.load-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                order: [[1, 'desc']],
                columns: [
                    {
                        data: 'nomer_faktur',
                        name: 'nomer_faktur',
                        render: function (data, type, row) {
                            return `<a href="${row.nomer_link}" class="text-primary">${data}</a>`;
                        }
                    },
                    {
                        data: 'tanggal_faktur',
                        name: 'tanggal_faktur',
                        className: 'text-start'
                    },
                    {
                        data: 'tanggal_jatuh_tempo',
                        name: 'tanggal_jatuh_tempo',
                        className: 'text-start'
                    },
                    {
                        data: 'nama_supplier',
                        name: 'supplier.nama_supplier'
                    },
                    {
                        data: 'kode_bangsal',
                        name: 'kode_bangsal'
                    },
                    {
                        data: 'kategori_faktur',
                        name: 'kategori_faktur'
                    },
                    {
                        data: 'grandtotal_display',
                        name: 'grandtotal',
                        className: 'text-end'
                    },
                    {
                        data: 'sudah_terbayar_display',
                        name: 'sudah_terbayar',
                        className: 'text-end'
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
