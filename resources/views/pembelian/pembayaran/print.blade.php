@php
    $formatRupiah = fn ($angka) => number_format((float) $angka, 0, ',', '.');

    $terbilang = function (int $nilai) use (&$terbilang): string {
        $angka = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($nilai < 12) {
            return $angka[$nilai];
        }
        if ($nilai < 20) {
            return $terbilang($nilai - 10).' belas';
        }
        if ($nilai < 100) {
            return $terbilang((int) floor($nilai / 10)).' puluh'.($nilai % 10 !== 0 ? ' '.$terbilang($nilai % 10) : '');
        }
        if ($nilai < 200) {
            return 'seratus'.($nilai - 100 !== 0 ? ' '.$terbilang($nilai - 100) : '');
        }
        if ($nilai < 1000) {
            return $terbilang((int) floor($nilai / 100)).' ratus'.($nilai % 100 !== 0 ? ' '.$terbilang($nilai % 100) : '');
        }
        if ($nilai < 2000) {
            return 'seribu'.($nilai - 1000 !== 0 ? ' '.$terbilang($nilai - 1000) : '');
        }
        if ($nilai < 1000000) {
            return $terbilang((int) floor($nilai / 1000)).' ribu'.($nilai % 1000 !== 0 ? ' '.$terbilang($nilai % 1000) : '');
        }
        if ($nilai < 1000000000) {
            return $terbilang((int) floor($nilai / 1000000)).' juta'.($nilai % 1000000 !== 0 ? ' '.$terbilang($nilai % 1000000) : '');
        }

        return $terbilang((int) floor($nilai / 1000000000)).' miliar'.($nilai % 1000000000 !== 0 ? ' '.$terbilang($nilai % 1000000000) : '');
    };

    $rincianPrint = $pembayaranPembelian->rincian->filter(fn ($item) => (float) $item->nominal_bayar > 0)->values();
    $totalGrand = $rincianPrint->sum(fn ($item) => (float) ($item->fakturPembelian?->grandtotal ?? 0));
    $totalSisa = $rincianPrint->sum(function ($item) {
        $grandTotal = (float) ($item->fakturPembelian?->grandtotal ?? 0);
        $sudahTerbayar = (float) ($item->fakturPembelian?->sudah_terbayar ?? 0);
        $nominalBayar = (float) $item->nominal_bayar;

        return max($grandTotal - $sudahTerbayar + $nominalBayar, 0);
    });
    $totalBayar = $rincianPrint->sum(fn ($item) => (float) $item->nominal_bayar);
    $potonganAdmin = (float) $pembayaranPembelian->potongan_admin;
    $nominalBankKeluar = max($totalBayar - $potonganAdmin, 0);
    $terbilangRupiah = $totalBayar <= 0 ? 'Nol' : ucfirst(trim($terbilang((int) round($totalBayar))));
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PP : {{ $pembayaranPembelian->nomer_pembayaran }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
    <style>
        @page {
            size: A5 portrait;
            margin: 10mm;
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
            padding: 6px;
        }

        .table-print {
            width: 100%;
            border-collapse: collapse !important;
            table-layout: fixed;
            margin-bottom: 8px;
        }

        .table-print th,
        .table-print td {
            border: 1px solid #000 !important;
            padding: 4px 6px !important;
            vertical-align: top;
            line-height: 1.25;
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
        <h5 class="text-center">{{ $namaRumahSakit }}</h5>

        <table class="table-print">
            <colgroup>
                <col style="width:45%">
                <col style="width:25%">
                <col style="width:30%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="wrap-text"><span class="label-strong">Dokumen:</span> Pembayaran Pembelian</td>
                    <td class="wrap-text"><span class="label-strong">Nomor:</span> {{ $pembayaranPembelian->nomer_pembayaran }}</td>
                    <td class="wrap-text"><span class="label-strong">Tanggal:</span> {{ optional($pembayaranPembelian->tanggal)->format('d/m/Y') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table-print">
            <colgroup>
                <col style="width:55%">
                <col style="width:45%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Supplier:</span><br>
                        {{ $pembayaranPembelian->supplier?->kode_supplier }} - {{ $pembayaranPembelian->supplier?->nama_supplier }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Bank:</span><br>
                        {{ $pembayaranPembelian->akunBank?->kode }} - {{ $pembayaranPembelian->akunBank?->nama }}
                    </td>
                </tr>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Akun Hutang:</span><br>
                        {{ $pembayaranPembelian->akunHutang?->kode }} - {{ $pembayaranPembelian->akunHutang?->nama }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Total Pelunasan:</span><br>
                        {{ $formatRupiah($totalBayar) }}
                    </td>
                </tr>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Akun Potongan:</span><br>
                        {{ $pembayaranPembelian->akunPotonganAdmin?->kode ? $pembayaranPembelian->akunPotonganAdmin->kode.' - '.$pembayaranPembelian->akunPotonganAdmin->nama : '-' }}
                    </td>
                    <td class="wrap-text">
                        <span class="label-strong">Nominal Bank:</span><br>
                        {{ $formatRupiah($nominalBankKeluar) }}
                    </td>
                </tr>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Potongan Admin:</span><br>
                        {{ $formatRupiah($potonganAdmin) }}
                    </td>
                    <td class="wrap-text"></td>
                </tr>
            </tbody>
        </table>

        <table class="table-print">
            <tbody>
                <tr>
                    <td class="wrap-text">
                        <span class="label-strong">Keterangan:</span><br>
                        {{ $pembayaranPembelian->keterangan }}
                        <br><br>
                        <span class="label-strong">Terbilang:</span> {{ $terbilangRupiah }} Rupiah
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="table-print mt-2">
            <colgroup>
                <col style="width:40%">
                <col style="width:15%">
                <col style="width:15%">
                <col style="width:15%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th>Info Faktur</th>
                    <th>Nominal</th>
                    <th>Terhutang</th>
                    <th>Bayar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rincianPrint as $item)
                    @php
                        $grandTotal = (float) ($item->fakturPembelian?->grandtotal ?? 0);
                        $sudahTerbayar = (float) ($item->fakturPembelian?->sudah_terbayar ?? 0);
                        $nominalBayar = (float) $item->nominal_bayar;
                        $sisaTagihan = max($grandTotal - $sudahTerbayar + $nominalBayar, 0);
                    @endphp
                    <tr>
                        <td class="wrap-text">
                            <div><span class="label-strong">Nomor:</span> {{ $item->fakturPembelian?->nomer_faktur }}</div>
                            <div><span class="label-strong">Tanggal:</span> {{ optional($item->fakturPembelian?->tanggal_faktur)->format('d M Y') }}</div>
                        </td>
                        <td class="num">{{ $formatRupiah($grandTotal) }}</td>
                        <td class="num">{{ $formatRupiah($sisaTagihan) }}</td>
                        <td class="num">{{ $formatRupiah($nominalBayar) }}</td>
                        <td class="text-center">✓</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center wrap-text">Tidak ada rincian untuk dicetak</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="num" style="font-weight:700;">Total</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($totalGrand) }}</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($totalSisa) }}</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($totalBayar) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <table class="table-print mt-3 text-center">
            <colgroup>
                <col style="width:33%">
                <col style="width:33%">
                <col style="width:34%">
            </colgroup>
            <tbody>
                <tr>
                    <td>Direktur</td>
                    <td>Kabag. Keuangan</td>
                    <td>Dicetak oleh</td>
                </tr>
                <tr>
                    <td style="height:65px; vertical-align:bottom;">
                        <span class="label-strong">({{ $ttdDirektur }})</span>
                    </td>
                    <td style="height:65px; vertical-align:bottom;">
                        <span class="label-strong">({{ $ttdKabag }})</span>
                    </td>
                    <td style="height:65px; vertical-align:bottom;">
                        <span class="label-strong">({{ $namaPetugas }})</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
