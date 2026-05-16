<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\TipeCoa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoaService
{
    public function getTreeRows(): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE coa_tree AS (
                SELECT
                    c.id,
                    c.parent_coa,
                    c.tipe_coa,
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
                EXISTS (
                    SELECT 1
                    FROM coa c2
                    WHERE c2.parent_coa = t.id
                    LIMIT 1
                ) AS is_parent
            FROM coa_tree t
            ORDER BY t.kode ASC
        SQL;

        return DB::select($sql);
    }

    public function getParentOptions(?int $excludeId = null): Collection
    {
        return Coa::query()
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('kode')
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

    public function create(array $data): Coa
    {
        return Coa::query()->create([
            'status_aktif' => (int) $data['status_aktif'],
            'parent_coa' => $data['parent_id'] ?: null,
            'tipe_coa' => $data['tipe_coa'],
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: null,
            'is_postable' => false,
        ]);
    }

    public function update(Coa $coa, array $data): Coa
    {
        $coa->fill([
            'status_aktif' => (int) $data['status_aktif'],
            'parent_coa' => $data['parent_id'] ?: null,
            'tipe_coa' => $data['tipe_coa'],
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: null,
        ]);

        $coa->save();

        return $coa->refresh();
    }

    public function delete(Coa $coa): void
    {
        $coa->delete();
    }
}
