<?php

namespace App\Services\Kasbank;

use App\Models\BukuBesar;
use App\Models\Coa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BukuBankService
{
    public function getCoaOptions(): Collection
    {
        return $this->queryKasbankCoa()->get(['id', 'kode', 'nama', 'status_aktif']);
    }

    public function getReport(string $startDate, string $endDate, array $coaIds = []): Collection
    {
        $requestedIds = collect($coaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $coaQuery = $this->queryKasbankCoa();
        if ($requestedIds->isNotEmpty()) {
            $coaQuery->whereIn('id', $requestedIds->all());
        }

        $coaList = $coaQuery->get(['id', 'kode', 'nama', 'status_aktif']);
        $validIds = $coaList->pluck('id')->all();

        if ($validIds === []) {
            return collect();
        }

        $openingBalances = BukuBesar::query()
            ->selectRaw('coa_id, SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE -nominal END) AS opening_balance')
            ->whereIn('coa_id', $validIds)
            ->where('tanggal', '<', $startDate)
            ->groupBy('coa_id')
            ->pluck('opening_balance', 'coa_id');

        $transactions = BukuBesar::query()
            ->whereIn('coa_id', $validIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('coa_id')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->groupBy('coa_id');

        return $coaList->map(function (Coa $coa) use ($openingBalances, $transactions, $startDate): array {
            $runningBalance = (float) ($openingBalances[$coa->id] ?? 0);
            $rows = collect([[
                'tanggal' => $startDate,
                'nomer' => '',
                'sumber_transaksi' => 'SALDO AWAL',
                'keterangan' => 'Saldo awal periode',
                'debit' => 0.0,
                'kredit' => 0.0,
                'saldo_berjalan' => $runningBalance,
            ]]);

            foreach ($transactions->get($coa->id, collect()) as $transaction) {
                $nominal = (float) $transaction->nominal;
                $debit = $transaction->tipe_mutasi === 'D' ? $nominal : 0.0;
                $kredit = $transaction->tipe_mutasi === 'K' ? $nominal : 0.0;
                $runningBalance += $debit - $kredit;

                $rows->push([
                    'tanggal' => optional($transaction->tanggal)->format('Y-m-d'),
                    'nomer' => (string) ($transaction->nomer ?? ''),
                    'sumber_transaksi' => (string) $transaction->sumber_transaksi,
                    'keterangan' => (string) ($transaction->keterangan ?? ''),
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo_berjalan' => $runningBalance,
                ]);
            }

            return [
                'coa_id' => (int) $coa->id,
                'kode_coa' => (string) $coa->kode,
                'nama_coa' => (string) $coa->nama,
                'status_aktif' => (int) $coa->status_aktif,
                'rows' => $rows,
            ];
        })->values();
    }

    private function queryKasbankCoa(): Builder
    {
        return Coa::query()
            ->leaf()
            ->whereRaw('LOWER(TRIM(tipe_coa)) = ?', ['kasbank'])
            ->orderBy('kode');
    }
}
