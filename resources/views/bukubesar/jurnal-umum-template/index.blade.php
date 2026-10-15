@extends('layouts.app')

@section('title', 'Template Jurnal Umum')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.jurnal-umum.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Template Jurnal Umum</span>
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
                                        <i class="bi bi-info-circle me-1"></i> Template digunakan untuk mempercepat pengisian baris rincian akun saat membuat Jurnal Umum.
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('bukubesar.jurnal-umum.index') }}" class="btn btn-light">
                                            <i class="bi bi-journal-text me-1"></i> Ke Jurnal Umum
                                        </a>
                                        <a href="{{ route('bukubesar.jurnal-umum-template.create') }}" class="btn btn-success fw-bold">
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
                                                <th>Keterangan Default</th>
                                                <th class="text-center" style="width: 120px;">Jumlah Baris</th>
                                                <th class="text-end" style="width: 150px;">Total Debit</th>
                                                <th class="text-end" style="width: 150px;">Total Kredit</th>
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
                ajax: '{{ route('bukubesar.jurnal-umum-template.load-data') }}',
                columns: [
                    { data: 'nama', name: 'nama' },
                    { data: 'keterangan', name: 'keterangan', defaultContent: '-' },
                    { data: 'rincian_count', name: 'rincian_count', className: 'text-center' },
                    { data: 'total_debit', name: 'total_debit', className: 'text-end', orderable: false, searchable: false },
                    { data: 'total_kredit', name: 'total_kredit', className: 'text-end', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'asc']]
            });
        });
    </script>
@endpush
