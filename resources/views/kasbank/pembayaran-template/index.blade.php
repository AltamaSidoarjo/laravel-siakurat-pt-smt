@extends('layouts.app')

@section('title', 'Template Kasbank Pembayaran')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('kasbank.pembayaran.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Template Kasbank Pembayaran</span>
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
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="text-muted small">
                                        <i class="bi bi-info-circle me-1"></i> Template digunakan untuk mempercepat pengisian rincian akun lawan beban/biaya pengeluaran kas/bank yang rutin atau berulang.
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('kasbank.pembayaran.index') }}" class="btn btn-light">
                                            <i class="bi bi-wallet2 me-1"></i> Ke Kasbank Pembayaran
                                        </a>
                                        <a href="{{ route('kasbank.pembayaran-template.create') }}" class="btn btn-success fw-bold">
                                            <i class="bi bi-plus-circle-fill me-1"></i> Tambah Template
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover align-middle" id="datatable">
                                        <thead>
                                            <tr>
                                                <th style="min-width: 200px;">Nama Template</th>
                                                <th style="min-width: 180px;">Bank Sumber Default</th>
                                                <th>Keterangan Default</th>
                                                <th class="text-center" style="width: 120px;">Jumlah Baris</th>
                                                <th class="text-end" style="width: 160px;">Total Nominal</th>
                                                <th class="text-center" style="width: 180px;">Aksi</th>
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
                ajax: '{{ route('kasbank.pembayaran-template.load-data') }}',
                columns: [
                    { data: 'nama', name: 'nama' },
                    { data: 'coa_display', name: 'coa_display', defaultContent: '-' },
                    { data: 'keterangan', name: 'keterangan', defaultContent: '-' },
                    { data: 'rincian_count', name: 'rincian_count', className: 'text-center' },
                    { data: 'total_nominal', name: 'total_nominal', className: 'text-end', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'asc']]
            });
        });
    </script>
@endpush
