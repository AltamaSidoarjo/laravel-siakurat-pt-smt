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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanArusKasLangsungTest extends TestCase
{
    private User $authorizedUser;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        TipeCoa::query()->create(['nama' => 'Kasbank', 'status_aktif' => 1]);
        TipeCoa::query()->create(['nama' => 'Pendapatan', 'status_aktif' => 1]);
        TipeCoa::query()->create(['nama' => 'Aktiva tetap', 'status_aktif' => 1]);
        TipeCoa::query()->create(['nama' => 'Hutang', 'status_aktif' => 1]);

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

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipeCoa,
        ?string $aktivitas = null,
        ?string $kelompok = null,
    ): Coa {
        return Coa::query()->create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipeCoa,
            'arus_kas_aktivitas' => $aktivitas,
            'arus_kas_kelompok' => $kelompok,
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    public function test_arus_kas_page_and_load_data_enforce_authorization(): void
    {
        $this->get(route('laporan.keuangan.arus-kas'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->unauthorizedUser)
            ->get(route('laporan.keuangan.arus-kas'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('laporan.keuangan.arus-kas.load-data'))
            ->assertForbidden();

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('laporan.keuangan.arus-kas', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-28',
            ]));

        $response->assertOk()
            ->assertSee('Laporan Arus Kas')
            ->assertSee('datatable-arus-kas')
            ->assertSee(route('laporan.keuangan.arus-kas.load-data'), false);
    }

    public function test_arus_kas_view_defers_detail_rows_and_load_data_supports_pagination_search_and_sort(): void
    {
        $kas = $this->createCoa('100.01', 'Kas Utama', 'Kasbank');
        $pendapatan = $this->createCoa('400.01', 'Pendapatan Pasien', 'Pendapatan', 'operasi', 'Penerimaan pasien');
        $aset = $this->createCoa('120.01', 'Alat Medis', 'Aktiva tetap', 'investasi', 'Pembelian aset tetap');
        $unmapped = $this->createCoa('290.01', 'Utang Lain', 'Hutang');

        // 1. Transaksi dengan sumber_id
        foreach ([
            [$kas->id, 'D', 250000],
            [$pendapatan->id, 'K', 250000],
        ] as [$coaId, $mutasi, $nominal]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'sumber_id' => 1,
                'tanggal' => '2026-11-02',
                'nomer' => 'TRX-001',
                'sumber_transaksi' => 'Penerimaan Pendapatan',
                'nominal' => $nominal,
                'tipe_mutasi' => $mutasi,
                'keterangan' => 'Pembayaran rawat jalan',
            ]);
        }

        // 2. Transaksi tanpa sumber_id dan tanpa nomer (nullable nomer edge case)
        foreach ([
            [$kas->id, 'K', 75000],
            [$aset->id, 'D', 75000],
        ] as [$coaId, $mutasi, $nominal]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'sumber_id' => null,
                'tanggal' => '2026-11-05',
                'nomer' => null,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $mutasi,
                'keterangan' => 'Pembelian tensimeter',
            ]);
        }

        // 3. Transaksi belum dipetakan + residual
        BukuBesar::query()->create([
            'coa_id' => $kas->id,
            'sumber_id' => 3,
            'tanggal' => '2026-11-10',
            'nomer' => 'TRX-003',
            'sumber_transaksi' => 'Kasbank Penerimaan',
            'nominal' => 50000,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Setoran lain',
        ]);
        BukuBesar::query()->create([
            'coa_id' => $unmapped->id,
            'sumber_id' => 3,
            'tanggal' => '2026-11-10',
            'nomer' => 'TRX-003',
            'sumber_transaksi' => 'Kasbank Penerimaan',
            'nominal' => 30000,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Titipan',
        ]);

        // Halaman utama tidak me-render baris detail inline
        $pageResponse = $this->actingAs($this->authorizedUser)
            ->get(route('laporan.keuangan.arus-kas', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-28',
            ]));
        $pageResponse->assertOk()
            ->assertDontSee('Pembayaran rawat jalan')
            ->assertDontSee('Pembelian tensimeter');

        // AJAX load-data halaman 1 (length = 2)
        $ajaxPage1 = $this->actingAs($this->authorizedUser)
            ->getJson(route('laporan.keuangan.arus-kas.load-data', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-28',
                'draw' => 2,
                'start' => 0,
                'length' => 2,
            ]));

        $ajaxPage1->assertOk()
            ->assertJson([
                'draw' => 2,
                'recordsTotal' => 4,
                'recordsFiltered' => 4,
            ])
            ->assertJsonCount(2, 'data');

        $firstRow = $ajaxPage1->json('data.0');
        $this->assertSame('2026-11-02', $firstRow['tanggal']);
        $this->assertSame('TRX-001', $firstRow['nomer']);
        $this->assertSame('Kas Utama', $firstRow['akun_kas']);
        $this->assertSame('400.01 Pendapatan Pasien', $firstRow['akun_lawan']);
        $this->assertSame('Pembayaran rawat jalan', $firstRow['keterangan']);
        $this->assertSame('250.000', $firstRow['nilai']);

        // AJAX search + sort desc by nilai
        $ajaxSearch = $this->actingAs($this->authorizedUser)
            ->getJson(route('laporan.keuangan.arus-kas.load-data', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-28',
                'draw' => 3,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'tensimeter'],
            ]));

        $ajaxSearch->assertOk()
            ->assertJson([
                'draw' => 3,
                'recordsTotal' => 4,
                'recordsFiltered' => 1,
            ])
            ->assertJsonCount(1, 'data');
        $this->assertSame('(75.000)', $ajaxSearch->json('data.0.nilai'));
        $this->assertSame('investasi', $ajaxSearch->json('data.0.aktivitas'));
    }

    public function test_summary_without_detail_matches_full_result_and_handles_large_volume_bulk_path(): void
    {
        $kas = $this->createCoa('100.01', 'Kas', 'Kasbank');
        $pendapatan = $this->createCoa('400.01', 'Pendapatan', 'Pendapatan', 'operasi', 'Penerimaan pasien');
        $piutang = $this->createCoa('110.01', 'Piutang', 'Akun Piutang');

        $now = now()->toDateTimeString();
        $rows = [];

        // 600 transaksi kas Jurnal Umum (melewati threshold batch 500)
        for ($i = 1; $i <= 600; $i++) {
            $rows[] = [
                'coa_id' => $kas->id,
                'sumber_id' => $i,
                'tanggal' => '2026-11-15',
                'nomer' => 'KM-'.$i,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => 1000,
                'tipe_mutasi' => 'D',
                'keterangan' => 'Tunai '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'coa_id' => $pendapatan->id,
                'sumber_id' => $i,
                'tanggal' => '2026-11-15',
                'nomer' => 'KM-'.$i,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => 1000,
                'tipe_mutasi' => 'K',
                'keterangan' => 'Tunai '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 200 transaksi akrual non-kas pada sumber_transaksi yang sama (Jurnal Umum) + Invoice Pendapatan (harus diabaikan)
        for ($i = 1001; $i <= 1200; $i++) {
            $rows[] = [
                'coa_id' => $piutang->id,
                'sumber_id' => $i,
                'tanggal' => '2026-11-15',
                'nomer' => 'JU-AKR-'.$i,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => 5000,
                'tipe_mutasi' => 'D',
                'keterangan' => 'Piutang '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'coa_id' => $pendapatan->id,
                'sumber_id' => $i,
                'tanggal' => '2026-11-15',
                'nomer' => 'JU-AKR-'.$i,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => 5000,
                'tipe_mutasi' => 'K',
                'keterangan' => 'Piutang '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('bukubesar')->insert($chunk);
        }

        $service = app(LaporanKeuanganService::class);
        $withDetail = $service->getArusKas('2026-11-01', '2026-11-28', includeDetail: true);
        $withoutDetail = $service->getArusKas('2026-11-01', '2026-11-28', includeDetail: false);

        $this->assertSame(600_000.0, $withDetail['berjalan']['bagian']['operasi']['subtotal']);
        $this->assertSame(600_000.0, $withoutDetail['berjalan']['bagian']['operasi']['subtotal']);
        $this->assertSame($withDetail['berjalan']['kenaikan_penurunan'], $withoutDetail['berjalan']['kenaikan_penurunan']);
        $this->assertSame($withDetail['berjalan']['kas_akhir'], $withoutDetail['berjalan']['kas_akhir']);
        $this->assertCount(600, $withDetail['berjalan']['detail']);
        $this->assertCount(0, $withoutDetail['berjalan']['detail']);
    }
}
