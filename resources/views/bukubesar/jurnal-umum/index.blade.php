@extends('layouts.app')

@section('title', 'Jurnal Umum')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user?->hasModuleAccess('bukubesar.jurnal-umum', 'create') ?? false;
        $totalCount = $summary['total_count'] ?? 0;
        $totalNominal = $summary['total_nominal'] ?? 0;
    @endphp

    {{-- Page Header --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Kembali ke Dashboard">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-bold mb-0 text-dark">Jurnal Umum</h4>
                    <p class="text-muted small mb-0">Kelola dan pantau seluruh transaksi pencatatan jurnal umum keuangan</p>
                </div>
            </div>
        </div>
        <div class="col-md-5 d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
            <a href="{{ route('bukubesar.jurnal-umum-template.index') }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Template Jurnal</span>
            </a>
            @if ($canCreate)
                <a href="{{ route('bukubesar.jurnal-umum.import.form') }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-upload"></i>
                    <span>Import</span>
                </a>
                <a href="{{ route('bukubesar.jurnal-umum.create') }}" class="btn btn-success d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Tambah Jurnal</span>
                </a>
            @endif
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    {{-- Quick Metric Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon-wrap metric-icon-green">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div>
                    <div class="metric-title">Total Transaksi</div>
                    <div class="metric-value">{{ number_format($totalCount, 0, ',', '.') }}</div>
                    <div class="metric-desc">Jurnal dalam rentang filter</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon-wrap metric-icon-blue">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="metric-title">Total Perputaran Nominal</div>
                    <div class="metric-value">Rp {{ number_format($totalNominal, 0, ',', '.') }}</div>
                    <div class="metric-desc">Total nominal debit tercatat</div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon-wrap metric-icon-amber">
                    <i class="bi bi-calendar3"></i>
                </div>
                <div>
                    <div class="metric-title">Periode Terpilih</div>
                    <div class="metric-value fs-6 fw-bold">
                        {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                    </div>
                    <div class="metric-desc">Status rentang data aktif</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Unified Card Surface --}}
    <div class="dt-surface mb-4">
        {{-- Filter Toolbar --}}
        <div class="dt-filter-section">
            <form id="filterForm" method="get" action="{{ route('bukubesar.jurnal-umum.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" name="startDate" id="startDateInput" class="form-control border-start-0 ps-0" value="{{ $startDate }}" required>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" name="endDate" id="endDateInput" class="form-control border-start-0 ps-0" value="{{ $endDate }}" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-funnel"></i> Terapkan
                        </button>
                        <a href="{{ route('bukubesar.jurnal-umum.index') }}" class="btn btn-light btn-sm border" title="Reset filter ke default">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#exportCsvModal">
                            <i class="bi bi-filetype-csv"></i> Export CSV
                        </button>
                    </div>
                </div>

                {{-- Quick Presets --}}
                <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top border-light flex-wrap">
                    <span class="small text-muted fw-semibold me-1"><i class="bi bi-clock-history me-1"></i>Preset Cepat:</span>
                    <button type="button" class="btn-quick-preset" id="presetToday">Hari Ini</button>
                    <button type="button" class="btn-quick-preset" id="presetThisMonth">Bulan Ini</button>
                    <button type="button" class="btn-quick-preset" id="presetLastMonth">Bulan Lalu</button>
                </div>
            </form>
        </div>

        {{-- Table Toolbar (Custom Search & Page Length info) --}}
        <div class="dt-toolbar-custom">
            <div class="d-flex align-items-center gap-2 text-muted small">
                <i class="bi bi-table text-success"></i>
                <span class="fw-semibold text-dark">Daftar Transaksi Jurnal</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="dt-search-input-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="customSearchInput" class="form-control form-control-sm" placeholder="Cari nomor, keterangan..." autocomplete="off">
                </div>
            </div>
        </div>

        {{-- Table Surface Body --}}
        <div class="dt-surface-body">
            <div class="table-responsive">
                <table class="table table-modern" id="datatable" style="min-width: 950px;">
                    <thead>
                        <tr>
                            <th class="col-freeze-left" style="width: 190px; min-width: 190px;">Nomor</th>
                            <th style="width: 150px; min-width: 150px;">Tanggal</th>
                            <th style="width: 180px; min-width: 180px;" class="text-end">Nominal</th>
                            <th style="min-width: 280px;">Keterangan</th>
                            <th class="col-freeze-right text-center" style="width: 130px; min-width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <div class="d-flex align-items-center gap-2 text-danger">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                        <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="mb-1 text-muted">Apakah Anda yakin ingin menghapus jurnal umum ini?</p>
                    <div class="p-2 bg-light rounded border text-dark fw-bold font-monospace my-2" id="deleteTargetNomer">-</div>
                    <small class="text-danger d-block">
                        <i class="bi bi-info-circle me-1"></i>Tindakan ini akan menghapus data jurnal beserta sinkronisasi mutasi buku besar terkait.
                    </small>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <form id="deleteForm" method="post" action="">
                        @csrf
                        @method('delete')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Ya, Hapus Jurnal
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Export CSV Modal --}}
    @include('partials.export-csv-modal', [
        'exportRoute' => route('bukubesar.jurnal-umum.export-csv'),
        'exportTitle' => 'Jurnal Umum',
        'startDate' => $startDate,
        'endDate' => $endDate,
    ])
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!(window.jQuery && window.jQuery.fn.DataTable)) {
                return;
            }

            var $ = window.jQuery;

            // Helper format YYYY-MM-DD local time
            function formatLocalDate(date) {
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }

            // Quick Date Presets
            $('#presetToday').on('click', function () {
                var today = formatLocalDate(new Date());
                $('#startDateInput').val(today);
                $('#endDateInput').val(today);
                $('#filterForm').submit();
            });

            $('#presetThisMonth').on('click', function () {
                var now = new Date();
                var firstDay = formatLocalDate(new Date(now.getFullYear(), now.getMonth(), 1));
                var today = formatLocalDate(now);
                $('#startDateInput').val(firstDay);
                $('#endDateInput').val(today);
                $('#filterForm').submit();
            });

            $('#presetLastMonth').on('click', function () {
                var now = new Date();
                var firstDay = formatLocalDate(new Date(now.getFullYear(), now.getMonth() - 1, 1));
                var lastDay = formatLocalDate(new Date(now.getFullYear(), now.getMonth(), 0));
                $('#startDateInput').val(firstDay);
                $('#endDateInput').val(lastDay);
                $('#filterForm').submit();
            });

            // Initialize DataTable
            var dataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: false,
                dom: "<'d-none'f>t<'dt-surface-footer d-flex flex-wrap align-items-center justify-content-between gap-3'lip>",
                language: {
                    processing: '<div class="spinner-border spinner-border-sm text-success me-2" role="status"></div> Memuat data...',
                    zeroRecords: '<div class="text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Tidak ada transaksi jurnal yang ditemukan pada periode ini.</div>',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ transaksi',
                    infoEmpty: 'Menampilkan 0 sampai 0 dari 0 transaksi',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        first: '<i class="bi bi-chevron-double-left"></i>',
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                        last: '<i class="bi bi-chevron-double-right"></i>'
                    }
                },
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'Semua']
                ],
                pageLength: 10,
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route('bukubesar.jurnal-umum.load-data') }}',
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
                        className: 'text-start align-middle col-freeze-left',
                        render: function (data, type, row) {
                            if (!data) return '-';
                            var targetUrl = row.can_update ? (row.edit_url || row.nomer_link) : '#';
                            var title = row.can_update ? 'Klik untuk edit jurnal' : 'Nomor Jurnal';
                            return '<a href="' + targetUrl + '" class="journal-code-badge" title="' + title + '">' +
                                   '<i class="bi bi-receipt"></i><span>' + data + '</span></a>';
                        }
                    },
                    {
                        data: 'tanggal',
                        name: 'tanggal',
                        className: 'text-start align-middle',
                        render: function (data) {
                            if (!data) return '-';
                            return '<span class="text-secondary small fw-medium"><i class="bi bi-calendar2-check me-1 text-muted"></i>' + data + '</span>';
                        }
                    },
                    {
                        data: 'debit',
                        name: 'debit',
                        className: 'text-end align-middle',
                        render: function (data) {
                            if (!data) return '<span class="text-muted">Rp 0</span>';
                            return '<span class="fw-bold text-dark font-monospace">Rp ' + data + '</span>';
                        }
                    },
                    {
                        data: 'keterangan',
                        name: 'keterangan',
                        className: 'align-middle',
                        render: function (data) {
                            if (!data) return '<span class="text-muted fst-italic small">- Tidak ada keterangan -</span>';
                            return '<span class="text-secondary small">' + data + '</span>';
                        }
                    },
                    {
                        data: 'id',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center align-middle col-freeze-right',
                        render: function (data, type, row) {
                            var actions = '<div class="action-btn-group">';

                            if (row.print_url) {
                                actions += '<a href="' + row.print_url + '" target="_blank" class="btn-table-action btn-table-action-print" data-bs-toggle="tooltip" data-bs-title="Cetak Jurnal">' +
                                           '<i class="bi bi-printer"></i></a>';
                            }

                            if (row.can_update && (row.edit_url || row.nomer_link)) {
                                var editUrl = row.edit_url || row.nomer_link;
                                actions += '<a href="' + editUrl + '" class="btn-table-action btn-table-action-edit" data-bs-toggle="tooltip" data-bs-title="Edit Jurnal">' +
                                           '<i class="bi bi-pencil-square"></i></a>';
                            }

                            if (row.can_delete && row.destroy_url) {
                                actions += '<button type="button" class="btn-table-action btn-table-action-delete btn-delete-row" data-bs-toggle="tooltip" data-bs-title="Hapus Jurnal" data-nomer="' + (row.nomer || '-') + '" data-destroy-url="' + row.destroy_url + '">' +
                                           '<i class="bi bi-trash"></i></button>';
                            }

                            actions += '</div>';
                            return actions;
                        }
                    }
                ],
                drawCallback: function () {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('#datatable [data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl, { container: 'body' });
                    });
                }
            });

            // Connect Custom Search Input with Debounce
            var searchDebounceTimer;
            $('#customSearchInput').on('keyup input', function () {
                var term = this.value;
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function () {
                    dataTable.search(term).draw();
                }, 300);
            });

            // Handle Row Delete Click
            $(document).on('click', '.btn-delete-row', function () {
                var nomer = $(this).data('nomer');
                var destroyUrl = $(this).data('destroy-url');

                $('#deleteTargetNomer').text(nomer);
                $('#deleteForm').attr('action', destroyUrl);

                var modalEl = document.getElementById('confirmDeleteModal');
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            });
        });
    </script>
@endpush
