@php
    $formatRupiah = fn ($angka) => number_format((float) $angka, 0, ',', '.');
    $statusPembayaran = (float) $invoicePendapatan->sudah_terbayar >= (float) $invoicePendapatan->grandtotal
        ? 'Sudah Lunas'
        : 'Belum Lunas';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Pendapatan : {{ $invoicePendapatan->nomor_faktur }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .wrapper {
            width: 100%;
            border: 1px solid #000;
            padding: 8px;
        }

        .table-print {
            width: 100%;
            border-collapse: collapse !important;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .table-print th,
        .table-print td {
            border: 1px solid #000 !important;
            padding: 5px 6px !important;
            vertical-align: top;
            line-height: 1.3;
            font-size: 11px;
            background: #fff !important;
        }

        .table-print thead th {
            font-weight: 700;
            text-align: center;
            font-size: 12px;
        }

        .wrap-text {
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .num {
            text-align: right !important;
            white-space: nowrap !important;
        }

        .label-strong {
            font-weight: 700;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            tr, td, th {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print d-flex justify-content-end gap-2 my-3">
        <a class="btn btn-light" href="javascript:history.back()">Kembali</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Print</button>
    </div>

    <div class="wrapper">
        <h5 class="text-center mb-3">{{ $namaRumahSakit }}</h5>

        <table class="table-print">
            <colgroup>
                <col style="width:40%">
                <col style="width:32%">
                <col style="width:28%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="wrap-text"><span class="label-strong">Dokumen:</span> Invoice Pendapatan</td>
                    <td class="wrap-text"><span class="label-strong">Nomor:</span> {{ $invoicePendapatan->nomor_faktur }}</td>
                    <td class="wrap-text"><span class="label-strong">Tanggal:</span> {{ optional($invoicePendapatan->tanggal_faktur)->format('d/m/Y') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table-print">
            <colgroup>
                <col style="width:35%">
                <col style="width:35%">
                <col style="width:30%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Pasien:</span><br>
                        {{ trim(($invoicePendapatan->nomer_rekam_medis ? $invoicePendapatan->nomer_rekam_medis . ' - ' : '') . ($invoicePendapatan->nama_pasien ?? '-')) }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Dokter:</span><br>
                        {{ $invoicePendapatan->nama_dokter ?: ($invoicePendapatan->dokter ?: '-') }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Status:</span><br>
                        {{ $statusPembayaran }}
                    </td>
                </tr>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Poli / Unit:</span><br>
                        {{ $invoicePendapatan->nama_poli ?: ($invoicePendapatan->poli ?: '-') }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Penjamin:</span><br>
                        {{ $invoicePendapatan->nama_penjamin ?: ($invoicePendapatan->penjamin ?: '-') }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Dicetak Oleh:</span><br>
                        {{ $namaPetugas }}
                    </td>
                </tr>
                @if ($invoicePendapatan->keterangan)
                <tr>
                    <td colspan="3" class="wrap-text">
                        <span class="label-strong">Keterangan:</span><br>
                        {{ $invoicePendapatan->keterangan }}
                    </td>
                </tr>
                @endif
            </tbody>
        </table>

        <table class="table-print">
            <colgroup>
                <col style="width:8%">
                <col style="width:52%">
                <col style="width:12%">
                <col style="width:14%">
                <col style="width:14%">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Rincian</th>
                    <th>Kuantitas</th>
                    <th>Biaya</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoicePendapatan->rincian as $index => $rinci)
                    <tr>
                        <td class="text-center wrap-text">{{ $index + 1 }}</td>
                        <td class="wrap-text">{{ $rinci->catatan }}</td>
                        <td class="num">{{ number_format((float) $rinci->kuantitas, 0, ',', '.') }}</td>
                        <td class="num">{{ $formatRupiah($rinci->harga) }}</td>
                        <td class="num">{{ $formatRupiah($rinci->subtotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center wrap-text">Tidak ada detail invoice</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="num" style="font-weight:700;">Grand Total</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($invoicePendapatan->grandtotal) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="text-end small">
            Dicetak pada {{ $printedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
