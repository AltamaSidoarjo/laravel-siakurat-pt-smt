@extends('layouts.app')

@section('title', 'Laporan Pendapatan Dokter')

@php
    $exportParams = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'pelaksanaId' => $pelaksanaId,
        'poli' => $poli,
        'penjamin' => $penjamin,
    ];
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('laporan.pendapatan.index') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
                <span class="fw-bold">Laporan Pendapatan Dokter</span>
            </div>
        </div>
    </div>

    <div class="card border-muhammadiyah">
        <div class="card-body d-flex flex-column gap-3">
            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <form method="get" action="{{ route('laporan.pendapatan.dokter') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="startDate" class="form-label">Dari tanggal</label>
                                <input type="date" id="startDate" name="startDate" class="form-control" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="endDate" class="form-label">Sampai tanggal</label>
                                <input type="date" id="endDate" name="endDate" class="form-control" value="{{ $endDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="pelaksanaId" class="form-label">Dokter</label>
                                <select id="pelaksanaId" name="pelaksanaId" class="form-select select2">
                                    <option value="">Semua dokter</option>
                                    @foreach ($pelaksanaOptions as $pelaksana)
                                        <option value="{{ $pelaksana->id }}" @selected((string) $pelaksanaId === (string) $pelaksana->id)>
                                            {{ $pelaksana->nama_pelaksana }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="poli" class="form-label">Poli</label>
                                <select id="poli" name="poli" class="form-select select2">
                                    <option value="">Semua poli</option>
                                    @foreach ($poliOptions as $option)
                                        <option value="{{ $option['nama'] }}" @selected($poli === $option['nama'])>
                                            {{ $option['nama'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="penjamin" class="form-label">Penjamin</label>
                                <select id="penjamin" name="penjamin" class="form-select select2">
                                    <option value="">Semua penjamin</option>
                                    @foreach ($penjaminOptions as $option)
                                        <option value="{{ $option['nama'] }}" @selected($penjamin === $option['nama'])>
                                            {{ $option['nama'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-9 d-flex justify-content-end align-items-end gap-2">
                                <a href="{{ route('laporan.pendapatan.dokter') }}" class="btn btn-light">Reset</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                                <a href="{{ route('laporan.pendapatan.dokter.export-pdf', $exportParams) }}"
                                   class="btn btn-outline-danger"><i class="bi bi-filetype-pdf me-1"></i> Export PDF</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if ($apiOptionsError)
                <div class="alert alert-warning mb-0">
                    Pilihan poli dan penjamin dari Billing API tidak dapat dimuat: {{ $apiOptionsError }}
                </div>
            @endif

            <div class="alert alert-info mb-0">
                Pendapatan dihitung dari subtotal rincian invoice yang memiliki pelaksana dokter. Jumlah billing adalah jumlah invoice unik.
            </div>

            <div class="card border-light shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover" id="datatable">
                            <thead>
                                <tr>
                                    <th>Dokter</th>
                                    <th>Kode Akun</th>
                                    <th>Sumber Pendapatan</th>
                                    <th>Jumlah Billing</th>
                                    <th>Total Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="alert alert-secondary fw-bold mb-0">Total Billing Unik: <span id="totalBillingValue">0</span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success fw-bold mb-0">Total Pendapatan Dokter: <span id="grandTotalValue">Rp 0</span></div>
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
            const table = window.jQuery('#datatable').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('laporan.pendapatan.dokter.load-data') }}',
                    data: function (data) {
                        data.startDate = @json($startDate);
                        data.endDate = @json($endDate);
                        data.pelaksanaId = @json($pelaksanaId);
                        data.poli = @json($poli);
                        data.penjamin = @json($penjamin);
                    }
                },
                order: [[0, 'asc'], [1, 'asc']],
                pageLength: 25,
                columns: [
                    { data: 'dokter', name: 'p.nama_pelaksana' },
                    { data: 'kode_akun', name: 'c.kode' },
                    { data: 'layanan', name: 'c.nama' },
                    { data: 'jumlah_billing', name: 'jumlah_billing', className: 'text-end', orderable: false },
                    {
                        data: 'total_pendapatan',
                        name: 'total_pendapatan',
                        className: 'text-end',
                        orderable: false,
                        render: function (value) { return 'Rp ' + value; }
                    }
                ],
                language: { emptyTable: 'Belum ada pendapatan dokter pada rentang ini.' }
            });

            table.on('xhr', function (event, settings, json) {
                if (!json) return;
                document.getElementById('totalBillingValue').textContent = Number(json.totalBilling || 0).toLocaleString('id-ID');
                document.getElementById('grandTotalValue').textContent = 'Rp ' + Number(json.grandTotal || 0).toLocaleString('id-ID');
            });
        });
    </script>
@endpush
