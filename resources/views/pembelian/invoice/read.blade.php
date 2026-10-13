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
                    @include('partials.flash-message')
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

                                @if ($invoicePembelian->tanggal_jatuh_tempo)
                                    <div class="row align-items-center mb-2">
                                        <label class="col-12 col-sm-2 col-form-label fw-bold">Jatuh Tempo</label>
                                        <div class="col">
                                            <input type="date" value="{{ optional($invoicePembelian->tanggal_jatuh_tempo)->format('Y-m-d') }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                @endif

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Supplier</label>
                                    <div class="col">
                                        <input type="text" value="{{ $invoicePembelian->supplier ? ($invoicePembelian->supplier->kode_supplier ? $invoicePembelian->supplier->kode_supplier.' - ' : '').$invoicePembelian->supplier->nama_supplier : '-' }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Akun Hutang</label>
                                    <div class="col">
                                        <input type="text" value="{{ $invoicePembelian->akunHutang ? $invoicePembelian->akunHutang->kode.' - '.$invoicePembelian->akunHutang->nama : '-' }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Grand Total</label>
                                    <div class="col">
                                        <input type="text" value="Rp {{ number_format((float) $invoicePembelian->grandtotal, 0, ',', '.') }}" class="form-control text-end" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Sudah Terbayar</label>
                                    <div class="col">
                                        <input type="text" value="Rp {{ number_format((float) $invoicePembelian->sudah_terbayar, 0, ',', '.') }}" class="form-control text-end" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Status</label>
                                    <div class="col">
                                        <input type="text" value="{{ (float) $invoicePembelian->sudah_terbayar >= (float) $invoicePembelian->grandtotal ? 'Sudah Lunas' : 'Belum Lunas' }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">Keterangan</label>
                                    <div class="col">
                                        <textarea class="form-control" rows="3" readonly>{{ $invoicePembelian->keterangan }}</textarea>
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
                                                <th class="text-center">Akun</th>
                                                <th class="text-center">Rincian / Catatan</th>
                                                <th class="text-center">Kuantitas</th>
                                                <th class="text-center">Harga</th>
                                                <th class="text-center">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($invoicePembelian->rincian as $rinci)
                                                <tr>
                                                    <td>{{ $rinci->coa ? $rinci->coa->kode.' - '.$rinci->coa->nama : ($rinci->nama_barang ?: '-') }}</td>
                                                    <td>{{ $rinci->catatan ?: ($rinci->kode_barang ? '['.$rinci->kode_barang.'] ' : '').($rinci->nama_barang ?: '-') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->kuantitas, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->harga_barang, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) ($rinci->subtotal ?: $rinci->total), 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Total</th>
                                                <th class="text-end">{{ number_format((float) $invoicePembelian->grandtotal, 0, ',', '.') }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-3 flex-wrap">
                                    <div>
                                        @if ($canMutate && auth()->user()?->hasModuleAccess('pembelian.invoice', 'delete'))
                                            <form method="post" action="{{ route('pembelian.invoice.destroy', $invoicePembelian) }}" onsubmit="return confirm('Hapus invoice pembelian ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger fw-bold"><i class="bi bi-trash3-fill"></i> Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-3 flex-wrap">
                                        @if ($canMutate && auth()->user()?->hasModuleAccess('pembelian.invoice', 'update'))
                                            <a href="{{ route('pembelian.invoice.edit', $invoicePembelian) }}" class="btn btn-warning fw-bold"><i class="bi bi-pencil-square"></i> Edit</a>
                                        @endif
                                        <a href="{{ route('pembelian.invoice.index') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Kembali
                                        </a>
                                        <a href="{{ route('pembelian.invoice.print', $invoicePembelian) }}" class="btn btn-outline-primary fw-bold" target="_blank">
                                            <i class="bi bi-printer-fill"></i> Print
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
