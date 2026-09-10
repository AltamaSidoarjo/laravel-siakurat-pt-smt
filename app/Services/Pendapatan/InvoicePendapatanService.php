<?php

namespace App\Services\Pendapatan;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\LogHapusImportPendapatan;
use App\Models\Pelanggan;
use App\Models\Pelaksana;
use App\Models\SimrsImportPendapatan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoicePendapatanService
{
    private const SOURCE = 'Invoice Pendapatan';

    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logService,
    ) {}

    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return FakturPenjualan::query()->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal_faktur')->orderByDesc('id');
    }

    public function getPelangganOptions(?FakturPenjualan $invoice = null): Collection
    {
        return Pelanggan::query()
            ->where(fn (Builder $query) => $query->active()->when(
                $invoice?->pelanggan_id,
                fn (Builder $query, int $pelangganId) => $query->orWhere('id', $pelangganId),
            ))
            ->orderBy('kode_pelanggan')
            ->get();
    }

    public function getReceivableCoaOptions(): Collection
    {
        return Coa::query()->selectableTransaction()
            ->where('is_postable', true)
            ->whereRaw('LOWER(tipe_coa) = ?', ['akun piutang'])
            ->get(['id', 'kode', 'nama', 'tipe_coa']);
    }

    public function getRevenueCoaOptions(): Collection
    {
        return Coa::query()->selectableTransaction()
            ->where('is_postable', true)
            ->whereRaw('LOWER(tipe_coa) = ?', ['pendapatan'])
            ->get(['id', 'kode', 'nama', 'tipe_coa']);
    }

    public function getPelaksanaOptions(?FakturPenjualan $invoice = null): Collection
    {
        $selectedIds = $invoice?->rincian->pluck('pelaksana_id')->filter()->all() ?? [];

        return Pelaksana::query()
            ->where(fn (Builder $query) => $query->where('status_aktif', true)->when(
                $selectedIds !== [],
                fn (Builder $query) => $query->orWhereIn('id', $selectedIds),
            ))
            ->orderBy('nama_pelaksana')
            ->get(['id', 'no_proyek', 'nama_pelaksana', 'status_aktif']);
    }

    public function isImported(FakturPenjualan $invoice): bool
    {
        return SimrsImportPendapatan::query()->where('import_ke', self::SOURCE)
            ->where('nomer_billing', $invoice->nomer_rawat ?: $invoice->nomor_faktur)->exists();
    }

    public function create(array $data, string $actor): FakturPenjualan
    {
        return DB::transaction(function () use ($data, $actor): FakturPenjualan {
            $pelanggan = Pelanggan::query()->findOrFail((int) $data['pelanggan_id']);
            $details = $this->mapDetails($data['rincian']);
            $grandtotal = round((float) collect($details)->sum('subtotal'), 2);
            $this->ensurePositiveTotal($grandtotal);

            $invoice = new FakturPenjualan;
            $invoice->pelanggan_id = $pelanggan->id;
            $invoice->akun_piutang_id = (int) $data['akun_piutang_id'];
            $invoice->nomor_faktur = $data['nomor_faktur'];
            $invoice->tanggal_faktur = $data['tanggal_faktur'];
            $invoice->tanggal_registrasi = $data['tanggal_faktur'];
            $invoice->keterangan = $data['keterangan'] ?? null;
            $invoice->grandtotal = $grandtotal;
            $invoice->sudah_terbayar = 0;
            $invoice->status_proses = 0;
            $invoice->created_by = $actor;
            $invoice->updated_by = $actor;
            $invoice->nama_pasien = $data['nama_pasien'];
            $invoice->nomer_rekam_medis = $data['nomer_rekam_medis'] ?? null;
            $invoice->nama_dokter = $data['nama_dokter'] ?? null;
            $invoice->nama_poli = $data['nama_poli'] ?? null;
            $invoice->nomer_rawat = '';
            $invoice->kode_penjamin = $pelanggan->kode_pelanggan;
            $invoice->nama_penjamin = $pelanggan->nama_pelanggan;
            $invoice->save();

            $invoice->rincian()->createMany($details);
            $this->syncLedger($invoice, $details);
            $this->logService->log(self::SOURCE, 'create', null, $this->logPayload($invoice, $details));

            return $invoice->load(['pelanggan', 'akunPiutang', 'rincian.coa', 'rincian.pelaksana']);
        });
    }

    public function update(FakturPenjualan $invoice, array $data, string $actor): FakturPenjualan
    {
        return DB::transaction(function () use ($invoice, $data, $actor): FakturPenjualan {
            $this->ensureMutable($invoice);
            $imported = $this->isImported($invoice);
            $oldData = $this->logPayload($invoice, $invoice->rincian->toArray());
            $details = $this->mapDetails($data['rincian'], $imported ? $invoice : null);
            $grandtotal = round((float) collect($details)->sum('subtotal'), 2);
            $this->ensurePositiveTotal($grandtotal);

            $invoice->akun_piutang_id = (int) $data['akun_piutang_id'];
            $invoice->tanggal_faktur = $data['tanggal_faktur'];
            $invoice->keterangan = $data['keterangan'] ?? null;
            $invoice->grandtotal = $grandtotal;
            $invoice->updated_by = $actor;

            if (! $imported) {
                $pelanggan = Pelanggan::query()->findOrFail((int) $data['pelanggan_id']);
                $invoice->pelanggan_id = $pelanggan->id;
                $invoice->nomor_faktur = $data['nomor_faktur'];
                $invoice->nama_pasien = $data['nama_pasien'];
                $invoice->nomer_rekam_medis = $data['nomer_rekam_medis'] ?? null;
                $invoice->nama_dokter = $data['nama_dokter'] ?? null;
                $invoice->nama_poli = $data['nama_poli'] ?? null;
                $invoice->tanggal_registrasi = $data['tanggal_faktur'];
                $invoice->kode_penjamin = $pelanggan->kode_pelanggan;
                $invoice->nama_penjamin = $pelanggan->nama_pelanggan;
            }

            $invoice->save();
            $invoice->rincian()->delete();
            $invoice->rincian()->createMany($details);
            $this->syncLedger($invoice, $details);

            if ($imported) {
                SimrsImportPendapatan::query()->where('import_ke', self::SOURCE)
                    ->where('nomer_billing', $invoice->nomer_rawat ?: $invoice->nomor_faktur)
                    ->update(['total_tagihan' => $grandtotal]);
            }

            $this->logService->log(self::SOURCE, 'update', $oldData, $this->logPayload($invoice, $details));

            return $invoice->load(['pelanggan', 'akunPiutang', 'rincian.coa', 'rincian.pelaksana']);
        });
    }

    public function delete(FakturPenjualan $invoice, string $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $this->ensureMutable($invoice);
            $importNumber = $invoice->nomer_rawat ?: $invoice->nomor_faktur;
            $import = SimrsImportPendapatan::query()->where('import_ke', self::SOURCE)
                ->where('nomer_billing', $importNumber)->first();
            $oldData = $this->logPayload($invoice, $invoice->rincian->toArray());

            $this->bukuBesarService->deleteBySource(self::SOURCE, (int) $invoice->id);
            $invoice->rincian()->delete();
            $invoice->delete();

            if ($import !== null) {
                LogHapusImportPendapatan::query()->create([
                    'nomer' => $import->nomer_billing,
                    'dihapus_oleh' => $actor,
                    'created_at' => now(),
                    'sumber_transaksi' => self::SOURCE,
                ]);
                $import->delete();
            }

            $this->logService->log(self::SOURCE, 'delete', $oldData);
        });
    }

    public function ensureMutable(FakturPenjualan $invoice): void
    {
        if ((float) $invoice->sudah_terbayar > 0 || $invoice->penerimaanPenjualanRincis()->exists()) {
            throw new RuntimeException('Invoice sudah memiliki penerimaan. Hapus penerimaan terlebih dahulu.');
        }
    }

    public function syncLedger(FakturPenjualan $invoice, array $details): void
    {
        if (! $invoice->akun_piutang_id) {
            throw new RuntimeException('Akun piutang invoice belum dipilih.');
        }

        $this->bukuBesarService->deleteBySource(self::SOURCE, (int) $invoice->id);
        $tanggal = $invoice->tanggal_faktur->format('Y-m-d');
        $payload = [[
            'coa_id' => (int) $invoice->akun_piutang_id,
            'sumber_id' => (int) $invoice->id,
            'tanggal' => $tanggal,
            ...BukuBesarService::resolvePeriode($tanggal),
            'nomer' => $invoice->nomor_faktur,
            'sumber_transaksi' => self::SOURCE,
            'nominal' => (float) $invoice->grandtotal,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Akun piutang pendapatan',
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        foreach ($details as $detail) {
            $subtotal = (float) $detail['subtotal'];
            $payload[] = [
                'coa_id' => (int) $detail['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $tanggal,
                ...BukuBesarService::resolvePeriode($tanggal),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::SOURCE,
                'nominal' => abs($subtotal),
                'tipe_mutasi' => $subtotal < 0 ? 'D' : 'K',
                'keterangan' => $detail['catatan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $debit = collect($payload)->where('tipe_mutasi', 'D')->sum('nominal');
        $kredit = collect($payload)->where('tipe_mutasi', 'K')->sum('nominal');
        if (abs($debit - $kredit) > 0.01) {
            throw new RuntimeException('Buku besar invoice tidak balance.');
        }

        BukuBesar::query()->insert($payload);
    }

    public function findById(int $id): ?FakturPenjualan
    {
        return FakturPenjualan::query()->with(['rincian.coa', 'rincian.pelaksana'])->find($id);
    }

    public function increaseSudahTerbayar(SupportCollection $rincian): void
    {
        $rincian->each(fn (array $row) => FakturPenjualan::query()->whereKey((int) $row['faktur_penjualan_id'])
            ->increment('sudah_terbayar', (float) $row['nominal_bayar']));
    }

    public function decreaseSudahTerbayar(SupportCollection $rincian): void
    {
        $rincian->each(fn (array $row) => FakturPenjualan::query()->whereKey((int) $row['faktur_penjualan_id'])
            ->decrement('sudah_terbayar', (float) $row['nominal_bayar']));
    }

    private function mapDetails(array $rows, ?FakturPenjualan $sourceInvoice = null): array
    {
        $pelaksana = Pelaksana::query()->whereIn('id', collect($rows)->pluck('pelaksana_id')->filter())
            ->get()->keyBy('id');
        $sourceDetails = $sourceInvoice?->rincian()->get(['id', 'kode_proyek'])->keyBy('id') ?? collect();

        return collect($rows)->map(function (array $row) use ($pelaksana, $sourceDetails): array {
            $executor = filled($row['pelaksana_id'] ?? null) ? $pelaksana->get((int) $row['pelaksana_id']) : null;
            $quantity = (float) $row['kuantitas'];
            $price = (float) $row['harga'];
            $sourceDetail = filled($row['id'] ?? null) ? $sourceDetails->get((int) $row['id']) : null;

            return [
                'coa_id' => (int) $row['coa_id'],
                'pelaksana_id' => $executor?->id,
                'kode_proyek' => filled($sourceDetail?->kode_proyek)
                    ? $sourceDetail->kode_proyek
                    : $executor?->no_proyek,
                'kuantitas' => $quantity,
                'harga' => $price,
                'subtotal' => round($quantity * $price, 2),
                'catatan' => filled($row['catatan'] ?? null) ? $row['catatan'] : null,
            ];
        })->all();
    }

    private function ensurePositiveTotal(float $grandtotal): void
    {
        if ($grandtotal <= 0) {
            throw new RuntimeException('Grand total invoice harus lebih besar dari nol.');
        }
    }

    private function logPayload(FakturPenjualan $invoice, array $details): array
    {
        return [
            'nomor_faktur' => $invoice->nomor_faktur,
            'tanggal_faktur' => optional($invoice->tanggal_faktur)->format('Y-m-d'),
            'pelanggan_id' => $invoice->pelanggan_id,
            'akun_piutang_id' => $invoice->akun_piutang_id,
            'grandtotal' => $invoice->grandtotal,
            'rincian' => $details,
        ];
    }
}
