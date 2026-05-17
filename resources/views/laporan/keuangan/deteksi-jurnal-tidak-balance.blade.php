@extends('layouts.app')

@section('title', 'Deteksi Jurnal Tidak Balance')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.keuangan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Deteksi Jurnal Tidak Balance</span>
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
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-warning fw-semibold">
                Grand total selisih: <span id="grandTotalSelisih">0</span>
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nomor</th>
                                    <th>Sumber transaksi</th>
                                    <th>Daftar COA</th>
                                    <th>Total debit</th>
                                    <th>Total kredit</th>
                                    <th>Selisih</th>
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

            window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                order: [[0, 'asc']],
                ajax: {
                    url: '{{ route('laporan.keuangan.deteksi-jurnal-tidak-balance.load-data') }}',
                    type: 'GET',
                    dataSrc: function (json) {
                        document.getElementById('grandTotalSelisih').textContent = json.grandTotalSelisih ?? '0';
                        return json.data;
                    },
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    { data: 'tanggal', name: 'tanggal' },
                    { data: 'nomer', name: 'nomer' },
                    { data: 'sumber_transaksi', name: 'sumber_transaksi' },
                    { data: 'daftar_coa', name: 'daftar_coa' },
                    { data: 'total_debit', name: 'total_debit', className: 'text-end' },
                    { data: 'total_kredit', name: 'total_kredit', className: 'text-end' },
                    { data: 'selisih', name: 'selisih', className: 'text-end fw-bold text-danger' }
                ]
            });
        });
    </script>
@endpush
