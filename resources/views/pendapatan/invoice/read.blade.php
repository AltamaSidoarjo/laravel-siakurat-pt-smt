@extends('layouts.app')

@section('title', 'Read Invoice Pendapatan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pendapatan.invoice.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Read Invoice Pendapatan</span>
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
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">
                                        Nomor<span class="text-danger">*</span>
                                    </label>
                                    <div class="col">
                                        <input type="text" value="{{ $invoicePendapatan->nomor_faktur }}" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">
                                        Tanggal<span class="text-danger">*</span>
                                    </label>
                                    <div class="col">
                                        <input type="date" value="{{ optional($invoicePendapatan->tanggal_faktur)->format('Y-m-d') }}" class="form-control" readonly>
                                    </div>
                                </div>

                                @foreach ([
                                    'Sumber' => $isImported ? 'Bridging SIMRS' : 'Manual',
                                    'Penjamin' => trim(($invoicePendapatan->kode_penjamin ?? '').' - '.($invoicePendapatan->nama_penjamin ?? ''), ' -'),
                                    'Akun Piutang' => $invoicePendapatan->akunPiutang ? $invoicePendapatan->akunPiutang->kode.' - '.$invoicePendapatan->akunPiutang->nama : '-',
                                    'Nomor Rawat' => $invoicePendapatan->nomer_rawat ?: '-',
                                    'No. RM' => $invoicePendapatan->nomer_rekam_medis ?: '-',
                                    'Pasien' => $invoicePendapatan->nama_pasien ?: '-',
                                    'Dokter' => $invoicePendapatan->nama_dokter ?: '-',
                                    'Poli' => $invoicePendapatan->nama_poli ?: '-',
                                    'Grand Total' => 'Rp '.number_format((float) $invoicePendapatan->grandtotal, 0, ',', '.'),
                                    'Sudah Terbayar' => 'Rp '.number_format((float) $invoicePendapatan->sudah_terbayar, 0, ',', '.'),
                                ] as $label => $value)
                                    <div class="row align-items-center mb-2">
                                        <label class="col-12 col-sm-2 col-form-label fw-bold">{{ $label }}</label>
                                        <div class="col"><input type="text" value="{{ $value }}" class="form-control" readonly></div>
                                    </div>
                                @endforeach

                                <div class="row align-items-center mb-2">
                                    <label class="col-12 col-sm-2 col-form-label fw-bold">
                                        Keterangan
                                    </label>
                                    <div class="col">
                                        <textarea class="form-control" rows="3" readonly>{{ $invoicePendapatan->keterangan }}</textarea>
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
                                                <th class="text-center">Rincian</th>
                                                <th class="text-center">Akun</th>
                                                <th class="text-center">Pelaksana</th>
                                                <th class="text-center">Kuantitas</th>
                                                <th class="text-center">Biaya</th>
                                                <th class="text-center">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($invoicePendapatan->rincian as $rinci)
                                                <tr>
                                                    <td>{{ $rinci->catatan }}</td>
                                                    <td>{{ $rinci->coa ? $rinci->coa->kode.' - '.$rinci->coa->nama : '-' }}</td>
                                                    <td>
                                                        @if ($rinci->pelaksana)
                                                            {{ $rinci->pelaksana->nama_pelaksana }}
                                                        @elseif ($rinci->kode_proyek)
                                                            {{ $rinci->kode_proyek }} <span class="badge text-bg-warning">Belum terpetakan</span>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-end">{{ number_format((float) $rinci->kuantitas, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->harga, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format((float) $rinci->subtotal, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td class="text-end fw-bold" colspan="5">Total :</td>
                                                <td class="text-end fw-bold">{{ number_format((float) $invoicePendapatan->rincian->sum('subtotal'), 0, ',', '.') }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        @if ($canMutate && auth()->user()?->hasModuleAccess('pendapatan.invoice', 'delete'))
                                            <form method="post" action="{{ route('pendapatan.invoice.destroy', $invoicePendapatan) }}" onsubmit="return confirm('Hapus invoice pendapatan ini?')">@csrf @method('DELETE')<button class="btn btn-danger fw-bold"><i class="bi bi-trash3-fill"></i> Hapus</button></form>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-3">
                                    @if ($canMutate && auth()->user()?->hasModuleAccess('pendapatan.invoice', 'update'))
                                        <a href="{{ route('pendapatan.invoice.edit', $invoicePendapatan) }}" class="btn btn-warning fw-bold"><i class="bi bi-pencil-square"></i> Edit</a>
                                    @endif
                                    <a href="{{ route('pendapatan.invoice.index') }}" class="btn btn-light fw-bold">
                                        <i class="bi bi-x-circle-fill"></i> Kembali
                                    </a>
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
