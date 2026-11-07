<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Aktivitas & Jasa Dokter</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }
        body {
            color: #1e293b;
            font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
            font-size: 7.5pt;
            line-height: 1.35;
        }

        /* Hospital Letterhead / Header */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2mm;
        }
        .kop-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .kop-logo {
            width: 60px;
            text-align: left;
        }
        .kop-logo img {
            max-height: 48px;
            max-width: 55px;
        }
        .kop-text {
            text-align: center;
        }
        .kop-hospital {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .kop-sub {
            font-size: 7.5pt;
            color: #475569;
            margin: 2px 0 0 0;
        }
        .kop-divider {
            border-top: 1.5pt solid #0f172a;
            border-bottom: 0.5pt solid #0f172a;
            height: 1.5pt;
            margin: 3mm 0 4mm 0;
        }

        /* Report Title & Metadata */
        .report-meta {
            text-align: center;
            margin-bottom: 4mm;
        }
        .report-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }
        .report-period {
            font-size: 8pt;
            color: #64748b;
            margin: 0;
        }

        /* Doctor Summary Card */
        .doctor-card {
            border: 0.75pt solid #cbd5e1;
            background-color: #f8fafc;
            border-left: 3pt solid #006837;
            padding: 4px 8px;
            margin-bottom: 3mm;
            page-break-inside: avoid;
        }
        .doctor-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .doctor-card td {
            border: none;
            padding: 1px 4px;
            font-size: 7.5pt;
        }
        .doctor-name {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .doctor-badge {
            font-size: 7pt;
            background-color: #e2e8f0;
            color: #475569;
            padding: 1px 4px;
            border-radius: 2px;
            font-family: monospace;
        }

        /* Data Table */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 4mm;
            page-break-inside: auto;
        }
        table.report-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.report-table th {
            background-color: #334155;
            color: #ffffff;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            padding: 4px 5px;
            border: 0.5pt solid #1e293b;
        }
        table.report-table td {
            padding: 3px 5px;
            font-size: 7pt;
            vertical-align: middle;
            border-bottom: 0.5pt solid #e2e8f0;
            word-wrap: break-word;
        }

        .col-no { width: 3%; text-align: center; }
        .col-date { width: 8%; text-align: center; }
        .col-ref { width: 11%; text-align: left; font-family: monospace; }
        .col-desc { width: 39%; text-align: left; }
        .col-cat { width: 15%; text-align: left; }
        .col-amount { width: 12%; text-align: right; }
        .col-cumulative { width: 12%; text-align: right; font-family: monospace; color: #475569; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* Rows Styling */
        .category-row td {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 7pt;
            padding: 4px 6px;
            border-top: 0.75pt solid #cbd5e1;
            border-bottom: 0.75pt solid #cbd5e1;
            border-left: 2.5pt solid #0284c7;
        }
        .zebra-row td {
            background-color: #fbfcfd;
        }
        .subtotal-row td {
            font-weight: bold;
            background-color: #f8fafc;
            border-top: 0.75pt solid #94a3b8;
            border-bottom: 1pt solid #64748b;
            padding: 4px 5px;
        }
        .doctor-total-row td {
            font-weight: bold;
            font-size: 7.5pt;
            background-color: #f0fdf4;
            color: #14532d;
            border-top: 1.5pt solid #22c55e;
            border-bottom: 1.5pt solid #22c55e;
            padding: 5px 6px;
        }
        .grand-total-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
            page-break-inside: avoid;
        }
        .grand-total-table td {
            font-weight: bold;
            font-size: 8pt;
            background-color: #f1f5f9;
            border-top: 1.5pt solid #0f172a;
            border-bottom: 2.5pt double #0f172a;
            padding: 5px 6px;
        }

        /* Sign-off / Lembar Tanda Tangan */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6mm;
            page-break-inside: avoid;
        }
        .signature-table td {
            border: none;
            padding: 0;
            text-align: center;
            font-size: 7.5pt;
            vertical-align: top;
        }

        .empty-message {
            text-align: center;
            color: #64748b;
            padding: 20mm;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <!-- Kop Surat Rumah Sakit -->
    <table class="kop-table">
        <tr>
            @if (!empty($logoRsUrl))
                <td class="kop-logo">
                    <img src="{{ $logoRsUrl }}" alt="Logo">
                </td>
            @endif
            <td class="kop-text">
                <div class="kop-hospital">{{ $companyName }}</div>
                <div class="kop-sub">Sistem Informasi Akuntansi & Keuangan Rumah Sakit (Siakurat)</div>
                <div class="kop-sub">Pelayanan Rawat Jalan, Bedah & Rawat Inap</div>
            </td>
        </tr>
    </table>
    <div class="kop-divider"></div>

    <!-- Judul Dokumen -->
    <div class="report-meta">
        <div class="report-title">LAPORAN AKTIVITAS & JASA DOKTER</div>
        <div class="report-period">Periode: {{ $periodLabel }}</div>
    </div>

    @forelse ($dokterGroups as $dokter)
        <!-- Doctor Info Header Box -->
        <div class="doctor-card">
            <table>
                <tr>
                    <td style="width: 50%;">
                        <span class="doctor-name">{{ $dokter['dokter'] }}</span>
                        @if (!empty($dokter['no_proyek']))
                            <span class="doctor-badge">{{ $dokter['no_proyek'] }}</span>
                        @endif
                    </td>
                    <td style="width: 25%; text-align: right;">
                        <span style="color: #64748b;">Total Aktivitas:</span>
                        <strong>{{ $dokter['jumlah_transaksi'] }} Tindakan</strong>
                    </td>
                    <td style="width: 25%; text-align: right;">
                        <span style="color: #64748b;">Total Jasa Medis:</span>
                        <strong style="color: #15803d;">Rp {{ number_format($dokter['total_mutasi'], 2, '.', ',') }}</strong>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Table Data -->
        <table class="report-table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th class="col-date">TANGGAL</th>
                    <th class="col-ref">NO. FAKTUR</th>
                    <th class="col-desc">PASIEN & KETERANGAN</th>
                    <th class="col-cat">KATEGORI TINDAKAN</th>
                    <th class="col-amount">NOMINAL JASA (RP)</th>
                    <th class="col-cumulative">KUMULATIF (RP)</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNumber = 1; @endphp
                @foreach ($dokter['kategori'] as $kategori)
                    <tr class="category-row">
                        <td colspan="7">
                            Kategori: {{ $kategori['nama_akun'] }} ({{ count($kategori['rincian']) }} transaksi)
                        </td>
                    </tr>
                    @foreach ($kategori['rincian'] as $index => $item)
                        <tr class="{{ $index % 2 === 1 ? 'zebra-row' : '' }}">
                            <td class="col-no">{{ $rowNumber++ }}</td>
                            <td class="col-date">{{ $item['tanggal_format'] ?? $item['tanggal'] }}</td>
                            <td class="col-ref">{{ $item['no_ref'] }}</td>
                            <td class="col-desc">
                                <strong>{{ $item['nama_pasien'] ?: $item['deskripsi'] }}</strong>
                                @if (!empty($item['poli']) || !empty($item['penjamin']))
                                    <span style="color: #64748b;">({{ implode(' - ', array_filter([$item['poli'], $item['penjamin']])) }})</span>
                                @endif
                                @if ($item['deskripsi'] && $item['deskripsi'] !== $item['nama_pasien'])
                                    <div style="color: #64748b; font-size: 6.5pt;">{{ $item['deskripsi'] }}</div>
                                @endif
                            </td>
                            <td class="col-cat">{{ $kategori['nama_akun'] }}</td>
                            <td class="col-amount">Rp {{ number_format($item['kredit'], 2, '.', ',') }}</td>
                            <td class="col-cumulative">Rp {{ number_format($item['mutasi'], 2, '.', ',') }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td colspan="5" class="text-right">TOTAL {{ $kategori['nama_akun'] }}:</td>
                        <td class="col-amount">Rp {{ number_format($kategori['total_kredit'], 2, '.', ',') }}</td>
                        <td class="col-cumulative">Rp {{ number_format($kategori['total_mutasi'], 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr class="doctor-total-row">
                    <td colspan="5" class="text-right">TOTAL JASA {{ $dokter['dokter'] }}:</td>
                    <td class="col-amount">Rp {{ number_format($dokter['total_kredit'], 2, '.', ',') }}</td>
                    <td class="col-cumulative">Rp {{ number_format($dokter['total_mutasi'], 2, '.', ',') }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <div class="empty-message">
            Tidak ada data aktivitas dokter pada periode ini.
        </div>
    @endforelse

    @if ($dokterGroups->count() > 1)
        <table class="grand-total-table">
            <tr>
                <td style="width: 76%; text-align: right;">TOTAL KESELURUHAN SELURUH DOKTER:</td>
                <td style="width: 12%; text-align: right; color: #15803d;">Rp {{ number_format($grandTotalKredit, 2, '.', ',') }}</td>
                <td style="width: 12%; text-align: right; color: #15803d;">Rp {{ number_format($grandTotalMutasi, 2, '.', ',') }}</td>
            </tr>
        </table>
    @endif

    <!-- Lembar Pengesahan -->
    <table class="signature-table">
        <tr>
            <td style="width: 35%;">
                Mengetahui,<br>
                <strong>Kepala Bagian Keuangan</strong>
                <div style="height: 18mm;"></div>
                ( ___________________________ )
            </td>
            <td style="width: 30%;"></td>
            <td style="width: 35%;">
                Bojonegoro, {{ now()->translatedFormat('d F Y') }}<br>
                <strong>Dokter Pelaksana</strong>
                <div style="height: 18mm;"></div>
                ( ___________________________ )
            </td>
        </tr>
    </table>
</body>
</html>
