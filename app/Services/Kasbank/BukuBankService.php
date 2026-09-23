<?php

namespace App\Services\Kasbank;

use App\Models\BukuBesar;
use App\Models\Coa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BukuBankService
{
    public function getBukuBank(string $startDate, string $endDate, array $coaIds = []): Collection
    {
        $selectedIds = collect($coaIds)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($selectedIds === []) {
            return collect();
        }

        $coaList = $this->queryKasbankCoa()
            ->whereIn('id', $selectedIds)
            ->get();
        $validIds = $coaList->pluck('id')->all();

        if ($validIds === []) {
            return collect();
        }

        $openingBalances = BukuBesar::query()
            ->selectRaw('coa_id, SUM(CASE WHEN tipe_mutasi = "D" THEN nominal ELSE -nominal END) as opening_balance')
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

        return $coaList->map(function (Coa $coa) use ($openingBalances, $transactions, $startDate) {
            $runningBalance = (float) ($openingBalances[$coa->id] ?? 0);
            $rows = collect();

            if ($openingBalances->has($coa->id)) {
                $rows->push([
                    'tanggal' => $startDate,
                    'nomer' => '',
                    'sumber_transaksi' => 'SALDO AWAL',
                    'keterangan' => 'Saldo awal periode',
                    'debit' => 0.0,
                    'kredit' => 0.0,
                    'saldo_berjalan' => $runningBalance,
                ]);
            }

            foreach ($transactions->get($coa->id, collect()) as $transaction) {
                $nominal = (float) $transaction->nominal;
                $debit = $transaction->tipe_mutasi === 'D' ? $nominal : 0.0;
                $kredit = $transaction->tipe_mutasi === 'K' ? $nominal : 0.0;
                $runningBalance += $debit - $kredit;

                $rows->push([
                    'tanggal' => optional($transaction->tanggal)->format('Y-m-d'),
                    'nomer' => (string) $transaction->nomer,
                    'sumber_transaksi' => (string) $transaction->sumber_transaksi,
                    'keterangan' => (string) $transaction->keterangan,
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo_berjalan' => $runningBalance,
                ]);
            }

            return [
                'coa_id' => (int) $coa->id,
                'kode_coa' => (string) $coa->kode,
                'nama_coa' => (string) $coa->nama,
                'rows' => $rows,
            ];
        })->filter(fn (array $coa) => $coa['rows']->isNotEmpty())->values();
    }

    public function getCoaOptions(): Collection
    {
        return $this->formatCoaOptions($this->queryKasbankCoa()->get());
    }

    public function searchCoaOptions(?string $keyword, int $limit = 30): Collection
    {
        $keyword = trim((string) $keyword);
        $query = $this->queryKasbankCoa();

        if ($keyword !== '') {
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('kode', 'like', '%'.$keyword.'%')
                    ->orWhere('nama', 'like', '%'.$keyword.'%');
            });
        }

        return $this->formatCoaOptions($query->limit($limit)->get());
    }

    private function queryKasbankCoa(): Builder
    {
        return Coa::query()
            ->leaf()
            ->whereRaw('LOWER(tipe_coa) = ?', ['kasbank'])
            ->orderBy('kode');
    }

    private function formatCoaOptions(Collection $items): Collection
    {
        return $items->map(fn (Coa $coa) => [
            'id' => (int) $coa->id,
            'kode' => (string) $coa->kode,
            'nama' => (string) $coa->nama,
        ])->values();
    }
}
