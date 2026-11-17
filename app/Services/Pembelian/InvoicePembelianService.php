<?php

namespace App\Services\Pembelian;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\Supplier;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoicePembelianService
{
    private const SOURCE = 'Invoice Pembelian';

    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getIndexQuery(string $startDate, string $endDate): Builder
    {
        return FakturPembelian::query()
            ->with(['supplier:id,nama_supplier', 'akunHutang:id,kode,nama'])
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id');
    }

    public function findById(int $id): ?FakturPembelian
    {
        return FakturPembelian::query()
            ->with(['supplier', 'akunHutang', 'rincian.coa'])
            ->find($id);
    }

    public function getSupplierOptions(?FakturPembelian $invoice = null): Collection
    {
        return Supplier::query()
            ->where(fn (Builder $query) => $query->where('status_aktif', true)->when(
                $invoice?->supplier_id,
                fn (Builder $query, int $supplierId) => $query->orWhere('id', $supplierId),
            ))
            ->orderBy('nama_supplier')
            ->get(['id', 'kode_supplier', 'nama_supplier']);
    }

    public function getPayableCoaOptions(): Collection
    {
        return Coa::query()->selectableTransaction()
            ->where(function (Builder $query) {
                $query->whereRaw('LOWER(tipe_coa) LIKE ?', ['%hutang%'])
                    ->orWhereRaw('LOWER(tipe_coa) LIKE ?', ['%utang%'])
                    ->orWhereRaw('LOWER(tipe_coa) LIKE ?', ['%liabilitas%']);
            })
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'tipe_coa']);
    }

    public function getPurchasingCoaOptions(): Collection
    {
        return Coa::query()->selectableTransaction()
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'tipe_coa']);
    }

    public function create(array $data, string $actor): FakturPembelian
    {
        return DB::transaction(function () use ($data, $actor): FakturPembelian {
            $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
            $details = $this->mapDetails($data['rincian']);
            $grandtotal = round((float) collect($details)->sum('subtotal'), 2);
            $this->ensurePositiveTotal($grandtotal);

            $invoice = new FakturPembelian;
            $invoice->supplier_id = $supplier->id;
            $invoice->akun_hutang_id = (int) $data['akun_hutang_id'];
            $invoice->nomer_faktur = $data['nomer_faktur'];
            $invoice->tanggal_faktur = $data['tanggal_faktur'];
            $invoice->tanggal_jatuh_tempo = $data['tanggal_jatuh_tempo'] ?? null;
            $invoice->keterangan = $data['keterangan'] ?? null;
            $invoice->nilai_ppn = 0;
            $invoice->biaya_kirim = 0;
            $invoice->grandtotal = $grandtotal;
            $invoice->sudah_terbayar = 0;
            $invoice->status_proses = '0';
            $invoice->created_by = $actor;
            $invoice->updated_by = $actor;
            $invoice->save();

            $invoice->rincian()->createMany($details);
            $this->syncLedger($invoice, $details);
            $this->logService->log(self::SOURCE, 'create', null, $this->logPayload($invoice, $details));

            return $invoice->load(['supplier', 'akunHutang', 'rincian.coa']);
        });
    }

    public function update(FakturPembelian $invoice, array $data, string $actor): FakturPembelian
    {
        return DB::transaction(function () use ($invoice, $data, $actor): FakturPembelian {
            $this->ensureMutable($invoice);
            $oldData = $this->logPayload($invoice, $invoice->rincian->toArray());
            $details = $this->mapDetails($data['rincian']);
            $grandtotal = round((float) collect($details)->sum('subtotal'), 2);
            $this->ensurePositiveTotal($grandtotal);

            $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
            $invoice->supplier_id = $supplier->id;
            $invoice->akun_hutang_id = (int) $data['akun_hutang_id'];
            $invoice->nomer_faktur = $data['nomer_faktur'];
            $invoice->tanggal_faktur = $data['tanggal_faktur'];
            $invoice->tanggal_jatuh_tempo = $data['tanggal_jatuh_tempo'] ?? null;
            $invoice->keterangan = $data['keterangan'] ?? null;
            $invoice->grandtotal = $grandtotal;
            $invoice->updated_by = $actor;
            $invoice->save();

            $invoice->rincian()->delete();
            $invoice->rincian()->createMany($details);
            $this->syncLedger($invoice, $details);

            $this->logService->log(self::SOURCE, 'update', $oldData, $this->logPayload($invoice, $details));

            return $invoice->load(['supplier', 'akunHutang', 'rincian.coa']);
        });
    }

    public function delete(FakturPembelian $invoice, string $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $this->ensureMutable($invoice);
            $oldData = $this->logPayload($invoice, $invoice->rincian->toArray());

            $this->bukuBesarService->deleteBySource(self::SOURCE, (int) $invoice->id);
            $invoice->rincian()->delete();
            $invoice->delete();

            $this->logService->log(self::SOURCE, 'delete', $oldData);
        });
    }

    public function ensureMutable(FakturPembelian $invoice): void
    {
        if ((float) $invoice->sudah_terbayar > 0 || $invoice->pembayaranPembelianRincis()->exists()) {
            throw new RuntimeException('Invoice sudah memiliki pembayaran. Hapus pembayaran terlebih dahulu.');
        }
    }

    public function syncLedger(FakturPembelian $invoice, array $details): void
    {
        if (! $invoice->akun_hutang_id) {
            throw new RuntimeException('Akun hutang invoice belum dipilih.');
        }

        $this->bukuBesarService->deleteBySource(self::SOURCE, (int) $invoice->id);
        $tanggal = optional($invoice->tanggal_faktur)->format('Y-m-d') ?: now()->toDateString();
        $keteranganBukuBesar = filled($invoice->keterangan) ? (string) $invoice->keterangan : null;

        $payload = [[
            'coa_id' => (int) $invoice->akun_hutang_id,
            'sumber_id' => (int) $invoice->id,
            'tanggal' => $tanggal,
            ...BukuBesarService::resolvePeriode($tanggal),
            'nomer' => $invoice->nomer_faktur,
            'sumber_transaksi' => self::SOURCE,
            'nominal' => (float) $invoice->grandtotal,
            'tipe_mutasi' => 'K',
            'keterangan' => $keteranganBukuBesar ?? 'Akun hutang pembelian',
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
                'nomer' => $invoice->nomer_faktur,
                'sumber_transaksi' => self::SOURCE,
                'nominal' => abs($subtotal),
                'tipe_mutasi' => $subtotal < 0 ? 'K' : 'D',
                'keterangan' => $keteranganBukuBesar ?? ($detail['catatan'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $debit = collect($payload)->where('tipe_mutasi', 'D')->sum('nominal');
        $kredit = collect($payload)->where('tipe_mutasi', 'K')->sum('nominal');
        if (abs($debit - $kredit) > 0.01) {
            throw new RuntimeException('Buku besar invoice pembelian tidak balance.');
        }

        BukuBesar::query()->insert($payload);
    }

    public function increaseSudahTerbayar(SupportCollection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPembelian::query()
                ->whereKey((int) $row['faktur_pembelian_id'])
                ->increment('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }

    public function decreaseSudahTerbayar(SupportCollection $rincian): void
    {
        $rincian->each(function (array $row) {
            FakturPembelian::query()
                ->whereKey((int) $row['faktur_pembelian_id'])
                ->decrement('sudah_terbayar', (float) $row['nominal_bayar']);
        });
    }

    private function mapDetails(array $rows): array
    {
        return collect($rows)->map(function (array $row): array {
            $quantity = (float) $row['kuantitas'];
            $price = (float) $row['harga'];
            $subtotal = round($quantity * $price, 2);

            return [
                'coa_id' => (int) $row['coa_id'],
                'kuantitas' => $quantity,
                'harga_barang' => $price,
                'diskon_rupiah' => 0,
                'subtotal' => $subtotal,
                'total' => $subtotal,
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

    private function logPayload(FakturPembelian $invoice, array $details): array
    {
        return [
            'nomer_faktur' => $invoice->nomer_faktur,
            'tanggal_faktur' => optional($invoice->tanggal_faktur)->format('Y-m-d'),
            'supplier_id' => $invoice->supplier_id,
            'akun_hutang_id' => $invoice->akun_hutang_id,
            'grandtotal' => $invoice->grandtotal,
            'rincian' => $details,
        ];
    }
}
