@extends('layouts.app')

@section('title', 'Bridging Pembelian')

@php
    $successResults = collect($results ?? [])->where('berhasil', true)->values();
    $failedResults = collect($results ?? [])->where('berhasil', false)->values();
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Bridging Pembelian</span>
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
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="{{ route('bridging.pembelian.tarik-obat') }}" class="btn btn-info text-white fw-bold">
                                        <i class="bi bi-download me-1"></i> Tarik Pembelian Obat & BHP
                                    </a>
                                    <a href="{{ route('bridging.pembelian.tarik-nonmedis') }}" class="btn btn-info text-white fw-bold">
                                        <i class="bi bi-download me-1"></i> Tarik Pembelian Barang Non Medis
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
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
                                        <div class="col-12 d-flex justify-content-end gap-2">
                                            <a href="{{ route('bridging.pembelian.index') }}" class="btn btn-light">Reset</a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        @if (($results ?? []) !== [])
                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    @if ($message)
                                        <div class="alert alert-info">{{ $message }}</div>
                                    @endif

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h5 class="text-success fw-bold">Berhasil ({{ $successResults->count() }})</h5>
                                            <ul class="list-group">
                                                @forelse ($successResults as $item)
                                                    <li class="list-group-item list-group-item-success">{{ $item['nomer_transaksi'] }}</li>
                                                @empty
                                                    <li class="list-group-item text-muted">Tidak ada data berhasil.</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="text-danger fw-bold">Gagal ({{ $failedResults->count() }})</h5>
                                            <ul class="list-group">
                                                @forelse ($failedResults as $item)
                                                    <li class="list-group-item list-group-item-danger">
                                                        <strong>{{ $item['nomer_transaksi'] }}</strong>
                                                        <div>{{ $item['alasan_gagal'] }}</div>
                                                    </li>
                                                @empty
                                                    <li class="list-group-item text-muted">Tidak ada data gagal.</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form id="formHapusMassal" method="post" action="{{ route('bridging.pembelian.destroy-bulk') }}">
                                    @csrf

                                    <div class="alert alert-info fw-bold mb-3">
                                        Total data terpilih: <span id="selectedCount">0</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width: 40px;">
                                                        <input type="checkbox" id="checkAll">
                                                    </th>
                                                    <th>Nomer</th>
                                                    <th>Tanggal</th>
                                                    <th>Tgl jth tempo</th>
                                                    <th>Supplier</th>
                                                    <th>Kode bangsal</th>
                                                    <th>Kategori faktur</th>
                                                    <th>Grandtotal</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div class="alert alert-success fw-bold mt-3" id="grandTotalInfo">
                                        Grand Total Tagihan: <span id="grandTotalValue">0</span>
                                    </div>

                                    <button type="submit" class="btn btn-danger mt-3">
                                        <i class="bi bi-trash me-1"></i> Hapus Massal
                                    </button>
                                </form>
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

            const updateSelectedCount = () => {
                document.getElementById('selectedCount').textContent = document.querySelectorAll('.row-checkbox:checked').length;
            };

            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                dom: 'Bfrltip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                order: [[2, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                ajax: {
                    url: '{{ route('bridging.pembelian.load-imported-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                    }
                },
                columns: [
                    {
                        data: 'checkbox',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<input type="checkbox" class="row-checkbox" name="selectedNoTransaksi[]" value="${data}">`;
                        }
                    },
                    { data: 'nomer_faktur', name: 'nomer_faktur' },
                    { data: 'tanggal_faktur_display', name: 'tanggal_faktur' },
                    { data: 'tanggal_jatuh_tempo_display', name: 'tanggal_jatuh_tempo' },
                    { data: 'nama_supplier', name: 'supplier.nama_supplier', orderable: false },
                    { data: 'kode_bangsal', name: 'kode_bangsal' },
                    { data: 'kategori_faktur', name: 'kategori_faktur' },
                    { data: 'grandtotal_display', name: 'grandtotal', className: 'text-end' }
                ]
            });

            table.on('draw', function () {
                document.getElementById('checkAll').checked = false;
                updateSelectedCount();
            });

            table.on('xhr', function (e, settings, json) {
                if (json && json.grandTotal !== undefined) {
                    const formatted = Number(json.grandTotal).toLocaleString('id-ID');
                    document.getElementById('grandTotalValue').textContent = 'Rp ' + formatted;
                }
            });

            document.addEventListener('change', function (event) {
                if (event.target.matches('.row-checkbox')) {
                    updateSelectedCount();
                }
            });

            document.getElementById('checkAll')?.addEventListener('change', function () {
                document.querySelectorAll('.row-checkbox').forEach((checkbox) => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            document.getElementById('formHapusMassal')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;
                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu data untuk dihapus.');
                    return;
                }

                if (!window.confirm(`Apakah Anda yakin ingin menghapus ${total} data terpilih?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
