<?php

namespace App\Services\Pengaturan;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use App\Services\LogAktifitasService;
use DomainException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PengaturanArusKasService
{
    private const LOG_MODUL = 'Pengaturan Arus Kas';

    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

    public function getCoaCompatibilityGroup(?string $sifatPos): string
    {
        return in_array($sifatPos, SettingArusKas::SIFAT_LABA_RUGI, true)
            ? 'laba_rugi_aktif'
            : 'neraca_non_kasbank';
    }

    /**
     * Check whether a COA's tipe_coa and status_aktif are compatible with a given sifat_pos or pengecualian mode.
     *
     * @return array{compatible: bool, reason: ?string}
     */
    public function isCoaCompatibleWithSifat(Coa $coa, ?string $sifatPos): array
    {
        if (
            $sifatPos !== null
            && $sifatPos !== 'pengecualian'
            && ! in_array($sifatPos, SettingArusKas::SIFAT_POS, true)
        ) {
            return [
                'compatible' => false,
                'reason' => "Sifat pos '{$sifatPos}' tidak valid.",
            ];
        }

        if (strtolower(trim((string) $coa->tipe_coa)) === 'kasbank') {
            return [
                'compatible' => false,
                'reason' => 'Akun Kasbank tidak dapat dipetakan pada Arus Kas Metode Tidak Langsung.',
            ];
        }

        $isLabaRugi = in_array((string) $coa->tipe_coa, SettingArusKas::TIPE_LABA_RUGI, true);

        if ($this->getCoaCompatibilityGroup($sifatPos) === 'laba_rugi_aktif') {
            if (! $isLabaRugi) {
                return [
                    'compatible' => false,
                    'reason' => 'Sifat pos Penambah/Pengurang Laba hanya menerima akun Laba Rugi (Pendapatan/Beban).',
                ];
            }

            if ((int) $coa->status_aktif !== 1) {
                return [
                    'compatible' => false,
                    'reason' => 'Akun Laba Rugi berstatus nonaktif tidak dapat dipetakan.',
                ];
            }

            return ['compatible' => true, 'reason' => null];
        }

        if ($isLabaRugi) {
            return [
                'compatible' => false,
                'reason' => 'Akun Laba Rugi tidak kompatibel dengan pos Neraca atau daftar pengecualian.',
            ];
        }

        return ['compatible' => true, 'reason' => null];
    }

    /**
     * Expand all descendant leaf COAs under a parent COA and classify into eligible vs skipped.
     *
     * @return array{eligible: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function getLeafDescendantsByParent(
        int $parentId,
        ?string $sifatPos = null,
        ?int $ignoreSettingArusKasId = null,
    ): array {
        if ($parentId <= 0) {
            return ['eligible' => [], 'skipped' => []];
        }

        $allCoas = Coa::query()
            ->orderBy('kode')
            ->get(['id', 'parent_coa', 'kode', 'nama', 'tipe_coa', 'status_aktif']);

        $childrenByParent = [];
        foreach ($allCoas as $coa) {
            if ($coa->parent_coa !== null && (int) $coa->parent_coa > 0) {
                $childrenByParent[(int) $coa->parent_coa][] = $coa;
            }
        }

        if (! isset($childrenByParent[$parentId])) {
            return ['eligible' => [], 'skipped' => []];
        }

        $visited = [$parentId => true];
        $leafNodes = [];
        $this->collectLeafDescendants($parentId, $childrenByParent, $visited, $leafNodes);

        if ($leafNodes === []) {
            return ['eligible' => [], 'skipped' => []];
        }

        usort(
            $leafNodes,
            fn (Coa $a, Coa $b): int => strcmp((string) $a->kode, (string) $b->kode) ?: ($a->id <=> $b->id)
        );

        $leafIds = array_map(fn (Coa $coa): int => (int) $coa->id, $leafNodes);
        $mappedByPos = $this->loadMappedByPosMap($leafIds);
        $excludedSet = $this->loadExcludedCoaIdSet($leafIds);

        $eligible = [];
        $skipped = [];

        foreach ($leafNodes as $leaf) {
            $leafId = (int) $leaf->id;
            $baseItem = [
                'id' => $leafId,
                'kode' => (string) $leaf->kode,
                'nama' => (string) $leaf->nama,
                'tipe_coa' => (string) ($leaf->tipe_coa ?? ''),
                'status_aktif' => (int) $leaf->status_aktif,
            ];

            $compatibility = $this->isCoaCompatibleWithSifat($leaf, $sifatPos);
            if (! $compatibility['compatible']) {
                $skipped[] = $baseItem + ['reason' => (string) $compatibility['reason']];

                continue;
            }

            if (isset($excludedSet[$leafId])) {
                $skipped[] = $baseItem + ['reason' => 'Akun berada dalam daftar pengecualian.'];

                continue;
            }

            if (isset($mappedByPos[$leafId])) {
                $holder = $mappedByPos[$leafId];
                if ($ignoreSettingArusKasId === null || $holder['pos_id'] !== $ignoreSettingArusKasId) {
                    $inactiveSuffix = $holder['is_active'] ? '' : ' (Nonaktif)';
                    $skipped[] = $baseItem + [
                        'reason' => "Sudah dipetakan ke pos '{$holder['nama_pos']}'{$inactiveSuffix}.",
                    ];

                    continue;
                }
            }

            $eligible[] = $baseItem;
        }

        return [
            'eligible' => $eligible,
            'skipped' => $skipped,
        ];
    }

    public function store(array $data): SettingArusKas
    {
        $kelompokBagian = (string) ($data['kelompok_bagian'] ?? '');
        $sifatPos = (string) ($data['sifat_pos'] ?? '');
        $namaPos = $this->sanitizeName((string) ($data['nama_pos'] ?? ''));

        $this->assertValidPosMetadata($kelompokBagian, $sifatPos, $namaPos);

        $rawKode = trim((string) ($data['kode_pos'] ?? ''));
        $kodePos = $rawKode !== ''
            ? $rawKode
            : $this->generateUniqueKodePos($kelompokBagian, $namaPos);

        if (SettingArusKas::query()->where('kode_pos', $kodePos)->exists()) {
            throw new DomainException("Kode pos '{$kodePos}' sudah digunakan.");
        }

        $urutan = (isset($data['urutan']) && $data['urutan'] !== '' && is_numeric($data['urutan']))
            ? max(1, (int) $data['urutan'])
            : ((int) SettingArusKas::query()->where('kelompok_bagian', $kelompokBagian)->max('urutan')) + 1;

        return DB::transaction(function () use ($data, $kodePos, $namaPos, $kelompokBagian, $sifatPos, $urutan): SettingArusKas {
            $pos = SettingArusKas::query()->create([
                'kode_pos' => $kodePos,
                'nama_pos' => $namaPos,
                'kelompok_bagian' => $kelompokBagian,
                'sifat_pos' => $sifatPos,
                'pakai_prefix_perubahan' => (bool) ($data['pakai_prefix_perubahan'] ?? false),
                'urutan' => $urutan,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncCoaMappings($pos, (array) ($data['coa_ids'] ?? []), $sifatPos);

            $pos->load(['coas' => fn ($query) => $query->orderBy('kode')]);

            $this->logService->log(self::LOG_MODUL, 'create', null, $this->buildAuditSnapshot($pos));

            return $pos;
        });
    }

    public function update(SettingArusKas $settingArusKas, array $data): SettingArusKas
    {
        return DB::transaction(function () use ($settingArusKas, $data): SettingArusKas {
            $settingArusKas->loadMissing('coas');
            $oldSnapshot = $this->buildAuditSnapshot($settingArusKas);
            $oldSifatPos = (string) $settingArusKas->sifat_pos;

            $kelompokBagian = (string) ($data['kelompok_bagian'] ?? $settingArusKas->kelompok_bagian);
            $sifatPos = (string) ($data['sifat_pos'] ?? $settingArusKas->sifat_pos);
            $namaPos = array_key_exists('nama_pos', $data)
                ? $this->sanitizeName((string) $data['nama_pos'])
                : (string) $settingArusKas->nama_pos;

            $this->assertValidPosMetadata($kelompokBagian, $sifatPos, $namaPos);

            $rawKode = isset($data['kode_pos']) ? trim((string) $data['kode_pos']) : '';
            $kodePos = $rawKode !== '' ? $rawKode : (string) $settingArusKas->kode_pos;

            if (
                $kodePos !== $settingArusKas->kode_pos
                && SettingArusKas::query()->where('kode_pos', $kodePos)->where('id', '!=', $settingArusKas->id)->exists()
            ) {
                throw new DomainException("Kode pos '{$kodePos}' sudah digunakan.");
            }

            $settingArusKas->update([
                'kode_pos' => $kodePos,
                'nama_pos' => $namaPos,
                'kelompok_bagian' => $kelompokBagian,
                'sifat_pos' => $sifatPos,
                'pakai_prefix_perubahan' => isset($data['pakai_prefix_perubahan'])
                    ? (bool) $data['pakai_prefix_perubahan']
                    : (bool) $settingArusKas->pakai_prefix_perubahan,
                'urutan' => (isset($data['urutan']) && $data['urutan'] !== '' && is_numeric($data['urutan']))
                    ? max(1, (int) $data['urutan'])
                    : (int) $settingArusKas->urutan,
                'is_active' => isset($data['is_active'])
                    ? (bool) $data['is_active']
                    : (bool) $settingArusKas->is_active,
            ]);

            if (array_key_exists('coa_ids', $data)) {
                $this->syncCoaMappings($settingArusKas, (array) ($data['coa_ids'] ?? []), $sifatPos);
            } elseif ($sifatPos !== $oldSifatPos) {
                $existingCoaIds = $settingArusKas->coas->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $this->syncCoaMappings($settingArusKas, $existingCoaIds, $sifatPos);
            }

            $settingArusKas->load(['coas' => fn ($query) => $query->orderBy('kode')]);

            $this->logService->log(
                self::LOG_MODUL,
                'update',
                $oldSnapshot,
                $this->buildAuditSnapshot($settingArusKas),
            );

            return $settingArusKas;
        });
    }

    public function toggleStatus(SettingArusKas $settingArusKas): bool
    {
        return DB::transaction(function () use ($settingArusKas): bool {
            $oldStatus = (bool) $settingArusKas->is_active;
            $settingArusKas->is_active = ! $oldStatus;
            $settingArusKas->save();

            $this->logService->log(
                self::LOG_MODUL,
                'toggle',
                [
                    'id' => $settingArusKas->id,
                    'kode_pos' => $settingArusKas->kode_pos,
                    'is_active' => $oldStatus,
                ],
                [
                    'id' => $settingArusKas->id,
                    'kode_pos' => $settingArusKas->kode_pos,
                    'is_active' => (bool) $settingArusKas->is_active,
                ],
            );

            return (bool) $settingArusKas->is_active;
        });
    }

    public function reorder(string $kelompokBagian, array $orderedIds): void
    {
        if (! in_array($kelompokBagian, SettingArusKas::KELOMPOK_BAGIAN, true)) {
            throw new DomainException("Kelompok bagian '{$kelompokBagian}' tidak valid.");
        }

        $rawIds = array_map('intval', $orderedIds);
        $ids = array_values(array_unique($rawIds));

        if ($ids === [] || count($ids) !== count($rawIds)) {
            throw new DomainException('Daftar urutan pos tidak valid atau mengandung duplikat.');
        }

        $positions = SettingArusKas::query()
            ->whereIn('id', $ids)
            ->get(['id', 'kelompok_bagian', 'urutan'])
            ->keyBy('id');

        if ($positions->count() !== count($ids)) {
            throw new DomainException('Sebagian pos arus kas tidak ditemukan.');
        }

        foreach ($ids as $id) {
            $pos = $positions->get($id);
            if ($pos === null || $pos->kelompok_bagian !== $kelompokBagian) {
                throw new DomainException('Semua pos yang diurutkan harus berasal dari bagian yang sama.');
            }
        }

        DB::transaction(function () use ($kelompokBagian, $ids): void {
            $oldOrder = SettingArusKas::query()
                ->where('kelompok_bagian', $kelompokBagian)
                ->urut()
                ->pluck('urutan', 'id')
                ->all();

            $remainingIds = SettingArusKas::query()
                ->where('kelompok_bagian', $kelompokBagian)
                ->whereNotIn('id', $ids)
                ->urut()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $finalOrderIds = array_merge($ids, $remainingIds);
            foreach ($finalOrderIds as $index => $id) {
                SettingArusKas::query()
                    ->whereKey($id)
                    ->update(['urutan' => $index + 1]);
            }

            $this->logService->log(
                self::LOG_MODUL,
                'reorder',
                ['kelompok_bagian' => $kelompokBagian, 'order' => $oldOrder],
                ['kelompok_bagian' => $kelompokBagian, 'ordered_ids' => $finalOrderIds],
            );
        });
    }

    public function delete(SettingArusKas $settingArusKas): void
    {
        DB::transaction(function () use ($settingArusKas): void {
            $settingArusKas->loadMissing('coas');
            $oldSnapshot = $this->buildAuditSnapshot($settingArusKas);

            $settingArusKas->mappingCoa()->delete();
            $settingArusKas->delete();

            $this->logService->log(self::LOG_MODUL, 'delete', $oldSnapshot);
        });
    }

    public function storePengecualian(array $coaIds, ?string $alasan = null): Collection
    {
        $normalizedIds = $this->normalizeCoaIds($coaIds);

        if ($normalizedIds === []) {
            throw new DomainException('Pilih minimal satu akun COA untuk dikecualikan.');
        }

        $errors = $this->validateCoaIds($normalizedIds, 'pengecualian');
        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }

        $cleanAlasan = trim((string) ($alasan ?? ''));
        $cleanAlasan = $cleanAlasan !== '' ? $cleanAlasan : null;

        return DB::transaction(function () use ($normalizedIds, $cleanAlasan): Collection {
            $now = now();
            $rows = array_map(fn (int $coaId): array => [
                'coa_id' => $coaId,
                'alasan' => $cleanAlasan,
                'created_at' => $now,
                'updated_at' => $now,
            ], $normalizedIds);

            SettingArusKasPengecualian::query()->insert($rows);

            $created = SettingArusKasPengecualian::query()
                ->with('coa')
                ->whereIn('coa_id', $normalizedIds)
                ->orderBy('id')
                ->get();

            $this->logService->log(
                self::LOG_MODUL,
                'create_pengecualian',
                null,
                [
                    'coa_ids' => $normalizedIds,
                    'alasan' => $cleanAlasan,
                ],
            );

            return $created;
        });
    }

    public function deletePengecualian(SettingArusKasPengecualian $pengecualian): void
    {
        DB::transaction(function () use ($pengecualian): void {
            $pengecualian->loadMissing('coa');
            $oldData = [
                'id' => $pengecualian->id,
                'coa_id' => $pengecualian->coa_id,
                'kode' => $pengecualian->coa?->kode,
                'nama' => $pengecualian->coa?->nama,
                'alasan' => $pengecualian->alasan,
            ];

            $pengecualian->delete();

            $this->logService->log(self::LOG_MODUL, 'delete_pengecualian', $oldData);
        });
    }

    /**
     * Find mutated Neraca non-Kasbank accounts within the given period that are neither mapped to an active pos nor excluded.
     *
     * @return Collection<int, array{coa_id: int, kode: string, nama: string, tipe_coa: string, status_aktif: int, debit: float, kredit: float, dampak_kas: float}>
     */
    public function getUnmappedMutatedNeracaAccounts(?string $startDate = null, ?string $endDate = null): Collection
    {
        if (! Schema::hasTable('bukubesar') || ! Schema::hasTable('coa')) {
            return collect();
        }

        $start = $startDate ?: now()->startOfYear()->toDateString();
        $end = $endDate ?: now()->endOfYear()->toDateString();
        $normalizedEnd = strlen($end) === 10 ? $end.' 23:59:59' : $end;

        $activeMappedCoaIds = (Schema::hasTable('setting_arus_kas') && Schema::hasTable('setting_arus_kas_coa'))
            ? SettingArusKasCoa::query()
                ->join('setting_arus_kas', 'setting_arus_kas.id', '=', 'setting_arus_kas_coa.setting_arus_kas_id')
                ->where('setting_arus_kas.is_active', true)
                ->pluck('setting_arus_kas_coa.coa_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
            : [];

        $excludedCoaIds = Schema::hasTable('setting_arus_kas_pengecualian')
            ? SettingArusKasPengecualian::query()
                ->pluck('coa_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
            : [];

        $ignoredCoaIds = array_values(array_unique(array_merge($activeMappedCoaIds, $excludedCoaIds)));

        $rows = BukuBesar::query()
            ->join('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->whereBetween('bukubesar.tanggal', [$start, $normalizedEnd])
            ->whereRaw("LOWER(COALESCE(coa.tipe_coa, '')) != ?", ['kasbank'])
            ->where(function ($query): void {
                $query->whereNull('coa.tipe_coa')
                    ->orWhereNotIn('coa.tipe_coa', SettingArusKas::TIPE_LABA_RUGI);
            })
            ->when($ignoredCoaIds !== [], fn ($query) => $query->whereNotIn('coa.id', $ignoredCoaIds))
            ->groupBy('coa.id', 'coa.kode', 'coa.nama', 'coa.tipe_coa', 'coa.status_aktif')
            ->orderBy('coa.kode')
            ->selectRaw("
                coa.id as coa_id,
                coa.kode,
                coa.nama,
                coa.tipe_coa,
                coa.status_aktif,
                SUM(CASE WHEN bukubesar.tipe_mutasi = 'D' THEN bukubesar.nominal ELSE 0 END) as debit,
                SUM(CASE WHEN bukubesar.tipe_mutasi = 'K' THEN bukubesar.nominal ELSE 0 END) as kredit
            ")
            ->get();

        return $rows
            ->map(function (object $row): array {
                $debit = (float) $row->debit;
                $kredit = (float) $row->kredit;

                return [
                    'coa_id' => (int) $row->coa_id,
                    'kode' => (string) $row->kode,
                    'nama' => (string) $row->nama,
                    'tipe_coa' => (string) ($row->tipe_coa ?? ''),
                    'status_aktif' => (int) $row->status_aktif,
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'dampak_kas' => $kredit - $debit,
                ];
            })
            ->filter(fn (array $item): bool => abs($item['debit']) >= 0.005 || abs($item['kredit']) >= 0.005)
            ->values();
    }

    /**
     * Validate COA IDs for compatibility, leaf-only, and cross-table exclusivity, returning errors keyed by input index.
     *
     * @return array<int, string>
     */
    public function validateCoaIds(
        array $coaIds,
        string $modeOrSifatPos,
        ?int $ignoreSettingArusKasId = null,
    ): array {
        if ($coaIds === []) {
            return [];
        }

        if ($modeOrSifatPos !== 'pengecualian' && ! in_array($modeOrSifatPos, SettingArusKas::SIFAT_POS, true)) {
            $firstKey = array_key_first($coaIds);

            return [$firstKey => "Mode atau sifat pos '{$modeOrSifatPos}' tidak valid."];
        }

        $uniqueIds = collect($coaIds)
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $coas = $uniqueIds !== []
            ? Coa::query()
                ->select(['id', 'kode', 'nama', 'tipe_coa', 'status_aktif'])
                ->withCount('children')
                ->whereIn('id', $uniqueIds)
                ->get()
                ->keyBy('id')
            : collect();

        $mappedByPos = $this->loadMappedByPosMap($uniqueIds);
        $excludedSet = $this->loadExcludedCoaIdSet($uniqueIds);

        $errors = [];
        foreach ($coaIds as $index => $rawCoaId) {
            $coaId = is_numeric($rawCoaId) ? (int) $rawCoaId : 0;
            $coa = $coaId > 0 ? $coas->get($coaId) : null;

            if ($coa === null) {
                $errors[$index] = 'Akun COA tidak ditemukan.';

                continue;
            }

            $label = "{$coa->kode} - {$coa->nama}";

            if ((int) $coa->children_count > 0) {
                $errors[$index] = "Akun {$label} merupakan akun induk (parent) dan tidak boleh dipilih langsung.";

                continue;
            }

            $compatibility = $this->isCoaCompatibleWithSifat($coa, $modeOrSifatPos);
            if (! $compatibility['compatible']) {
                $errors[$index] = "Akun {$label}: {$compatibility['reason']}";

                continue;
            }

            if (isset($excludedSet[$coaId])) {
                $errors[$index] = $modeOrSifatPos === 'pengecualian'
                    ? "Akun {$label} sudah terdaftar dalam daftar pengecualian."
                    : "Akun {$label} berada dalam daftar pengecualian dan tidak dapat dipetakan ke pos.";

                continue;
            }

            if (isset($mappedByPos[$coaId])) {
                $holder = $mappedByPos[$coaId];
                if ($ignoreSettingArusKasId === null || $holder['pos_id'] !== $ignoreSettingArusKasId) {
                    $inactiveSuffix = $holder['is_active'] ? '' : ' (Nonaktif)';
                    $errors[$index] = "Akun {$label} sudah dipetakan ke pos '{$holder['nama_pos']}'{$inactiveSuffix}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate COA IDs and attach per-index errors directly onto a Laravel Validator instance.
     */
    public function validateCoaIdsForValidator(
        ValidatorContract $validator,
        mixed $coaIds,
        mixed $modeOrSifatPos,
        ?int $ignoreSettingArusKasId = null,
    ): void {
        if (! is_array($coaIds) || $coaIds === []) {
            return;
        }

        if (
            ! is_string($modeOrSifatPos)
            || ($modeOrSifatPos !== 'pengecualian' && ! in_array($modeOrSifatPos, SettingArusKas::SIFAT_POS, true))
        ) {
            return;
        }

        $errors = $this->validateCoaIds($coaIds, $modeOrSifatPos, $ignoreSettingArusKasId);
        foreach ($errors as $index => $message) {
            $key = "coa_ids.{$index}";
            if (! $validator->errors()->has($key)) {
                $validator->errors()->add($key, $message);
            }
        }
    }

    /**
     * Prepare COA selector metadata (leaf options, parent options, active/inactive mappings, exclusions) for UI modals.
     *
     * @return array{
     *     leafCoas: Collection<int, Coa>,
     *     parentCoas: Collection<int, Coa>,
     *     mappedByPos: array<int, array{pos_id: int, nama_pos: string, is_active: bool}>,
     *     excludedCoaIds: array<int, int>
     * }
     */
    public function getCoaOptionsData(): array
    {
        $allCoas = Coa::query()
            ->orderBy('kode')
            ->get(['id', 'parent_coa', 'kode', 'nama', 'tipe_coa', 'status_aktif']);

        $parentIds = [];
        foreach ($allCoas as $coa) {
            if ($coa->parent_coa !== null && (int) $coa->parent_coa > 0) {
                $parentIds[(int) $coa->parent_coa] = true;
            }
        }

        $parentCoas = $allCoas
            ->filter(fn (Coa $coa): bool => isset($parentIds[(int) $coa->id]))
            ->values();

        $leafCoas = $allCoas
            ->filter(
                fn (Coa $coa): bool => ! isset($parentIds[(int) $coa->id])
                    && strtolower(trim((string) $coa->tipe_coa)) !== 'kasbank'
            )
            ->values();

        $excludedCoaIds = SettingArusKasPengecualian::query()
            ->orderBy('id')
            ->pluck('coa_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'leafCoas' => $leafCoas,
            'parentCoas' => $parentCoas,
            'mappedByPos' => $this->loadMappedByPosMap(),
            'excludedCoaIds' => $excludedCoaIds,
        ];
    }

    public function generateUniqueKodePos(string $kelompokBagian, string $namaPos, ?int $ignoreId = null): string
    {
        $prefix = match ($kelompokBagian) {
            'penyesuaian_laba' => 'PL',
            'modal_kerja' => 'MK',
            'investasi' => 'INV',
            'pendanaan' => 'PEND',
            default => 'POS',
        };

        $slug = Str::upper(Str::slug($namaPos, '_'));
        if ($slug === '') {
            $slug = 'POS';
        }

        $base = Str::limit("{$prefix}_{$slug}", 70, '');
        $candidate = $base;
        $counter = 2;

        while (
            SettingArusKas::query()
                ->where('kode_pos', $candidate)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix = "_{$counter}";
            $candidate = Str::limit($base, 80 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }

    private function syncCoaMappings(SettingArusKas $pos, array $coaIds, string $sifatPos): void
    {
        $normalizedIds = $this->normalizeCoaIds($coaIds);

        if ($normalizedIds !== []) {
            $errors = $this->validateCoaIds($normalizedIds, $sifatPos, $pos->id);
            if ($errors !== []) {
                throw new DomainException(implode(' ', $errors));
            }
        }

        SettingArusKasCoa::query()
            ->where('setting_arus_kas_id', $pos->id)
            ->delete();

        if ($normalizedIds !== []) {
            $now = now();
            $rows = array_map(fn (int $coaId): array => [
                'setting_arus_kas_id' => $pos->id,
                'coa_id' => $coaId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $normalizedIds);

            SettingArusKasCoa::query()->insert($rows);
        }

        $pos->unsetRelation('coas')->unsetRelation('mappingCoa');
    }

    /**
     * @param  array<int, array<int, Coa>>  $childrenByParent
     * @param  array<int, bool>  $visited
     * @param  array<int, Coa>  $leafNodes
     */
    private function collectLeafDescendants(
        int $currentParentId,
        array $childrenByParent,
        array &$visited,
        array &$leafNodes,
    ): void {
        foreach ($childrenByParent[$currentParentId] ?? [] as $child) {
            $childId = (int) $child->id;
            if (isset($visited[$childId])) {
                continue;
            }

            $visited[$childId] = true;

            if (! isset($childrenByParent[$childId]) || $childrenByParent[$childId] === []) {
                $leafNodes[] = $child;

                continue;
            }

            $this->collectLeafDescendants($childId, $childrenByParent, $visited, $leafNodes);
        }
    }

    /**
     * @param  array<int, int>|null  $filterCoaIds
     * @return array<int, array{pos_id: int, nama_pos: string, is_active: bool}>
     */
    private function loadMappedByPosMap(?array $filterCoaIds = null): array
    {
        if ($filterCoaIds === []) {
            return [];
        }

        $mappings = SettingArusKasCoa::query()
            ->with('settingArusKas:id,nama_pos,is_active')
            ->when($filterCoaIds !== null, fn ($query) => $query->whereIn('coa_id', $filterCoaIds))
            ->get(['id', 'setting_arus_kas_id', 'coa_id']);

        $mapped = [];
        foreach ($mappings as $mapping) {
            if ($mapping->settingArusKas === null) {
                continue;
            }

            $mapped[(int) $mapping->coa_id] = [
                'pos_id' => (int) $mapping->setting_arus_kas_id,
                'nama_pos' => (string) $mapping->settingArusKas->nama_pos,
                'is_active' => (bool) $mapping->settingArusKas->is_active,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<int, int>|null  $filterCoaIds
     * @return array<int, bool>
     */
    private function loadExcludedCoaIdSet(?array $filterCoaIds = null): array
    {
        if ($filterCoaIds === []) {
            return [];
        }

        return SettingArusKasPengecualian::query()
            ->when($filterCoaIds !== null, fn ($query) => $query->whereIn('coa_id', $filterCoaIds))
            ->pluck('coa_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeCoaIds(array $coaIds): array
    {
        return collect($coaIds)
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => is_numeric($id) ? (int) $id : 0)
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    private function assertValidPosMetadata(string $kelompokBagian, string $sifatPos, string $namaPos): void
    {
        if ($namaPos === '') {
            throw new DomainException('Nama pos arus kas wajib diisi.');
        }

        if (! in_array($kelompokBagian, SettingArusKas::KELOMPOK_BAGIAN, true)) {
            throw new DomainException("Kelompok bagian '{$kelompokBagian}' tidak valid.");
        }

        if (! in_array($sifatPos, SettingArusKas::SIFAT_POS, true)) {
            throw new DomainException("Sifat pos '{$sifatPos}' tidak valid.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuditSnapshot(SettingArusKas $pos): array
    {
        return [
            'id' => (int) $pos->id,
            'kode_pos' => (string) $pos->kode_pos,
            'nama_pos' => (string) $pos->nama_pos,
            'kelompok_bagian' => (string) $pos->kelompok_bagian,
            'sifat_pos' => (string) $pos->sifat_pos,
            'pakai_prefix_perubahan' => (bool) $pos->pakai_prefix_perubahan,
            'urutan' => (int) $pos->urutan,
            'is_active' => (bool) $pos->is_active,
            'coa_ids' => $pos->coas
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
