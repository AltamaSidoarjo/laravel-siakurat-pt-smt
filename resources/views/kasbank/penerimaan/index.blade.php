@extends('layouts.app')

@section('title', 'Kasbank Penerimaan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Kasbank Penerimaan</span>
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
                                        <div class="col-md-4 d-flex align-items-end justify-content-end gap-2 flex-wrap">
                                            <a href="{{ route('kasbank.penerimaan.index') }}" class="btn btn-light">Reset</a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i>Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-success text-nowrap" data-bs-toggle="modal" data-bs-target="#exportCsvModal"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-3 align-items-center">
                                    <a href="{{ route('kasbank.penerimaan.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </a>
                                </div>
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
                                                <th>Bank</th>
                                                <th>Nominal</th>
                                                <th>Keterangan</th>
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
    @include('partials.export-csv-modal', ['exportRoute' => route('kasbank.penerimaan.export-csv'), 'exportTitle' => 'Kasbank Penerimaan', 'startDate' => $startDate, 'endDate' => $endDate])
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
                dom: 'frltip',
                lengthMenu: [
                    [10, 50, 1000, -1],
                    [10, 50, 1000, 'All']
                ],
                pageLength: 10,
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route('kasbank.penerimaan.load-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    {
                        data: 'nomer',
                        name: 'nomer',
                        className: 'text-start',
                        render: function (data, type, row) {
                            return `<a href="${row.nomer_link}">${data}</a>`;
                        }
                    },
                    {
                        data: 'tanggal',
                        name: 'tanggal',
                        className: 'text-start'
                    },
                    {
                        data: 'coa_display',
                        name: 'coa_id'
                    },
                    {
                        data: 'total',
                        name: 'total',
                        className: 'text-end'
                    },
                    {
                        data: 'keterangan',
                        name: 'keterangan'
                    }
                ]
            });
        });
    </script>
@endpush
