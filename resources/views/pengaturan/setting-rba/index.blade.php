@extends('layouts.app')

@section('title', 'Setting RBA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Setting RBA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @include('partials.flash-message')
                        @include('partials.validation-errors')

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-3 align-items-center">
                                    <a href="{{ route('pengaturan.setting-rba.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-header">
                                <button class="btn btn-light w-100 text-start fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="true">
                                    <i class="bi bi-funnel-fill"></i> Filter Data
                                </button>
                            </div>
                            <div class="collapse show" id="filterSection">
                                <div class="card-body">
                                    <form id="filterForm" method="get" action="">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="yearFrom" class="form-label">Dari Tahun</label>
                                                <input type="number" min="1900" max="3000" name="yearFrom" id="yearFrom" class="form-control" value="{{ $yearFrom }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="yearTo" class="form-label">Sampai Tahun</label>
                                                <input type="number" min="1900" max="3000" name="yearTo" id="yearTo" class="form-control" value="{{ $yearTo }}">
                                            </div>
                                            <div class="col-12 d-flex gap-2">
                                                <a href="{{ route('pengaturan.setting-rba.index') }}" class="btn btn-sm btn-light fw-bold">
                                                    <i class="bi bi-x-circle"></i> Reset
                                                </a>
                                                <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                                    <i class="bi bi-funnel"></i> Filter
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered" id="datatable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center align-middle">COA</th>
                                                <th class="text-center align-middle">Tahun</th>
                                                <th class="text-center align-middle">Nominal</th>
                                                <th class="text-center align-middle">Catatan</th>
                                                <th class="text-center align-middle">Aksi</th>
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

            const datatable = window.jQuery('#datatable').DataTable({
                autoWidth: false,
                scrollX: true,
                scrollCollapse: true,
                processing: true,
                serverSide: true,
                dom: 'Bfrtip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                ajax: {
                    url: '{{ route('pengaturan.setting-rba.load-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.yearFrom = document.getElementById('yearFrom')?.value;
                        d.yearTo = document.getElementById('yearTo')?.value;
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'coa_display', name: 'coa.kode' },
                    { data: 'tahun', name: 'tahun', width: '120px' },
                    { data: 'nominal_display', name: 'total_nominal', width: '180px', className: 'text-end' },
                    { data: 'catatan', name: 'catatan', orderable: false },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        width: '100px',
                        className: 'text-center',
                        render: function (data) {
                            const action = '{{ url('/pengaturan/setting-rba') }}/' + data;
                            return `
                                <form method="post" action="${action}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                    @csrf
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            `;
                        }
                    }
                ]
            });

            document.getElementById('filterForm')?.addEventListener('submit', function (event) {
                event.preventDefault();

                const params = new URLSearchParams(new FormData(this));
                window.history.replaceState({}, '', `${this.action || window.location.pathname}?${params.toString()}`);
                datatable.ajax.reload();
            });
        });
    </script>
@endpush
