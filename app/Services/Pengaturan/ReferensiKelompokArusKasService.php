<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReferensiKelompokArusKasService
{
    /**
     * Sanitize reference group name by replacing multiple whitespace with single space and trimming.
     */
    public function sanitizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    /**
     * Get active reference groups ordered by name and grouped by aktivitas.
     */
    public function getGroupedActive(): Collection
    {
        if (! Schema::hasTable('referensi_kelompok_arus_kas')) {
            return collect();
        }

        return ReferensiKelompokArusKas::aktif()
            ->orderBy('nama', 'asc')
            ->get()
            ->groupBy('aktivitas');
    }

    /**
     * Store a new reference group.
     */
    public function store(array $data): ReferensiKelompokArusKas
    {
        $cleanName = $this->sanitizeName($data['nama'] ?? '');

        return ReferensiKelompokArusKas::create([
            'aktivitas' => $data['aktivitas'],
            'nama' => $cleanName,
            'status_aktif' => (bool) ($data['status_aktif'] ?? true),
        ]);
    }

    /**
     * Update an existing reference group with transactional cascade to associated COA.
     */
    public function update(ReferensiKelompokArusKas $ref, array $data): ReferensiKelompokArusKas
    {
        return DB::transaction(function () use ($ref, $data) {
            $oldName = $ref->nama;
            $cleanName = isset($data['nama']) ? $this->sanitizeName($data['nama']) : $oldName;

            $updateData = [];
            if (isset($data['nama'])) {
                $updateData['nama'] = $cleanName;
            }
            if (isset($data['status_aktif'])) {
                $updateData['status_aktif'] = (bool) $data['status_aktif'];
            }
            if (isset($data['aktivitas'])) {
                $updateData['aktivitas'] = $data['aktivitas'];
            }

            $ref->update($updateData);

            if ($cleanName !== $oldName) {
                Coa::where('arus_kas_aktivitas', $ref->aktivitas)
                    ->where('arus_kas_kelompok', $oldName)
                    ->update(['arus_kas_kelompok' => $cleanName]);
            }

            return $ref;
        });
    }

    /**
     * Check if a reference group can be safely deleted.
     *
     * @return array{allowed: bool, coa_count: int, reason: ?string}
     */
    public function canDelete(ReferensiKelompokArusKas $ref): array
    {
        $count = Coa::where('arus_kas_aktivitas', $ref->aktivitas)
            ->where('arus_kas_kelompok', $ref->nama)
            ->count();

        return [
            'allowed' => $count === 0,
            'coa_count' => $count,
            'reason' => $count > 0 ? "Pos ini sedang digunakan oleh {$count} akun COA." : null,
        ];
    }

    /**
     * Delete a reference group with usage guardrails.
     *
     * @throws DomainException
     */
    public function delete(ReferensiKelompokArusKas $ref): void
    {
        $check = $this->canDelete($ref);

        if (! $check['allowed']) {
            throw new DomainException($check['reason'] ?? 'Pos referensi tidak dapat dihapus karena sedang digunakan.');
        }

        $ref->delete();
    }

    /**
     * Toggle active status of a reference group.
     */
    public function toggleStatus(ReferensiKelompokArusKas $ref): bool
    {
        $ref->status_aktif = ! $ref->status_aktif;
        $ref->save();

        return (bool) $ref->status_aktif;
    }

    /**
     * Ensure a reference group exists by case-insensitive name and aktivitas, creating if absent.
     */
    public function ensureExists(string $aktivitas, string $nama): ReferensiKelompokArusKas
    {
        $cleanName = $this->sanitizeName($nama);

        if (! Schema::hasTable('referensi_kelompok_arus_kas')) {
            return new ReferensiKelompokArusKas([
                'aktivitas' => $aktivitas,
                'nama' => $cleanName,
                'status_aktif' => true,
            ]);
        }

        $existing = ReferensiKelompokArusKas::where('aktivitas', $aktivitas)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($cleanName)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ReferensiKelompokArusKas::create([
            'aktivitas' => $aktivitas,
            'nama' => $cleanName,
            'status_aktif' => true,
        ]);
    }
}
