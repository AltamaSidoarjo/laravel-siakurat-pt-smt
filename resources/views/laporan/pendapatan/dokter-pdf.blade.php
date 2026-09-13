<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendapatan Dokter</title>
    <style>
        @page { margin: 15mm 14mm 18mm; }
        body {
            color: #111;
            font-family: "DejaVu Serif", serif;
            font-size: 10pt;
            line-height: 1.45;
        }
        .report-header { text-align: center; margin-bottom: 26px; }
        .company { font-size: 11pt; font-weight: bold; margin: 0 0 10px; text-transform: uppercase; }
        .title { color: #0076b5; font-size: 11pt; font-weight: normal; margin: 0 0 2px; }
        .period { color: #d53b32; font-size: 10pt; margin: 0; }
        .header-rule { border: 0; border-top: 2px solid #c8c8c8; margin: 8px 0 0; }
        .doctor { margin-bottom: 23px; }
        .doctor-name { font-size: 11pt; margin: 0 0 20px; }
        .section-title { color: #0076b5; font-size: 10.5pt; margin: 0 0 15px 18px; }
        .account-group { margin: 0 0 15px 36px; page-break-inside: avoid; }
        .group-title { color: #0076b5; font-size: 10.5pt; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { padding: 5px 0; vertical-align: top; }
        .date { width: 16%; padding-left: 10px; }
        .code { width: 20%; }
        .account { width: 39%; }
        .amount { width: 25%; text-align: right; white-space: nowrap; }
        .group-total td { color: #24bd72; padding-top: 7px; }
        .group-total .amount { border-top: 1px solid #24bd72; }
        .doctor-total { margin: 2px 0 0; page-break-inside: avoid; }
        .doctor-total td { padding-top: 8px; }
        .doctor-total .label, .doctor-total .amount { color: #24bd72; }
        .doctor-total .label, .doctor-result td:first-child, .grand-total td:first-child { width: 75%; }
        .doctor-total .label { width: 76%; padding-left: 18px; }
        .doctor-total .amount { width: 24%; border-top: 1px solid #24bd72; }
        .doctor-result { margin-top: 11px; page-break-inside: avoid; }
        .doctor-result .amount { border-top: 1px solid #111; padding-top: 9px; }
        .grand-total { margin-top: 8px; page-break-inside: avoid; }
        .grand-total td { font-weight: bold; padding-top: 10px; }
        .grand-total .amount { border-top: 2px solid #111; }
        .empty { color: #666; text-align: center; margin-top: 60px; }
    </style>
</head>
<body>
    <header class="report-header">
        <p class="company">{{ $companyName }}</p>
        <h1 class="title">PENDAPATAN DOKTER</h1>
        <p class="period">{{ $periodLabel }}</p>
        <hr class="header-rule">
    </header>

    @forelse ($dokterGroups as $dokter)
        <section class="doctor">
            <p class="doctor-name">{{ $dokter['nama'] }}</p>
            <p class="section-title">Pendapatan</p>

            @foreach ($dokter['kelompok'] as $kelompok)
                <div class="account-group">
                    <p class="group-title">{{ $kelompok['nama'] }}</p>
                    <table>
                        @foreach ($kelompok['rincian'] as $rincian)
                            <tr>
                                <td class="date">{{ \Carbon\Carbon::parse($rincian->tanggal)->format('Y-m-d') }}</td>
                                <td class="code">{{ $rincian->kode_akun_format }}</td>
                                <td class="account">{{ $rincian->nama_akun }}</td>
                                <td class="amount">Rp {{ number_format((float) $rincian->total_pendapatan, 2, '.', ',') }}</td>
                            </tr>
                        @endforeach
                        <tr class="group-total">
                            <td colspan="3">Total {{ $kelompok['nama'] }}</td>
                            <td class="amount">Rp {{ number_format($kelompok['subtotal'], 2, '.', ',') }}</td>
                        </tr>
                    </table>
                </div>
            @endforeach

            <table class="doctor-total">
                <tr>
                    <td class="label">Total Pendapatan</td>
                    <td class="amount">Rp {{ number_format($dokter['total'], 2, '.', ',') }}</td>
                </tr>
            </table>
            <table class="doctor-result">
                <tr>
                    <td>Total Pendapatan Dokter {{ $dokter['nama'] }}</td>
                    <td class="amount">Rp {{ number_format($dokter['total'], 2, '.', ',') }}</td>
                </tr>
            </table>
        </section>
    @empty
        <p class="empty">Tidak ada data pendapatan dokter pada periode ini.</p>
    @endforelse

    @if ($dokterGroups->isNotEmpty())
        <table class="grand-total">
            <tr>
                <td>Total Pendapatan Seluruh Dokter</td>
                <td class="amount">Rp {{ number_format($grandTotal, 2, '.', ',') }}</td>
            </tr>
        </table>
    @endif
</body>
</html>
