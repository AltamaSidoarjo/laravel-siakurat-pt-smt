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
            ->selectRaw('c.kode AS kode_akun')
            ->selectRaw('c.nama AS layanan')
            ->selectRaw('COUNT(DISTINCT fp.id) AS jumlah_billing')
            ->selectRaw('SUM(fpr.subtotal) AS total_pendapatan')
            ->groupBy('p.id', 'p.nama_pelaksana', 'c.id', 'c.kode', 'c.nama')
            ->orderBy('p.nama_pelaksana')
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
        $rows = $this->getPendapatanDokterPdfRows($startDate, $endDate, $pelaksanaId, $poli, $penjamin);
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

        $companyName = (Schema::hasTable('preferensi_perusahaan')
            ? PreferensiPerusahaan::query()->value('nama_perusahaan')
            : null) ?: config('siakurat.rs_name', 'RSA BOJONEGORO');

        $html = view('laporan.pendapatan.dokter-pdf', [
            'companyName' => $companyName,
            'periodLabel' => Carbon::parse($startDate)->locale('id')->translatedFormat('j F Y')
                .' - '.Carbon::parse($endDate)->locale('id')->translatedFormat('j F Y'),
            'dokterGroups' => $dokterGroups,
            'grandTotal' => (float) $rows->sum('total_pendapatan'),
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

    private function getPendapatanDokterPdfRows(
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
            ->selectRaw('COALESCE(pc.id, c.id) AS kelompok_id')
            ->selectRaw('COALESCE(pc.kode, c.kode) AS kode_kelompok')
            ->selectRaw('COALESCE(pc.nama, c.nama) AS kelompok')
            ->selectRaw('c.kode AS kode_akun')
            ->selectRaw('c.nama AS nama_akun')
            ->selectRaw('SUM(fpr.subtotal) AS total_pendapatan')
            ->groupBy(
                'p.id',
                'p.nama_pelaksana',
                'pc.id',
                'pc.kode',
                'pc.nama',
                'c.id',
                'c.kode',
                'c.nama',
            )
            ->orderBy('p.nama_pelaksana')
            ->orderByRaw('COALESCE(pc.kode, c.kode)')
            ->orderBy('c.kode')
            ->get()
            ->map(function (object $row): object {
                $row->kode_akun_format = $this->formatCoaCode((string) $row->kode_akun);

                return $row;
            });
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
