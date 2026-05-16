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
        if ($nilai < 1000000000000) {
            return $terbilang((int) floor($nilai / 1000000000)).' miliar'.($nilai % 1000000000 !== 0 ? ' '.$terbilang($nilai % 1000000000) : '');
        }

        return $terbilang((int) floor($nilai / 1000000000000)).' triliun'.($nilai % 1000000000000 !== 0 ? ' '.$terbilang($nilai % 1000000000000) : '');
    };

    $nominalTotal = (int) round((float) $jurnalUmum->debit);
    $terbilangRupiah = $nominalTotal === 0
        ? 'Nol'
        : ucfirst(trim($terbilang(abs($nominalTotal))));
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JU : {{ $jurnalUmum->nomer }}</title>
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
            hyphens: auto;
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
                    <td class="wrap-text"><b>Dokumen:</b> Jurnal Umum</td>
                    <td class="wrap-text"><b>Nomor:</b> {{ $jurnalUmum->nomer }}</td>
                    <td class="wrap-text"><b>Tanggal:</b> {{ optional($jurnalUmum->tanggal)->format('d/m/Y') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table table-bordered border-dark mb-2">
            <tr>
                <td>
                    <span class="label-strong">Keterangan:</span><br>
                    {{ $jurnalUmum->keterangan }}
                    <br><br>
                    <span class="label-strong">Terbilang:</span> {{ $terbilangRupiah }} Rupiah
                </td>
            </tr>
        </table>

        <table class="table-print mt-2">
            <colgroup>
                <col style="width:42%">
                <col style="width:28%">
                <col style="width:15%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th>Akun</th>
                    <th>Catatan</th>
                    <th>Debit</th>
                    <th>Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jurnalUmum->rincian as $item)
                    <tr>
                        <td class="wrap-text">{{ $item->coa?->kode }} - {{ $item->coa?->nama }}</td>
                        <td class="wrap-text">{{ $item->catatan }}</td>
                        <td class="num">{{ $formatRupiah($item->debit) }}</td>
                        <td class="num">{{ $formatRupiah($item->kredit) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="wrap-text text-center">Belum ada rincian ditambahkan</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="num" style="font-weight:700;">Total</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($jurnalUmum->debit) }}</td>
                    <td class="num" style="font-weight:700;">{{ $formatRupiah($jurnalUmum->kredit) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="table table-bordered border-dark mt-3 text-center">
            <tr>
                <td style="width:33%;">Direktur</td>
                <td style="width:33%;">Kabag. Keuangan</td>
                <td style="width:34%;">Dicetak oleh</td>
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
        </table>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
