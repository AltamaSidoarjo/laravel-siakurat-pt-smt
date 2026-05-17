<?php

namespace App\Services\Bukubesar;

use App\Models\BukuBesar;

class BukuBesarService
{
    public function syncFromJurnalUmum(int $jurnalUmumId, string $nomer, string $tanggal, ?string $keterangan, array $rincian): void
    {
        $this->deleteBySource('Jurnal Umum', $jurnalUmumId);

        $payload = collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(function (array $row) use ($jurnalUmumId, $nomer, $tanggal, $keterangan) {
                $debit = (float) ($row['debit'] ?? 0);
                $kredit = (float) ($row['kredit'] ?? 0);

                return [
                    'coa_id' => (int) $row['coa_id'],
                    'sumber_id' => $jurnalUmumId,
                    'tanggal' => $tanggal,
                    'nomer' => $nomer,
                    'sumber_transaksi' => 'Jurnal Umum',
                    'nominal' => $debit > 0 ? $debit : $kredit,
                    'tipe_mutasi' => $debit > 0 ? 'D' : 'K',
                    'keterangan' => $row['catatan'] ?? $keterangan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if ($payload !== []) {
            BukuBesar::query()->insert($payload);
        }
    }

    public function deleteBySource(string $sourceTransaction, int $sourceId): void
    {
        BukuBesar::query()
            ->forSource($sourceTransaction, $sourceId)
            ->delete();
    }

    public function syncFromKasbankPenerimaan(
        int $kasbankPenerimaanId,
        int $coaId,
        string $nomer,
        string $tanggal,
        ?string $keterangan,
        float $total,
        array $rincian,
    ): void {
        $this->deleteBySource('Kasbank Penerimaan', $kasbankPenerimaanId);

        $payload = [[
            'coa_id' => $coaId,
            'sumber_id' => $kasbankPenerimaanId,
            'tanggal' => $tanggal,
            'nomer' => $nomer,
            'sumber_transaksi' => 'Kasbank Penerimaan',
            'nominal' => $total,
            'tipe_mutasi' => 'D',
            'keterangan' => $keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        $detailPayload = collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(function (array $row) use ($kasbankPenerimaanId, $nomer, $tanggal) {
                return [
                    'coa_id' => (int) $row['coa_id'],
                    'sumber_id' => $kasbankPenerimaanId,
                    'tanggal' => $tanggal,
                    'nomer' => $nomer,
                    'sumber_transaksi' => 'Kasbank Penerimaan',
                    'nominal' => (float) ($row['nominal'] ?? 0),
                    'tipe_mutasi' => 'K',
                    'keterangan' => $row['catatan'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        BukuBesar::query()->insert([...$payload, ...$detailPayload]);
    }

    public function syncFromKasbankPembayaran(
        int $kasbankPembayaranId,
        int $coaId,
        string $nomer,
        string $tanggal,
        ?string $keterangan,
        float $total,
        array $rincian,
    ): void {
        $this->deleteBySource('Kasbank Pembayaran', $kasbankPembayaranId);

        $payload = [[
            'coa_id' => $coaId,
            'sumber_id' => $kasbankPembayaranId,
            'tanggal' => $tanggal,
            'nomer' => $nomer,
            'sumber_transaksi' => 'Kasbank Pembayaran',
            'nominal' => $total,
            'tipe_mutasi' => 'K',
            'keterangan' => $keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        $detailPayload = collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(function (array $row) use ($kasbankPembayaranId, $nomer, $tanggal) {
                return [
                    'coa_id' => (int) $row['coa_id'],
                    'sumber_id' => $kasbankPembayaranId,
                    'tanggal' => $tanggal,
                    'nomer' => $nomer,
                    'sumber_transaksi' => 'Kasbank Pembayaran',
                    'nominal' => (float) ($row['nominal'] ?? 0),
                    'tipe_mutasi' => 'D',
                    'keterangan' => $row['catatan'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        BukuBesar::query()->insert([...$payload, ...$detailPayload]);
    }
}
