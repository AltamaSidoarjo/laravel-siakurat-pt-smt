<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use App\Models\TipeCoa;
use App\Models\User;
use App\Services\Laporan\LaporanKeuanganService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanArusKasTidakLangsungDynamicTest extends TestCase
{
    private User $reportOnlyUser;

    private User $reportAndSettingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        $this->seedTipeCoa();

        $reportOnlyRole = Role::query()->create([
            'nama' => 'Pelapor Saja',
            'kode' => 'pelapor_saja',
        ]);

        $reportAndSettingRole = Role::query()->create([
            'nama' => 'Akuntan Senior',
            'kode' => 'akuntan_senior',
        ]);

        $laporanModule = AccessModule::query()->create([
            'kode' => 'laporan.keuangan',
            'nama' => 'Laporan Keuangan',
            'group_nama' => 'Laporan',
        ]);

        $settingModule = AccessModule::query()->create([
            'kode' => 'pengaturan.arus-kas',
            'nama' => 'Pengaturan Arus Kas',
            'group_nama' => 'Pengaturan',
        ]);

        RolePermission::query()->create([
            'role_id' => $reportOnlyRole->id,
            'access_module_id' => $laporanModule->id,
            'can_view' => true,
        ]);

        RolePermission::query()->create([
            'role_id' => $reportAndSettingRole->id,
            'access_module_id' => $laporanModule->id,
            'can_view' => true,
        ]);

        RolePermission::query()->create([
            'role_id' => $reportAndSettingRole->id,
            'access_module_id' => $settingModule->id,
            'can_view' => true,
        ]);

        $this->reportOnlyUser = User::query()->create([
            'name' => 'Pelapor Saja',
            'email' => 'pelapor@example.com',
            'password' => bcrypt('password'),
            'role_id' => $reportOnlyRole->id,
        ]);

        $this->reportAndSettingUser = User::query()->create([
            'name' => 'Akuntan Senior',
            'email' => 'senior@example.com',
            'password' => bcrypt('password'),
            'role_id' => $reportAndSettingRole->id,
        ]);
    }

    private function prepareTables(): void
    {
        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'bukubesar',
            'setting_rba',
            'coa',
            'tipe_coa',
            'role_permissions',
            'access_modules',
            'roles',
            'users',
            'preferensi_perusahaan',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->timestamps();
        });

        Schema::create('tipe_coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('setting_rba', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('coa_id');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('total_nominal', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php'))->up();
    }

    private function seedTipeCoa(): void
    {
        $types = [
            'Kasbank', 'Akun Piutang', 'Piutang Usaha', 'Persediaan',
            'Aktiva Lancar lainnya', 'Aset Lancar lainnya', 'Akumulasi Penyusutan',
            'Aset Tetap', 'Aktiva Lainnya', 'Akun Hutang', 'Utang Usaha',
            'Hutang Lancar lainnya', 'Liabilitas Jangka Pendek', 'Hutang Jangka Panjang',
            'Ekuitas', 'Pendapatan', 'Pendapatan lain', 'Beban Pokok Penjualan',
            'Beban', 'Beban lain',
        ];

        foreach ($types as $type) {
            TipeCoa::query()->create(['nama' => $type, 'status_aktif' => 1]);
        }
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipeCoa,
        int $statusAktif = 1,
        ?string $arusKasAktivitas = null,
        ?string $arusKasKelompok = null,
    ): Coa {
        return Coa::query()->create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipeCoa,
            'status_aktif' => $statusAktif,
            'is_postable' => true,
            'arus_kas_aktivitas' => $arusKasAktivitas,
            'arus_kas_kelompok' => $arusKasKelompok,
        ]);
    }

    private function postEntry(Coa $coa, string $tanggal, string $nomer, string $sumber, float $nominal, string $tipeMutasi): void
    {
        BukuBesar::query()->create([
            'coa_id' => $coa->id,
            'tanggal' => $tanggal,
            'nomer' => $nomer,
            'sumber_transaksi' => $sumber,
            'nominal' => $nominal,
            'tipe_mutasi' => $tipeMutasi,
        ]);
    }

    private function createPos(
        string $kodePos,
        string $namaPos,
        string $bagian,
        string $sifatPos,
        int $urutan,
        bool $pakaiPrefix = false,
        bool $isActive = true,
        array $coaIds = [],
    ): SettingArusKas {
        $pos = SettingArusKas::query()->create([
            'kode_pos' => $kodePos,
            'nama_pos' => $namaPos,
            'kelompok_bagian' => $bagian,
            'sifat_pos' => $sifatPos,
            'pakai_prefix_perubahan' => $pakaiPrefix,
            'urutan' => $urutan,
            'is_active' => $isActive,
        ]);

        foreach ($coaIds as $coaId) {
            SettingArusKasCoa::query()->create([
                'setting_arus_kas_id' => $pos->id,
                'coa_id' => $coaId,
            ]);
        }

        return $pos;
    }

    public function test_fallback_to_legacy_when_setting_arus_kas_table_exists_with_zero_rows(): void
    {
        $this->assertTrue(Schema::hasTable('setting_arus_kas'));
        $this->assertSame(0, SettingArusKas::query()->count());

        $kas = $this->createCoa('1101', 'Kas Utama', 'Kasbank');
        $piutang = $this->createCoa('112.01', 'Piutang Pasien', 'Piutang Usaha');
        $utang = $this->createCoa('211.01', 'Utang Pemasok', 'Utang Usaha');
        $pendapatan = $this->createCoa('4101', 'Pendapatan Layanan', 'Pendapatan');
        $bebanPenyusutan = $this->createCoa('706.01', 'Biaya Penyusutan Alat', 'Beban');
        $akumulasi = $this->createCoa('142.01', 'Akumulasi Penyusutan Alat', 'Akumulasi Penyusutan');

        // Pendapatan 1.000.000 (Kas 750.000, Piutang 250.000)
        $this->postEntry($kas, '2026-07-05', 'TRX-1', 'Pendapatan', 750_000, 'D');
        $this->postEntry($piutang, '2026-07-05', 'TRX-1', 'Pendapatan', 250_000, 'D');
        $this->postEntry($pendapatan, '2026-07-05', 'TRX-1', 'Pendapatan', 1_000_000, 'K');

        // Utang naik 100.000 (Kas +100.000)
        $this->postEntry($kas, '2026-07-08', 'TRX-2', 'Kas & Bank', 100_000, 'D');
        $this->postEntry($utang, '2026-07-08', 'TRX-2', 'Kas & Bank', 100_000, 'K');

        // Penyusutan non-kas 50.000
        $this->postEntry($bebanPenyusutan, '2026-07-10', 'JUR-1', 'Jurnal Umum', 50_000, 'D');
        $this->postEntry($akumulasi, '2026-07-10', 'JUR-1', 'Jurnal Umum', 50_000, 'K');

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $berjalan = $result['berjalan'];

        $this->assertFalse($berjalan['uses_dynamic_setting']);
        $this->assertFalse($result['pembanding']['uses_dynamic_setting']);
        $this->assertEquals(950_000.0, $berjalan['laba_bersih']);
        $this->assertEquals(50_000.0, $berjalan['penyesuaian_non_kas']['subtotal']);
        $this->assertEquals(1_000_000.0, $berjalan['laba_sebelum_modal_kerja']);
        $this->assertEquals(-250_000.0, $berjalan['modal_kerja']['items']->get('piutang')['dampak_kas']);
        $this->assertEquals(100_000.0, $berjalan['modal_kerja']['items']->get('utang_usaha')['dampak_kas']);
        $this->assertEquals(850_000.0, $berjalan['kas_operasi']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);
        $this->assertTrue($berjalan['akun_belum_dipetakan']->isEmpty());
        $this->assertEquals(0.0, $berjalan['belum_dipetakan']);

        $this->actingAs($this->reportAndSettingUser)
            ->get(route('laporan.keuangan.arus-kas-tidak-langsung', ['startDate' => '2026-07-01', 'endDate' => '2026-07-31']))
            ->assertOk()
            ->assertSee('Beban Penyusutan & Amortisasi', false)
            ->assertDontSee('Laba Operasi Sebelum Perubahan Modal Kerja');
    }

    public function test_dynamic_four_section_calculation_and_sifat_pos_rules(): void
    {
        $kas = $this->createCoa('111.01', 'Kas Utama', 'Kasbank');
        $piutang = $this->createCoa('112.01', 'Piutang Pasien Umum', 'Piutang Usaha');
        $persediaan = $this->createCoa('113.01', 'Persediaan Obat', 'Persediaan');
        $pajakDimuka = $this->createCoa('114.01', 'Pajak Dibayar Dimuka', 'Aktiva Lancar lainnya');
        $asetTetap = $this->createCoa('131', 'Peralatan Medis', 'Aset Tetap');
        $akumPenyusutan = $this->createCoa('142.01', 'Akumulasi Penyusutan Peralatan', 'Akumulasi Penyusutan');
        $cadanganPiutang = $this->createCoa('112.09', 'Cadangan Kerugian Piutang', 'Piutang Usaha');
        $hutangUsaha = $this->createCoa('211.01', 'Hutang Supplier Obat', 'Utang Usaha');
        $hutangPajak = $this->createCoa('212.01', 'Hutang PPh 21', 'Hutang Lancar lainnya');
        $bebanYmh = $this->createCoa('213.01', 'Beban YMH Dibayar', 'Liabilitas Jangka Pendek');
        $hutangAset = $this->createCoa('221.01', 'Hutang Pembelian Aset Tetap', 'Hutang Jangka Panjang');
        $modal = $this->createCoa('311', 'Modal Disetor', 'Ekuitas');
        $koreksiEkuitas = $this->createCoa('321', 'Pengembalian Modal', 'Ekuitas');
        $pendapatan = $this->createCoa('410.01', 'Pendapatan Rawat Jalan', 'Pendapatan');
        $pemulihanGain = $this->createCoa('490.01', 'Laba Pelepasan / Pemulihan Non-Kas', 'Pendapatan lain');
        $hpp = $this->createCoa('510.01', 'Beban Pokok Obat', 'Beban Pokok Penjualan');
        $bebanPenyusutan = $this->createCoa('706.01', 'Beban Penyusutan Peralatan', 'Beban');

        // Pengecualian akun kontra agar tidak double-counting atau masuk akun belum dipetakan
        SettingArusKasPengecualian::query()->create(['coa_id' => $akumPenyusutan->id, 'alasan' => 'Kontra penyusutan']);
        SettingArusKasPengecualian::query()->create(['coa_id' => $cadanganPiutang->id, 'alasan' => 'Kontra piutang']);

        // 1. Penyesuaian Laba
        $this->createPos('PL_PENYUSUTAN', 'Penyusutan Aset Tetap', 'penyesuaian_laba', 'penambah_laba', 1, false, true, [$bebanPenyusutan->id]);
        $this->createPos('PL_PENGURANG', 'Laba Non-Kas Pelepasan', 'penyesuaian_laba', 'pengurang_laba', 2, false, true, [$pemulihanGain->id]);
        $this->createPos('PL_PLACEHOLDER', 'Penyesuaian Lainnya', 'penyesuaian_laba', 'penambah_laba', 3, false, true, []);

        // 2. Modal Kerja
        $this->createPos('MK_PIUTANG', 'Piutang usaha', 'modal_kerja', 'aset', 1, true, true, [$piutang->id]);
        $this->createPos('MK_PERSEDIAAN', 'Persediaan', 'modal_kerja', 'aset', 2, true, true, [$persediaan->id]);
        $this->createPos('MK_PAJAK_DIMUKA', 'Pajak dibayar dimuka', 'modal_kerja', 'aset', 3, true, true, [$pajakDimuka->id]);
        $this->createPos('MK_HUTANG_USAHA', 'Hutang usaha', 'modal_kerja', 'liabilitas', 4, true, true, [$hutangUsaha->id]);
        $this->createPos('MK_HUTANG_PAJAK', 'Hutang pajak', 'modal_kerja', 'liabilitas', 5, true, true, [$hutangPajak->id]);
        $this->createPos('MK_BEBAN_YMH', 'Beban YMH dibayar', 'modal_kerja', 'liabilitas', 6, true, true, [$bebanYmh->id]);

        // 3. Investasi
        $this->createPos('INV_PEMBELIAN_ASET', 'Pembelian aset', 'investasi', 'aset', 1, false, true, [$asetTetap->id]);
        $this->createPos('INV_HUTANG_ASET', 'Hutang pembelian aset tetap', 'investasi', 'liabilitas', 2, true, true, [$hutangAset->id]);

        // 4. Pendanaan
        $this->createPos('PEND_MODAL', 'Modal', 'pendanaan', 'ekuitas', 1, true, true, [$modal->id]);
        $this->createPos('PEND_PENGEMBALIAN', 'Pengembalian Modal', 'pendanaan', 'ekuitas', 2, true, true, [$koreksiEkuitas->id]);
        $this->createPos('PEND_HUTANG_BANK', 'Hutang bank', 'pendanaan', 'liabilitas', 3, true, true, []);

        // Transaksi periode berjalan (2026-07-01..2026-07-31):
        // 1. Pendapatan 1.000.000 (Kas +700.000, Piutang +300.000)
        $this->postEntry($kas, '2026-07-02', 'INV-01', 'Pendapatan', 700_000, 'D');
        $this->postEntry($piutang, '2026-07-02', 'INV-01', 'Pendapatan', 300_000, 'D');
        $this->postEntry($pendapatan, '2026-07-02', 'INV-01', 'Pendapatan', 1_000_000, 'K');

        // 2. Pemakaian Persediaan 450.000 & Pembelian Kredit Persediaan 200.000 (Persediaan net K 250.000, Utang K 200.000)
        $this->postEntry($hpp, '2026-07-04', 'JUR-01', 'Jurnal Umum', 450_000, 'D');
        $this->postEntry($persediaan, '2026-07-04', 'JUR-01', 'Jurnal Umum', 450_000, 'K');
        $this->postEntry($persediaan, '2026-07-05', 'PEM-01', 'Pembelian', 200_000, 'D');
        $this->postEntry($hutangUsaha, '2026-07-05', 'PEM-01', 'Pembelian', 200_000, 'K');

        // 3. Pembayaran Hutang Pajak 50.000 (Kas -50.000, Hutang Pajak D 50.000)
        $this->postEntry($hutangPajak, '2026-07-07', 'KB-01', 'Kas & Bank', 50_000, 'D');
        $this->postEntry($kas, '2026-07-07', 'KB-01', 'Kas & Bank', 50_000, 'K');

        // 4. Penyusutan non-kas 120.000 (706.01 D 120.000, 142.01 K 120.000) -> 142.01 dikecualikan (no double count)
        $this->postEntry($bebanPenyusutan, '2026-07-10', 'JUR-02', 'Jurnal Umum', 120_000, 'D');
        $this->postEntry($akumPenyusutan, '2026-07-10', 'JUR-02', 'Jurnal Umum', 120_000, 'K');

        // 5. Pemulihan / Laba non-kas 30.000 (112.09 D 30.000, 490.01 K 30.000) -> membalik +(K-D) keluar dari kas operasi
        $this->postEntry($cadanganPiutang, '2026-07-12', 'JUR-03', 'Jurnal Umum', 30_000, 'D');
        $this->postEntry($pemulihanGain, '2026-07-12', 'JUR-03', 'Jurnal Umum', 30_000, 'K');

        // 6. Pembelian aset tetap 400.000 (Kas -250.000, Hutang Pembelian Aset +150.000)
        $this->postEntry($asetTetap, '2026-07-15', 'INV-AST', 'Pembelian', 400_000, 'D');
        $this->postEntry($kas, '2026-07-15', 'INV-AST', 'Pembelian', 250_000, 'K');
        $this->postEntry($hutangAset, '2026-07-15', 'INV-AST', 'Pembelian', 150_000, 'K');

        // 7. Setoran modal 500.000 & pengembalian modal 80.000 (Kas +420.000)
        $this->postEntry($kas, '2026-07-20', 'EQ-01', 'Kas & Bank', 500_000, 'D');
        $this->postEntry($modal, '2026-07-20', 'EQ-01', 'Kas & Bank', 500_000, 'K');
        $this->postEntry($koreksiEkuitas, '2026-07-22', 'EQ-02', 'Kas & Bank', 80_000, 'D');
        $this->postEntry($kas, '2026-07-22', 'EQ-02', 'Kas & Bank', 80_000, 'K');

        $service = new LaporanKeuanganService;
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $b = $result['berjalan'];

        $this->assertTrue($b['uses_dynamic_setting']);

        // Laba Bersih = 1.000.000 + 30.000 - 450.000 - 120.000 = 460.000
        $this->assertEquals(460_000.0, $b['laba_bersih']);

        // Penyesuaian non-kas: +120.000 (706.01) - 30.000 (490.01) + 0 (placeholder) = +90.000
        $plItems = $b['penyesuaian_non_kas']['items'];
        $this->assertSame(['PL_PENYUSUTAN', 'PL_PENGURANG', 'PL_PLACEHOLDER'], $plItems->keys()->all());
        $this->assertSame('Penyusutan Aset Tetap', $plItems->get('PL_PENYUSUTAN')['label']);
        $this->assertEquals(120_000.0, $plItems->get('PL_PENYUSUTAN')['dampak_kas']);
        $this->assertSame('Laba Non-Kas Pelepasan', $plItems->get('PL_PENGURANG')['label']);
        $this->assertEquals(30_000.0, $plItems->get('PL_PENGURANG')['perubahan']);
        $this->assertEquals(-30_000.0, $plItems->get('PL_PENGURANG')['dampak_kas']);
        $this->assertEquals(0.0, $plItems->get('PL_PLACEHOLDER')['dampak_kas']);
        $this->assertEquals(90_000.0, $b['penyesuaian_non_kas']['subtotal']);

        // Laba Operasi Sebelum Perubahan Modal Kerja = 460.000 + 90.000 = 550.000
        $this->assertEquals(550_000.0, $b['laba_sebelum_modal_kerja']);

        // Modal Kerja items & dynamic prefix formatting
        $mkItems = $b['modal_kerja']['items'];
        $this->assertSame('(Kenaikan) Piutang usaha', $mkItems->get('MK_PIUTANG')['label']);
        $this->assertEquals(-300_000.0, $mkItems->get('MK_PIUTANG')['dampak_kas']);

        $this->assertSame('Penurunan Persediaan', $mkItems->get('MK_PERSEDIAAN')['label']);
        $this->assertEquals(250_000.0, $mkItems->get('MK_PERSEDIAAN')['dampak_kas']);
        $this->assertCount(1, $mkItems->get('MK_PERSEDIAAN')['detail_akun']);
        $this->assertEquals(200_000.0, $mkItems->get('MK_PERSEDIAAN')['detail_akun']->first()['debit']);
        $this->assertEquals(450_000.0, $mkItems->get('MK_PERSEDIAAN')['detail_akun']->first()['kredit']);

        $this->assertSame('Penurunan (kenaikan) Pajak dibayar dimuka', $mkItems->get('MK_PAJAK_DIMUKA')['label']);
        $this->assertEquals(0.0, $mkItems->get('MK_PAJAK_DIMUKA')['dampak_kas']);

        $this->assertSame('Kenaikan Hutang usaha', $mkItems->get('MK_HUTANG_USAHA')['label']);
        $this->assertEquals(200_000.0, $mkItems->get('MK_HUTANG_USAHA')['dampak_kas']);

        $this->assertSame('(Penurunan) Hutang pajak', $mkItems->get('MK_HUTANG_PAJAK')['label']);
        $this->assertEquals(-50_000.0, $mkItems->get('MK_HUTANG_PAJAK')['dampak_kas']);

        $this->assertSame('Kenaikan (penurunan) Beban YMH dibayar', $mkItems->get('MK_BEBAN_YMH')['label']);
        $this->assertEquals(0.0, $mkItems->get('MK_BEBAN_YMH')['dampak_kas']);

        $this->assertEquals(100_000.0, $b['modal_kerja']['subtotal']);
        $this->assertEquals(650_000.0, $b['kas_operasi']);

        // Investasi: -400.000 + 150.000 = -250.000
        $invItems = $b['investasi']['items'];
        $this->assertSame('Pembelian aset', $invItems->get('INV_PEMBELIAN_ASET')['label']);
        $this->assertEquals(-400_000.0, $invItems->get('INV_PEMBELIAN_ASET')['dampak_kas']);
        $this->assertSame('Kenaikan Hutang pembelian aset tetap', $invItems->get('INV_HUTANG_ASET')['label']);
        $this->assertEquals(150_000.0, $invItems->get('INV_HUTANG_ASET')['dampak_kas']);
        $this->assertEquals(-250_000.0, $b['investasi']['subtotal']);
        $this->assertSame([
            ['kelompok' => 'Pembelian aset', 'nilai' => -400_000.0],
            ['kelompok' => 'Kenaikan Hutang pembelian aset tetap', 'nilai' => 150_000.0],
        ], $b['investasi']['kelompok']->all());

        // Pendanaan: +500.000 - 80.000 + 0 = +420.000
        $pendItems = $b['pendanaan']['items'];
        $this->assertSame('Kenaikan Modal', $pendItems->get('PEND_MODAL')['label']);
        $this->assertEquals(500_000.0, $pendItems->get('PEND_MODAL')['dampak_kas']);
        $this->assertSame('(Penurunan) Pengembalian Modal', $pendItems->get('PEND_PENGEMBALIAN')['label']);
        $this->assertEquals(-80_000.0, $pendItems->get('PEND_PENGEMBALIAN')['dampak_kas']);
        $this->assertSame('Kenaikan (penurunan) Hutang bank', $pendItems->get('PEND_HUTANG_BANK')['label']);
        $this->assertEquals(0.0, $pendItems->get('PEND_HUTANG_BANK')['dampak_kas']);
        $this->assertEquals(420_000.0, $b['pendanaan']['subtotal']);
        $this->assertSame([
            ['kelompok' => 'Kenaikan Modal', 'nilai' => 500_000.0],
            ['kelompok' => '(Penurunan) Pengembalian Modal', 'nilai' => -80_000.0],
            ['kelompok' => 'Kenaikan (penurunan) Hutang bank', 'nilai' => 0.0],
        ], $b['pendanaan']['kelompok']->all());

        // Excluded accounts must NOT appear in akun_belum_dipetakan
        $this->assertTrue($b['akun_belum_dipetakan']->isEmpty());
        $this->assertEquals(0.0, $b['belum_dipetakan']);

        // Rekonsiliasi: 650.000 - 250.000 + 420.000 = 820.000 == Kas Riil
        $this->assertEquals(820_000.0, $b['kenaikan_penurunan']);
        $this->assertEquals(820_000.0, $b['kas_akhir']);
        $this->assertEquals(820_000.0, $b['kas_akhir_riil']);
        $this->assertEquals(0.0, $b['selisih_rekonsiliasi']);
    }

    public function test_unmapped_active_inactive_and_deactivated_pos_neraca_accounts_and_exclusions(): void
    {
        $kas = $this->createCoa('111.01', 'Kas Utama', 'Kasbank');
        $piutang = $this->createCoa('112.01', 'Piutang Pasien', 'Piutang Usaha');
        $unmappedActive = $this->createCoa('115.01', 'Uang Muka Vendor Baru', 'Aktiva Lancar lainnya', 1);
        $unmappedInactiveHistorical = $this->createCoa('115.99', 'Uang Muka Lama', 'Aktiva Lancar lainnya', 0);
        $deactivatedPosCoa = $this->createCoa('215.01', 'Uang Muka Pasien', 'Liabilitas Jangka Pendek', 1);
        $akumPenyusutan = $this->createCoa('142.01', 'Akumulasi Penyusutan', 'Akumulasi Penyusutan', 1);
        $cadanganPiutang = $this->createCoa('112.09', 'Cadangan Kerugian Piutang', 'Piutang Usaha', 1);
        $pendapatan = $this->createCoa('410.01', 'Pendapatan Jasa', 'Pendapatan', 1);
        $bebanPenyusutan = $this->createCoa('706.01', 'Beban Penyusutan', 'Beban', 1);
        $bebanPenyisihan = $this->createCoa('909.03', 'Beban Penyisihan Piutang', 'Beban lain', 1);

        SettingArusKasPengecualian::query()->create(['coa_id' => $akumPenyusutan->id, 'alasan' => 'Kontra penyusutan']);
        SettingArusKasPengecualian::query()->create(['coa_id' => $cadanganPiutang->id, 'alasan' => 'Kontra piutang']);

        $this->createPos('PL_PENY', 'Penyusutan Aset Tetap', 'penyesuaian_laba', 'penambah_laba', 1, false, true, [$bebanPenyusutan->id]);
        $this->createPos('PL_PENYISIHAN', 'Penyisihan kerugian piutang tak tertagih', 'penyesuaian_laba', 'penambah_laba', 2, false, true, [$bebanPenyisihan->id]);
        $this->createPos('MK_PIUTANG', 'Piutang usaha', 'modal_kerja', 'aset', 1, true, true, [$piutang->id]);
        // Pos nonaktif -> akunnya tetap bermutasi sehingga harus terdeteksi sebagai belum dipetakan
        $this->createPos('MK_UM_PASIEN', 'Uang muka pasien', 'modal_kerja', 'liabilitas', 2, true, false, [$deactivatedPosCoa->id]);

        // Periode pembanding (2026-06-01..2026-06-30): akun Neraca nonaktif punya mutasi historis
        $this->postEntry($unmappedInactiveHistorical, '2026-06-15', 'HIST-01', 'Kas & Bank', 75_000, 'D');
        $this->postEntry($kas, '2026-06-15', 'HIST-01', 'Kas & Bank', 75_000, 'K');

        // Periode berjalan (2026-07-01..2026-07-31):
        // 1. Pendapatan tunai 400.000
        $this->postEntry($kas, '2026-07-03', 'TRX-B1', 'Pendapatan', 400_000, 'D');
        $this->postEntry($pendapatan, '2026-07-03', 'TRX-B1', 'Pendapatan', 400_000, 'K');

        // 2. Uang muka vendor (unmapped aktif) D 125.000 via kas
        $this->postEntry($unmappedActive, '2026-07-06', 'TRX-B2', 'Kas & Bank', 125_000, 'D');
        $this->postEntry($kas, '2026-07-06', 'TRX-B2', 'Kas & Bank', 125_000, 'K');

        // 3. Uang muka pasien (pos is_active = false) K 90.000 via kas
        $this->postEntry($kas, '2026-07-09', 'TRX-B3', 'Kas & Bank', 90_000, 'D');
        $this->postEntry($deactivatedPosCoa, '2026-07-09', 'TRX-B3', 'Kas & Bank', 90_000, 'K');

        // 4. Akun dikecualikan bermutasi (tidak boleh muncul di akun_belum_dipetakan)
        $this->postEntry($bebanPenyusutan, '2026-07-12', 'TRX-B4', 'Jurnal Umum', 40_000, 'D');
        $this->postEntry($akumPenyusutan, '2026-07-12', 'TRX-B4', 'Jurnal Umum', 40_000, 'K');
        $this->postEntry($bebanPenyisihan, '2026-07-13', 'TRX-B5', 'Jurnal Umum', 20_000, 'D');
        $this->postEntry($cadanganPiutang, '2026-07-13', 'TRX-B5', 'Jurnal Umum', 20_000, 'K');

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');

        $berjalan = $result['berjalan'];
        $pembanding = $result['pembanding'];

        // Berjalan: pos nonaktif tidak ada di modal_kerja.items, akunnya + unmappedActive masuk akun_belum_dipetakan
        $this->assertFalse($berjalan['modal_kerja']['items']->has('MK_UM_PASIEN'));
        $this->assertSame(['115.01', '215.01'], $berjalan['akun_belum_dipetakan']->pluck('kode')->all());
        $this->assertNotContains('142.01', $berjalan['akun_belum_dipetakan']->pluck('kode')->all());
        $this->assertNotContains('112.09', $berjalan['akun_belum_dipetakan']->pluck('kode')->all());
        $this->assertEquals(-125_000.0 + 90_000.0, $berjalan['belum_dipetakan']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);

        // Pembanding: akun Neraca nonaktif (status_aktif = 0) dengan mutasi historis tetap terdeteksi
        $this->assertSame(['115.99'], $pembanding['akun_belum_dipetakan']->pluck('kode')->all());
        $this->assertSame(0, $pembanding['akun_belum_dipetakan']->first()['status_aktif']);
        $this->assertEquals(-75_000.0, $pembanding['belum_dipetakan']);
        $this->assertEquals(0.0, $pembanding['selisih_rekonsiliasi']);
    }

    public function test_controller_and_view_render_dynamic_sections_warning_banner_permission_link_and_accordion(): void
    {
        $kas = $this->createCoa('111.01', 'Kas Utama', 'Kasbank');
        $piutang = $this->createCoa('112.01', 'Piutang Pasien', 'Piutang Usaha');
        $pajakDimuka = $this->createCoa('114.01', 'Pajak Dibayar Dimuka', 'Aktiva Lancar lainnya');
        $asetTetap = $this->createCoa('131', 'Peralatan Medis', 'Aset Tetap');
        $modal = $this->createCoa('311', 'Modal Disetor', 'Ekuitas');
        $unmapped = $this->createCoa('115.01', 'Uang Muka Vendor', 'Aktiva Lancar lainnya');
        $pendapatan = $this->createCoa('410.01', 'Pendapatan', 'Pendapatan');
        $bebanPenyusutan = $this->createCoa('706.01', 'Beban Penyusutan', 'Beban');
        $akumPenyusutan = $this->createCoa('142.01', 'Akumulasi Penyusutan', 'Akumulasi Penyusutan');

        SettingArusKasPengecualian::query()->create(['coa_id' => $akumPenyusutan->id, 'alasan' => 'Kontra penyusutan']);

        $this->createPos('PL_PENYUSUTAN', 'Penyusutan Aset Tetap', 'penyesuaian_laba', 'penambah_laba', 1, false, true, [$bebanPenyusutan->id]);
        $this->createPos('MK_PIUTANG', 'Piutang usaha', 'modal_kerja', 'aset', 1, true, true, [$piutang->id]);
        $this->createPos('MK_PAJAK_DIMUKA', 'Pajak dibayar dimuka', 'modal_kerja', 'aset', 2, true, true, [$pajakDimuka->id]);
        $this->createPos('INV_PEMBELIAN_ASET', 'Pembelian aset', 'investasi', 'aset', 1, false, true, [$asetTetap->id]);
        $this->createPos('PEND_MODAL', 'Modal', 'pendanaan', 'ekuitas', 1, true, true, [$modal->id]);

        $this->postEntry($kas, '2026-07-02', 'T1', 'Pendapatan', 600_000, 'D');
        $this->postEntry($piutang, '2026-07-02', 'T1', 'Pendapatan', 150_000, 'D');
        $this->postEntry($pendapatan, '2026-07-02', 'T1', 'Pendapatan', 750_000, 'K');

        $this->postEntry($bebanPenyusutan, '2026-07-05', 'T2', 'Jurnal Umum', 80_000, 'D');
        $this->postEntry($akumPenyusutan, '2026-07-05', 'T2', 'Jurnal Umum', 80_000, 'K');

        $this->postEntry($asetTetap, '2026-07-08', 'T3', 'Kas & Bank', 200_000, 'D');
        $this->postEntry($kas, '2026-07-08', 'T3', 'Kas & Bank', 200_000, 'K');

        $this->postEntry($kas, '2026-07-10', 'T4', 'Kas & Bank', 300_000, 'D');
        $this->postEntry($modal, '2026-07-10', 'T4', 'Kas & Bank', 300_000, 'K');

        $this->postEntry($unmapped, '2026-07-12', 'T5', 'Kas & Bank', 50_000, 'D');
        $this->postEntry($kas, '2026-07-12', 'T5', 'Kas & Bank', 50_000, 'K');

        $url = route('laporan.keuangan.arus-kas-tidak-langsung', ['startDate' => '2026-07-01', 'endDate' => '2026-07-31']);

        // User dengan izin pengaturan.arus-kas melihat banner + link pengaturan
        $responseWithAccess = $this->actingAs($this->reportAndSettingUser)->get($url);
        $responseWithAccess->assertOk()
            ->assertSee('115.01 - Uang Muka Vendor (Aktiva Lancar lainnya)')
            ->assertSee(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']))
            ->assertSee('Laba Operasi Sebelum Perubahan Modal Kerja')
            ->assertSee('Penyusutan Aset Tetap')
            ->assertSee('(Kenaikan) Piutang usaha')
            ->assertSee('Penurunan (kenaikan) Pajak dibayar dimuka')
            ->assertSee('Pembelian aset')
            ->assertSee('Kenaikan Modal')
            ->assertSee('Perubahan Akun Neraca Belum Dipetakan')
            ->assertSee('Penyesuaian Laba')
            ->assertSee('Modal Kerja')
            ->assertSee('Investasi')
            ->assertSee('Pendanaan')
            ->assertSee('Belum Dipetakan')
            ->assertSee('706.01')
            ->assertSee('112.01')
            ->assertSee('131')
            ->assertSee('311')
            ->assertSee('115.01');

        // User tanpa izin pengaturan.arus-kas tetap melihat banner peringatan, namun TANPA link pengaturan
        $responseWithoutSettingAccess = $this->actingAs($this->reportOnlyUser)->get($url);
        $responseWithoutSettingAccess->assertOk()
            ->assertSee('115.01 - Uang Muka Vendor (Aktiva Lancar lainnya)')
            ->assertDontSee(route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung']));
    }

    public function test_deactivated_laba_rugi_coa_is_skipped_in_penyesuaian_laba_while_inactive_neraca_coa_is_included(): void
    {
        $kas = $this->createCoa('111.01', 'Kas Utama', 'Kasbank');
        $piutangNonaktif = $this->createCoa('112.99', 'Piutang Pasien Lama', 'Piutang Usaha', 0);
        $akumPenyusutan = $this->createCoa('142.01', 'Akumulasi Penyusutan', 'Akumulasi Penyusutan', 1);
        $pendapatan = $this->createCoa('410.01', 'Pendapatan Jasa', 'Pendapatan', 1);
        $bebanPenyusutanAktif = $this->createCoa('706.01', 'Beban Penyusutan Aktif', 'Beban', 1);
        $bebanPenyusutanNonaktif = $this->createCoa('706.99', 'Beban Penyusutan Lama', 'Beban', 0);

        SettingArusKasPengecualian::query()->create(['coa_id' => $akumPenyusutan->id, 'alasan' => 'Kontra penyusutan']);

        $this->createPos(
            'PL_PENYUSUTAN',
            'Penyusutan Aset Tetap',
            'penyesuaian_laba',
            'penambah_laba',
            1,
            false,
            true,
            [$bebanPenyusutanAktif->id, $bebanPenyusutanNonaktif->id]
        );
        $this->createPos(
            'MK_PIUTANG',
            'Piutang usaha',
            'modal_kerja',
            'aset',
            1,
            true,
            true,
            [$piutangNonaktif->id]
        );

        // Pendapatan tunai 200.000
        $this->postEntry($kas, '2026-07-02', 'TRX-1', 'Pendapatan', 200_000, 'D');
        $this->postEntry($pendapatan, '2026-07-02', 'TRX-1', 'Pendapatan', 200_000, 'K');

        // Beban penyusutan aktif 40.000 (masuk laba bersih & penyesuaian_non_kas)
        $this->postEntry($bebanPenyusutanAktif, '2026-07-05', 'TRX-2', 'Jurnal Umum', 40_000, 'D');
        $this->postEntry($akumPenyusutan, '2026-07-05', 'TRX-2', 'Jurnal Umum', 40_000, 'K');

        // Beban penyusutan nonaktif 60.000 (dikecualikan dari laba bersih -> wajib dilewati di penyesuaian_non_kas)
        $this->postEntry($bebanPenyusutanNonaktif, '2026-07-06', 'TRX-3', 'Jurnal Umum', 60_000, 'D');
        $this->postEntry($akumPenyusutan, '2026-07-06', 'TRX-3', 'Jurnal Umum', 60_000, 'K');

        // Pelunasan piutang dari akun Neraca nonaktif 100.000 (tetap dihitung di pos Neraca)
        $this->postEntry($kas, '2026-07-08', 'TRX-4', 'Kas & Bank', 100_000, 'D');
        $this->postEntry($piutangNonaktif, '2026-07-08', 'TRX-4', 'Kas & Bank', 100_000, 'K');

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $berjalan = $result['berjalan'];

        $this->assertEquals(160_000.0, $berjalan['laba_bersih']);

        $posPenyusutan = $berjalan['penyesuaian_non_kas']['items']->get('PL_PENYUSUTAN');
        $this->assertEquals(40_000.0, $posPenyusutan['dampak_kas']);
        $this->assertEquals(40_000.0, $berjalan['penyesuaian_non_kas']['subtotal']);
        $this->assertSame(['706.01'], $posPenyusutan['detail_akun']->pluck('kode')->all());

        $posPiutang = $berjalan['modal_kerja']['items']->get('MK_PIUTANG');
        $this->assertEquals(100_000.0, $posPiutang['dampak_kas']);
        $this->assertSame(['112.99'], $posPiutang['detail_akun']->pluck('kode')->all());

        $this->assertEquals(300_000.0, $berjalan['kas_operasi']);
        $this->assertEquals(300_000.0, $berjalan['kas_akhir_riil']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);
    }
}
