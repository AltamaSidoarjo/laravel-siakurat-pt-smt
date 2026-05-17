@extends('layouts.app')

@section('title', 'Billing Pasien SIMRS')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Billing Pasien SIMRS</span>
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
                                            <a href="{{ route('bridging.pendapatan.tarik-billing-simrs') }}" class="btn btn-light">Reset</a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-funnel me-1"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <form id="formImport" method="post" action="{{ route('bridging.pendapatan.process-import') }}">
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
                                                    <th>Penjamin</th>
                                                    <th>Status layanan</th>
                                                    <th>Total tagihan</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Import ke:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisJurnalUmum" value="JurnalUmum" checked>
                                            <label class="form-check-label" for="jenisJurnalUmum">Jurnal Umum</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisInvoicePendapatan" value="InvoicePendapatan">
                                            <label class="form-check-label" for="jenisInvoicePendapatan">Invoice Pendapatan</label>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Basis tanggal pengakuan:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="basisTanggalPengakuan" id="basisTanggalRegistrasi" value="TanggalRegistrasi" checked>
                                            <label class="form-check-label" for="basisTanggalRegistrasi">Tanggal Registrasi</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="basisTanggalPengakuan" id="basisTanggalKeluarRanap" value="TanggalKeluarRanap">
                                            <label class="form-check-label" for="basisTanggalKeluarRanap">Tanggal Keluar RS</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-3">
                                        <i class="bi bi-send me-1"></i> Kirim Data
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
                order: [[2, 'desc']],
                ajax: {
                    url: '{{ route('bridging.pendapatan.load-billing-simrs') }}',
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
                        data: 'no_rawat',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<input type="checkbox" class="row-checkbox" name="selectedNoRawat[]" value="${data}">`;
                        }
                    },
                    { data: 'no_rawat', name: 'no_rawat' },
                    { data: 'tanggal_registrasi', name: 'tanggal_registrasi' },
                    { data: 'nama_pasien', name: 'nama_pasien' },
                    { data: 'nama_dokter', name: 'nama_dokter' },
                    { data: 'nama_poli', name: 'nama_poli' },
                    { data: 'penjamin', name: 'penjamin' },
                    { data: 'status_lanjut', name: 'status_lanjut' },
                    {
                        data: 'total_biaya',
                        name: 'total_biaya',
                        className: 'text-end',
                        render: function (data) {
                            return Number(data).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                ]
            });

            table.on('draw', function () {
                document.getElementById('checkAll').checked = false;
                updateSelectedCount();
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

            document.getElementById('formImport')?.addEventListener('submit', function (event) {
                const total = document.querySelectorAll('.row-checkbox:checked').length;

                if (total === 0) {
                    event.preventDefault();
                    window.alert('Pilih minimal satu data billing untuk diproses.');
                    return;
                }

                const proses = document.querySelector('input[name="jenisProses"]:checked')?.value || '';
                const basis = document.querySelector('input[name="basisTanggalPengakuan"]:checked')?.value || '';
                const konfirmasi = window.confirm(`Apakah Anda yakin ingin mengirim ${total} data ke proses ${proses} dengan basis tanggal ${basis}?`);

                if (!konfirmasi) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
