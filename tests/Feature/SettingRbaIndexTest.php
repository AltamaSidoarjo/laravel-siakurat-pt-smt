<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingRba;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingRbaIndexTest extends TestCase
{
    private User $adminUser;
    private User $viewerUser;
    private Coa $coaKas;
    private Coa $coaPendapatan;
    private Coa $coaBeban;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('setting_rba_rinci');
        Schema::dropIfExists('setting_rba');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode');
            $table->string('nama');
            $table->string('group_nama');
            $table->integer('urutan')->default(0);
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

        Schema::create('setting_rba', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->integer('tahun');
            $table->decimal('total_nominal', 15, 2)->default(0);
            $table->string('catatan')->nullable();
            $table->boolean('is_rinci')->default(false);
            $table->timestamps();
        });

        $adminRole = Role::query()->create([
            'nama' => 'Admin',
            'kode' => 'admin',
        ]);

        $viewerRole = Role::query()->create([
            'nama' => 'Viewer',
            'kode' => 'viewer',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'pengaturan.setting-rba',
            'nama' => 'Setting RBA',
            'group_nama' => 'Pengaturan',
            'urutan' => 170,
        ]);

        RolePermission::query()->create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        RolePermission::query()->create([
            'role_id' => $viewerRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $this->adminUser = User::query()->create([
            'name' => 'Admin RBA',
            'email' => 'admin.rba@example.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->viewerUser = User::query()->create([
            'name' => 'Viewer RBA',
            'email' => 'viewer.rba@example.com',
            'password' => bcrypt('password'),
            'role_id' => $viewerRole->id,
        ]);

        $this->coaPendapatan = Coa::query()->create([
            'kode' => '4101',
            'nama' => 'Pendapatan Rawat Inap',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaBeban = Coa::query()->create([
            'kode' => '5101',
            'nama' => 'Beban Gaji Pegawai',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaKas = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Operasional',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        // Record 1: 4101, 2024, 50.000.000
        SettingRba::query()->create([
            'coa_id' => $this->coaPendapatan->id,
            'tahun' => 2024,
            'total_nominal' => 50000000,
            'catatan' => 'Catatan Pendapatan',
            'is_rinci' => false,
        ]);

        // Record 2: 5101, 2025, 10.000.000
        SettingRba::query()->create([
            'coa_id' => $this->coaBeban->id,
            'tahun' => 2025,
            'total_nominal' => 10000000,
            'catatan' => 'Catatan Operasional',
            'is_rinci' => false,
        ]);

        // Record 3: 1101, 2026, 30.000.000
        SettingRba::query()->create([
            'coa_id' => $this->coaKas->id,
            'tahun' => 2026,
            'total_nominal' => 30000000,
            'catatan' => 'Catatan Kas',
            'is_rinci' => false,
        ]);
    }

    public function test_index_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.index'));

        $response->assertStatus(200);
        $response->assertSee('Setting RBA');
        $response->assertSee(route('pengaturan.setting-rba.create'));
    }

    public function test_index_page_hides_create_button_without_permission(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->get(route('pengaturan.setting-rba.index'));

        $response->assertStatus(200);
        $response->assertDontSee(route('pengaturan.setting-rba.create'));
    }

    public function test_load_data_returns_expected_payload(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data'));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('coa_display', $data[0]);
        $this->assertArrayHasKey('nominal_display', $data[0]);
        $this->assertArrayHasKey('destroy_url', $data[0]);
        $this->assertArrayHasKey('can_delete', $data[0]);
        $this->assertTrue($data[0]['can_delete']);
    }

    public function test_load_data_can_delete_false_for_viewer(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->get(route('pengaturan.setting-rba.load-data'));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertFalse($data[0]['can_delete']);
    }

    public function test_load_data_default_sorting_by_tahun_desc_and_id_desc(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data'));

        $response->assertStatus(200);
        $data = $response->json('data');
        $years = array_column($data, 'tahun');
        $this->assertSame([2026, 2025, 2024], $years);
    }

    public function test_load_data_sort_by_tahun_asc(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data', [
                'columns' => [
                    0 => ['data' => 'coa_display', 'name' => 'coa_display', 'searchable' => 'true', 'orderable' => 'true'],
                    1 => ['data' => 'tahun', 'name' => 'tahun', 'searchable' => 'true', 'orderable' => 'true'],
                    2 => ['data' => 'nominal_display', 'name' => 'total_nominal', 'searchable' => 'true', 'orderable' => 'true'],
                    3 => ['data' => 'catatan', 'name' => 'catatan', 'searchable' => 'true', 'orderable' => 'false'],
                    4 => ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'order' => [
                    0 => ['column' => 1, 'dir' => 'asc'],
                ],
            ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $years = array_column($data, 'tahun');
        $this->assertSame([2024, 2025, 2026], $years, 'Sorting by tahun ASC failed');
    }

    public function test_load_data_sort_by_coa_asc(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data', [
                'columns' => [
                    0 => ['data' => 'coa_display', 'name' => 'coa_display', 'searchable' => 'true', 'orderable' => 'true'],
                    1 => ['data' => 'tahun', 'name' => 'tahun', 'searchable' => 'true', 'orderable' => 'true'],
                    2 => ['data' => 'nominal_display', 'name' => 'total_nominal', 'searchable' => 'true', 'orderable' => 'true'],
                    3 => ['data' => 'catatan', 'name' => 'catatan', 'searchable' => 'true', 'orderable' => 'false'],
                    4 => ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'order' => [
                    0 => ['column' => 0, 'dir' => 'asc'],
                ],
            ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $coaDisplays = array_column($data, 'coa_display');
        $this->assertSame([
            '1101 - Kas Operasional',
            '4101 - Pendapatan Rawat Inap',
            '5101 - Beban Gaji Pegawai',
        ], $coaDisplays, 'Sorting by COA ASC failed');
    }

    public function test_load_data_sort_by_nominal_desc(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data', [
                'columns' => [
                    0 => ['data' => 'coa_display', 'name' => 'coa_display', 'searchable' => 'true', 'orderable' => 'true'],
                    1 => ['data' => 'tahun', 'name' => 'tahun', 'searchable' => 'true', 'orderable' => 'true'],
                    2 => ['data' => 'nominal_display', 'name' => 'total_nominal', 'searchable' => 'true', 'orderable' => 'true'],
                    3 => ['data' => 'catatan', 'name' => 'catatan', 'searchable' => 'true', 'orderable' => 'false'],
                    4 => ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'order' => [
                    0 => ['column' => 2, 'dir' => 'desc'],
                ],
            ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $nominals = array_column($data, 'total_nominal');
        $this->assertEquals(['50000000.00', '30000000.00', '10000000.00'], $nominals, 'Sorting by nominal DESC failed');
    }

    public function test_load_data_searching_coa_nama(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data', [
                'columns' => [
                    0 => ['data' => 'coa_display', 'name' => 'coa_display', 'searchable' => 'true', 'orderable' => 'true'],
                    1 => ['data' => 'tahun', 'name' => 'tahun', 'searchable' => 'true', 'orderable' => 'true'],
                    2 => ['data' => 'nominal_display', 'name' => 'total_nominal', 'searchable' => 'true', 'orderable' => 'true'],
                    3 => ['data' => 'catatan', 'name' => 'catatan', 'searchable' => 'true', 'orderable' => 'false'],
                    4 => ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'search' => [
                    'value' => 'Gaji',
                ],
            ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data, 'Search by COA name should find 1 record');
        $this->assertSame('5101 - Beban Gaji Pegawai', $data[0]['coa_display']);
    }

    public function test_load_data_searching_coa_kode(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('pengaturan.setting-rba.load-data', [
                'columns' => [
                    0 => ['data' => 'coa_display', 'name' => 'coa_display', 'searchable' => 'true', 'orderable' => 'true'],
                    1 => ['data' => 'tahun', 'name' => 'tahun', 'searchable' => 'true', 'orderable' => 'true'],
                    2 => ['data' => 'nominal_display', 'name' => 'total_nominal', 'searchable' => 'true', 'orderable' => 'true'],
                    3 => ['data' => 'catatan', 'name' => 'catatan', 'searchable' => 'true', 'orderable' => 'false'],
                    4 => ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'search' => [
                    'value' => '1101',
                ],
            ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data, 'Search by COA kode should find 1 record');
        $this->assertSame('1101 - Kas Operasional', $data[0]['coa_display']);
    }
}
