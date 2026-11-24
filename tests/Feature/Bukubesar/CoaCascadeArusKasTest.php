<?php

namespace Tests\Feature\Bukubesar;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\LogAktifitas;
use App\Models\ReferensiKelompokArusKas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TipeCoa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaCascadeArusKasTest extends TestCase
{
    private User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $accountingRole = Role::create(['nama' => 'Accounting', 'kode' => 'akunting', 'is_system' => false]);

        $module = AccessModule::create([
            'kode' => 'bukubesar.coa',
            'nama' => 'COA',
            'group_nama' => 'Bukubesar',
            'urutan' => 30,
        ]);

        RolePermission::create([
            'role_id' => $accountingRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->authorizedUser = User::create([
            'name' => 'Akuntan Cascade',
            'email' => 'akuntan.cascade@test.com',
            'password' => bcrypt('password'),
            'role_id' => $accountingRole->id,
        ]);

        TipeCoa::create(['nama' => 'Beban', 'status_aktif' => 1]);
        TipeCoa::create(['nama' => 'Kasbank', 'status_aktif' => 1]);
    }

    private function prepareSchema(): void
    {
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::dropIfExists('tipe_coa');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
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
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 20)->unique();
            $table->string('nama', 100)->unique();
            $table->string('tipe_coa', 50);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->integer('status_aktif')->default(1);
            $table->boolean('is_postable')->default(false);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });
    }

    public function test_three_level_hierarchy_cascade_with_overwrite_all_updates_all_levels(): void
    {
        $parent = $this->createCoa('5.1', 'Beban Operasional', 'Beban');
        $child = $this->createCoa('5.1.01', 'Beban Layanan', 'Beban', $parent->id, 'investasi', 'Kelompok Child Lama');
        $grandchildEmpty = $this->createCoa('5.1.01.01', 'Beban Medis', 'Beban', $child->id);
        $grandchildCustom = $this->createCoa('5.1.01.02', 'Beban Farmasi', 'Beban', $child->id, 'pendanaan', 'Kelompok Grandchild Lama');

        $this->actingAs($this->authorizedUser)
            ->getJson(route('bukubesar.coa.cascade-preview', $parent))
            ->assertOk()
            ->assertExactJson([
                'total_descendants' => 3,
                'empty_count' => 1,
                'existing_count' => 2,
                'existing_samples' => [
                    [
                        'kode' => '5.1.01',
                        'nama' => 'Beban Layanan',
                        'aktivitas' => 'investasi',
                        'kelompok' => 'Kelompok Child Lama',
                    ],
                    [
                        'kode' => '5.1.01.02',
                        'nama' => 'Beban Farmasi',
                        'aktivitas' => 'pendanaan',
                        'kelompok' => 'Kelompok Grandchild Lama',
                    ],
                ],
            ]);

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
                'kode' => '5.1',
                'nama' => 'Beban Operasional',
                'cascade_to_children' => 1,
                'cascade_mode' => 'overwrite_all',
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('bukubesar.coa.index'));

        foreach ([$parent, $child, $grandchildEmpty, $grandchildCustom] as $coa) {
            $this->assertDatabaseHas('coa', [
                'id' => $coa->id,
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
            ]);
        }
    }

    public function test_three_level_hierarchy_cascade_with_fill_empty_preserves_existing_mappings(): void
    {
        $parent = $this->createCoa('5.2', 'Beban Umum', 'Beban');
        $childEmpty = $this->createCoa('5.2.01', 'Beban Kantor', 'Beban', $parent->id);
        $childExisting = $this->createCoa('5.2.02', 'Beban Pemeliharaan', 'Beban', $parent->id, 'investasi', 'Pengadaan & Perbaikan Aset');

        $grandchildEmpty = $this->createCoa('5.2.01.01', 'Beban ATK', 'Beban', $childEmpty->id);
        $grandchildCustom = $this->createCoa('5.2.01.02', 'Beban Sewa Gedung', 'Beban', $childEmpty->id, 'pendanaan', 'Pembayaran Sewa Pembiayaan');
        $grandchildUnderExistingEmpty = $this->createCoa('5.2.02.01', 'Beban Servis Rutin', 'Beban', $childExisting->id, '', '');

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Umum',
                'kode' => '5.2',
                'nama' => 'Beban Umum',
                'cascade_to_children' => 1,
                'cascade_mode' => 'fill_empty',
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('bukubesar.coa.index'));

        foreach ([$parent, $childEmpty, $grandchildEmpty, $grandchildUnderExistingEmpty] as $coa) {
            $this->assertDatabaseHas('coa', [
                'id' => $coa->id,
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Umum',
            ]);
        }

        $this->assertDatabaseHas('coa', [
            'id' => $childExisting->id,
            'arus_kas_aktivitas' => 'investasi',
            'arus_kas_kelompok' => 'Pengadaan & Perbaikan Aset',
        ]);

        $this->assertDatabaseHas('coa', [
            'id' => $grandchildCustom->id,
            'arus_kas_aktivitas' => 'pendanaan',
            'arus_kas_kelompok' => 'Pembayaran Sewa Pembiayaan',
        ]);
    }

    public function test_kasbank_grandchild_remains_null_and_untouched_when_cascade_runs(): void
    {
        $parent = $this->createCoa('1.1', 'Aset Lancar', 'Beban');
        $child = $this->createCoa('1.1.01', 'Sub Aset Lancar', 'Beban', $parent->id);
        $grandchildNonKasbank = $this->createCoa('1.1.01.01', 'Piutang Usaha', 'Beban', $child->id);
        $grandchildKasbank = $this->createCoa('1.1.01.02', 'Kas Kecil Unit', 'Kasbank', $child->id);

        $this->actingAs($this->authorizedUser)
            ->getJson(route('bukubesar.coa.cascade-preview', $parent))
            ->assertOk()
            ->assertExactJson([
                'total_descendants' => 2,
                'empty_count' => 2,
                'existing_count' => 0,
                'existing_samples' => [],
            ]);

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Penerimaan & Pengeluaran Operasional',
                'kode' => '1.1',
                'nama' => 'Aset Lancar',
                'cascade_to_children' => 1,
                'cascade_mode' => 'overwrite_all',
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('bukubesar.coa.index'));

        $this->assertDatabaseHas('coa', [
            'id' => $grandchildNonKasbank->id,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan & Pengeluaran Operasional',
        ]);

        $this->assertDatabaseHas('coa', [
            'id' => $grandchildKasbank->id,
            'tipe_coa' => 'Kasbank',
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);
    }

    public function test_activity_log_records_single_cascade_arus_kas_entry_with_accurate_affected_count(): void
    {
        $parent = $this->createCoa('5.3', 'Beban Pemasaran', 'Beban');
        $child = $this->createCoa('5.3.01', 'Beban Promosi', 'Beban', $parent->id);
        $this->createCoa('5.3.01.01', 'Promosi Digital', 'Beban', $child->id);
        $this->createCoa('5.3.01.02', 'Promosi Cetak', 'Beban', $child->id, 'investasi', 'Branding Aset');
        $this->createCoa('5.3.01.99', 'Kas Promosi', 'Kasbank', $child->id);

        $this->actingAs($this->authorizedUser)
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Pemasaran',
                'kode' => '5.3',
                'nama' => 'Beban Pemasaran',
                'cascade_to_children' => 1,
                'cascade_mode' => 'fill_empty',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bukubesar.coa.index'));

        $cascadeLogs = LogAktifitas::query()
            ->where('modul', 'COA')
            ->where('tipe', 'cascade_arus_kas')
            ->get();

        $this->assertCount(1, $cascadeLogs);

        $log = $cascadeLogs->first();
        $this->assertSame('Akuntan Cascade', $log->nama_user);

        $payload = json_decode((string) $log->payload, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('new', $payload);
        $this->assertSame($parent->id, $payload['new']['parent_id']);
        $this->assertSame('5.3', $payload['new']['parent_kode']);
        $this->assertSame('fill_empty', $payload['new']['mode']);
        $this->assertSame('operasi', $payload['new']['aktivitas']);
        $this->assertSame('Pembayaran Beban Pemasaran', $payload['new']['kelompok']);
        // 1 child + 1 empty grandchild (excludes 1 existing grandchild & 1 Kasbank grandchild)
        $this->assertSame(2, $payload['new']['affected_count']);
    }

    public function test_creating_child_coa_with_parent_cashflow_attributes_persists_them_on_save(): void
    {
        $parent = $this->createCoa(
            '5.4',
            'Beban SDM',
            'Beban',
            null,
            'operasi',
            'Pembayaran Beban Pegawai'
        );

        $createResponse = $this->actingAs($this->authorizedUser)
            ->get(route('bukubesar.coa.create'));

        $createResponse->assertOk()
            ->assertSee('data-aktivitas="operasi"', false)
            ->assertSee('data-kelompok="Pembayaran Beban Pegawai"', false);

        $storeResponse = $this->actingAs($this->authorizedUser)
            ->post(route('bukubesar.coa.store'), [
                'status_aktif' => 1,
                'parent_id' => $parent->id,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Pegawai',
                'kode' => '5.4.01',
                'nama' => 'Beban Gaji Tetap',
                'deskripsi' => 'Sub-akun mewarisi arus kas parent saat dibuat',
            ]);

        $storeResponse->assertSessionHasNoErrors()
            ->assertRedirect(route('bukubesar.coa.index'));

        $this->assertDatabaseHas('coa', [
            'parent_coa' => $parent->id,
            'kode' => '5.4.01',
            'nama' => 'Beban Gaji Tetap',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Pegawai',
            'is_postable' => 0,
        ]);

        $this->assertTrue(
            ReferensiKelompokArusKas::query()
                ->where('aktivitas', 'operasi')
                ->where('nama', 'Pembayaran Beban Pegawai')
                ->exists()
        );
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipeCoa,
        ?int $parentId = null,
        ?string $aktivitas = null,
        ?string $kelompok = null,
    ): Coa {
        return Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parentId,
            'tipe_coa' => $tipeCoa,
            'kode' => $kode,
            'nama' => $nama,
            'arus_kas_aktivitas' => $aktivitas,
            'arus_kas_kelompok' => $kelompok,
        ]);
    }
}
