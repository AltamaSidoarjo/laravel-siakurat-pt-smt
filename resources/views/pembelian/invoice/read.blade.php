@extends('layouts.app')

@section('title', 'Read Invoice Pembelian')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pembelian.invoice.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Read Invoice Pembelian</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <div class="d-flex flex-column gap-3">
                        <div class="card border-light shadow-sm">
                            <div class="card-header fw-bold bg-success-subtle text-success">
                                Header
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Nomor<span class="text-danger">*</span></label>
                                    <div class="col">
                                        <input type="text" value="{{ $invoicePembelian->nomer_faktur }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Tanggal<span class="text-danger">*</span></label>
                                    <div class="col">
                                        <input type="date" value="{{ optional($invoicePembelian->tanggal_faktur)->format('Y-m-d') }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Keterangan</label>
                                    <div class="col">
                                        <textarea class="form-control" rows="3" readonly>{{ $invoicePembelian->keterangan }}</textarea>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Grandtotal</label>
                                    <div class="col">
                                        <input type="text" value="{{ number_format((float) $invoicePembelian->grandtotal, 0, ',', '.') }}" class="form-control text-end" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-header fw-bold bg-success-subtle text-success">
                                Detail
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="table_data_detail" class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="text-center">Kode barang</th>
                                                <th class="text-center">Nama barang</th>
                                                <th class="text-center">Harga barang</th>
                                                <th class="text-center">Kuantitas</th>
                                                <th class="text-center">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($invoicePembelian->rincian as $rinci)
                                                <tr>
                                                    <td>{{ $rinci->kode_barang }}</td>
                                                    <td>{{ $rinci->nama_barang }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->harga_barang, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->kuantitas, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->total, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Subtotal Netto</th>
                                                <th class="text-end">{{ number_format((float) $invoicePembelian->rincian->sum('total'), 0, ',', '.') }}</th>
                                            </tr>
                                            <tr>
                                                <th colspan="4" class="text-end">PPN</th>
                                                <th class="text-end">{{ number_format((float) $invoicePembelian->nilai_ppn, 0, ',', '.') }}</th>
                                            </tr>
                                            <tr>
                                                <th colspan="4" class="text-end">Grandtotal</th>
                                                <th class="text-end">{{ number_format((float) $invoicePembelian->grandtotal, 0, ',', '.') }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-3">
                                    <a href="{{ route('pembelian.invoice.index') }}" class="btn btn-light fw-bold">
                                        <i class="bi bi-x-circle-fill"></i> Kembali
                                    </a>
                                    <button type="button" class="btn btn-success fw-bold" id="btn_export_excel">
                                        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                                    </button>
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
            const exportButton = document.getElementById('btn_export_excel');
            if (!exportButton || !window.XLSX) {
                return;
            }

            exportButton.addEventListener('click', function () {
                const table = document.getElementById('table_data_detail');
                const workbook = window.XLSX.utils.table_to_book(table, { sheet: 'Detail Invoice Pembelian' });
                window.XLSX.writeFile(workbook, 'DetailInvoicePembelian_{{ $invoicePembelian->nomer_faktur }}.xlsx');
            });
        });
    </script>
@endpush
