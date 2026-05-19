@php
    $formatRupiah = fn ($angka) => number_format((float) $angka, 0, ',', '.');
    $subtotalNetto = (float) $invoicePembelian->rincian->sum('total');
    $statusPembayaran = (float) $invoicePembelian->sudah_terbayar >= (float) $invoicePembelian->grandtotal
        ? 'Sudah Lunas'
        : 'Belum Lunas';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Pembelian : {{ $invoicePembelian->nomer_faktur }}</title>
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
                    <td class="wrap-text"><span class="label-strong">Dokumen:</span> Invoice Pembelian</td>
                    <td class="wrap-text"><span class="label-strong">Nomor:</span> {{ $invoicePembelian->nomer_faktur }}</td>
                    <td class="wrap-text"><span class="label-strong">Tanggal:</span> {{ optional($invoicePembelian->tanggal_faktur)->format('d/m/Y') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table-print">
            <colgroup>
                <col style="width:50%">
                <col style="width:25%">
                <col style="width:25%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Supplier:</span><br>
                        {{ trim(($invoicePembelian->supplier?->kode_supplier ?? '').' - '.($invoicePembelian->supplier?->nama_supplier ?? '')) }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Jatuh Tempo:</span><br>
                        {{ optional($invoicePembelian->tanggal_jatuh_tempo)->format('d/m/Y') ?: '-' }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Status:</span><br>
                        {{ $statusPembayaran }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="wrap-text">
                        <span class="label-strong">Keterangan:</span><br>
                        {{ $invoicePembelian->keterangan ?: '-' }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Dicetak Oleh:</span><br>
                        {{ $namaPetugas }}
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="table-print">
            <colgroup>
                <col style="width:14%">
                <col style="width:34%">
                <col style="width:10%">
                <col style="width:14%">
                <col style="width:10%">
                <col style="width:18%">
            </colgroup>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th>Harga</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoicePembelian->rincian as $rinci)
                    <tr>
                        <td class="wrap-text">{{ $rinci->kode_barang }}</td>
                        <td class="wrap-text">{{ $rinci->nama_barang }}</td>
                        <td class="wrap-text">{{ $rinci->satuan_barang ?: '-' }}</td>
                        <td class="num">{{ $formatRupiah($rinci->harga_barang) }}</td>
                        <td class="num">{{ number_format((float) $rinci->kuantitas, 0, ',', '.') }}</td>
                        <td class="num">{{ $formatRupiah($rinci->total) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center wrap-text">Tidak ada detail invoice</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="num" style="font-weight:700;">Subtotal Netto</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($subtotalNetto) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="num" style="font-weight:700;">PPN</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($invoicePembelian->nilai_ppn) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="num" style="font-weight:700;">Biaya Kirim</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($invoicePembelian->biaya_kirim) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="num" style="font-weight:700;">Grand Total</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($invoicePembelian->grandtotal) }}</td>
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
