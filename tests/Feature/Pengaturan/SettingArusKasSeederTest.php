<?php

namespace Tests\Feature\Pengaturan;

use App\Models\Coa;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use Database\Seeders\SettingArusKasSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingArusKasSeederTest extends TestCase
{
    /** @var array<string, Coa> */
    private array $coas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();
        $this->seedCoaHierarchy();
    }

    private function prepareSchema(): void
    {
        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'coa',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php'))->up();
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipeCoa,
        ?string $parentKode = null,
        int $statusAktif = 1,
    ): Coa {
        $parentId = $parentKode !== null ? $this->coas[$parentKode]->id : null;

        $coa = Coa::create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipeCoa,
            'parent_coa' => $parentId,
            'status_aktif' => $statusAktif,
        ]);

        $this->coas[$kode] = $coa;

        return $coa;
    }

    private function seedCoaHierarchy(): void
    {
        // Kasbank (must never be mapped or excluded)
        $this->createCoa('111.01', 'Kas Utama', 'Kasbank');

        // Piutang & sub-hierarchy
        $this->createCoa('112', 'Piutang', 'Piutang Usaha');
        $this->createCoa('112.01', 'Piutang Pasien Umum', 'Piutang Usaha', '112');
        $this->createCoa('112.01.01', 'Piutang Pasien Rawat Jalan', 'Piutang Usaha', '112.01');
        $this->createCoa('112.01.99', 'Piutang Pasien Nonaktif', 'Piutang Usaha', '112.01', 0);

        $this->createCoa('112.02', 'Piutang BPJS', 'Piutang Usaha', '112');
        $this->createCoa('112.02.01', 'Piutang Klaim BPJS', 'Piutang Usaha', '112.02');

        $this->createCoa('112.03', 'Piutang Karyawan', 'Piutang Usaha', '112');
        $this->createCoa('112.03.01', 'Piutang Gaji Karyawan', 'Piutang Usaha', '112.03');

        $this->createCoa('112.04', 'Piutang Direksi', 'Piutang Usaha', '112');

        $this->createCoa('112.05', 'Piutang Asuransi', 'Piutang Usaha', '112');
        $this->createCoa('112.05.01', 'Piutang Asuransi Swasta', 'Piutang Usaha', '112.05');

        $this->createCoa('112.08', 'Piutang Lainnya', 'Piutang Usaha', '112');
        $this->createCoa('112.08.01', 'Piutang Lain-lain 1', 'Piutang Usaha', '112.08');
        $this->createCoa('112.08.08', 'Piutang Lain-lain 8', 'Piutang Usaha', '112.08');
        $this->createCoa('112.08.09', 'Penyisihan Piutang Lain-lain', 'Piutang Usaha', '112.08');

        $this->createCoa('112.09', 'Cadangan Kerugian Piutang', 'Piutang Usaha', '112');

        // Persediaan, Pajak Dibayar Dimuka, Uang Muka Pembelian
        $this->createCoa('113', 'Persediaan', 'Persediaan');
        $this->createCoa('113.01', 'Persediaan Obat', 'Persediaan', '113');

        $this->createCoa('114', 'Pajak Dibayar Dimuka', 'Aset Lancar Lainnya');
        $this->createCoa('114.01', 'PPN Masukan', 'Aset Lancar Lainnya', '114');

        $this->createCoa('115', 'Uang Muka Pembelian', 'Aset Lancar Lainnya');
        $this->createCoa('115.01', 'Uang Muka Vendor', 'Aset Lancar Lainnya', '115');

        // Aset Tetap (131..137), Akumulasi Penyusutan (142..147), Aset Lainnya (1501, 1502)
        foreach (range(131, 137) as $kode) {
            $this->createCoa((string) $kode, "Aset Tetap {$kode}", 'Aset Tetap');
        }
        $this->createCoa('1501', 'Aset Dalam Penyelesaian', 'Aset Lainnya');
        $this->createCoa('1502', 'Aset Lain-lain Operasional', 'Aset Lainnya');

        foreach (range(142, 147) as $kode) {
            $parentKode = (string) $kode;
            $this->createCoa($parentKode, "Akumulasi Penyusutan {$kode}", 'Akumulasi Penyusutan');
            $this->createCoa("{$parentKode}.01", "Akumulasi Penyusutan {$kode}.01", 'Akumulasi Penyusutan', $parentKode);
        }

        // Kewajiban
        $this->createCoa('211', 'Hutang Usaha', 'Hutang Usaha');
        $this->createCoa('211.01', 'Hutang Supplier Obat', 'Hutang Usaha', '211');

        $this->createCoa('212', 'Hutang Pajak', 'Kewajiban Lancar Lainnya');
        $this->createCoa('212.01', 'Hutang PPh 21', 'Kewajiban Lancar Lainnya', '212');

        $this->createCoa('213', 'Beban YMH Dibayar', 'Kewajiban Lancar Lainnya');
        $this->createCoa('213.01', 'Beban Gaji YMH Dibayar', 'Kewajiban Lancar Lainnya', '213');

        $this->createCoa('214', 'Premi Belum Dibagi', 'Kewajiban Lancar Lainnya');
        $this->createCoa('214.01', 'Jasa Medis Belum Dibagi', 'Kewajiban Lancar Lainnya', '214');

        $this->createCoa('215', 'Uang Muka Pasien', 'Kewajiban Lancar Lainnya');
        $this->createCoa('215.01', 'Deposit Pasien Rawat Inap', 'Kewajiban Lancar Lainnya', '215');

        $this->createCoa('216', 'Hutang Lain-lain', 'Kewajiban Lancar Lainnya');
        $this->createCoa('216.01', 'Hutang Lain-lain Operasional', 'Kewajiban Lancar Lainnya', '216');
        $this->createCoa('216.02', 'Hutang Titipan', 'Kewajiban Lancar Lainnya', '216');
        $this->createCoa('216.04', 'Hutang Usaha Lainnya', 'Kewajiban Lancar Lainnya', '216');
        $this->createCoa('216.09', 'Hutang Rupa-rupa', 'Kewajiban Lancar Lainnya', '216');
        $this->createCoa('2161', 'Hutang Lain-lain 2161', 'Kewajiban Lancar Lainnya');

        $this->createCoa('221', 'Hutang Pembelian Aset Tetap', 'Kewajiban Jangka Panjang');
        $this->createCoa('221.01', 'Hutang Leasing Alat Medis', 'Kewajiban Jangka Panjang', '221');

        // Ekuitas
        foreach (['311', '321', '322', '323', '324', '331', '341', '342', '343'] as $kode) {
            $this->createCoa($kode, "Ekuitas {$kode}", 'Ekuitas');
        }

        // Laba Rugi (Penyesuaian Laba)
        $this->createCoa('706', 'Beban Penyusutan', 'Beban');
        $this->createCoa('706.01', 'Beban Penyusutan Bangunan', 'Beban', '706');

        $this->createCoa('909', 'Beban Lain-lain', 'Beban lain');
        $this->createCoa('909.03', 'Beban Penyisihan Piutang Tak Tertagih', 'Beban lain', '909');
    }

    public function test_seeder_populates_20_pos_initial_coa_mappings_and_pengecualian(): void
    {
        $this->seed(SettingArusKasSeeder::class);

        $this->assertSame(20, SettingArusKas::query()->count());
        $this->assertSame(3, SettingArusKas::query()->where('kelompok_bagian', 'penyesuaian_laba')->count());
        $this->assertSame(11, SettingArusKas::query()->where('kelompok_bagian', 'modal_kerja')->count());
        $this->assertSame(3, SettingArusKas::query()->where('kelompok_bagian', 'investasi')->count());
        $this->assertSame(3, SettingArusKas::query()->where('kelompok_bagian', 'pendanaan')->count());

        $expectedPos = [
            ['PL_PENYUSUTAN_ASET_TETAP', 'penyesuaian_laba', 1, 'Penyusutan Aset Tetap', 'penambah_laba', false],
            ['PL_PENYISIHAN_PIUTANG', 'penyesuaian_laba', 2, 'Penyisihan kerugian piutang tak tertagih', 'penambah_laba', false],
            ['PL_KOREKSI_ASET_BERSIH', 'penyesuaian_laba', 3, 'Koreksi Aset Bersih', 'ekuitas', false],
            ['MK_PIUTANG_USAHA', 'modal_kerja', 1, 'Piutang usaha', 'aset', true],
            ['MK_PIUTANG_LAIN_LAIN', 'modal_kerja', 2, 'Piutang lain-lain', 'aset', true],
            ['MK_PERSEDIAAN', 'modal_kerja', 3, 'Persediaan', 'aset', true],
            ['MK_PAJAK_DIBAYAR_DIMUKA', 'modal_kerja', 4, 'Pajak dibayar dimuka', 'aset', true],
            ['MK_UANG_MUKA_PEMBELIAN', 'modal_kerja', 5, 'Uang muka pembelian', 'aset', true],
            ['MK_HUTANG_USAHA', 'modal_kerja', 6, 'Hutang usaha', 'liabilitas', true],
            ['MK_HUTANG_PAJAK', 'modal_kerja', 7, 'Hutang pajak', 'liabilitas', true],
            ['MK_PREMI_BELUM_DIBAGI', 'modal_kerja', 8, 'Premi belum dibagi', 'liabilitas', true],
            ['MK_BEBAN_YMH_DIBAYAR', 'modal_kerja', 9, 'Beban YMH dibayar', 'liabilitas', true],
            ['MK_UANG_MUKA_PASIEN', 'modal_kerja', 10, 'Uang muka pasien', 'liabilitas', true],
            ['MK_HUTANG_LAIN_LAIN', 'modal_kerja', 11, 'Hutang lain-lain', 'liabilitas', true],
            ['INV_PEMBELIAN_ASET', 'investasi', 1, 'Pembelian aset', 'aset', false],
            ['INV_HUTANG_PEMBELIAN_ASET_TETAP', 'investasi', 2, 'Hutang pembelian aset tetap', 'liabilitas', true],
            ['INV_ASET_LAIN_LAIN', 'investasi', 3, 'Aset lain-lain', 'aset', true],
            ['PEND_HUTANG_BANK', 'pendanaan', 1, 'Hutang bank', 'liabilitas', true],
            ['PEND_HUTANG_PIHAK_ISTIMEWA', 'pendanaan', 2, 'Hutang pada pihak istimewa', 'liabilitas', true],
            ['PEND_MODAL', 'pendanaan', 3, 'Modal', 'ekuitas', true],
        ];

        foreach ($expectedPos as [$kodePos, $bagian, $urutan, $namaPos, $sifatPos, $pakaiPrefix]) {
            $pos = SettingArusKas::query()->where('kode_pos', $kodePos)->first();
            $this->assertNotNull($pos, "Missing pos {$kodePos}");
            $this->assertSame($bagian, $pos->kelompok_bagian, "Bagian mismatch on {$kodePos}");
            $this->assertSame($urutan, $pos->urutan, "Urutan mismatch on {$kodePos}");
            $this->assertSame($namaPos, $pos->nama_pos, "Nama mismatch on {$kodePos}");
            $this->assertSame($sifatPos, $pos->sifat_pos, "Sifat mismatch on {$kodePos}");
            $this->assertSame($pakaiPrefix, $pos->pakai_prefix_perubahan, "Prefix mismatch on {$kodePos}");
            $this->assertTrue($pos->is_active, "Active mismatch on {$kodePos}");
        }

        $mappedCodes = fn (string $kodePos): array => SettingArusKas::query()
            ->where('kode_pos', $kodePos)
            ->firstOrFail()
            ->coas()
            ->orderBy('kode')
            ->pluck('kode')
            ->all();

        $this->assertSame(['706.01'], $mappedCodes('PL_PENYUSUTAN_ASET_TETAP'));
        $this->assertSame(['909.03'], $mappedCodes('PL_PENYISIHAN_PIUTANG'));
        $this->assertSame(['324', '331'], $mappedCodes('PL_KOREKSI_ASET_BERSIH'));
        $this->assertSame(['112.01.01', '112.02.01', '112.05.01'], $mappedCodes('MK_PIUTANG_USAHA'));
        $this->assertSame(['112.03.01', '112.04', '112.08.01', '112.08.08'], $mappedCodes('MK_PIUTANG_LAIN_LAIN'));
        $this->assertSame(['113.01'], $mappedCodes('MK_PERSEDIAAN'));
        $this->assertSame(['114.01'], $mappedCodes('MK_PAJAK_DIBAYAR_DIMUKA'));
        $this->assertSame(['115.01'], $mappedCodes('MK_UANG_MUKA_PEMBELIAN'));
        $this->assertSame(['211.01', '216.04'], $mappedCodes('MK_HUTANG_USAHA'));
        $this->assertSame(['212.01'], $mappedCodes('MK_HUTANG_PAJAK'));
        $this->assertSame(['214.01'], $mappedCodes('MK_PREMI_BELUM_DIBAGI'));
        $this->assertSame(['213.01'], $mappedCodes('MK_BEBAN_YMH_DIBAYAR'));
        $this->assertSame(['215.01'], $mappedCodes('MK_UANG_MUKA_PASIEN'));
        $this->assertSame(['216.01', '216.02', '216.09', '2161'], $mappedCodes('MK_HUTANG_LAIN_LAIN'));
        $this->assertSame(['131', '132', '133', '134', '135', '136', '137', '1501'], $mappedCodes('INV_PEMBELIAN_ASET'));
        $this->assertSame(['221.01'], $mappedCodes('INV_HUTANG_PEMBELIAN_ASET_TETAP'));
        $this->assertSame(['1502'], $mappedCodes('INV_ASET_LAIN_LAIN'));
        $this->assertSame([], $mappedCodes('PEND_HUTANG_BANK'));
        $this->assertSame([], $mappedCodes('PEND_HUTANG_PIHAK_ISTIMEWA'));
        $this->assertSame(['311', '321', '322', '323', '341', '342', '343'], $mappedCodes('PEND_MODAL'));

        // Kasbank and inactive leaf must not be mapped or excluded
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $this->coas['111.01']->id]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $this->coas['112.01.99']->id]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $this->coas['706']->id]);

        // Initial pengecualian seeded (142.01..147.01, 112.09, 112.08.09), not parent 142..147
        $excludedCodes = SettingArusKasPengecualian::query()
            ->join('coa', 'coa.id', '=', 'setting_arus_kas_pengecualian.coa_id')
            ->orderBy('coa.kode')
            ->pluck('coa.kode')
            ->all();

        $this->assertSame([
            '112.08.09',
            '112.09',
            '142.01',
            '143.01',
            '144.01',
            '145.01',
            '146.01',
            '147.01',
        ], $excludedCodes);

        foreach ($excludedCodes as $code) {
            $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $this->coas[$code]->id]);
        }
    }

    public function test_rerunning_seeder_preserves_user_edits_deletions_and_moved_coas_without_unique_conflict(): void
    {
        $this->seed(SettingArusKasSeeder::class);

        // 1. User edits a pos name
        $piutangPos = SettingArusKas::query()->where('kode_pos', 'MK_PIUTANG_USAHA')->firstOrFail();
        $piutangPos->update(['nama_pos' => 'Piutang Pasien & Klaim']);

        // 2. User deletes a mapping (112.01.01)
        SettingArusKasCoa::query()
            ->where('setting_arus_kas_id', $piutangPos->id)
            ->where('coa_id', $this->coas['112.01.01']->id)
            ->delete();

        // 3. User moves 1502 from INV_ASET_LAIN_LAIN to INV_PEMBELIAN_ASET
        $pembelianAsetPos = SettingArusKas::query()->where('kode_pos', 'INV_PEMBELIAN_ASET')->firstOrFail();
        $asetLainPos = SettingArusKas::query()->where('kode_pos', 'INV_ASET_LAIN_LAIN')->firstOrFail();
        SettingArusKasCoa::query()
            ->where('setting_arus_kas_id', $asetLainPos->id)
            ->where('coa_id', $this->coas['1502']->id)
            ->delete();
        SettingArusKasCoa::create([
            'setting_arus_kas_id' => $pembelianAsetPos->id,
            'coa_id' => $this->coas['1502']->id,
        ]);

        // 4. User deletes 112.09 exception
        SettingArusKasPengecualian::query()
            ->where('coa_id', $this->coas['112.09']->id)
            ->delete();

        // Re-run seeder
        $this->seed(SettingArusKasSeeder::class);

        $this->assertSame(20, SettingArusKas::query()->count());
        $this->assertSame('Piutang Pasien & Klaim', $piutangPos->fresh()->nama_pos);
        $this->assertDatabaseMissing('setting_arus_kas_coa', ['coa_id' => $this->coas['112.01.01']->id]);
        $this->assertDatabaseHas('setting_arus_kas_coa', [
            'setting_arus_kas_id' => $pembelianAsetPos->id,
            'coa_id' => $this->coas['1502']->id,
        ]);
        $this->assertDatabaseMissing('setting_arus_kas_coa', [
            'setting_arus_kas_id' => $asetLainPos->id,
            'coa_id' => $this->coas['1502']->id,
        ]);
        $this->assertDatabaseMissing('setting_arus_kas_pengecualian', ['coa_id' => $this->coas['112.09']->id]);
    }
}
