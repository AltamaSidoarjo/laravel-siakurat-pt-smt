<?php

namespace App\Services\Pendapatan;

use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\Pelanggan;
use App\Models\PenerimaanPenjualan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PenerimaanPendapatanService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly InvoicePendapatanService $invoicePendapatanService,
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getIndexQuery(): Builder
    {
        return PenerimaanPenjualan::query()
            ->with([
                'pelanggan:id,kode_pelanggan,nama_pelanggan',
                'akunBank:id,kode,nama',
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getPelangganOptions(): Collection
    {
        return Pelanggan::query()
            ->active()
            ->whereHas('fakturPenjualans', function ($query) {
                $query
                    ->whereRaw('COALESCE(grandtotal, 0) <> COALESCE(sudah_terbayar, 0)')
                    ->where(function ($statusQuery) {
                        $statusQuery
                            ->whereNull('status_proses')
                            ->orWhere('status_proses', '!=', 3);
                    });
            })
            ->orderBy('kode_pelanggan')
            ->get(['id', 'kode_pelanggan', 'nama_pelanggan']);
    }

    public function getAvailableInvoicesByPelanggan(int $pelangganId): Collection
    {
        return FakturPenjualan::query()
            ->where('pelanggan_id', $pelangganId)
            ->whereRaw('COALESCE(grandtotal, 0) <> COALESCE(sudah_terbayar, 0)')
            ->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('status_proses')
                    ->orWhere('status_proses', '!=', 3);
            })
            ->orderBy('tanggal_faktur')
            ->orderBy('nomor_faktur')
            ->get([
                'id',
                'nomor_faktur',
                'tanggal_faktur',
                'grandtotal',
                'sudah_terbayar',
                'nama_pasien',
                'nomer_rekam_medis',
            ]);
    }

    public function create(array $data, string $actor = 'system'): PenerimaanPenjualan
    {
        $selectedRincian = $this->extractSelectedRincian($data['rincian']);

        $penerimaanPenjualan = PenerimaanPenjualan::query()->create([
            'pelanggan_id' => $data['pelanggan_id'],
            'akun_bank_id' => $data['akun_bank_id'],
            'akun_piutang_id' => $data['akun_piutang_id'],
            'akun_selisih_tarif_id' => $data['akun_selisih_tarif_id'] ?? null,
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'jumlah_pembayaran' => $data['jumlah_pembayaran'],
            'selisih_tarif' => $data['selisih_tarif'] ?? 0,
            'keterangan' => $data['keterangan'] ?? null,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $penerimaanPenjualan->rincian()->createMany($selectedRincian->map(fn ($row) => [
            'faktur_penjualan_id' => $row['faktur_penjualan_id'],
            'nominal_bayar' => $row['nominal_bayar'],
        ])->all());

        $this->invoicePendapatanService->increaseSudahTerbayar($selectedRincian);
        $this->bukuBesarService->syncFromPenerimaanPendapatan(
            penerimaanPenjualanId: (int) $penerimaanPenjualan->id,
            akunBankId: (int) $penerimaanPenjualan->akun_bank_id,
            akunPiutangId: (int) $penerimaanPenjualan->akun_piutang_id,
            akunSelisihTarifId: $penerimaanPenjualan->akun_selisih_tarif_id ? (int) $penerimaanPenjualan->akun_selisih_tarif_id : null,
            nomer: $penerimaanPenjualan->nomer,
            tanggal: $penerimaanPenjualan->tanggal->format('Y-m-d'),
            keterangan: $penerimaanPenjualan->keterangan,
            jumlahPembayaran: (float) $penerimaanPenjualan->jumlah_pembayaran,
            selisihTarif: (float) $penerimaanPenjualan->selisih_tarif,
        );

        $this->logService->log('Penerimaan Pendapatan', 'create', null, [
            'nomer' => $penerimaanPenjualan->nomer,
            'tanggal' => $penerimaanPenjualan->tanggal->format('Y-m-d'),
            'pelanggan_id' => $penerimaanPenjualan->pelanggan_id,
            'jumlah_pembayaran' => $penerimaanPenjualan->jumlah_pembayaran,
            'selisih_tarif' => $penerimaanPenjualan->selisih_tarif,
            'keterangan' => $penerimaanPenjualan->keterangan,
        ]);

        return $penerimaanPenjualan->load([
            'pelanggan',
            'akunBank',
            'akunPiutang',
            'akunSelisihTarif',
            'rincian.fakturPenjualan',
        ]);
    }

    public function update(PenerimaanPenjualan $penerimaanPenjualan, array $data, string $actor = 'system'): PenerimaanPenjualan
    {
        $oldData = [
            'nomer' => $penerimaanPenjualan->nomer,
            'tanggal' => $penerimaanPenjualan->tanggal->format('Y-m-d'),
            'jumlah_pembayaran' => $penerimaanPenjualan->jumlah_pembayaran,
            'selisih_tarif' => $penerimaanPenjualan->selisih_tarif,
            'keterangan' => $penerimaanPenjualan->keterangan,
        ];

        $oldRincian = $penerimaanPenjualan->rincian()
            ->get(['faktur_penjualan_id', 'nominal_bayar'])
            ->map(fn ($item) => [
                'faktur_penjualan_id' => (int) $item->faktur_penjualan_id,
                'nominal_bayar' => (float) $item->nominal_bayar,
            ]);

        $this->invoicePendapatanService->decreaseSudahTerbayar($oldRincian);

        $penerimaanPenjualan->update([
            'akun_bank_id' => $data['akun_bank_id'],
            'akun_piutang_id' => $data['akun_piutang_id'],
            'akun_selisih_tarif_id' => $data['akun_selisih_tarif_id'] ?? null,
            'nomer' => $data['nomer'],
            'tanggal' => $data['tanggal'],
            'jumlah_pembayaran' => $data['jumlah_pembayaran'],
            'selisih_tarif' => $data['selisih_tarif'] ?? 0,
            'keterangan' => $data['keterangan'] ?? null,
            'updated_by' => $actor,
        ]);

        $penerimaanPenjualan->rincian()->delete();
        $selectedRincian = $this->extractSelectedRincian($data['rincian']);
        $penerimaanPenjualan->rincian()->createMany($selectedRincian->map(fn ($row) => [
            'faktur_penjualan_id' => $row['faktur_penjualan_id'],
            'nominal_bayar' => $row['nominal_bayar'],
        ])->all());

        $this->invoicePendapatanService->increaseSudahTerbayar($selectedRincian);
        $this->bukuBesarService->syncFromPenerimaanPendapatan(
            penerimaanPenjualanId: (int) $penerimaanPenjualan->id,
            akunBankId: (int) $penerimaanPenjualan->akun_bank_id,
            akunPiutangId: (int) $penerimaanPenjualan->akun_piutang_id,
            akunSelisihTarifId: $penerimaanPenjualan->akun_selisih_tarif_id ? (int) $penerimaanPenjualan->akun_selisih_tarif_id : null,
            nomer: $penerimaanPenjualan->nomer,
            tanggal: $penerimaanPenjualan->tanggal->format('Y-m-d'),
            keterangan: $penerimaanPenjualan->keterangan,
            jumlahPembayaran: (float) $penerimaanPenjualan->jumlah_pembayaran,
            selisihTarif: (float) $penerimaanPenjualan->selisih_tarif,
        );

        $this->logService->log('Penerimaan Pendapatan', 'update', $oldData, [
            'nomer' => $penerimaanPenjualan->nomer,
            'tanggal' => $penerimaanPenjualan->tanggal->format('Y-m-d'),
            'jumlah_pembayaran' => $penerimaanPenjualan->jumlah_pembayaran,
            'selisih_tarif' => $penerimaanPenjualan->selisih_tarif,
            'keterangan' => $penerimaanPenjualan->keterangan,
        ]);

        return $penerimaanPenjualan->load([
            'pelanggan',
            'akunBank',
            'akunPiutang',
            'akunSelisihTarif',
            'rincian.fakturPenjualan',
        ]);
    }

    public function delete(PenerimaanPenjualan $penerimaanPenjualan): void
    {
        $this->logService->log('Penerimaan Pendapatan', 'delete', [
            'nomer' => $penerimaanPenjualan->nomer,
            'tanggal' => $penerimaanPenjualan->tanggal->format('Y-m-d'),
            'pelanggan_id' => $penerimaanPenjualan->pelanggan_id,
            'jumlah_pembayaran' => $penerimaanPenjualan->jumlah_pembayaran,
            'selisih_tarif' => $penerimaanPenjualan->selisih_tarif,
            'keterangan' => $penerimaanPenjualan->keterangan,
        ]);

        $rincian = $penerimaanPenjualan->rincian()
            ->get(['faktur_penjualan_id', 'nominal_bayar'])
            ->map(fn ($item) => [
                'faktur_penjualan_id' => (int) $item->faktur_penjualan_id,
                'nominal_bayar' => (float) $item->nominal_bayar,
            ]);

        $this->bukuBesarService->deleteBySource('Penerimaan Pendapatan', (int) $penerimaanPenjualan->id);
        $this->invoicePendapatanService->decreaseSudahTerbayar($rincian);
        $penerimaanPenjualan->rincian()->delete();
        $penerimaanPenjualan->delete();
    }

    public function findById(int $id, bool $includeInvoice = false): ?PenerimaanPenjualan
    {
        $relations = [
            'pelanggan',
            'akunBank',
            'akunPiutang',
            'rincian',
        ];

        if ($includeInvoice) {
            $relations[] = 'rincian.fakturPenjualan';
        }

        return PenerimaanPenjualan::query()
            ->with($relations)
            ->find($id);
    }

    private function extractSelectedRincian(array $rincian): SupportCollection
    {
        return collect($rincian)
            ->filter(fn ($row) => filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn ($row) => [
                'faktur_penjualan_id' => (int) $row['faktur_penjualan_id'],
                'nominal_bayar' => (float) ($row['nominal_bayar'] ?? 0),
            ])
            ->values();
    }
}
