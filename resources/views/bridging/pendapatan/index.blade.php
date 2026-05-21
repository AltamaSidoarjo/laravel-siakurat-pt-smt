@extends('layouts.app')

@section('title', 'Bridging Pendapatan')

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
                <span class="fw-bold">Bridging Pendapatan</span>
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
                                    <a href="{{ route('bridging.pendapatan.tarik-billing-simrs') }}" class="btn btn-info text-white fw-bold">
                                        <i class="bi bi-download me-1"></i> Tarik SIMRS
                                    </a>
                                    <a href="{{ route('bridging.pendapatan.data-tidak-balance', ['startDate' => $startDate, 'endDate' => $endDate]) }}" class="btn btn-warning fw-bold">
                                        <i class="bi bi-search me-1"></i> Halaman Tidak Balance
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
                                        <div class="col-md-3">
                                            <label class="form-label">Poli</label>
                                            <input type="text" name="poli" id="filterPoli" class="form-control" value="{{ $poli }}" placeholder="Cari Poli">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Penjamin</label>
                                            <input type="text" name="penjamin" id="filterPenjamin" class="form-control" value="{{ $penjamin }}" placeholder="Cari Penjamin">
                                        </div>
                                        <div class="col-12 d-flex justify-content-end gap-2">
                                            <a href="{{ route('bridging.pendapatan.index') }}" class="btn btn-light">Reset</a>
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
                                                    <li class="list-group-item list-group-item-success">{{ $item['no_rawat'] }}</li>
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
                                                        <strong>{{ $item['no_rawat'] }}</strong>
                                                        <div>{!! $item['alasan_gagal'] !!}</div>
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
                                <form id="formHapusMassal" method="post" action="{{ route('bridging.pendapatan.destroy-bulk') }}">
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
                                                    <th>No rawat</th>
                                                    <th>Tgl reg</th>
                                                    <th>Pasien</th>
                                                    <th>Dokter</th>
                                                    <th>Poli</th>
                                                    <th>Status layanan</th>
                                                    <th>Penjamin</th>
                                                    <th>Total tagihan</th>
                                                    <th>Import ke</th>
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
                scrollCollapse: true,
                dom: 'Bfrltip',
                buttons: ['csv', 'excel', 'pdf', 'print'],
                order: [[2, 'desc']],
                lengthMenu: [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                ajax: {
                    url: '{{ route('bridging.pendapatan.load-imported-data') }}',
                    type: 'GET',
                    data: function (d) {
                        d.startDate = '{{ $startDate }}';
                        d.endDate = '{{ $endDate }}';
                        d.poli = document.getElementById('filterPoli')?.value || '';
                        d.penjamin = document.getElementById('filterPenjamin')?.value || '';
                    }
                },
                columns: [
                    {
                        data: 'checkbox',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<input type="checkbox" class="row-checkbox" name="selectedNoRawat[]" value="${data}">`;
                        }
                    },
                    { data: 'nomer_billing', name: 'nomer_billing' },
                    { data: 'tanggal_reg_display', name: 'tanggal_reg' },
                    { data: 'nama_pasien', name: 'nama_pasien' },
                    { data: 'dokter', name: 'dokter' },
                    { data: 'poli', name: 'poli' },
                    { data: 'status_layanan', name: 'status_layanan' },
                    { data: 'penjamin', name: 'penjamin' },
                    { data: 'total_tagihan_display', name: 'total_tagihan', className: 'text-end' },
                    { data: 'import_ke', name: 'import_ke' }
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
