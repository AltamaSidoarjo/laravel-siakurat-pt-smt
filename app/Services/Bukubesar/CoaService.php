<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\TipeCoa;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoaService
{
    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
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
            ->get(['id', 'kode', 'nama']);
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
        $coa = Coa::query()->create([
            'status_aktif' => (int) $data['status_aktif'],
            'parent_coa' => ($data['parent_id'] ?? null) ?: null,
            'tipe_coa' => $data['tipe_coa'],
            'arus_kas_aktivitas' => $data['tipe_coa'] === 'Kasbank' ? null : ($data['arus_kas_aktivitas'] ?? null),
            'arus_kas_kelompok' => $data['tipe_coa'] === 'Kasbank' ? null : ($data['arus_kas_kelompok'] ?? null),
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
        $original = $coa->only($this->loggableFields());

        $coa->fill([
            'status_aktif' => (int) $data['status_aktif'],
            'parent_coa' => ($data['parent_id'] ?? null) ?: null,
            'tipe_coa' => $data['tipe_coa'],
            'arus_kas_aktivitas' => $data['tipe_coa'] === 'Kasbank' ? null : ($data['arus_kas_aktivitas'] ?? null),
            'arus_kas_kelompok' => $data['tipe_coa'] === 'Kasbank' ? null : ($data['arus_kas_kelompok'] ?? null),
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

        return $coa->refresh();
    }

    public function delete(Coa $coa): void
    {
        $this->logService->log('COA', 'delete', $coa->only($this->loggableFields()));

        $coa->delete();
    }

    private function loggableFields(): array
    {
        return ['kode', 'nama', 'tipe_coa', 'arus_kas_aktivitas', 'arus_kas_kelompok', 'parent_coa', 'status_aktif', 'deskripsi'];
    }
}
