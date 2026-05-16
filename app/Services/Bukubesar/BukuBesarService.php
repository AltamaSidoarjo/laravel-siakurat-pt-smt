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
}
