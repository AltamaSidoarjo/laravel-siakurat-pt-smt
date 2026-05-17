<?php

namespace App\Services\Pengaturan;

use App\Models\Coa;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\MappingPendapatan;
use App\Models\MappingPendapatanKamar;
use App\Models\MappingPendapatanUmum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MappingPendapatanTindakanService
{
    private const HIDDEN_TYPE_OPTIONS = [
        // Sementara sembunyikan dari UI, backend definisinya tetap dipertahankan
        // agar mudah diaktifkan kembali tanpa membongkar flow yang lain.
        'operasi',
    ];

    public const MAPPING_UMUM_NAMES = [
        'Registrasi',
        'Harian',
        'Service',
        'Dokter',
        'Perawat',
        'Resep Pulang',
        'Retur Obat',
        'Obat',
        'Tambahan',
        'Potongan',
        'Operasi',
    ];

    private const TYPE_DEFINITIONS = [
        'rawat_jalan' => [
            'label' => 'Rawat Jalan',
            'source' => 'Rawat Jalan',
            'query' => <<<'SQL'
                SELECT jp.kd_jenis_prw, jp.nm_perawatan, jp.kd_poli, jp.kd_pj, pj.png_jawab
                FROM jns_perawatan jp
                JOIN penjab pj ON pj.kd_pj = jp.kd_pj
                WHERE jp.status = '1'
                ORDER BY jp.nm_perawatan ASC
            SQL,
        ],
        'rawat_inap' => [
            'label' => 'Rawat Inap',
            'source' => 'Rawat Inap',
            'query' => <<<'SQL'
                SELECT jp.kd_jenis_prw, jp.nm_perawatan, NULL AS kd_poli, jp.kd_pj, pj.png_jawab
                FROM jns_perawatan_inap jp
                JOIN penjab pj ON pj.kd_pj = jp.kd_pj
                WHERE jp.status = '1'
                ORDER BY jp.nm_perawatan ASC
            SQL,
        ],
        'lab' => [
            'label' => 'Laborat',
            'source' => 'Laborat',
            'query' => <<<'SQL'
                SELECT jp.kd_jenis_prw, jp.nm_perawatan, NULL AS kd_poli, jp.kd_pj, pj.png_jawab
                FROM jns_perawatan_lab jp
                JOIN penjab pj ON pj.kd_pj = jp.kd_pj
                WHERE jp.status = '1'
                ORDER BY jp.nm_perawatan ASC
            SQL,
        ],
        'radiologi' => [
            'label' => 'Radiologi',
            'source' => 'Radiologi',
            'query' => <<<'SQL'
                SELECT jp.kd_jenis_prw, jp.nm_perawatan, NULL AS kd_poli, jp.kd_pj, pj.png_jawab
                FROM jns_perawatan_radiologi jp
                JOIN penjab pj ON pj.kd_pj = jp.kd_pj
                WHERE jp.status = '1'
                ORDER BY jp.nm_perawatan ASC
            SQL,
        ],
        'utd' => [
            'label' => 'UTD',
            'source' => 'Utd',
            'query' => <<<'SQL'
                SELECT jp.kd_jenis_prw, jp.nm_perawatan, NULL AS kd_poli, jp.kd_pj, pj.png_jawab
                FROM jns_perawatan_utd jp
                JOIN penjab pj ON pj.kd_pj = jp.kd_pj
                WHERE jp.status = '1'
                ORDER BY jp.nm_perawatan ASC
            SQL,
        ],
        'operasi' => [
            'label' => 'Operasi',
            'source' => 'Operasi',
            'query' => <<<'SQL'
                SELECT po.kode_paket AS kd_jenis_prw, po.nm_perawatan, NULL AS kd_poli, po.kd_pj, pj.png_jawab
                FROM paket_operasi po
                JOIN penjab pj ON pj.kd_pj = po.kd_pj
                WHERE po.status = '1'
                ORDER BY po.nm_perawatan ASC
            SQL,
        ],
        'kamar' => [
            'label' => 'Kamar',
            'source' => 'Kamar',
            'query' => <<<'SQL'
                SELECT k.kd_kamar, CONCAT(k.kd_kamar, ' | ', b.nm_bangsal) AS nm_kamar
                FROM kamar k
                JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
                ORDER BY b.nm_bangsal ASC, k.kd_kamar ASC
            SQL,
        ],
    ];

    public function resolveTypeKey(?string $typeKey): string
    {
        return array_key_exists($typeKey ?? '', self::TYPE_DEFINITIONS)
            ? $typeKey
            : 'rawat_jalan';
    }

    public function getTypeOptions(): array
    {
        return collect(self::TYPE_DEFINITIONS)
            ->reject(fn (array $_, string $key) => in_array($key, self::HIDDEN_TYPE_OPTIONS, true))
            ->map(fn (array $item, string $key) => [
                'key' => $key,
                'label' => $item['label'],
                'source' => $item['source'],
            ])
            ->values()
            ->all();
    }

    public function getTypeDefinition(string $typeKey): array
    {
        $definition = self::TYPE_DEFINITIONS[$typeKey] ?? null;

        if (! $definition) {
            throw new InvalidArgumentException('Jenis tindakan tidak valid.');
        }

        return $definition;
    }

    public function getTypeKeyFromSource(string $source): string
    {
        foreach (self::TYPE_DEFINITIONS as $key => $item) {
            if ($item['source'] === $source) {
                return $key;
            }
        }

        return 'rawat_jalan';
    }

    public function getCoaOptions(): EloquentCollection
    {
        return Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama']);
    }

    public function getIndexData(string $typeKey): EloquentCollection
    {
        if ($this->isKamarType($typeKey)) {
            return MappingPendapatanKamar::query()
                ->with('coa:id,kode,nama')
                ->orderBy('nama_kamar')
                ->orderBy('kode_kamar')
                ->get();
        }

        $definition = $this->getTypeDefinition($typeKey);

        return MappingPendapatan::query()
            ->with('coa:id,kode,nama')
            ->where('sumber_tindakan', $definition['source'])
            ->orderBy('nm_perawatan')
            ->orderBy('kode_jenis_perawatan')
            ->get();
    }

    public function getAvailableTindakan(string $typeKey): Collection
    {
        $references = $this->getSimrsTindakanReferences($typeKey);

        if ($this->isKamarType($typeKey)) {
            $mappedKeys = MappingPendapatanKamar::query()
                ->pluck('kode_kamar');

            return $references
                ->reject(fn (array $item) => $mappedKeys->contains($item['selection_key']))
                ->values();
        }

        $definition = $this->getTypeDefinition($typeKey);
        $mappedKeys = MappingPendapatan::query()
            ->where('sumber_tindakan', $definition['source'])
            ->get(['kode_jenis_perawatan', 'kode_penjamin'])
            ->map(fn (MappingPendapatan $item) => $this->buildSelectionKey($item->kode_jenis_perawatan, $item->kode_penjamin));

        return $references
            ->reject(fn (array $item) => $mappedKeys->contains($item['selection_key']))
            ->values();
    }

    public function createMappings(string $typeKey, array $rows, string $actor = 'system'): array
    {
        if ($this->isKamarType($typeKey)) {
            return $this->createKamarMappings($rows);
        }

        $definition = $this->getTypeDefinition($typeKey);
        $referencesByKey = $this->getSimrsTindakanReferences($typeKey)->keyBy('selection_key');

        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $selectionKey = (string) ($row['tindakan_key'] ?? '');
            $reference = $referencesByKey->get($selectionKey);

            if (! $reference) {
                $failedCount++;
                continue;
            }

            $exists = MappingPendapatan::query()
                ->where('sumber_tindakan', $definition['source'])
                ->where('kode_jenis_perawatan', $reference['kd_jenis_prw'])
                ->where('kode_penjamin', $reference['kd_pj'])
                ->exists();

            if ($exists) {
                $failedCount++;
                continue;
            }

            MappingPendapatan::query()->create([
                'kode_jenis_perawatan' => $reference['kd_jenis_prw'],
                'kode_penjamin' => $reference['kd_pj'],
                'kode_poli' => $reference['kd_poli'],
                'coa_id' => (int) $row['coa_id'],
                'user_create' => $actor,
                'user_edit' => $actor,
                'sumber_tindakan' => $definition['source'],
                'nm_perawatan' => $reference['nm_perawatan'],
            ]);

            $successCount++;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }

    public function delete(MappingPendapatan $mappingPendapatan): void
    {
        $mappingPendapatan->delete();
    }

    public function deleteKamar(MappingPendapatanKamar $mappingPendapatanKamar): void
    {
        $mappingPendapatanKamar->delete();
    }

    public function getUmumIndexData(): EloquentCollection
    {
        return MappingPendapatanUmum::query()
            ->with('coa:id,kode,nama')
            ->orderBy('nama')
            ->orderBy('kode_penjamin')
            ->get();
    }

    public function getUmumNameOptions(): array
    {
        return self::MAPPING_UMUM_NAMES;
    }

    public function getPenjaminOptions(): Collection
    {
        return collect(DB::connection('simrs')->select(
            'SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab ASC'
        ))->map(fn (object $row) => [
            'kd_pj' => (string) $row->kd_pj,
            'png_jawab' => (string) $row->png_jawab,
        ])->values();
    }

    public function createUmumMapping(array $data): MappingPendapatanUmum
    {
        return MappingPendapatanUmum::query()->create([
            'nama' => $data['nama'],
            'kode_penjamin' => $data['kode_penjamin'],
            'coa_id' => (int) $data['coa_id'],
        ]);
    }

    public function deleteUmum(MappingPendapatanUmum $mappingPendapatanUmum): void
    {
        $mappingPendapatanUmum->delete();
    }

    public function getLawanPendapatanIndexData(): EloquentCollection
    {
        return MappingLawanPendapatanSimrs::query()
            ->with('coa:id,kode,nama')
            ->orderBy('kode_coa_simrs')
            ->get();
    }

    public function getRekeningSimrsOptions(): Collection
    {
        $existingMapping = MappingLawanPendapatanSimrs::query()
            ->pluck('kode_coa_simrs');

        return collect(DB::connection('simrs')->select(
            'SELECT kd_rek AS kode_rekening, nm_rek AS nama_rekening FROM rekening ORDER BY kd_rek ASC'
        ))
            ->map(fn (object $row) => [
                'kode_rekening' => (string) $row->kode_rekening,
                'nama_rekening' => blank($row->nama_rekening ?? null) ? null : (string) $row->nama_rekening,
            ])
            ->reject(fn (array $row) => $existingMapping->contains($row['kode_rekening']))
            ->values();
    }

    public function createLawanPendapatanMapping(array $data): MappingLawanPendapatanSimrs
    {
        $namaCoaSimrs = collect(DB::connection('simrs')->select(
            'SELECT kd_rek AS kode_rekening, nm_rek AS nama_rekening FROM rekening WHERE kd_rek = ? LIMIT 1',
            [$data['kode_coa_simrs']]
        ))->first();

        return MappingLawanPendapatanSimrs::query()->create([
            'kode_coa_simrs' => $data['kode_coa_simrs'],
            'nama_coa_simrs' => (string) ($namaCoaSimrs->nama_rekening ?? ''),
            'coa_id' => (int) $data['coa_id'],
        ]);
    }

    public function deleteLawanPendapatan(MappingLawanPendapatanSimrs $mappingLawanPendapatanSimrs): void
    {
        $mappingLawanPendapatanSimrs->delete();
    }

    private function getSimrsTindakanReferences(string $typeKey): Collection
    {
        $definition = $this->getTypeDefinition($typeKey);

        if ($this->isKamarType($typeKey)) {
            return collect(DB::connection('simrs')->select($definition['query']))
                ->map(fn (object $row) => [
                    'selection_key' => (string) $row->kd_kamar,
                    'kd_jenis_prw' => (string) $row->kd_kamar,
                    'nm_perawatan' => (string) $row->nm_kamar,
                    'kd_poli' => null,
                    'kd_pj' => null,
                    'png_jawab' => 'Kamar',
                ])
                ->values();
        }

        return collect(DB::connection('simrs')->select($definition['query']))
            ->map(function (object $row) {
                $kodeJenisPerawatan = (string) $row->kd_jenis_prw;
                $kodePenjamin = (string) $row->kd_pj;

                return [
                    'selection_key' => $this->buildSelectionKey($kodeJenisPerawatan, $kodePenjamin),
                    'kd_jenis_prw' => $kodeJenisPerawatan,
                    'nm_perawatan' => (string) $row->nm_perawatan,
                    'kd_poli' => blank($row->kd_poli ?? null) ? null : (string) $row->kd_poli,
                    'kd_pj' => $kodePenjamin,
                    'png_jawab' => (string) $row->png_jawab,
                ];
            })
            ->sortBy(fn (array $item) => implode('|', [
                mb_strtolower($item['nm_perawatan']),
                mb_strtolower($item['png_jawab']),
                mb_strtolower($item['kd_jenis_prw']),
            ]))
            ->values();
    }

    private function buildSelectionKey(string $kodeJenisPerawatan, string $kodePenjamin): string
    {
        return $kodeJenisPerawatan.'||'.$kodePenjamin;
    }

    private function createKamarMappings(array $rows): array
    {
        $referencesByKey = $this->getSimrsTindakanReferences('kamar')->keyBy('selection_key');
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $selectionKey = (string) ($row['tindakan_key'] ?? '');
            $reference = $referencesByKey->get($selectionKey);

            if (! $reference) {
                $failedCount++;
                continue;
            }

            $exists = MappingPendapatanKamar::query()
                ->where('kode_kamar', $reference['kd_jenis_prw'])
                ->exists();

            if ($exists) {
                $failedCount++;
                continue;
            }

            MappingPendapatanKamar::query()->create([
                'kode_kamar' => $reference['kd_jenis_prw'],
                'nama_kamar' => $reference['nm_perawatan'],
                'status_aktif' => '1',
                'pendapatan_kamar_coa_id' => (int) $row['coa_id'],
            ]);

            $successCount++;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }

    private function isKamarType(string $typeKey): bool
    {
        return $typeKey === 'kamar';
    }
}
