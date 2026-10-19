@extends('layouts.app')

@section('title', 'Piutang Obat & BHP SIMRS')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pendapatan-obat.index') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
                <span class="fw-bold">Piutang Obat & BHP SIMRS</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah mb-2">
        <div class="card-body d-flex flex-column gap-3">
            @include('partials.flash-message')
            @include('partials.validation-errors')

            <div class="card border-light shadow-sm"><div class="card-body">
                <form method="get" action="">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Dari tanggal</label><input type="date" name="startDate" class="form-control" value="{{ $startDate }}"></div>
                        <div class="col-md-4"><label class="form-label">Sampai tanggal</label><input type="date" name="endDate" class="form-control" value="{{ $endDate }}"></div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <a href="{{ route('bridging.pendapatan-obat.tarik-piutang') }}" class="btn btn-light w-100">Reset</a>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                        </div>
                    </div>
                </form>
            </div></div>

            <div class="card border-light shadow-sm"><div class="card-body">
                <form id="formImportPiutang" method="post" action="{{ route('bridging.pendapatan-obat.process-import-piutang') }}">
                    @csrf
                    <div class="alert alert-info fw-bold">Total data terpilih: <span id="selectedCount">0</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                            <thead><tr>
                                <th class="text-center" style="width:40px"><input type="checkbox" id="checkAll"></th>
                                <th>Nomor Piutang</th><th>Tanggal</th><th>No. RM</th><th>Nama Pasien</th>
                                <th>Jenis Jual</th><th>Jatuh Tempo</th><th>Grandtotal</th><th>Uang Muka</th><th>Sisa Piutang</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="alert alert-warning mb-0">Grandtotal invoice dihitung dari rincian + PPN + ongkir. Nilai uang muka dicatat sebagai sudah terbayar. Transaksi yang tidak rekonsiliasi akan ditolak per transaksi.</div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send me-1"></i> Import ke Invoice Pendapatan</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!(window.jQuery && window.jQuery.fn.DataTable)) return;

            const updateSelectedCount = () => {
                document.getElementById('selectedCount').textContent = document.querySelectorAll('.row-checkbox:checked').length;
            };

            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                scrollCollapse: true,
                order: [[2, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                ajax: {
                    url: '{{ route('bridging.pendapatan-obat.load-piutang-simrs') }}',
                    type: 'GET',
                    data: function (d) { d.startDate = '{{ $startDate }}'; d.endDate = '{{ $endDate }}'; }
                },
                columns: [
                    { data: 'nomer_transaksi', orderable: false, searchable: false, className: 'text-center', render: data => `<input type="checkbox" class="row-checkbox" name="selectedNoTransaksi[]" value="${data}">` },
                    { data: 'nomer_transaksi', name: 'nomer_transaksi' },
                    { data: 'tanggal', name: 'tanggal' },
                    { data: 'nomer_rekam_medis', name: 'nomer_rekam_medis' },
                    { data: 'nama_pelanggan', name: 'nama_pelanggan' },
                    { data: 'jenis_jual', name: 'jenis_jual' },
                    { data: 'tanggal_jatuh_tempo', name: 'tanggal_jatuh_tempo' },
                    { data: 'grandtotal', name: 'grandtotal', className: 'text-end', render: data => Number(data).toLocaleString('id-ID') },
                    { data: 'uangmuka', name: 'uangmuka', className: 'text-end', render: data => Number(data).toLocaleString('id-ID') },
                    { data: 'sisapiutang', name: 'sisapiutang', className: 'text-end', render: data => Number(data).toLocaleString('id-ID') }
                ]
            });

            table.on('draw', function () { document.getElementById('checkAll').checked = false; updateSelectedCount(); });
            document.addEventListener('change', function (event) {
                if (event.target.matches('.row-checkbox')) updateSelectedCount();
            });
            document.getElementById('checkAll')?.addEventListener('change', function () {
                document.querySelectorAll('.row-checkbox').forEach(checkbox => { checkbox.checked = this.checked; });
                updateSelectedCount();
            });
            document.getElementById('formImportPiutang')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;
                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu transaksi piutang untuk diproses.');
                    return;
                }
                if (!window.confirm(`Import ${total} piutang terpilih ke Invoice Pendapatan?`)) event.preventDefault();
            });
        });
    </script>
@endpush
