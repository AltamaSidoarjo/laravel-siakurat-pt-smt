@extends('layouts.app')

@section('title', 'Billing Pasien API')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bridging.pendapatan.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Billing Pasien API</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    @if ($apiError)
                        <div class="alert alert-danger">{{ $apiError }}</div>
                    @endif

                    <div class="alert alert-warning" id="apiErrorAlert" hidden></div>

                    <div class="d-flex flex-column gap-3">
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
                                        <div class="col-md-4">
                                            <label for="jenisLayanan" class="form-label">Jenis layanan</label>
                                            <select name="jenisLayanan" id="jenisLayanan" class="form-select">
                                                <option value="rawat_jalan" @selected($jenisLayanan === 'rawat_jalan')>Rawat Jalan</option>
                                                <option value="igd" @selected($jenisLayanan === 'igd')>IGD</option>
                                                <option value="rawat_inap" disabled>Rawat Inap — Endpoint belum tersedia</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 rawat-jalan-filter">
                                            <label for="spesialisId" class="form-label">Spesialis</label>
                                            <select name="spesialisId" id="spesialisId" class="form-select">
                                                <option value="">Semua spesialis</option>
                                                @foreach ($spesialisOptions as $spesialis)
                                                    <option value="{{ $spesialis['id'] }}" @selected($spesialisId === $spesialis['id'])>
                                                        {{ $spesialis['nama'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 rawat-jalan-filter">
                                            <label for="dokterId" class="form-label">Dokter</label>
                                            <select name="dokterId" id="dokterId" class="form-select">
                                                <option value="">Semua dokter</option>
                                                @foreach ($dokterOptions as $dokter)
                                                    <option value="{{ $dokter['id'] }}" @selected($dokterId === $dokter['id'])>
                                                        {{ $dokter['nama'] }}
                                                    </option>
                                                @endforeach
                                            </select>
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

                                    <div class="alert alert-secondary mb-3">
                                        Proses Jurnal Umum dan Invoice Pendapatan belum tersedia pada fase ini.
                                        Data API tetap dapat difilter, dilihat, dan dipilih.
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
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Import ke:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisJurnalUmum" value="JurnalUmum" checked disabled>
                                            <label class="form-check-label" for="jenisJurnalUmum">Jurnal Umum</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="jenisProses" id="jenisInvoicePendapatan" value="InvoicePendapatan" disabled>
                                            <label class="form-check-label" for="jenisInvoicePendapatan">Invoice Pendapatan</label>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="fw-bold d-block mb-2">Basis tanggal pengakuan:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="basisTanggalPengakuan" id="basisTanggalRegistrasi" value="TanggalRegistrasi" checked disabled>
                                            <label class="form-check-label" for="basisTanggalRegistrasi">Tanggal Registrasi</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="basisTanggalPengakuan" id="basisTanggalKeluarRanap" value="TanggalKeluarRanap" disabled>
                                            <label class="form-check-label" for="basisTanggalKeluarRanap">Tanggal Keluar RS</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-3" disabled>
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
            const jenisLayanan = document.getElementById('jenisLayanan');
            const rawatJalanFilters = document.querySelectorAll('.rawat-jalan-filter');
            const toggleRawatJalanFilters = () => {
                const show = jenisLayanan?.value === 'rawat_jalan';

                rawatJalanFilters.forEach((element) => {
                    element.classList.toggle('d-none', !show);
                });
            };

            jenisLayanan?.addEventListener('change', toggleRawatJalanFilters);
            toggleRawatJalanFilters();

            if (!(window.jQuery && window.jQuery.fn.DataTable)) {
                return;
            }

            const updateSelectedCount = () => {
                document.getElementById('selectedCount').textContent = document.querySelectorAll('.row-checkbox:checked').length;
            };
            const textRenderer = window.jQuery.fn.dataTable.render.text();

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
                        d.jenisLayanan = '{{ $jenisLayanan }}';
                        d.spesialisId = '{{ $spesialisId }}';
                        d.dokterId = '{{ $dokterId }}';
                    }
                },
                columns: [
                    {
                        data: 'no_rawat',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            const safeValue = textRenderer.display(data ?? '');

                            return `<input type="checkbox" class="row-checkbox" name="selectedNoRawat[]" value="${safeValue}">`;
                        }
                    },
                    { data: 'no_rawat', name: 'no_rawat', render: textRenderer },
                    { data: 'tanggal_registrasi', name: 'tanggal_registrasi', render: textRenderer },
                    { data: 'nama_pasien', name: 'nama_pasien', render: textRenderer },
                    { data: 'nama_dokter', name: 'nama_dokter', render: textRenderer },
                    { data: 'nama_poli', name: 'nama_poli', render: textRenderer },
                    { data: 'status_lanjut', name: 'status_lanjut', render: textRenderer }
                ]
            });

            table.on('xhr', function (event, settings, json) {
                const alert = document.getElementById('apiErrorAlert');
                if (!alert) {
                    return;
                }

                if (json?.error) {
                    alert.textContent = json.error;
                    alert.hidden = false;
                    return;
                }

                alert.textContent = '';
                alert.hidden = true;
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

        });
    </script>
@endpush
