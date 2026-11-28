<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingArusKasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (
            ! Schema::hasTable('setting_arus_kas')
            || ! Schema::hasTable('setting_arus_kas_coa')
            || ! Schema::hasTable('setting_arus_kas_pengecualian')
            || ! Schema::hasTable('coa')
        ) {
            return;
        }

        $pivotWasEmpty = SettingArusKasCoa::query()->doesntExist();
        $pengecualianWasEmpty = SettingArusKasPengecualian::query()->doesntExist();

        DB::transaction(function () use ($pivotWasEmpty, $pengecualianWasEmpty): void {
            $allCoas = Coa::query()
                ->orderBy('kode')
                ->get(['id', 'parent_coa', 'kode', 'tipe_coa', 'status_aktif']);

            $leafCoas = $this->extractLeafCoas($allCoas);
            $claimedCoaIds = $this->loadClaimedCoaIds();

            if ($pengecualianWasEmpty) {
                $this->seedInitialPengecualian($leafCoas, $claimedCoaIds);
            }

            $now = now();

            foreach ($this->posDefinitions() as $definition) {
                $pos = SettingArusKas::query()->firstOrCreate(
                    ['kode_pos' => $definition['kode_pos']],
                    [
                        'nama_pos' => $definition['nama_pos'],
                        'kelompok_bagian' => $definition['kelompok_bagian'],
                        'sifat_pos' => $definition['sifat_pos'],
                        'pakai_prefix_perubahan' => $definition['pakai_prefix_perubahan'],
                        'urutan' => $definition['urutan'],
                        'is_active' => true,
                    ]
                );

                if (! $pivotWasEmpty && ! $pos->wasRecentlyCreated) {
                    continue;
                }

                if ($definition['patterns'] === []) {
                    continue;
                }

                $rows = [];
                foreach ($leafCoas as $coa) {
                    $coaId = (int) $coa->id;

                    if ((int) $coa->status_aktif !== 1 || isset($claimedCoaIds[$coaId])) {
                        continue;
                    }

                    if (! $this->isCompatibleWithSifat($coa, $pos->sifat_pos)) {
                        continue;
                    }

                    if (! $this->matchesAnyPattern(trim((string) $coa->kode), $definition['patterns'])) {
                        continue;
                    }

                    $claimedCoaIds[$coaId] = true;
                    $rows[] = [
                        'setting_arus_kas_id' => $pos->id,
                        'coa_id' => $coaId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    SettingArusKasCoa::query()->insert($rows);
                }
            }
        });
    }

    /**
     * @param  Collection<int, Coa>  $allCoas
     * @return Collection<int, Coa>
     */
    private function extractLeafCoas(Collection $allCoas): Collection
    {
        $parentIds = [];
        foreach ($allCoas as $coa) {
            if ($coa->parent_coa !== null && (int) $coa->parent_coa > 0) {
                $parentIds[(int) $coa->parent_coa] = true;
            }
        }

        return $allCoas
            ->filter(fn (Coa $coa): bool => ! isset($parentIds[(int) $coa->id]))
            ->values();
    }

    /**
     * @return array<int, bool>
     */
    private function loadClaimedCoaIds(): array
    {
        $ids = SettingArusKasCoa::query()
            ->pluck('coa_id')
            ->merge(SettingArusKasPengecualian::query()->pluck('coa_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @param  Collection<int, Coa>  $leafCoas
     * @param  array<int, bool>  $claimedCoaIds
     */
    private function seedInitialPengecualian(Collection $leafCoas, array &$claimedCoaIds): void
    {
        $rules = [
            [
                'patterns' => ['142', '143', '144', '145', '146', '147'],
                'alasan' => 'Akun kontra penyusutan; penyesuaian non-kas sudah dihitung via akun beban 706.*',
            ],
            [
                'patterns' => ['112.09', '112.08.09'],
                'alasan' => 'Akun kontra penyisihan piutang; penyesuaian dihitung terpisah atau disesuaikan sesuai kebijakan akuntansi',
            ],
        ];

        $now = now();
        $rows = [];

        foreach ($leafCoas as $coa) {
            $coaId = (int) $coa->id;

            if (isset($claimedCoaIds[$coaId]) || ! $this->isNeracaNonKasbank($coa)) {
                continue;
            }

            $kode = trim((string) $coa->kode);
            foreach ($rules as $rule) {
                if (! $this->matchesAnyPattern($kode, $rule['patterns'])) {
                    continue;
                }

                $claimedCoaIds[$coaId] = true;
                $rows[] = [
                    'coa_id' => $coaId,
                    'alasan' => $rule['alasan'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                break;
            }
        }

        if ($rows !== []) {
            SettingArusKasPengecualian::query()->insert($rows);
        }
    }

    private function isNeracaNonKasbank(Coa $coa): bool
    {
        if (strtolower(trim((string) $coa->tipe_coa)) === 'kasbank') {
            return false;
        }

        return ! in_array((string) $coa->tipe_coa, SettingArusKas::TIPE_LABA_RUGI, true);
    }

    private function isCompatibleWithSifat(Coa $coa, string $sifatPos): bool
    {
        if (strtolower(trim((string) $coa->tipe_coa)) === 'kasbank') {
            return false;
        }

        $isLabaRugi = in_array((string) $coa->tipe_coa, SettingArusKas::TIPE_LABA_RUGI, true);

        if (in_array($sifatPos, SettingArusKas::SIFAT_LABA_RUGI, true)) {
            return $isLabaRugi;
        }

        return ! $isLabaRugi;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAnyPattern(string $kode, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($kode, $prefix.'.')) {
                    return true;
                }

                continue;
            }

            if ($kode === $pattern || str_starts_with($kode, $pattern.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{
     *     kode_pos: string,
     *     nama_pos: string,
     *     kelompok_bagian: string,
     *     sifat_pos: string,
     *     pakai_prefix_perubahan: bool,
     *     urutan: int,
     *     patterns: array<int, string>
     * }>
     */
    private function posDefinitions(): array
    {
        return [
            [
                'kode_pos' => 'PL_PENYUSUTAN_ASET_TETAP',
                'nama_pos' => 'Penyusutan Aset Tetap',
                'kelompok_bagian' => 'penyesuaian_laba',
                'sifat_pos' => 'penambah_laba',
                'pakai_prefix_perubahan' => false,
                'urutan' => 1,
                'patterns' => ['706.*'],
            ],
            [
                'kode_pos' => 'PL_PENYISIHAN_PIUTANG',
                'nama_pos' => 'Penyisihan kerugian piutang tak tertagih',
                'kelompok_bagian' => 'penyesuaian_laba',
                'sifat_pos' => 'penambah_laba',
                'pakai_prefix_perubahan' => false,
                'urutan' => 2,
                'patterns' => ['909.03'],
            ],
            [
                'kode_pos' => 'PL_KOREKSI_ASET_BERSIH',
                'nama_pos' => 'Koreksi Aset Bersih',
                'kelompok_bagian' => 'penyesuaian_laba',
                'sifat_pos' => 'ekuitas',
                'pakai_prefix_perubahan' => false,
                'urutan' => 3,
                'patterns' => ['331', '324'],
            ],
            [
                'kode_pos' => 'MK_PIUTANG_USAHA',
                'nama_pos' => 'Piutang usaha',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 1,
                'patterns' => ['112.01.*', '112.02.*', '112.05.*'],
            ],
            [
                'kode_pos' => 'MK_PIUTANG_LAIN_LAIN',
                'nama_pos' => 'Piutang lain-lain',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 2,
                'patterns' => [
                    '112.03.*',
                    '112.04',
                    '112.08.01',
                    '112.08.02',
                    '112.08.03',
                    '112.08.04',
                    '112.08.05',
                    '112.08.06',
                    '112.08.07',
                    '112.08.08',
                ],
            ],
            [
                'kode_pos' => 'MK_PERSEDIAAN',
                'nama_pos' => 'Persediaan',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 3,
                'patterns' => ['113.*'],
            ],
            [
                'kode_pos' => 'MK_PAJAK_DIBAYAR_DIMUKA',
                'nama_pos' => 'Pajak dibayar dimuka',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 4,
                'patterns' => ['114.*'],
            ],
            [
                'kode_pos' => 'MK_UANG_MUKA_PEMBELIAN',
                'nama_pos' => 'Uang muka pembelian',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 5,
                'patterns' => ['115.*'],
            ],
            [
                'kode_pos' => 'MK_HUTANG_USAHA',
                'nama_pos' => 'Hutang usaha',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 6,
                'patterns' => ['211.*', '216.04'],
            ],
            [
                'kode_pos' => 'MK_HUTANG_PAJAK',
                'nama_pos' => 'Hutang pajak',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 7,
                'patterns' => ['212.*'],
            ],
            [
                'kode_pos' => 'MK_PREMI_BELUM_DIBAGI',
                'nama_pos' => 'Premi belum dibagi',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 8,
                'patterns' => ['214.*'],
            ],
            [
                'kode_pos' => 'MK_BEBAN_YMH_DIBAYAR',
                'nama_pos' => 'Beban YMH dibayar',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 9,
                'patterns' => ['213.*'],
            ],
            [
                'kode_pos' => 'MK_UANG_MUKA_PASIEN',
                'nama_pos' => 'Uang muka pasien',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 10,
                'patterns' => ['215.*'],
            ],
            [
                'kode_pos' => 'MK_HUTANG_LAIN_LAIN',
                'nama_pos' => 'Hutang lain-lain',
                'kelompok_bagian' => 'modal_kerja',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 11,
                'patterns' => ['216.01', '216.02', '216.09', '2161'],
            ],
            [
                'kode_pos' => 'INV_PEMBELIAN_ASET',
                'nama_pos' => 'Pembelian aset',
                'kelompok_bagian' => 'investasi',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => false,
                'urutan' => 1,
                'patterns' => ['131', '132', '133', '134', '135', '136', '137', '1501'],
            ],
            [
                'kode_pos' => 'INV_HUTANG_PEMBELIAN_ASET_TETAP',
                'nama_pos' => 'Hutang pembelian aset tetap',
                'kelompok_bagian' => 'investasi',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 2,
                'patterns' => ['221.*'],
            ],
            [
                'kode_pos' => 'INV_ASET_LAIN_LAIN',
                'nama_pos' => 'Aset lain-lain',
                'kelompok_bagian' => 'investasi',
                'sifat_pos' => 'aset',
                'pakai_prefix_perubahan' => true,
                'urutan' => 3,
                'patterns' => ['1502'],
            ],
            [
                'kode_pos' => 'PEND_HUTANG_BANK',
                'nama_pos' => 'Hutang bank',
                'kelompok_bagian' => 'pendanaan',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 1,
                'patterns' => [],
            ],
            [
                'kode_pos' => 'PEND_HUTANG_PIHAK_ISTIMEWA',
                'nama_pos' => 'Hutang pada pihak istimewa',
                'kelompok_bagian' => 'pendanaan',
                'sifat_pos' => 'liabilitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 2,
                'patterns' => [],
            ],
            [
                'kode_pos' => 'PEND_MODAL',
                'nama_pos' => 'Modal',
                'kelompok_bagian' => 'pendanaan',
                'sifat_pos' => 'ekuitas',
                'pakai_prefix_perubahan' => true,
                'urutan' => 3,
                'patterns' => ['311', '321', '322', '323', '341', '342', '343'],
            ],
        ];
    }
}
