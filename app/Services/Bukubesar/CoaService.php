<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\TipeCoa;
use App\Services\LogAktifitasService;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CoaService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
        private readonly ?ReferensiKelompokArusKasService $referensiKelompokService = null,
    ) {
    }

    private function getReferensiKelompokService(): ReferensiKelompokArusKasService
    {
        return $this->referensiKelompokService ?? app(ReferensiKelompokArusKasService::class);
    }

    public function getTreeRows(): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE coa_tree AS (
                SELECT
                    c.id,
                    c.parent_coa,
                    c.tipe_coa,
                    c.arus_kas_aktivitas,
                    c.arus_kas_kelompok,
                    c.kode,
                    c.nama,
                    0 AS level,
                    CAST(LPAD(c.kode, 20, '0') AS CHAR(1000)) AS sort_path
                FROM coa c
                LEFT JOIN coa p ON p.id = c.parent_coa
                WHERE c.parent_coa IS NULL
                    OR c.parent_coa = 0
                    OR p.id IS NULL

                UNION ALL

                SELECT
                    ch.id,
                    ch.parent_coa,
                    ch.tipe_coa,
                    ch.arus_kas_aktivitas,
                    ch.arus_kas_kelompok,
                    ch.kode,
                    ch.nama,
                    ct.level + 1 AS level,
                    CONCAT(ct.sort_path, '>', LPAD(ch.kode, 20, '0')) AS sort_path
                FROM coa ch
                JOIN coa_tree ct ON ch.parent_coa = ct.id
            )
            SELECT
                t.id AS id,
                t.parent_coa AS parent_coa,
                t.level AS level,
                t.kode AS kode,
                t.nama AS nama,
                t.tipe_coa AS tipe_coa,
                t.arus_kas_aktivitas AS arus_kas_aktivitas,
                t.arus_kas_kelompok AS arus_kas_kelompok,
                EXISTS (
                    SELECT 1
                    FROM coa c2
                    WHERE c2.parent_coa = t.id
                    LIMIT 1
                ) AS is_parent,
                EXISTS (
                    SELECT 1
                    FROM coa c3
                    WHERE c3.parent_coa = t.id
                    LIMIT 1
                ) AS has_children
            FROM coa_tree t
            ORDER BY t.sort_path ASC
        SQL;

        return DB::select($sql);
    }

    public function getParentOptions(?int $excludeId = null): Collection
    {
        $excludedIds = collect();

        if ($excludeId !== null) {
            $excludedIds = $this->getDescendantIds($excludeId)->push($excludeId);
        }

        return Coa::query()
            ->when($excludedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludedIds->all()))
            ->where(function ($query) {
                $query->whereHas('children')
                    ->orWhereDoesntHave('bukuBesar');
            })
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'tipe_coa', 'arus_kas_aktivitas', 'arus_kas_kelompok']);
    }

    public function getSelectableTransactionOptions(): Collection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getTipeOptions(): Collection
    {
        return TipeCoa::query()
            ->active()
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    public function hasChildren(int $id): bool
    {
        return Coa::query()->where('parent_coa', $id)->exists();
    }

    public function cannotBeSelectedAsParent(int $coaId): bool
    {
        return Coa::query()
            ->whereKey($coaId)
            ->whereDoesntHave('children')
            ->whereHas('bukuBesar')
            ->exists();
    }

    public function getDescendantIds(int $coaId): Collection
    {
        $sql = <<<'SQL'
            WITH RECURSIVE coa_descendants AS (
                SELECT id, parent_coa
                FROM coa
                WHERE parent_coa = ?

                UNION ALL

                SELECT child.id, child.parent_coa
                FROM coa child
                INNER JOIN coa_descendants descendants ON child.parent_coa = descendants.id
            )
            SELECT id
            FROM coa_descendants
        SQL;

        return collect(DB::select($sql, [$coaId]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function wouldCreateHierarchyCycle(int $coaId, int $parentId): bool
    {
        return $this->getDescendantIds($coaId)->contains($parentId);
    }

    public function create(array $data): Coa
    {
        $isKasbank = strtolower((string) ($data['tipe_coa'] ?? '')) === 'kasbank';
        $kelompok = $isKasbank ? null : ($data['arus_kas_kelompok'] ?? null);
        $aktivitas = $isKasbank ? null : ($data['arus_kas_aktivitas'] ?? null);

        if (! $isKasbank && ! empty($aktivitas) && ! empty($kelompok)) {
            $ref = $this->getReferensiKelompokService()->ensureExists($aktivitas, $kelompok);
            $kelompok = $ref->nama;
        }

        $coa = Coa::query()->create([
            'status_aktif' => (int) $data['status_aktif'],
            'parent_coa' => ($data['parent_id'] ?? null) ?: null,
            'tipe_coa' => $data['tipe_coa'],
            'arus_kas_aktivitas' => $aktivitas,
            'arus_kas_kelompok' => $kelompok,
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => ($data['deskripsi'] ?? null) ?: null,
            'is_postable' => false,
        ]);

        $this->logService->log('COA', 'create', null, $coa->only($this->loggableFields()));

        return $coa;
    }

    public function update(Coa $coa, array $data): Coa
    {
        return DB::transaction(function () use ($coa, $data) {
            $original = $coa->only($this->loggableFields());

            $isKasbank = strtolower((string) $data['tipe_coa']) === 'kasbank';
            $kelompok = $isKasbank ? null : ($data['arus_kas_kelompok'] ?? null);
            $aktivitas = $isKasbank ? null : ($data['arus_kas_aktivitas'] ?? null);

            if (! $isKasbank && ! empty($aktivitas) && ! empty($kelompok)) {
                $ref = $this->getReferensiKelompokService()->ensureExists($aktivitas, $kelompok);
                $kelompok = $ref->nama;
            }

            $coa->fill([
                'status_aktif' => (int) $data['status_aktif'],
                'parent_coa' => ($data['parent_id'] ?? null) ?: null,
                'tipe_coa' => $data['tipe_coa'],
                'arus_kas_aktivitas' => $aktivitas,
                'arus_kas_kelompok' => $kelompok,
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'deskripsi' => ($data['deskripsi'] ?? null) ?: null,
            ]);

            $changes = $coa->getDirty();
            $coa->save();

            if ($changes) {
                $this->logService->log(
                    'COA',
                    'update',
                    array_intersect_key($original, $changes),
                    $changes,
                );
            }

            $coa->refresh();

            if (! empty($data['cascade_to_children']) && ! empty($data['cascade_mode'])) {
                $this->cascadeArusKas($coa, (string) $data['cascade_mode']);
            }

            return $coa;
        });
    }

    /**
     * Ringkasan akun turunan non-Kasbank yang belum vs sudah memiliki pemetaan arus kas.
     *
     * @return array{
     *     total_descendants: int,
     *     empty_count: int,
     *     existing_count: int,
     *     existing_samples: array<int, array{kode: string, nama: string, aktivitas: ?string, kelompok: ?string}>
     * }
     */
    public function getCascadePreview(int $coaId): array
    {
        $descendantIds = $this->getDescendantIds($coaId);

        if ($descendantIds->isEmpty()) {
            return [
                'total_descendants' => 0,
                'empty_count' => 0,
                'existing_count' => 0,
                'existing_samples' => [],
            ];
        }

        $descendants = $this->nonKasbankDescendantQuery($descendantIds)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'arus_kas_aktivitas', 'arus_kas_kelompok']);

        [$empty, $existing] = $descendants->partition(
            fn (Coa $item) => empty($item->arus_kas_aktivitas)
        );

        return [
            'total_descendants' => $descendants->count(),
            'empty_count' => $empty->count(),
            'existing_count' => $existing->count(),
            'existing_samples' => $existing
                ->take(5)
                ->map(fn (Coa $item) => [
                    'kode' => (string) $item->kode,
                    'nama' => (string) $item->nama,
                    'aktivitas' => $item->arus_kas_aktivitas,
                    'kelompok' => $item->arus_kas_kelompok,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Terapkan klasifikasi arus kas akun induk ke seluruh sub-akun turunan non-Kasbank.
     */
    public function cascadeArusKas(Coa $parent, string $mode = 'overwrite_all'): int
    {
        if (! in_array($mode, ['overwrite_all', 'fill_empty'], true)) {
            throw new InvalidArgumentException("Mode kaskade tidak valid: {$mode}");
        }

        if (strtolower((string) $parent->tipe_coa) === 'kasbank' || empty($parent->arus_kas_aktivitas)) {
            return 0;
        }

        $descendantIds = $this->getDescendantIds((int) $parent->id);

        if ($descendantIds->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($parent, $mode, $descendantIds) {
            $query = $this->nonKasbankDescendantQuery($descendantIds);

            if ($mode === 'fill_empty') {
                $query->where(function (Builder $q) {
                    $q->whereNull('arus_kas_aktivitas')
                        ->orWhere('arus_kas_aktivitas', '');
                });
            }

            $affectedCount = $query->update([
                'arus_kas_aktivitas' => $parent->arus_kas_aktivitas,
                'arus_kas_kelompok' => $parent->arus_kas_kelompok,
            ]);

            $this->logService->log('COA', 'cascade_arus_kas', null, [
                'parent_id' => $parent->id,
                'parent_kode' => $parent->kode,
                'mode' => $mode,
                'aktivitas' => $parent->arus_kas_aktivitas,
                'kelompok' => $parent->arus_kas_kelompok,
                'affected_count' => $affectedCount,
            ]);

            return $affectedCount;
        });
    }

    private function nonKasbankDescendantQuery(Collection $descendantIds): Builder
    {
        return Coa::query()
            ->whereIn('id', $descendantIds->all())
            ->where(function (Builder $query) {
                $query->whereNull('tipe_coa')
                    ->orWhereRaw('LOWER(tipe_coa) != ?', ['kasbank']);
            });
    }

    public function hasTransactions(int $coaId): bool
    {
        if (Schema::hasTable('bukubesar') && DB::table('bukubesar')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('jurnal_umum_rinci') && DB::table('jurnal_umum_rinci')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('kasbank_penerimaan') && DB::table('kasbank_penerimaan')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('kasbank_penerimaan_rinci') && DB::table('kasbank_penerimaan_rinci')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('kasbank_pembayaran') && DB::table('kasbank_pembayaran')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('kasbank_pembayaran_rinci') && DB::table('kasbank_pembayaran_rinci')->where('coa_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('faktur_penjualan') && DB::table('faktur_penjualan')->where('akun_piutang_id', $coaId)->exists()) {
            return true;
        }

        if (Schema::hasTable('penerimaan_penjualan') && DB::table('penerimaan_penjualan')
            ->where(function ($query) use ($coaId) {
                $query->where('akun_bank_id', $coaId)
                    ->orWhere('akun_piutang_id', $coaId)
                    ->orWhere('akun_selisih_tarif_id', $coaId);
            })->exists()) {
            return true;
        }

        if (Schema::hasTable('pembayaran_pembelian') && DB::table('pembayaran_pembelian')
            ->where(function ($query) use ($coaId) {
                $query->where('akun_bank_id', $coaId)
                    ->orWhere('akun_hutang_id', $coaId)
                    ->orWhere('akun_potongan_admin_id', $coaId);
            })->exists()) {
            return true;
        }

        return false;
    }

    public function isReferencedInConfigurations(int $coaId): bool
    {
        $mappingTables = [
            'mapping_pendapatan_tindakan' => 'coa_id',
            'mapping_pendapatan_kamar' => 'coa_id',
            'mapping_pendapatan_umum' => 'coa_id',
            'mapping_lawan_pendapatan_simrs' => 'coa_id',
            'mapping_coa_simrs' => 'coa_id',
            'setting_rba' => 'coa_id',
        ];

        foreach ($mappingTables as $table => $column) {
            if (Schema::hasTable($table) && DB::table($table)->where($column, $coaId)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function canDelete(Coa $coa): array
    {
        if ($this->hasChildren((int) $coa->id)) {
            return [
                'allowed' => false,
                'message' => "Akun parent [{$coa->kode}] tidak dapat dihapus karena memiliki akun turunan (sub-akun).",
            ];
        }

        if ($this->hasTransactions((int) $coa->id)) {
            return [
                'allowed' => false,
                'message' => "Akun [{$coa->kode}] tidak dapat dihapus karena memiliki riwayat transaksi keuangan / buku besar.",
            ];
        }

        if ($this->isReferencedInConfigurations((int) $coa->id)) {
            return [
                'allowed' => false,
                'message' => "Akun [{$coa->kode}] tidak dapat dihapus karena masih digunakan dalam konfigurasi pemetaan akun sistem.",
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
    }

    public function delete(Coa $coa): void
    {
        $check = $this->canDelete($coa);
        if (! $check['allowed']) {
            throw new DomainException($check['message']);
        }

        DB::transaction(function () use ($coa) {
            $this->logService->log('COA', 'delete', $coa->only($this->loggableFields()));
            $coa->delete();
        });
    }

    private function loggableFields(): array
    {
        return ['kode', 'nama', 'tipe_coa', 'arus_kas_aktivitas', 'arus_kas_kelompok', 'parent_coa', 'status_aktif', 'deskripsi'];
    }
}
