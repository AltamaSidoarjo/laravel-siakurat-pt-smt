<?php

namespace App\Services\Pengaturan;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Services\LogAktifitasService;
use App\Support\CoaTreeHelper;
use DomainException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReferensiKelompokArusKasService
{
    private const LOG_MODUL = 'Pengaturan Arus Kas';

    public function __construct(
        private ?LogAktifitasService $logService = null,
    ) {
        if ($this->logService === null && function_exists('app')) {
            try {
                $this->logService = app(LogAktifitasService::class);
            } catch (\Throwable) {
                $this->logService = null;
            }
        }
    }

    /**
     * Record audit log if logService is injected and log_aktifitas table exists.
     */
    private function recordLog(string $tipe, ?array $old = null, ?array $new = null): void
    {
        if ($this->logService !== null && Schema::hasTable('log_aktifitas')) {
            $this->logService->log(self::LOG_MODUL, $tipe, $old, $new);
        }
    }

    /**
     * Sanitize reference group name by replacing multiple whitespace with single space and trimming.
     */
    public function sanitizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    /**
     * Build case-insensitive composite group key for matching (aktivitas, kelompok).
     */
    private function buildGroupKey(string $aktivitas, string $kelompok): string
    {
        return mb_strtolower(trim($aktivitas)).'||'.mb_strtolower(trim($kelompok));
    }

    /**
     * Load map of parent COA IDs (accounts that have at least one child).
     *
     * @return array<int, bool>
     */
    private function loadParentCoaIdSet(): array
    {
        if (! Schema::hasTable('coa') || ! Schema::hasColumn('coa', 'parent_coa')) {
            return [];
        }

        return Coa::query()
            ->whereNotNull('parent_coa')
            ->where('parent_coa', '>', 0)
            ->pluck('parent_coa')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Normalize an array of mixed COA IDs into unique positive integers.
     *
     * @param  array  $coaIds
     * @return int[]
     */
    private function normalizeCoaIds(array $coaIds): array
    {
        return collect($coaIds)
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get leaf COA IDs currently mapped to a reference group.
     *
     * @return int[]
     */
    private function getMappedLeafCoaIds(ReferensiKelompokArusKas $ref): array
    {
        $parentIds = $this->loadParentCoaIdSet();

        return Coa::query()
            ->whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower(trim($ref->aktivitas))])
            ->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower(trim($ref->nama))])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => ! isset($parentIds[$id]))
            ->values()
            ->all();
    }

    /**
     * Build audit snapshot for a reference group.
     *
     * @param  int[]  $coaIds
     * @return array{id: int, aktivitas: string, nama: string, status_aktif: bool, coa_ids: int[]}
     */
    private function buildSnapshot(ReferensiKelompokArusKas $ref, array $coaIds = []): array
    {
        return [
            'id' => (int) $ref->id,
            'aktivitas' => (string) $ref->aktivitas,
            'nama' => (string) $ref->nama,
            'status_aktif' => (bool) $ref->status_aktif,
            'coa_ids' => array_values(array_map('intval', $coaIds)),
        ];
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
     * Prepare COA selector metadata (leaf options, parent options, mappedByCoaId) for direct method modals.
     *
     * @return array{
     *     leafCoas: Collection<int, Coa>,
     *     parentCoas: Collection<int, Coa>,
     *     mappedByCoaId: array<int, array{aktivitas: string, kelompok: string, referensi_id: ?int}>
     * }
     */
    public function getCoaOptionsData(): array
    {
        $allCoas = Coa::query()
            ->orderBy('kode')
            ->get(['id', 'parent_coa', 'kode', 'nama', 'tipe_coa', 'status_aktif', 'arus_kas_aktivitas', 'arus_kas_kelompok']);

        $partition = CoaTreeHelper::partitionParentAndLeafIds($allCoas);

        $parentCoas = $partition['parentCoas'];
        $leafCoas = $partition['leafCoas']
            ->filter(fn (Coa $c): bool => strtolower(trim((string) $c->tipe_coa)) !== 'kasbank')
            ->values();

        $refMap = [];
        if (Schema::hasTable('referensi_kelompok_arus_kas')) {
            foreach (ReferensiKelompokArusKas::all() as $ref) {
                $refMap[$this->buildGroupKey($ref->aktivitas, $ref->nama)] = (int) $ref->id;
            }
        }

        $mappedByCoaId = [];
        foreach ($leafCoas as $coa) {
            $akt = trim((string) ($coa->arus_kas_aktivitas ?? ''));
            $kel = trim((string) ($coa->arus_kas_kelompok ?? ''));
            if ($akt !== '' && $kel !== '') {
                $key = $this->buildGroupKey($akt, $kel);
                $mappedByCoaId[(int) $coa->id] = [
                    'aktivitas' => $akt,
                    'kelompok' => $kel,
                    'referensi_id' => $refMap[$key] ?? null,
                ];
            }
        }

        return [
            'leafCoas' => $leafCoas,
            'parentCoas' => $parentCoas,
            'mappedByCoaId' => $mappedByCoaId,
        ];
    }

    /**
     * Expand descendant leaves under a parent COA and classify into eligible, reassignable, and skipped.
     *
     * @return array{
     *     eligible: array<int, array{id: int, kode: string, nama: string, tipe_coa: string, status_aktif: int}>,
     *     reassignable: array<int, array{id: int, kode: string, nama: string, tipe_coa: string, status_aktif: int, current_aktivitas: string, current_kelompok: string, current_referensi_id: ?int}>,
     *     skipped: array<int, array{id: int, kode: string, nama: string, tipe_coa: string, status_aktif: int, reason: string}>
     * }
     */
    public function getLeafDescendantsByParent(int $parentId, ?int $ignoreReferensiId = null): array
    {
        if ($parentId <= 0) {
            return ['eligible' => [], 'reassignable' => [], 'skipped' => []];
        }

        $allCoas = Coa::query()
            ->orderBy('kode')
            ->get(['id', 'parent_coa', 'kode', 'nama', 'tipe_coa', 'status_aktif', 'arus_kas_aktivitas', 'arus_kas_kelompok']);

        $leafNodes = CoaTreeHelper::collectLeafDescendants($parentId, $allCoas);

        if ($leafNodes === []) {
            return ['eligible' => [], 'reassignable' => [], 'skipped' => []];
        }

        $ignoreKey = null;
        $refMap = [];

        if (Schema::hasTable('referensi_kelompok_arus_kas')) {
            $allRefs = ReferensiKelompokArusKas::all();
            foreach ($allRefs as $ref) {
                $key = $this->buildGroupKey($ref->aktivitas, $ref->nama);
                $refMap[$key] = (int) $ref->id;
                if ($ignoreReferensiId !== null && (int) $ref->id === $ignoreReferensiId) {
                    $ignoreKey = $key;
                }
            }
        }

        $eligible = [];
        $reassignable = [];
        $skipped = [];

        foreach ($leafNodes as $leaf) {
            $base = [
                'id' => (int) $leaf->id,
                'kode' => (string) $leaf->kode,
                'nama' => (string) $leaf->nama,
                'tipe_coa' => (string) ($leaf->tipe_coa ?? ''),
                'status_aktif' => (int) $leaf->status_aktif,
            ];

            if (strtolower(trim((string) $leaf->tipe_coa)) === 'kasbank') {
                $skipped[] = $base + [
                    'reason' => 'Akun Kasbank tidak dapat dipetakan ke kelompok arus kas langsung.',
                ];

                continue;
            }

            $currentAkt = trim((string) ($leaf->arus_kas_aktivitas ?? ''));
            $currentKel = trim((string) ($leaf->arus_kas_kelompok ?? ''));

            if ($currentAkt === '' || $currentKel === '') {
                $eligible[] = $base;

                continue;
            }

            $currentKey = $this->buildGroupKey($currentAkt, $currentKel);

            if ($ignoreKey !== null && $currentKey === $ignoreKey) {
                $eligible[] = $base;

                continue;
            }

            $reassignable[] = $base + [
                'current_aktivitas' => $currentAkt,
                'current_kelompok' => $currentKel,
                'current_referensi_id' => $refMap[$currentKey] ?? null,
            ];
        }

        return [
            'eligible' => $eligible,
            'reassignable' => $reassignable,
            'skipped' => $skipped,
        ];
    }

    /**
     * Validate COA IDs for direct method: accounts must exist, must be leaf (children_count == 0), and cannot be Kasbank.
     *
     * @param  array  $coaIds
     * @return array<int, string>
     */
    public function validateCoaIds(array $coaIds): array
    {
        if ($coaIds === []) {
            return [];
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

        $errors = [];
        foreach ($coaIds as $index => $rawCoaId) {
            $coaId = is_numeric($rawCoaId) ? (int) $rawCoaId : 0;
            $coa = $coaId > 0 ? $coas->get($coaId) : null;

            if ($coa === null) {
                $errors[$index] = "Akun COA dengan ID {$rawCoaId} tidak ditemukan.";

                continue;
            }

            $label = "{$coa->kode} - {$coa->nama}";

            if ((int) $coa->children_count > 0) {
                $errors[$index] = "Akun '{$label}' merupakan akun induk (parent) dan tidak dapat dipetakan langsung.";

                continue;
            }

            if (strtolower(trim((string) $coa->tipe_coa)) === 'kasbank') {
                $errors[$index] = "Akun '{$label}' bertipe Kasbank dan tidak dapat dipetakan ke kelompok arus kas langsung.";

                continue;
            }
        }

        return $errors;
    }

    /**
     * Validate COA IDs and attach per-index or direct errors to a Laravel Validator instance.
     */
    public function validateCoaIdsForValidator(
        ValidatorContract $validator,
        mixed $coaIds,
        string $fieldPrefix = 'coa_ids',
        bool $indexed = true,
    ): void {
        if (! is_array($coaIds) || $coaIds === []) {
            return;
        }

        $errors = $this->validateCoaIds($coaIds);
        foreach ($errors as $index => $message) {
            $key = $indexed ? "{$fieldPrefix}.{$index}" : $fieldPrefix;
            if (! $validator->errors()->has($key)) {
                $validator->errors()->add($key, $message);
            }
        }
    }

    /**
     * Synchronize COA mappings atomically for a reference group.
     * Leaves only: parent COAs are never touched by this method.
     *
     * @param  array  $coaIds
     * @return array{
     *     coa_ids: int[],
     *     added_coa_ids: int[],
     *     removed_coa_ids: int[],
     *     moved_from: array<int, array{aktivitas: string, kelompok: string}>
     * }
     */
    public function syncCoaMappings(ReferensiKelompokArusKas $ref, array $coaIds): array
    {
        return DB::transaction(function () use ($ref, $coaIds) {
            $normalizedIds = $this->normalizeCoaIds($coaIds);

            if ($normalizedIds !== []) {
                $errors = $this->validateCoaIds($normalizedIds);
                if ($errors !== []) {
                    throw new DomainException(reset($errors));
                }
            }

            $parentIds = $this->loadParentCoaIdSet();

            $currentMappedCoas = Coa::query()
                ->whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower(trim($ref->aktivitas))])
                ->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower(trim($ref->nama))])
                ->get();

            $currentLeafCoas = $currentMappedCoas->filter(fn (Coa $c): bool => ! isset($parentIds[(int) $c->id]));
            $currentLeafIds = $currentLeafCoas->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $removedIds = array_values(array_diff($currentLeafIds, $normalizedIds));
            if ($removedIds !== []) {
                Coa::query()
                    ->whereIn('id', $removedIds)
                    ->update([
                        'arus_kas_aktivitas' => null,
                        'arus_kas_kelompok' => null,
                    ]);
            }

            $targetCoas = $normalizedIds !== []
                ? Coa::query()->whereIn('id', $normalizedIds)->get()->keyBy('id')
                : collect();

            $addedIds = [];
            $movedFrom = [];

            foreach ($normalizedIds as $targetId) {
                $c = $targetCoas->get($targetId);
                if ($c === null) {
                    continue;
                }

                $currAkt = trim((string) ($c->arus_kas_aktivitas ?? ''));
                $currKel = trim((string) ($c->arus_kas_kelompok ?? ''));

                if (! in_array($targetId, $currentLeafIds, true)) {
                    $addedIds[] = $targetId;
                }

                $isSameGroup = mb_strtolower($currAkt) === mb_strtolower(trim($ref->aktivitas))
                    && mb_strtolower($currKel) === mb_strtolower(trim($ref->nama));

                if (! $isSameGroup && $currAkt !== '' && $currKel !== '') {
                    $movedFrom[$targetId] = [
                        'aktivitas' => $currAkt,
                        'kelompok' => $currKel,
                    ];
                }
            }

            if ($normalizedIds !== []) {
                Coa::query()
                    ->whereIn('id', $normalizedIds)
                    ->update([
                        'arus_kas_aktivitas' => $ref->aktivitas,
                        'arus_kas_kelompok' => $ref->nama,
                    ]);
            }

            return [
                'coa_ids' => $normalizedIds,
                'added_coa_ids' => $addedIds,
                'removed_coa_ids' => $removedIds,
                'moved_from' => $movedFrom,
            ];
        });
    }

    /**
     * Store a new reference group and optionally sync initial leaf COAs.
     */
    public function store(array $data): ReferensiKelompokArusKas
    {
        return DB::transaction(function () use ($data) {
            $cleanName = $this->sanitizeName($data['nama'] ?? '');

            $ref = ReferensiKelompokArusKas::create([
                'aktivitas' => $data['aktivitas'],
                'nama' => $cleanName,
                'status_aktif' => (bool) ($data['status_aktif'] ?? true),
            ]);

            $syncResult = null;
            if (array_key_exists('coa_ids', $data)) {
                $syncResult = $this->syncCoaMappings($ref, (array) ($data['coa_ids'] ?? []));
            }

            $snapshot = $this->buildSnapshot($ref, $syncResult !== null ? $syncResult['coa_ids'] : []);
            $this->recordLog('create_langsung', null, $snapshot);

            return $ref;
        });
    }

    /**
     * Update an existing reference group with cascade rename and optional COA synchronization.
     * Sentinel compatibility: COA mappings are only modified if 'coa_ids' key is present in $data.
     */
    public function update(ReferensiKelompokArusKas $ref, array $data): ReferensiKelompokArusKas
    {
        return DB::transaction(function () use ($ref, $data) {
            $oldName = $ref->nama;
            $oldAktivitas = $ref->aktivitas;
            $oldStatus = (bool) $ref->status_aktif;
            $oldLeafIds = $this->getMappedLeafCoaIds($ref);
            $oldSnapshot = $this->buildSnapshot($ref, $oldLeafIds);

            $cleanName = isset($data['nama']) ? $this->sanitizeName($data['nama']) : $oldName;

            $updateData = [];
            if (isset($data['nama'])) {
                $updateData['nama'] = $cleanName;
            }
            if (isset($data['status_aktif'])) {
                $updateData['status_aktif'] = (bool) $data['status_aktif'];
            }

            $ref->update($updateData);

            if ($cleanName !== $oldName) {
                Coa::query()
                    ->whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower($oldAktivitas)])
                    ->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower($oldName)])
                    ->update(['arus_kas_kelompok' => $cleanName]);
            }

            $syncResult = null;
            if (array_key_exists('coa_ids', $data)) {
                $syncResult = $this->syncCoaMappings($ref, (array) ($data['coa_ids'] ?? []));
            }

            $currentLeafIds = $syncResult !== null ? $syncResult['coa_ids'] : $this->getMappedLeafCoaIds($ref);
            $newSnapshot = $this->buildSnapshot($ref, $currentLeafIds);
            if ($syncResult !== null) {
                $newSnapshot['added_coa_ids'] = $syncResult['added_coa_ids'];
                $newSnapshot['removed_coa_ids'] = $syncResult['removed_coa_ids'];
                $newSnapshot['moved_from'] = $syncResult['moved_from'];
            }

            $this->recordLog('update_langsung', $oldSnapshot, $newSnapshot);

            return $ref;
        });
    }

    /**
     * Quickly map a single leaf non-Kasbank COA to a reference group.
     *
     * @return array{coa: Coa, referensi: ReferensiKelompokArusKas}
     *
     * @throws DomainException
     */
    public function quickMapCoa(int $coaId, int $referensiId): array
    {
        return DB::transaction(function () use ($coaId, $referensiId) {
            $ref = ReferensiKelompokArusKas::find($referensiId);
            if ($ref === null) {
                throw new DomainException('Pos referensi tidak ditemukan.');
            }

            $errors = $this->validateCoaIds([$coaId]);
            if ($errors !== []) {
                throw new DomainException(reset($errors));
            }

            $coa = Coa::findOrFail($coaId);
            $oldPayload = [
                'id' => (int) $coa->id,
                'kode' => (string) $coa->kode,
                'nama' => (string) $coa->nama,
                'arus_kas_aktivitas' => $coa->arus_kas_aktivitas,
                'arus_kas_kelompok' => $coa->arus_kas_kelompok,
            ];

            $coa->arus_kas_aktivitas = $ref->aktivitas;
            $coa->arus_kas_kelompok = $ref->nama;
            $coa->save();

            $newPayload = [
                'id' => (int) $coa->id,
                'kode' => (string) $coa->kode,
                'nama' => (string) $coa->nama,
                'arus_kas_aktivitas' => $ref->aktivitas,
                'arus_kas_kelompok' => $ref->nama,
                'referensi_id' => (int) $ref->id,
            ];

            $this->recordLog('quick_map_langsung', $oldPayload, $newPayload);

            return [
                'coa' => $coa,
                'referensi' => $ref,
            ];
        });
    }

    /**
     * Check if a reference group can be safely deleted, distinguishing leaf vs parent COA usage case-insensitively.
     *
     * @return array{allowed: bool, coa_count: int, leaf_count: int, parent_count: int, reason: ?string}
     */
    public function canDelete(ReferensiKelompokArusKas $ref): array
    {
        $coas = Coa::query()
            ->whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower($ref->aktivitas)])
            ->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower($ref->nama)])
            ->get();

        $parentIds = $this->loadParentCoaIdSet();

        $leafCount = 0;
        $parentCount = 0;

        foreach ($coas as $coa) {
            if (isset($parentIds[(int) $coa->id])) {
                $parentCount++;
            } else {
                $leafCount++;
            }
        }

        $coaCount = $leafCount + $parentCount;
        $allowed = ($coaCount === 0);

        $reason = null;
        if (! $allowed) {
            if ($leafCount > 0 && $parentCount === 0) {
                $reason = "Pos ini sedang digunakan oleh {$leafCount} akun COA.";
            } elseif ($leafCount === 0 && $parentCount > 0) {
                $reason = "Pos ini sedang digunakan oleh {$parentCount} akun induk (template Master COA).";
            } else {
                $reason = "Pos ini sedang digunakan oleh {$leafCount} akun COA dan {$parentCount} akun induk (template Master COA).";
            }
        }

        return [
            'allowed' => $allowed,
            'coa_count' => $coaCount,
            'leaf_count' => $leafCount,
            'parent_count' => $parentCount,
            'reason' => $reason,
        ];
    }

    /**
     * Delete a reference group with usage guardrails and audit logging.
     *
     * @throws DomainException
     */
    public function delete(ReferensiKelompokArusKas $ref): void
    {
        $check = $this->canDelete($ref);

        if (! $check['allowed']) {
            throw new DomainException($check['reason'] ?? 'Pos referensi tidak dapat dihapus karena sedang digunakan.');
        }

        DB::transaction(function () use ($ref) {
            $snapshot = $this->buildSnapshot($ref, []);
            $ref->delete();
            $this->recordLog('delete_langsung', $snapshot);
        });
    }

    /**
     * Toggle active status of a reference group with audit logging.
     */
    public function toggleStatus(ReferensiKelompokArusKas $ref): bool
    {
        return DB::transaction(function () use ($ref) {
            $oldStatus = (bool) $ref->status_aktif;
            $ref->status_aktif = ! $oldStatus;
            $ref->save();

            $this->recordLog(
                'toggle_langsung',
                ['id' => $ref->id, 'status_aktif' => $oldStatus],
                ['id' => $ref->id, 'status_aktif' => (bool) $ref->status_aktif]
            );

            return (bool) $ref->status_aktif;
        });
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

    /**
     * Detect unmapped non-Kasbank counterpart accounts from cash transactions within the given date range.
     *
     * @return Collection<int, array{
     *     coa_id: int,
     *     kode: string,
     *     nama: string,
     *     tipe_coa: string,
     *     status_aktif: int,
     *     debit: float,
     *     kredit: float,
     *     dampak_kas: float,
     *     trx_count: int
     * }>
     */
    public function getUnmappedCashCounterpartAccounts(?string $startDate = null, ?string $endDate = null): Collection
    {
        if (! Schema::hasTable('bukubesar') || ! Schema::hasTable('coa')) {
            return collect();
        }

        $start = $startDate ?: now()->startOfYear()->toDateString();
        $end = $endDate ?: now()->endOfYear()->toDateString();
        $normalizedEnd = strlen($end) === 10 ? $end.' 23:59:59' : $end;

        $rows = BukuBesar::query()
            ->join('coa', 'coa.id', '=', 'bukubesar.coa_id')
            ->whereBetween('bukubesar.tanggal', [$start, $normalizedEnd])
            ->get([
                'bukubesar.id',
                'bukubesar.sumber_id',
                'bukubesar.tanggal',
                'bukubesar.nomer',
                'bukubesar.sumber_transaksi',
                'bukubesar.nominal',
                'bukubesar.tipe_mutasi',
                'coa.id as coa_id',
                'coa.kode as kode_coa',
                'coa.nama as nama_coa',
                'coa.tipe_coa',
                'coa.status_aktif',
                'coa.arus_kas_aktivitas',
                'coa.arus_kas_kelompok',
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        $parentIds = $this->loadParentCoaIdSet();

        $txGroups = $rows->groupBy(function (object $row): string {
            if ($row->sumber_id !== null && (string) $row->sumber_id !== '') {
                return ((string) $row->sumber_transaksi).'|'.((string) $row->sumber_id);
            }

            return ((string) $row->sumber_transaksi).'|'.((string) $row->nomer).'|'.substr((string) $row->tanggal, 0, 10);
        });

        $unmappedByCoa = [];

        foreach ($txGroups as $txKey => $transaction) {
            $cashRows = $transaction->filter(
                fn (object $row): bool => strtolower(trim((string) $row->tipe_coa)) === 'kasbank'
            );

            if ($cashRows->isEmpty()) {
                continue;
            }

            $cashNet = (float) $cashRows->sum(
                fn (object $row): float => $row->tipe_mutasi === 'D' ? (float) $row->nominal : -(float) $row->nominal
            );

            if (abs($cashNet) < 0.005) {
                continue;
            }

            $counterparts = $transaction->reject(
                fn (object $row): bool => strtolower(trim((string) $row->tipe_coa)) === 'kasbank'
            );

            $countedCoaIdsInTx = [];

            foreach ($counterparts as $counterpart) {
                $coaId = (int) $counterpart->coa_id;

                if (isset($parentIds[$coaId])) {
                    continue;
                }

                $nominal = (float) $counterpart->nominal;
                if (abs($nominal) < 0.005) {
                    continue;
                }

                $aktivitas = strtolower(trim((string) ($counterpart->arus_kas_aktivitas ?? '')));
                $kelompok = trim((string) ($counterpart->arus_kas_kelompok ?? ''));
                $isMapped = in_array($aktivitas, ['operasi', 'investasi', 'pendanaan'], true) && $kelompok !== '';

                if ($isMapped) {
                    continue;
                }

                if (! isset($unmappedByCoa[$coaId])) {
                    $unmappedByCoa[$coaId] = [
                        'coa_id' => $coaId,
                        'kode' => (string) $counterpart->kode_coa,
                        'nama' => (string) $counterpart->nama_coa,
                        'tipe_coa' => (string) ($counterpart->tipe_coa ?? ''),
                        'status_aktif' => (int) ($counterpart->status_aktif ?? 1),
                        'debit' => 0.0,
                        'kredit' => 0.0,
                        'dampak_kas' => 0.0,
                        'trx_count' => 0,
                    ];
                }

                if ($counterpart->tipe_mutasi === 'D') {
                    $unmappedByCoa[$coaId]['debit'] += $nominal;
                } elseif ($counterpart->tipe_mutasi === 'K') {
                    $unmappedByCoa[$coaId]['kredit'] += $nominal;
                }

                if (! isset($countedCoaIdsInTx[$coaId])) {
                    $unmappedByCoa[$coaId]['trx_count']++;
                    $countedCoaIdsInTx[$coaId] = true;
                }
            }
        }

        $result = collect();
        foreach ($unmappedByCoa as $item) {
            $debit = (float) $item['debit'];
            $kredit = (float) $item['kredit'];

            if (abs($debit) < 0.005 && abs($kredit) < 0.005) {
                continue;
            }

            $item['debit'] = $debit;
            $item['kredit'] = $kredit;
            $item['dampak_kas'] = $kredit - $debit;

            $result->push($item);
        }

        return $result->sortBy('kode')->values();
    }
}
