<?php

namespace App\Services\Laporan;

use App\Models\PreferensiPerusahaan;
use App\Models\SimrsImportPendapatan;
use App\Models\SimrsImportPendapatanJualObat;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class LaporanPendapatanService
{
    public function getQueryPendapatanDokter(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): QueryBuilder {
        return $this->getPendapatanDokterBaseQuery($startDate, $endDate, $pelaksanaId, $poli, $penjamin)
            ->selectRaw('p.id AS pelaksana_id')
            ->selectRaw('p.nama_pelaksana AS dokter')
            ->selectRaw('fp.tanggal_faktur AS tanggal')
            ->selectRaw('c.kode AS kode_akun')
            ->selectRaw('c.nama AS layanan')
            ->selectRaw('COUNT(DISTINCT fp.id) AS jumlah_billing')
            ->selectRaw('SUM(fpr.subtotal) AS total_pendapatan')
            ->groupBy('p.id', 'p.nama_pelaksana', 'fp.tanggal_faktur', 'c.id', 'c.kode', 'c.nama')
            ->orderBy('p.nama_pelaksana')
            ->orderBy('fp.tanggal_faktur')
            ->orderBy('c.kode');
    }

    public function getPendapatanDokterSummary(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
        string $search = '',
    ): array {
        $query = $this->getPendapatanDokterBaseQuery(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $this->applyPendapatanDokterSearch($query, $search);

        return [
            'totalBilling' => (clone $query)->distinct()->count('fp.id'),
            'grandTotal' => (float) (clone $query)->sum('fpr.subtotal'),
        ];
    }

    public function getPelaksanaOptions(): Collection
    {
        return DB::table('pelaksana')
            ->whereIn(DB::raw('SUBSTR(no_proyek, 1, 2)'), ['1_', '2_'])
            ->orderBy('nama_pelaksana')
            ->get(['id', 'nama_pelaksana']);
    }

    public function applyPendapatanDokterSearch(QueryBuilder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $likeSearch = '%'.$search.'%';

        $query->where(function (QueryBuilder $searchQuery) use ($likeSearch): void {
            $searchQuery
                ->where('p.nama_pelaksana', 'like', $likeSearch)
                ->orWhere('p.no_proyek', 'like', $likeSearch)
                ->orWhere('c.kode', 'like', $likeSearch)
                ->orWhere('c.nama', 'like', $likeSearch)
                ->orWhere('fp.nama_poli', 'like', $likeSearch)
                ->orWhere('fp.nama_penjamin', 'like', $likeSearch);
        });
    }

    public function getQueryKunjungan(
        string $startDate,
        string $endDate,
        string $poli = '',
        string $penjamin = '',
    ): Builder {
        return SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->when($poli !== '', fn (Builder $query) => $query->where('poli', 'like', "%{$poli}%"))
            ->when($penjamin !== '', fn (Builder $query) => $query->where('penjamin', 'like', "%{$penjamin}%"))
            ->orderByDesc('tanggal_reg')
            ->orderByDesc('id');
    }

    public function getQueryPenjualanObat(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function streamKunjunganCsv(string $startDate, string $endDate): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'No. Billing',
            'Tanggal Registrasi',
            'Pasien',
            'Status Layanan',
            'Dokter',
            'Poli',
            'Penjamin',
            'Nominal',
        ]);

        $this->getQueryKunjunganForExport($startDate, $endDate)
            ->lazyById(1000, 'id')
            ->each(function (SimrsImportPendapatan $row) use ($handle) {
                fputcsv($handle, [
                    (string) $row->nomer_billing,
                    optional($row->tanggal_reg)->format('Y-m-d'),
                    (string) ($row->nama_pasien ?? ''),
                    (string) ($row->status_layanan ?? ''),
                    (string) ($row->dokter ?? ''),
                    (string) ($row->poli ?? ''),
                    (string) ($row->penjamin ?? ''),
                    $this->formatCsvNumber($row->total_tagihan),
                ]);
            });

        fclose($handle);
    }

    public function streamPenjualanObatCsv(string $startDate, string $endDate): void
    {
        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka output stream CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'No. Transaksi',
            'Tanggal',
            'Pelanggan',
            'Jenis',
            'Gudang',
            'Rekening',
            'Keterangan',
            'Ongkir',
            'PPN',
            'Nominal',
        ]);

        $this->getQueryPenjualanObatForExport($startDate, $endDate)
            ->lazyById(1000, 'id')
            ->each(function (SimrsImportPendapatanJualObat $row) use ($handle) {
                fputcsv($handle, [
                    (string) $row->nomer_transaksi,
                    optional($row->tanggal)->format('Y-m-d'),
                    (string) ($row->nama_pelanggan ?? ''),
                    (string) ($row->jenis_jual ?? ''),
                    (string) ($row->kode_gudang ?? ''),
                    (string) ($row->nama_rekening ?? ''),
                    (string) ($row->keterangan ?? ''),
                    $this->formatCsvNumber($row->ongkir),
                    $this->formatCsvNumber($row->ppn),
                    $this->formatCsvNumber($row->grandtotal),
                ]);
            });

        fclose($handle);
    }

    public function renderPendapatanDokterPdf(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): string {
        $report = $this->getPendapatanDokterReportData(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $html = view('laporan.pendapatan.dokter-pdf', [
            'companyName' => $report['companyName'],
            'periodLabel' => $report['periodLabel'],
            'dokterGroups' => $report['dokterGroups'],
            'grandTotal' => $report['grandTotal'],
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Serif');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function streamPendapatanDokterExcel(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId = null,
        string $poli = '',
        string $penjamin = '',
    ): void {
        $report = $this->getPendapatanDokterReportData(
            $startDate,
            $endDate,
            $pelaksanaId,
            $poli,
            $penjamin,
        );

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle('Pendapatan Dokter')
            ->setSubject('Laporan pendapatan dokter');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pendapatan Dokter');
        $sheet->setShowGridlines(true);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.55)
            ->setRight(0.45)
            ->setBottom(0.65)
            ->setLeft(0.45);

        foreach (['A1:C1', 'A2:C2', 'A3:C3'] as $range) {
            $sheet->mergeCells($range);
        }

        $sheet->setCellValue('A1', mb_strtoupper($report['companyName']));
        $sheet->setCellValue('A2', 'PENDAPATAN DOKTER');
        $sheet->setCellValue('A3', $report['periodLabel']);
        $sheet->getStyle('A1:C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->setColor(new Color('0076B5'));
        $sheet->getStyle('A3')->getFont()->setSize(11)->setColor(new Color('D53B32'));
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(21);
        $sheet->getRowDimension(3)->setRowHeight(20);

        $sheet->fromArray(['Kode Akun', 'Keterangan', 'Saldo'], null, 'A5');
        $sheet->getStyle('A5:C5')->getFont()->setBold(true);
        $sheet->getStyle('A5:C5')->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->setColor(new Color('C8C8C8'));
        $sheet->getStyle('C5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $currentRow = 6;
        $doctorResultCells = [];

        foreach ($report['dokterGroups'] as $dokter) {
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $dokter['nama']);
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
            $currentRow++;

            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Pendapatan');
            $sheet->getStyle("A{$currentRow}")->getFont()->setColor(new Color('0076B5'));
            $currentRow++;

            $groupSubtotalCells = [];

            foreach ($dokter['kelompok'] as $kelompok) {
                $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $kelompok['nama']);
                $sheet->getStyle("A{$currentRow}")->getFont()->setColor(new Color('0076B5'));
                $currentRow++;

                $detailStartRow = $currentRow;

                foreach ($kelompok['rincian'] as $rincian) {
                    $sheet->setCellValueExplicit("A{$currentRow}", $rincian->kode_akun_format, DataType::TYPE_STRING);
                    $sheet->setCellValue("B{$currentRow}", $rincian->nama_akun);
                    $sheet->setCellValue("C{$currentRow}", (float) $rincian->total_pendapatan);
                    $currentRow++;
                }

                $detailEndRow = $currentRow - 1;
                $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'Total '.$kelompok['nama']);
                $sheet->setCellValue("C{$currentRow}", "=SUM(C{$detailStartRow}:C{$detailEndRow})");
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setColor(new Color('24BD72'));
                $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new Color('24BD72'));
                $groupSubtotalCells[] = "C{$currentRow}";
                $currentRow++;
            }

            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan');
            $sheet->setCellValue("C{$currentRow}", '=SUM('.implode(',', $groupSubtotalCells).')');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setColor(new Color('24BD72'));
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color('24BD72'));
            $doctorTotalCell = "C{$currentRow}";
            $currentRow++;

            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan Dokter '.$dokter['nama']);
            $sheet->setCellValue("C{$currentRow}", "={$doctorTotalCell}");
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)
                ->setColor(new Color(Color::COLOR_BLACK));
            $doctorResultCells[] = "C{$currentRow}";
            $currentRow += 2;
        }

        if ($doctorResultCells === []) {
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data pendapatan dokter pada periode ini.');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        } else {
            $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Total Pendapatan Seluruh Dokter');
            $sheet->setCellValue("C{$currentRow}", '=SUM('.implode(',', $doctorResultCells).')');
            $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_DOUBLE)
                ->setColor(new Color(Color::COLOR_BLACK));
            $currentRow++;
        }

        $lastRow = $currentRow - 1;
        $sheet->getStyle("C6:C{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(58);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->freezePane('A6');
        $sheet->getPageSetup()->setPrintArea("A1:C{$lastRow}");

        try {
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function getQueryKunjunganForExport(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatan::query()
            ->select([
                'id',
                'nomer_billing',
                'tanggal_reg',
                'nama_pasien',
                'status_layanan',
                'dokter',
                'poli',
                'penjamin',
                'total_tagihan',
            ])
            ->betweenDates($startDate, $endDate)
            ->orderBy('id');
    }

    private function getPendapatanDokterReportRows(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): Collection {
        return $this->getPendapatanDokterBaseQuery($startDate, $endDate, $pelaksanaId, $poli, $penjamin)
            ->leftJoin('coa as pc', 'pc.id', '=', 'c.parent_coa')
            ->selectRaw('p.id AS pelaksana_id')
            ->selectRaw('p.nama_pelaksana AS dokter')
            ->selectRaw('fp.tanggal_faktur AS tanggal')
            ->selectRaw('COALESCE(pc.id, c.id) AS kelompok_id')
            ->selectRaw('COALESCE(pc.kode, c.kode) AS kode_kelompok')
            ->selectRaw('COALESCE(pc.nama, c.nama) AS kelompok')
            ->selectRaw('c.kode AS kode_akun')
            ->selectRaw('c.nama AS nama_akun')
            ->selectRaw('SUM(fpr.subtotal) AS total_pendapatan')
            ->groupBy(
                'p.id',
                'p.nama_pelaksana',
                'fp.tanggal_faktur',
                'pc.id',
                'pc.kode',
                'pc.nama',
                'c.id',
                'c.kode',
                'c.nama',
            )
            ->orderBy('p.nama_pelaksana')
            ->orderBy('fp.tanggal_faktur')
            ->orderByRaw('COALESCE(pc.kode, c.kode)')
            ->orderBy('c.kode')
            ->get()
            ->map(function (object $row): object {
                $row->kode_akun_format = $this->formatCoaCode((string) $row->kode_akun);

                return $row;
            });
    }

    /**
     * @return array{companyName: string, periodLabel: string, dokterGroups: Collection, grandTotal: float}
     */
    private function getPendapatanDokterReportData(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): array {
        $rows = $this->getPendapatanDokterReportRows($startDate, $endDate, $pelaksanaId, $poli, $penjamin);
        $dokterGroups = $rows
            ->groupBy('pelaksana_id')
            ->map(function (Collection $doctorRows): array {
                return [
                    'nama' => (string) $doctorRows->first()->dokter,
                    'kelompok' => $doctorRows
                        ->groupBy('kelompok_id')
                        ->map(fn (Collection $accountRows): array => [
                            'nama' => (string) $accountRows->first()->kelompok,
                            'rincian' => $accountRows->values(),
                            'subtotal' => (float) $accountRows->sum('total_pendapatan'),
                        ])
                        ->values(),
                    'total' => (float) $doctorRows->sum('total_pendapatan'),
                ];
            })
            ->values();

        return [
            'companyName' => (Schema::hasTable('preferensi_perusahaan')
                ? PreferensiPerusahaan::query()->value('nama_perusahaan')
                : null) ?: config('siakurat.rs_name', 'RSA BOJONEGORO'),
            'periodLabel' => Carbon::parse($startDate)->locale('id')->translatedFormat('j F Y')
                .' - '.Carbon::parse($endDate)->locale('id')->translatedFormat('j F Y'),
            'dokterGroups' => $dokterGroups,
            'grandTotal' => (float) $rows->sum('total_pendapatan'),
        ];
    }

    private function formatCoaCode(string $code): string
    {
        if (preg_match('/^\d{9}$/', $code) !== 1) {
            return $code;
        }

        return substr($code, 0, 4).'-'.substr($code, 4, 2).'-'.substr($code, 6, 3);
    }

    private function getQueryPenjualanObatForExport(string $startDate, string $endDate): Builder
    {
        return SimrsImportPendapatanJualObat::query()
            ->select([
                'id',
                'nomer_transaksi',
                'tanggal',
                'nama_pelanggan',
                'jenis_jual',
                'kode_gudang',
                'nama_rekening',
                'keterangan',
                'ongkir',
                'ppn',
                'grandtotal',
            ])
            ->betweenDates($startDate, $endDate)
            ->orderBy('id');
    }

    private function formatCsvNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function getPendapatanDokterBaseQuery(
        string $startDate,
        string $endDate,
        ?int $pelaksanaId,
        string $poli,
        string $penjamin,
    ): QueryBuilder {
        return DB::table('faktur_penjualan_rinci as fpr')
            ->join('faktur_penjualan as fp', 'fp.id', '=', 'fpr.faktur_penjualan_id')
            ->join('pelaksana as p', 'p.id', '=', 'fpr.pelaksana_id')
            ->join('coa as c', 'c.id', '=', 'fpr.coa_id')
            ->whereIn(DB::raw('SUBSTR(p.no_proyek, 1, 2)'), ['1_', '2_'])
            ->whereBetween('fp.tanggal_faktur', [$startDate, $endDate])
            ->when($pelaksanaId !== null, fn (QueryBuilder $query) => $query->where('p.id', $pelaksanaId))
            ->when($poli !== '', fn (QueryBuilder $query) => $query->where('fp.nama_poli', 'like', '%'.$poli.'%'))
            ->when($penjamin !== '', fn (QueryBuilder $query) => $query->where('fp.nama_penjamin', 'like', '%'.$penjamin.'%'));
    }
}
