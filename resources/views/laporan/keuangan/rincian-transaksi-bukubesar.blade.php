@extends('layouts.app')

@section('title', 'Rincian Transaksi Bukubesar')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Rincian Transaksi Bukubesar</span>
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
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-success w-100" id="export-excel-button">
                                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
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
                                    <th>Akun</th>
                                    <th>Sumber transaksi</th>
                                    <th>Keterangan</th>
                                    <th>Debit</th>
                                    <th>Kredit</th>
                                </tr>
                            </thead>
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
            if (!(window.jQuery && window.jQuery.fn.DataTable)) {
                return;
            }

            const dataTable = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                dom: 'frtip',
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route('laporan.keuangan.rincian-transaksi-bukubesar.load-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    { data: 'nomer', name: 'nomer' },
                    { data: 'tanggal', name: 'tanggal' },
                    { data: 'coa', name: 'coa' },
                    { data: 'sumber_transaksi', name: 'sumber_transaksi' },
                    { data: 'keterangan', name: 'keterangan' },
                    { data: 'debit', name: 'debit', className: 'text-end' },
                    { data: 'kredit', name: 'kredit', className: 'text-end' }
                ]
            });

            document.getElementById('export-excel-button')?.addEventListener('click', function () {
                if (!(window.XLSX && window.XLSX.utils)) {
                    return;
                }

                const exportRows = [
                    ['Nomor', 'Tanggal', 'Akun', 'Sumber transaksi', 'Keterangan', 'Debit', 'Kredit']
                ];

                dataTable.rows({ search: 'applied' }).every(function () {
                    const row = this.data();
                    exportRows.push([
                        row.nomer ?? '',
                        row.tanggal ?? '',
                        row.coa ?? '',
                        row.sumber_transaksi ?? '',
                        row.keterangan ?? '',
                        row.debit ?? '',
                        row.kredit ?? ''
                    ]);
                });

                const worksheet = XLSX.utils.aoa_to_sheet(exportRows);
                const workbook = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(workbook, worksheet, 'RincianBukubesar');
                XLSX.writeFile(workbook, 'Rincian_Transaksi_Bukubesar_{{ $startDate }}_sd_{{ $endDate }}.xlsx');
            });
        });
    </script>
@endpush
