<?php

namespace App\Services\Bridging;

use App\Models\BukuBesar;
use App\Models\FakturPembelian;
use App\Models\FakturPembelianRinci;
use App\Models\MappingCoaSimrs;
use App\Models\Supplier;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BridgingPembelianService
{
    private const KATEGORI_OBAT_BHP = 'Obat & BHP';
    private const KATEGORI_NON_MEDIS = 'Barang Non Medis';
    private const SUMBER_TRANSAKSI = 'Invoice Pembelian';
    private const IMPORT_INVOICE_PEMBELIAN = 'InvoicePembelian';
    private const METODE_TANGGAL_INVOICE = 'TanggalInvoice';
    private const METODE_TANGGAL_BARANG_DATANG = 'TanggalBarangDatang';

    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getQueryHasilImport(string $startDate, string $endDate): Builder
    {
        return FakturPembelian::query()
            ->with('supplier:id,nama_supplier')
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id');
    }

    public function getKandidatPembelianObat(string $startDate, string $endDate): Collection
    {
        $nomerFakturTerpakai = $this->ambilLookupFakturTerpakai($startDate, $endDate);

        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_faktur,
                p.no_order,
                p.tgl_faktur,
                p.tgl_pesan,
                p.tgl_tempo,
                p.kode_suplier,
                s.nama_suplier,
                p.kd_bangsal,
                p.status,
                p.ppn,
                p.tagihan
            FROM pemesanan p
            LEFT JOIN datasuplier s ON s.kode_suplier = p.kode_suplier
            WHERE p.tgl_faktur BETWEEN ? AND ?
            ORDER BY p.tgl_faktur DESC, p.no_faktur DESC
            SQL,
            [$startDate, $endDate]
        ))
            ->reject(fn (object $row) => isset($nomerFakturTerpakai[(string) $row->no_faktur]))
            ->values()
            ->map(fn (object $row) => [
                'no_faktur' => (string) $row->no_faktur,
                'no_order' => (string) ($row->no_order ?? ''),
                'tgl_faktur' => (string) $row->tgl_faktur,
                'tgl_pesan' => (string) ($row->tgl_pesan ?? ''),
                'tgl_tempo' => (string) ($row->tgl_tempo ?? ''),
                'kode_suplier' => (string) ($row->kode_suplier ?? ''),
                'nama_suplier' => (string) ($row->nama_suplier ?? ''),
                'kd_bangsal' => (string) ($row->kd_bangsal ?? ''),
                'status' => (string) ($row->status ?? ''),
                'ppn' => (float) ($row->ppn ?? 0),
                'tagihan' => (float) ($row->tagihan ?? 0),
            ]);
    }

    public function getKandidatPembelianObatQuery(string $startDate, string $endDate): QueryBuilder
    {
        $nomerFakturTerpakai = array_keys($this->ambilLookupFakturTerpakai($startDate, $endDate));

        $query = DB::connection('simrs')
            ->table('pemesanan as p')
            ->leftJoin('datasuplier as s', 's.kode_suplier', '=', 'p.kode_suplier')
            ->whereBetween('p.tgl_faktur', [$startDate, $endDate])
            ->select([
                'p.no_faktur',
                'p.no_order',
                'p.tgl_faktur',
                'p.tgl_pesan',
                'p.tgl_tempo',
                'p.kode_suplier',
                's.nama_suplier',
                'p.kd_bangsal',
                'p.status',
                'p.ppn',
                'p.tagihan',
            ]);

        if ($nomerFakturTerpakai !== []) {
            $query->whereNotIn('p.no_faktur', $nomerFakturTerpakai);
        }

        return $query;
    }

    public function getKandidatPembelianNonMedis(string $startDate, string $endDate): Collection
    {
        $nomerFakturTerpakai = $this->ambilLookupFakturTerpakai($startDate, $endDate);

        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_faktur,
                p.no_order,
                p.tgl_faktur,
                p.tgl_pesan,
                p.tgl_tempo,
                p.kode_suplier,
                s.nama_suplier,
                p.status,
                p.ppn,
                p.tagihan
            FROM ipsrspemesanan p
            LEFT JOIN ipsrssuplier s ON s.kode_suplier = p.kode_suplier
            WHERE p.tgl_faktur BETWEEN ? AND ?
            ORDER BY p.tgl_faktur DESC, p.no_faktur DESC
            SQL,
            [$startDate, $endDate]
        ))
            ->reject(fn (object $row) => isset($nomerFakturTerpakai[(string) $row->no_faktur]))
            ->values()
            ->map(fn (object $row) => [
                'no_faktur' => (string) $row->no_faktur,
                'no_order' => (string) ($row->no_order ?? ''),
                'tgl_faktur' => (string) $row->tgl_faktur,
                'tgl_pesan' => (string) ($row->tgl_pesan ?? ''),
                'tgl_tempo' => (string) ($row->tgl_tempo ?? ''),
                'kode_suplier' => (string) ($row->kode_suplier ?? ''),
                'nama_suplier' => (string) ($row->nama_suplier ?? ''),
                'status' => (string) ($row->status ?? ''),
                'ppn' => (float) ($row->ppn ?? 0),
                'tagihan' => (float) ($row->tagihan ?? 0),
            ]);
    }

    public function imporBanyakPembelianObat(
        array $daftarNomerTransaksi,
        string $jenisProses,
        string $metodeTanggalPengakuan,
        string $actor,
    ): array {
        $hasil = [];

        foreach (array_values(array_unique($daftarNomerTransaksi)) as $nomerTransaksi) {
            try {
                $hasil[] = $this->imporSatuPembelianObat(
                    (string) $nomerTransaksi,
                    $jenisProses,
                    $metodeTanggalPengakuan,
                    $actor,
                );
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    public function imporBanyakPembelianNonMedis(
        array $daftarNomerTransaksi,
        string $jenisProses,
        string $actor,
    ): array {
        $hasil = [];

        foreach (array_values(array_unique($daftarNomerTransaksi)) as $nomerTransaksi) {
            try {
                $hasil[] = $this->imporSatuPembelianNonMedis(
                    (string) $nomerTransaksi,
                    $jenisProses,
                    $actor,
                );
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    public function hapusBanyak(array $daftarNomerTransaksi): array
    {
        $hasil = [];

        foreach (array_values(array_unique($daftarNomerTransaksi)) as $nomerTransaksi) {
            try {
                DB::transaction(function () use ($nomerTransaksi) {
                    $faktur = FakturPembelian::query()
                        ->with(['rincian', 'pembayaranPembelianRincis'])
                        ->where('nomer_faktur', $nomerTransaksi)
                        ->first();

                    if ($faktur === null) {
                        throw new RuntimeException('Tidak ditemukan faktur pembelian nomor: '.$nomerTransaksi);
                    }

                    if ($faktur->pembayaranPembelianRincis->isNotEmpty()) {
                        throw new RuntimeException('Sudah ada pembayaran untuk faktur pembelian nomor: '.$nomerTransaksi);
                    }

                    BukuBesar::query()
                        ->where('sumber_id', (int) $faktur->id)
                        ->where('nomer', $nomerTransaksi)
                        ->where('sumber_transaksi', self::SUMBER_TRANSAKSI)
                        ->delete();

                    $faktur->rincian()->delete();
                    $faktur->delete();
                });

                $this->logService->log('Bridging Pembelian', 'delete', [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                ]);

                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => true,
                    'alasan_gagal' => null,
                ];
            } catch (\Throwable $exception) {
                $hasil[] = [
                    'nomer_transaksi' => (string) $nomerTransaksi,
                    'berhasil' => false,
                    'alasan_gagal' => $exception->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    private function imporSatuPembelianObat(
        string $nomerTransaksi,
        string $jenisProses,
        string $metodeTanggalPengakuan,
        string $actor,
    ): array {
        if ($jenisProses !== self::IMPORT_INVOICE_PEMBELIAN) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ];
        }

        $tagihan = $this->ambilTagihanObatByNomer($nomerTransaksi);
        if ($tagihan === null) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Data tagihan pembelian obat & BHP tidak ditemukan di SIMRS.',
            ];
        }

        return $this->imporTagihan(
            tagihan: $tagihan,
            rincian: $this->ambilRincianTagihanObatByNomer($nomerTransaksi),
            kategoriSupplier: self::KATEGORI_OBAT_BHP,
            kategoriFaktur: self::KATEGORI_OBAT_BHP,
            metodeTanggalPengakuan: $metodeTanggalPengakuan,
            actor: $actor,
        );
    }

    private function imporSatuPembelianNonMedis(
        string $nomerTransaksi,
        string $jenisProses,
        string $actor,
    ): array {
        if ($jenisProses !== self::IMPORT_INVOICE_PEMBELIAN) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ];
        }

        $tagihan = $this->ambilTagihanNonMedisByNomer($nomerTransaksi);
        if ($tagihan === null) {
            return [
                'nomer_transaksi' => $nomerTransaksi,
                'berhasil' => false,
                'alasan_gagal' => 'Data tagihan pembelian barang non medis tidak ditemukan di SIMRS.',
            ];
        }

        return $this->imporTagihan(
            tagihan: $tagihan,
            rincian: $this->ambilRincianTagihanNonMedisByNomer($nomerTransaksi),
            kategoriSupplier: self::KATEGORI_NON_MEDIS,
            kategoriFaktur: self::KATEGORI_NON_MEDIS,
            metodeTanggalPengakuan: self::METODE_TANGGAL_INVOICE,
            actor: $actor,
        );
    }

    private function imporTagihan(
        array $tagihan,
        Collection $rincian,
        string $kategoriSupplier,
        string $kategoriFaktur,
        string $metodeTanggalPengakuan,
        string $actor,
    ): array {
        if ($rincian->isEmpty()) {
            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => false,
                'alasan_gagal' => 'Rincian tagihan tidak ditemukan.',
            ];
        }

        if ($tagihan['kode_suplier'] === '') {
            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => false,
                'alasan_gagal' => 'Kode supplier tidak tersedia pada data tagihan.',
            ];
        }

        if (FakturPembelian::query()->where('nomer_faktur', $tagihan['no_faktur'])->exists()) {
            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => false,
                'alasan_gagal' => 'Faktur pembelian dengan nomor tersebut sudah ada di sistem.',
            ];
        }

        $nomerJurnal = $this->ambilNomerJurnalSimrsTerakhir($tagihan['no_faktur']);
        if ($nomerJurnal === null) {
            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => false,
                'alasan_gagal' => 'Data jurnal SIMRS tidak ditemukan untuk nomor bukti ini.',
            ];
        }

        $rincianJurnal = $this->ambilRincianJurnalSimrs($nomerJurnal);
        if ($rincianJurnal->isEmpty()) {
            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => false,
                'alasan_gagal' => 'Rincian jurnal SIMRS tidak ditemukan.',
            ];
        }

        $result = DB::transaction(function () use (
            $tagihan,
            $rincian,
            $kategoriSupplier,
            $kategoriFaktur,
            $metodeTanggalPengakuan,
            $actor,
            $rincianJurnal
        ) {
            $supplier = $this->cariAtauBuatSupplier(
                kodeSupplier: $tagihan['kode_suplier'],
                namaSupplier: $tagihan['nama_suplier'],
                kategoriSupplier: $kategoriSupplier,
            );

            $faktur = FakturPembelian::query()->create([
                'supplier_id' => (int) $supplier->id,
                'nomer_faktur' => $tagihan['no_faktur'],
                'tanggal_faktur' => $this->tentukanTanggalPengakuan($tagihan, $metodeTanggalPengakuan),
                'keterangan' => $this->buatKeteranganFaktur($tagihan, $rincian),
                'nilai_ppn' => $tagihan['ppn'],
                'biaya_kirim' => 0,
                'sudah_terbayar' => 0,
                'status_proses' => '0',
                'created_by' => $actor,
                'updated_by' => $actor,
                'grandtotal' => $tagihan['tagihan'],
                'tanggal_jatuh_tempo' => $tagihan['tgl_tempo'] !== '' ? $tagihan['tgl_tempo'] : null,
                'tanggal_pesan' => $tagihan['tgl_pesan'] !== '' ? $tagihan['tgl_pesan'] : null,
                'kategori_faktur' => $kategoriFaktur,
                'kode_bangsal' => $tagihan['kd_bangsal'] !== '' ? $tagihan['kd_bangsal'] : null,
            ]);

            foreach ($rincian as $row) {
                FakturPembelianRinci::query()->create([
                    'faktur_pembelian_id' => (int) $faktur->id,
                    'kuantitas' => (int) round((float) $row['jumlah']),
                    'diskon_rupiah' => (float) $row['besardis'],
                    'subtotal' => (float) $row['subtotal'],
                    'catatan' => null,
                    'harga_barang' => (float) $row['harga_barang'],
                    'kode_barang' => $row['kode_barang'],
                    'nama_barang' => $row['nama_barang'],
                    'satuan_barang' => $row['kode_sat'],
                    'total' => (float) $row['total'],
                ]);
            }

            $mappingGeneral = MappingCoaSimrs::query()->get();

            // Mutasi buku besar mengikuti jurnal SIMRS apa adanya supaya invoice pembelian
            // tetap konsisten dengan pembukuan yang sudah terbentuk di sistem sumber.
            foreach ($rincianJurnal as $rinciJurnal) {
                $mapping = $mappingGeneral->firstWhere('kode_rekening', $rinciJurnal['kd_rek']);
                if ($mapping === null) {
                    throw new RuntimeException('Mapping COA tidak ditemukan untuk kode rekening: '.$rinciJurnal['kd_rek']);
                }

                $debit = (float) $rinciJurnal['debet'];
                $kredit = (float) $rinciJurnal['kredit'];

                BukuBesar::query()->create([
                    'coa_id' => (int) $mapping->coa_id,
                    'sumber_id' => (int) $faktur->id,
                    'tanggal' => $faktur->tanggal_faktur,
                    ...BukuBesarService::resolvePeriode($faktur->tanggal_faktur),
                    'nomer' => $faktur->nomer_faktur,
                    'sumber_transaksi' => self::SUMBER_TRANSAKSI,
                    'nominal' => $debit > 0 ? $debit : $kredit,
                    'tipe_mutasi' => $debit > 0 ? 'D' : 'K',
                    'keterangan' => 'Bridging pembelian nomor: '.$faktur->nomer_faktur,
                ]);
            }

            return [
                'nomer_transaksi' => $tagihan['no_faktur'],
                'berhasil' => true,
                'alasan_gagal' => null,
            ];
        });

        $this->logService->log('Bridging Pembelian', 'create', null, [
            'nomer_transaksi' => $tagihan['no_faktur'],
            'kategori' => $kategoriFaktur,
            'tagihan' => $tagihan['tagihan'],
        ]);

        return $result;
    }

    private function ambilLookupFakturTerpakai(string $startDate, string $endDate): array
    {
        return array_fill_keys(
            FakturPembelian::query()
                ->betweenDates($startDate, $endDate)
                ->pluck('nomer_faktur')
                ->all(),
            true,
        );
    }

    private function ambilTagihanObatByNomer(string $nomerTransaksi): ?array
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_faktur,
                p.no_order,
                p.tgl_faktur,
                p.tgl_pesan,
                p.tgl_tempo,
                p.kode_suplier,
                s.nama_suplier,
                p.kd_bangsal,
                p.status,
                p.ppn,
                p.tagihan
            FROM pemesanan p
            LEFT JOIN datasuplier s ON s.kode_suplier = p.kode_suplier
            WHERE p.no_faktur = ?
            LIMIT 1
            SQL,
            [$nomerTransaksi]
        ))->first();

        return $row !== null ? $this->mapTagihanHeader($row, true) : null;
    }

    private function ambilTagihanNonMedisByNomer(string $nomerTransaksi): ?array
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                p.no_faktur,
                p.no_order,
                p.tgl_faktur,
                p.tgl_pesan,
                p.tgl_tempo,
                p.kode_suplier,
                s.nama_suplier,
                p.status,
                p.ppn,
                p.tagihan
            FROM ipsrspemesanan p
            LEFT JOIN ipsrssuplier s ON s.kode_suplier = p.kode_suplier
            WHERE p.no_faktur = ?
            LIMIT 1
            SQL,
            [$nomerTransaksi]
        ))->first();

        return $row !== null ? $this->mapTagihanHeader($row, false) : null;
    }

    private function ambilRincianTagihanObatByNomer(string $nomerTransaksi): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                dp.no_faktur,
                dp.kode_brng,
                db.nama_brng,
                dp.h_pesan,
                dp.jumlah,
                dp.besardis,
                dp.subtotal,
                dp.kode_sat,
                dp.total
            FROM detailpesan dp
            JOIN databarang db ON db.kode_brng = dp.kode_brng
            WHERE dp.no_faktur = ?
            ORDER BY dp.kode_brng ASC
            SQL,
            [$nomerTransaksi]
        ))->map(fn (object $row) => [
            'kode_barang' => (string) $row->kode_brng,
            'nama_barang' => (string) $row->nama_brng,
            'harga_barang' => (float) $row->h_pesan,
            'jumlah' => (float) $row->jumlah,
            'besardis' => (float) $row->besardis,
            'subtotal' => (float) $row->subtotal,
            'kode_sat' => (string) $row->kode_sat,
            'total' => (float) $row->total,
        ]);
    }

    private function ambilRincianTagihanNonMedisByNomer(string $nomerTransaksi): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT
                dp.no_faktur,
                dp.kode_brng,
                b.nama_brng,
                dp.harga,
                dp.jumlah,
                dp.besardis,
                dp.subtotal,
                dp.kode_sat,
                dp.total
            FROM ipsrsdetailpesan dp
            JOIN ipsrsbarang b ON b.kode_brng = dp.kode_brng
            WHERE dp.no_faktur = ?
            ORDER BY dp.kode_brng ASC
            SQL,
            [$nomerTransaksi]
        ))->map(fn (object $row) => [
            'kode_barang' => (string) $row->kode_brng,
            'nama_barang' => (string) $row->nama_brng,
            'harga_barang' => (float) $row->harga,
            'jumlah' => (float) $row->jumlah,
            'besardis' => (float) $row->besardis,
            'subtotal' => (float) $row->subtotal,
            'kode_sat' => (string) $row->kode_sat,
            'total' => (float) $row->total,
        ]);
    }

    private function ambilNomerJurnalSimrsTerakhir(string $nomerBukti): ?string
    {
        $row = collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT no_jurnal
            FROM jurnal
            WHERE no_bukti = ?
            ORDER BY no_jurnal DESC
            LIMIT 1
            SQL,
            [$nomerBukti]
        ))->first();

        return $row !== null ? (string) $row->no_jurnal : null;
    }

    private function ambilRincianJurnalSimrs(string $nomerJurnal): Collection
    {
        return collect(DB::connection('simrs')->select(
            <<<'SQL'
            SELECT no_jurnal, kd_rek, debet, kredit
            FROM detailjurnal
            WHERE no_jurnal = ?
            ORDER BY kd_rek ASC
            SQL,
            [$nomerJurnal]
        ))->map(fn (object $row) => [
            'no_jurnal' => (string) $row->no_jurnal,
            'kd_rek' => (string) $row->kd_rek,
            'debet' => (float) ($row->debet ?? 0),
            'kredit' => (float) ($row->kredit ?? 0),
        ]);
    }

    private function cariAtauBuatSupplier(string $kodeSupplier, string $namaSupplier, string $kategoriSupplier): Supplier
    {
        $existingSupplier = Supplier::query()
            ->where('kode_supplier', $kodeSupplier)
            ->where('nama_supplier', $namaSupplier)
            ->first();

        if ($existingSupplier !== null) {
            if ($existingSupplier->kategori_supplier !== $kategoriSupplier) {
                $existingSupplier->kategori_supplier = $existingSupplier->kategori_supplier ?: $kategoriSupplier;
                $existingSupplier->save();
            }

            return $existingSupplier;
        }

        return Supplier::query()->create([
            'status_aktif' => true,
            'kode_supplier' => $kodeSupplier,
            'nama_supplier' => $namaSupplier,
            'kategori_supplier' => $kategoriSupplier,
        ]);
    }

    private function tentukanTanggalPengakuan(array $tagihan, string $metodeTanggalPengakuan): string
    {
        $gunakanTanggalBarangDatang = $metodeTanggalPengakuan === self::METODE_TANGGAL_BARANG_DATANG;

        if ($gunakanTanggalBarangDatang && $tagihan['tgl_pesan'] !== '') {
            return $tagihan['tgl_pesan'];
        }

        return $tagihan['tgl_faktur'];
    }

    private function buatKeteranganFaktur(array $tagihan, Collection $rincian): string
    {
        $header = [
            'No. order: '.($tagihan['no_order'] !== '' ? $tagihan['no_order'] : '-'),
            'Status SIMRS: '.($tagihan['status'] !== '' ? $tagihan['status'] : '-'),
        ];

        if ($tagihan['kd_bangsal'] !== '') {
            $header[] = 'Kode bangsal: '.$tagihan['kd_bangsal'];
        }

        $rincianText = $rincian
            ->map(fn (array $item) => sprintf(
                '%s - %s - %s x %s = %s',
                $item['kode_barang'],
                $item['nama_barang'],
                $this->formatNominal($item['jumlah']),
                $this->formatNominal($item['harga_barang']),
                $this->formatNominal($item['total']),
            ))
            ->implode(PHP_EOL);

        return trim(implode(PHP_EOL, $header).PHP_EOL.PHP_EOL.'Rincian:'.PHP_EOL.$rincianText);
    }

    private function mapTagihanHeader(object $row, bool $hasBangsal): array
    {
        return [
            'no_faktur' => (string) $row->no_faktur,
            'no_order' => (string) ($row->no_order ?? ''),
            'tgl_faktur' => (string) $row->tgl_faktur,
            'tgl_pesan' => (string) ($row->tgl_pesan ?? ''),
            'tgl_tempo' => (string) ($row->tgl_tempo ?? ''),
            'kode_suplier' => (string) ($row->kode_suplier ?? ''),
            'nama_suplier' => (string) ($row->nama_suplier ?? ''),
            'kd_bangsal' => $hasBangsal ? (string) ($row->kd_bangsal ?? '') : '',
            'status' => (string) ($row->status ?? ''),
            'ppn' => (float) ($row->ppn ?? 0),
            'tagihan' => (float) ($row->tagihan ?? 0),
        ];
    }

    private function formatNominal(float $nominal): string
    {
        $rounded = round($nominal, 2);

        if (abs($rounded - round($rounded)) < 0.00001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }
}
