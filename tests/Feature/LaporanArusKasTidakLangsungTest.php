<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TipeCoa;
use App\Models\User;
use App\Services\Laporan\LaporanKeuanganService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanArusKasTidakLangsungTest extends TestCase
{
    private User $authorizedUser;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        $this->seedTipeCoa();

        $roleWithAccess = Role::query()->create([
            'nama' => 'Akuntan',
            'kode' => 'akuntan',
        ]);

        $roleWithoutAccess = Role::query()->create([
            'nama' => 'Staf Biasa',
            'kode' => 'staf_biasa',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'laporan.keuangan',
            'nama' => 'Laporan Keuangan',
            'group_nama' => 'Laporan',
        ]);

        RolePermission::query()->create([
            'role_id' => $roleWithAccess->id,
            'access_module_id' => $module->id,
            'can_view' => true,
        ]);

        $this->authorizedUser = User::query()->create([
            'name' => 'User Akuntan',
            'email' => 'akuntan@example.com',
            'password' => bcrypt('password'),
            'role_id' => $roleWithAccess->id,
        ]);

        $this->unauthorizedUser = User::query()->create([
            'name' => 'User Biasa',
            'email' => 'biasa@example.com',
            'password' => bcrypt('password'),
            'role_id' => $roleWithoutAccess->id,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('setting_rba');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('tipe_coa');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('preferensi_perusahaan');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
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

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
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

        Schema::create('setting_rba', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('total_nominal', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
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

    private function createCoa(string $kode, string $nama, string $tipeCoa): Coa
    {
        return Coa::query()->create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipeCoa,
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    public function test_akses_arus_kas_tidak_langsung_memerlukan_otentikasi_dan_permission(): void
    {
        // 1. Guest diarahkan ke login
        $this->get(route('laporan.keuangan.arus-kas-tidak-langsung'))
            ->assertRedirect(route('login'));

        // 2. User tanpa permission ditolak 403
        $this->actingAs($this->unauthorizedUser)
            ->get(route('laporan.keuangan.arus-kas-tidak-langsung'))
            ->assertForbidden();

        // 3. User dengan permission berhasil 200 OK
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('laporan.keuangan.arus-kas-tidak-langsung'));

        $response->assertOk()
            ->assertSee('Arus Kas (Metode Tidak Langsung)')
            ->assertSee('Laba (Rugi) Bersih Periode Berjalan')
            ->assertSee('Metode Langsung');
    }

    public function test_kalkulasi_arus_kas_tidak_langsung_laba_rugi_dan_modal_kerja(): void
    {
        $kas = $this->createCoa('1101', 'Kas Operasional', 'Kasbank');
        $piutang = $this->createCoa('1201', 'Piutang Pasien', 'Piutang Usaha');
        $persediaan = $this->createCoa('1301', 'Persediaan Obat', 'Persediaan');
        $utang = $this->createCoa('2101', 'Utang Pemasok', 'Utang Usaha');
        $pendapatan = $this->createCoa('4101', 'Pendapatan Rawat Inap', 'Pendapatan');
        $hpp = $this->createCoa('5101', 'Beban Pokok Obat', 'Beban Pokok Penjualan');

        // Transaksi 1: Penjualan/Pelayanan total 1.000.000 (Kas 700.000, Piutang 300.000)
        BukuBesar::query()->create([
            'coa_id' => $kas->id,
            'tanggal' => '2026-07-05',
            'nomer' => 'TRX-01',
            'sumber_transaksi' => 'Pendapatan',
            'nominal' => 700_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $piutang->id,
            'tanggal' => '2026-07-05',
            'nomer' => 'TRX-01',
            'sumber_transaksi' => 'Pendapatan',
            'nominal' => 300_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $pendapatan->id,
            'tanggal' => '2026-07-05',
            'nomer' => 'TRX-01',
            'sumber_transaksi' => 'Pendapatan',
            'nominal' => 1_000_000,
            'tipe_mutasi' => 'K',
        ]);

        // Transaksi 2: Pemakaian Obat (HPP 400.000, Persediaan berkurang 400.000)
        BukuBesar::query()->create([
            'coa_id' => $hpp->id,
            'tanggal' => '2026-07-06',
            'nomer' => 'TRX-02',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 400_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $persediaan->id,
            'tanggal' => '2026-07-06',
            'nomer' => 'TRX-02',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 400_000,
            'tipe_mutasi' => 'K',
        ]);

        // Transaksi 3: Pembelian Persediaan Kredit (Persediaan bertambah 200.000, Utang bertambah 200.000)
        BukuBesar::query()->create([
            'coa_id' => $persediaan->id,
            'tanggal' => '2026-07-07',
            'nomer' => 'TRX-03',
            'sumber_transaksi' => 'Pembelian',
            'nominal' => 200_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $utang->id,
            'tanggal' => '2026-07-07',
            'nomer' => 'TRX-03',
            'sumber_transaksi' => 'Pembelian',
            'nominal' => 200_000,
            'tipe_mutasi' => 'K',
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $berjalan = $result['berjalan'];

        // Laba Bersih = 1.000.000 (Pendapatan) - 400.000 (HPP) = 600.000
        $this->assertEquals(600_000.0, $berjalan['laba_bersih']);

        // Perubahan Piutang: D 300.000 -> Dampak Kas: -300.000
        $this->assertEquals(-300_000.0, $berjalan['modal_kerja']['items']->get('piutang')['dampak_kas']);

        // Perubahan Persediaan: D 200.000, K 400.000 -> Net -200.000 -> Dampak Kas: +200.000
        $this->assertEquals(200_000.0, $berjalan['modal_kerja']['items']->get('persediaan')['dampak_kas']);

        // Perubahan Utang Usaha: K 200.000 -> Dampak Kas: +200.000
        $this->assertEquals(200_000.0, $berjalan['modal_kerja']['items']->get('utang_usaha')['dampak_kas']);

        // Total Modal Kerja = -300.000 + 200.000 + 200.000 = +100.000
        $this->assertEquals(100_000.0, $berjalan['modal_kerja']['subtotal']);

        // Kas Operasi = Laba Bersih 600.000 + Modal Kerja 100.000 = 700.000
        $this->assertEquals(700_000.0, $berjalan['kas_operasi']);

        // Kenaikan Kas Bersih = 700.000, Kas Akhir = 700.000, Selisih Rekonsiliasi = 0
        $this->assertEquals(700_000.0, $berjalan['kenaikan_penurunan']);
        $this->assertEquals(700_000.0, $berjalan['kas_akhir']);
        $this->assertEquals(700_000.0, $berjalan['kas_akhir_riil']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);
    }

    public function test_penyesuaian_non_kas_beban_penyusutan(): void
    {
        $kas = $this->createCoa('1101', 'Kas Operasional', 'Kasbank');
        $pendapatan = $this->createCoa('4101', 'Pendapatan', 'Pendapatan');
        $bebanPenyusutan = $this->createCoa('5301', 'Biaya Penyusutan Gedung', 'Beban');
        $akumulasiPenyusutan = $this->createCoa('1401', 'Akumulasi Penyusutan Gedung', 'Akumulasi Penyusutan');

        // Pendapatan tunai 500.000
        BukuBesar::query()->create([
            'coa_id' => $kas->id,
            'tanggal' => '2026-07-10',
            'nomer' => 'TRX-01',
            'sumber_transaksi' => 'Pendapatan',
            'nominal' => 500_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $pendapatan->id,
            'tanggal' => '2026-07-10',
            'nomer' => 'TRX-01',
            'sumber_transaksi' => 'Pendapatan',
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
        ]);

        // Jurnal Beban Penyusutan non-kas 100.000
        BukuBesar::query()->create([
            'coa_id' => $bebanPenyusutan->id,
            'tanggal' => '2026-07-15',
            'nomer' => 'JUR-DEP',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 100_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $akumulasiPenyusutan->id,
            'tanggal' => '2026-07-15',
            'nomer' => 'JUR-DEP',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 100_000,
            'tipe_mutasi' => 'K',
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $berjalan = $result['berjalan'];

        // Laba Bersih = 500.000 - 100.000 = 400.000
        $this->assertEquals(400_000.0, $berjalan['laba_bersih']);

        // Penyesuaian non-kas = +100.000 (beban penyusutan ditambahkan kembali)
        $this->assertEquals(100_000.0, $berjalan['penyesuaian_non_kas']['subtotal']);

        // Kas Operasi = Laba Bersih 400.000 + Non-Kas 100.000 = 500.000 (sama dengan kas riil)
        $this->assertEquals(500_000.0, $berjalan['kas_operasi']);
        $this->assertEquals(500_000.0, $berjalan['kas_akhir']);
        $this->assertEquals(500_000.0, $berjalan['kas_akhir_riil']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);
    }

    public function test_arus_kas_investasi_dan_pendanaan_tercatat_pada_metode_tidak_langsung(): void
    {
        $kas = $this->createCoa('1101', 'Kas Operasional', 'Kasbank');
        $asetTetap = Coa::query()->create([
            'kode' => '1501',
            'nama' => 'Peralatan Medis',
            'tipe_coa' => 'Aset Tetap',
            'arus_kas_aktivitas' => 'investasi',
            'arus_kas_kelompok' => 'Pembelian aset tetap',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
        $modal = Coa::query()->create([
            'kode' => '3101',
            'nama' => 'Modal Pemilik',
            'tipe_coa' => 'Ekuitas',
            'arus_kas_aktivitas' => 'pendanaan',
            'arus_kas_kelompok' => 'Setoran modal',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        // Pembelian aset tetap tunai 200.000 (Investasi keluar: -200.000)
        BukuBesar::query()->create([
            'coa_id' => $asetTetap->id,
            'tanggal' => '2026-07-12',
            'nomer' => 'INV-01',
            'sumber_transaksi' => 'Kas & Bank',
            'nominal' => 200_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $kas->id,
            'tanggal' => '2026-07-12',
            'nomer' => 'INV-01',
            'sumber_transaksi' => 'Kas & Bank',
            'nominal' => 200_000,
            'tipe_mutasi' => 'K',
        ]);

        // Setoran modal tunai 500.000 (Pendanaan masuk: +500.000)
        BukuBesar::query()->create([
            'coa_id' => $kas->id,
            'tanggal' => '2026-07-13',
            'nomer' => 'PEN-01',
            'sumber_transaksi' => 'Kas & Bank',
            'nominal' => 500_000,
            'tipe_mutasi' => 'D',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $modal->id,
            'tanggal' => '2026-07-13',
            'nomer' => 'PEN-01',
            'sumber_transaksi' => 'Kas & Bank',
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getArusKasTidakLangsung('2026-07-01', '2026-07-31');
        $berjalan = $result['berjalan'];

        $this->assertEquals(-200_000.0, $berjalan['investasi']['subtotal']);
        $this->assertEquals(500_000.0, $berjalan['pendanaan']['subtotal']);
        $this->assertEquals(300_000.0, $berjalan['kenaikan_penurunan']);
        $this->assertEquals(300_000.0, $berjalan['kas_akhir']);
        $this->assertEquals(300_000.0, $berjalan['kas_akhir_riil']);
        $this->assertEquals(0.0, $berjalan['selisih_rekonsiliasi']);
    }
}
