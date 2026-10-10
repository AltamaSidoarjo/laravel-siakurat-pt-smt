<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\SaldoAwal;
use App\Services\LogAktifitasService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class SaldoAwalService
{
    public function __construct(
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logAktivitasService,
    ) {
    }

    public function getCoaOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama', 'tipe_coa']);
    }

    public function getIndexQuery(): Builder
    {
        return SaldoAwal::query()
            ->with(['createdByUser', 'lockedByUser'])
            ->orderByDesc('tanggal_cutoff')
            ->orderByDesc('id');
    }

    public function generateNomer(string $tanggalCutoff): string
    {
        $date = Carbon::parse($tanggalCutoff);
        $prefix = 'SA-' . $date->format('Ymd');

        $counter = 1;
        $candidate = $prefix;
        while (SaldoAwal::query()->where('nomer', $candidate)->exists()) {
            $candidate = $prefix . '-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $candidate;
    }

    public function create(array $data, int $userId): SaldoAwal
    {
        $action = $data['action'] ?? 'save_draft';
        $isLockRequested = $action === 'save_lock';

        $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
        $totalDebit = collect($rincianPayload)->sum('debit');
        $totalKredit = collect($rincianPayload)->sum('kredit');

        if ($isLockRequested && abs($totalDebit - $totalKredit) > 0.01) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo awal tidak seimbang (Total Debit tidak sama dengan Total Kredit). Hanya saldo yang seimbang yang dapat dikunci dan diposting ke Buku Besar.',
            ]);
        }

        $status = $isLockRequested ? 'locked' : 'draft';
        $nomer = ! empty($data['nomer']) ? $data['nomer'] : $this->generateNomer($data['tanggal_cutoff']);

        $saldoAwal = SaldoAwal::query()->create([
            'nomer' => $nomer,
            'tanggal_cutoff' => $data['tanggal_cutoff'],
            'keterangan' => $data['keterangan'] ?? null,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'status' => $status,
            'locked_at' => $isLockRequested ? now() : null,
            'locked_by' => $isLockRequested ? $userId : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $saldoAwal->rincian()->createMany($rincianPayload);

        if ($isLockRequested) {
            $this->bukuBesarService->syncFromSaldoAwal(
                (int) $saldoAwal->id,
                $saldoAwal->nomer,
                $saldoAwal->tanggal_cutoff->format('Y-m-d'),
                $saldoAwal->keterangan,
                $rincianPayload,
            );
        }

        $this->logAktivitasService->log('Saldo Awal', 'create', null, [
            'nomer' => $saldoAwal->nomer,
            'tanggal_cutoff' => $saldoAwal->tanggal_cutoff->format('Y-m-d'),
            'status' => $saldoAwal->status,
            'total_debit' => $saldoAwal->total_debit,
            'total_kredit' => $saldoAwal->total_kredit,
            'rincian' => $rincianPayload,
        ]);

        return $saldoAwal->load('rincian.coa');
    }

    public function update(SaldoAwal $saldoAwal, array $data, int $userId): SaldoAwal
    {
        if ($saldoAwal->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Data Saldo Awal sudah terkunci dan tidak dapat diubah secara langsung. Silakan buka kunci terlebih dahulu.',
            ]);
        }

        $action = $data['action'] ?? 'save_draft';
        $isLockRequested = $action === 'save_lock';

        $rincianPayload = $this->mapRincianPayload($data['rincian'] ?? []);
        $totalDebit = collect($rincianPayload)->sum('debit');
        $totalKredit = collect($rincianPayload)->sum('kredit');

        if ($isLockRequested && abs($totalDebit - $totalKredit) > 0.01) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo awal tidak seimbang (Total Debit tidak sama dengan Total Kredit). Hanya saldo yang seimbang yang dapat dikunci dan diposting ke Buku Besar.',
            ]);
        }

        $status = $isLockRequested ? 'locked' : 'draft';

        $oldData = [
            'nomer' => $saldoAwal->nomer,
            'tanggal_cutoff' => $saldoAwal->tanggal_cutoff->format('Y-m-d'),
            'status' => $saldoAwal->status,
            'total_debit' => $saldoAwal->total_debit,
            'total_kredit' => $saldoAwal->total_kredit,
            'rincian' => $saldoAwal->rincian->map->only(['coa_id', 'debit', 'kredit', 'catatan'])->all(),
        ];

        $saldoAwal->update([
            'nomer' => ! empty($data['nomer']) ? $data['nomer'] : $saldoAwal->nomer,
            'tanggal_cutoff' => $data['tanggal_cutoff'],
            'keterangan' => $data['keterangan'] ?? null,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'status' => $status,
            'locked_at' => $isLockRequested ? now() : null,
            'locked_by' => $isLockRequested ? $userId : null,
            'updated_by' => $userId,
        ]);

        $saldoAwal->rincian()->delete();
        $saldoAwal->rincian()->createMany($rincianPayload);

        if ($isLockRequested) {
            $this->bukuBesarService->syncFromSaldoAwal(
                (int) $saldoAwal->id,
                $saldoAwal->nomer,
                $saldoAwal->tanggal_cutoff->format('Y-m-d'),
                $saldoAwal->keterangan,
                $rincianPayload,
            );
        } else {
            $this->bukuBesarService->deleteBySource('Saldo Awal', (int) $saldoAwal->id);
        }

        $this->logAktivitasService->log('Saldo Awal', 'update', $oldData, [
            'nomer' => $saldoAwal->nomer,
            'tanggal_cutoff' => $saldoAwal->tanggal_cutoff->format('Y-m-d'),
            'status' => $saldoAwal->status,
            'total_debit' => $saldoAwal->total_debit,
            'total_kredit' => $saldoAwal->total_kredit,
            'rincian' => $rincianPayload,
        ]);

        return $saldoAwal->load('rincian.coa');
    }

    public function lock(SaldoAwal $saldoAwal, int $userId): SaldoAwal
    {
        if ($saldoAwal->isLocked()) {
            return $saldoAwal;
        }

        if (! $saldoAwal->isBalanced()) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo awal belum seimbang (selisih belum Rp 0). Tidak dapat dikunci dan diposting ke Buku Besar.',
            ]);
        }

        $saldoAwal->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $userId,
            'updated_by' => $userId,
        ]);

        $rincianPayload = $saldoAwal->rincian->map(fn ($row) => [
            'coa_id' => $row->coa_id,
            'debit' => (float) $row->debit,
            'kredit' => (float) $row->kredit,
            'catatan' => $row->catatan,
        ])->all();

        $this->bukuBesarService->syncFromSaldoAwal(
            (int) $saldoAwal->id,
            $saldoAwal->nomer,
            $saldoAwal->tanggal_cutoff->format('Y-m-d'),
            $saldoAwal->keterangan,
            $rincianPayload,
        );

        $this->logAktivitasService->log('Saldo Awal', 'lock', null, [
            'nomer' => $saldoAwal->nomer,
            'locked_by' => $userId,
            'locked_at' => now()->toDateTimeString(),
        ]);

        return $saldoAwal;
    }

    public function unlock(SaldoAwal $saldoAwal, int $userId): SaldoAwal
    {
        if (! $saldoAwal->isLocked()) {
            return $saldoAwal;
        }

        $this->bukuBesarService->deleteBySource('Saldo Awal', (int) $saldoAwal->id);

        $saldoAwal->update([
            'status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
            'updated_by' => $userId,
        ]);

        $this->logAktivitasService->log('Saldo Awal', 'unlock', null, [
            'nomer' => $saldoAwal->nomer,
            'unlocked_by' => $userId,
            'unlocked_at' => now()->toDateTimeString(),
        ]);

        return $saldoAwal;
    }

    public function delete(SaldoAwal $saldoAwal): void
    {
        if ($saldoAwal->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Saldo Awal yang sudah terkunci tidak dapat dihapus. Silakan buka kunci terlebih dahulu jika ingin menghapus.',
            ]);
        }

        $this->logAktivitasService->log('Saldo Awal', 'delete', [
            'nomer' => $saldoAwal->nomer,
            'tanggal_cutoff' => $saldoAwal->tanggal_cutoff->format('Y-m-d'),
            'total_debit' => $saldoAwal->total_debit,
            'total_kredit' => $saldoAwal->total_kredit,
        ]);

        $this->bukuBesarService->deleteBySource('Saldo Awal', (int) $saldoAwal->id);
        $saldoAwal->rincian()->delete();
        $saldoAwal->delete();
    }

    public function mapRincianPayload(array $rincian): array
    {
        return collect($rincian)
            ->filter(fn ($row) => ! empty($row['coa_id']))
            ->map(function ($row) {
                $debit = (float) str_replace(['.', ','], ['', '.'], (string) ($row['debit'] ?? 0));
                $kredit = (float) str_replace(['.', ','], ['', '.'], (string) ($row['kredit'] ?? 0));

                return [
                    'coa_id' => (int) $row['coa_id'],
                    'debit' => max(0, $debit),
                    'kredit' => max(0, $kredit),
                    'catatan' => $row['catatan'] ?? null,
                ];
            })
            ->filter(fn ($row) => $row['debit'] > 0 || $row['kredit'] > 0)
            ->values()
            ->all();
    }
}

