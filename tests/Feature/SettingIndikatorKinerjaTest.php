<?php

namespace Tests\Feature;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Models\AccessModule;
use App\Models\IndikatorKinerjaVariabelManual;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingIndikatorKinerjaTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private AccessModule $module;

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => [
                'database/migrations/2026_11_13_000001_create_indikator_kinerja_variabel_manual_table.php',
                'database/migrations/2026_11_13_000002_create_indikator_kinerja_table.php',
                'database/migrations/2026_11_13_000003_create_indikator_kinerja_mapping_coa_table.php',
                'database/migrations/2026_11_13_000004_create_indikator_kinerja_manual_data_table.php',
            ],
            '--realpath' => false,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nama');
                $table->string('kode')->unique();
                $table->text('deskripsi')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('access_modules')) {
            Schema::create('access_modules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('kode')->unique();
                $table->string('nama');
                $table->string('group_nama');
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
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
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('nama_lengkap')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->unsignedInteger('role_id')->nullable();
                $table->string('profile_picture')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coa')) {
            Schema::create('coa', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('kode');
                $table->string('nama');
                $table->unsignedInteger('parent_coa')->nullable();
                $table->string('tipe_coa')->nullable();
                $table->integer('status_aktif')->default(1);
                $table->boolean('is_postable')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bukubesar')) {
            Schema::create('bukubesar', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('coa_id');
                $table->unsignedInteger('sumber_id')->nullable();
                $table->date('tanggal');
                $table->integer('periode_tahun')->nullable();
                $table->integer('periode_bulan')->nullable();
                $table->string('nomer')->nullable();
                $table->string('sumber_transaksi')->nullable();
                $table->decimal('nominal', 15, 2)->default(0);
                $table->string('tipe_mutasi', 2);
                $table->string('keterangan')->nullable();
                $table->timestamps();
            });
        }

        $this->adminRole = Role::firstOrCreate(
            ['kode' => 'admin_test'],
            ['nama' => 'Admin Test', 'deskripsi' => 'Admin Test']
        );

        $this->module = AccessModule::firstOrCreate(
            ['kode' => 'pengaturan.setting-indikator-kinerja'],
            [
                'nama' => 'Setting Indikator Kinerja',
                'group_nama' => 'Pengaturan',
                'urutan' => 175,
            ]
        );

        RolePermission::updateOrCreate(
            ['role_id' => $this->adminRole->id, 'access_module_id' => $this->module->id],
            [
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ]
        );

        $roleId = $this->adminRole->id;
        User::created(function (User $user) use ($roleId): void {
            if ($user->role_id === null) {
                $user->role_id = $roleId;
                $user->saveQuietly();
            }
        });
    }

    public function test_user_can_view_setting_index_with_tabs(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('Total Indikator')
            ->assertSee('Indikator Aktif')
            ->assertSee('Indikator Nonaktif')
            ->assertSee('Variabel Manual')
            ->assertSee('Manajemen Indikator')
            ->assertSee('Entri Data Manual')
            ->assertSee('Turnover (Hari)')
            ->assertSee('Manual (Non-Akuntansi)')
            ->assertSee('Rasio')
            ->assertDontSee('Bridging SIMRS Otomatis');
    }

    public function test_user_can_filter_indicators_by_search_query_and_pola_evaluasi(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        // Search by name
        $responseSearch = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', ['q' => 'EBITDA']));
        $responseSearch->assertOk()
            ->assertSee('EBITDA');

        // Filter by pola evaluasi HIGHER_IS_BETTER
        $responsePola = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', ['pola' => 'HIGHER_IS_BETTER']));
        $responsePola->assertOk();
    }

    public function test_user_can_view_manual_tab_with_year_filter(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', [
            'tab' => 'manual',
            'tahun' => 2026,
        ]));
        $response->assertOk()
            ->assertSee('Entri Data Manual')
            ->assertSee('2026')
            ->assertSee('Simpan Data Bulanan');
    }

    public function test_user_can_save_manual_matrix_data(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::first();

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.simpan-manual-data'), [
            'tahun' => 2026,
            'data' => [
                $variabel->id => [
                    1 => 92.5,
                    2 => 94.0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerja_manual_data', [
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 1,
            'nilai' => 92.50,
        ]);
        $this->assertDatabaseHas('indikator_kinerja_manual_data', [
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 2,
            'nilai' => 94.00,
        ]);
    }

    public function test_user_can_store_new_manual_variable(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store-variabel'), [
            'kode' => 'VAR_TEST_BARU',
            'nama' => 'Variabel Uji Coba Baru',
            'satuan' => SatuanIndikator::PASIEN->value,
            'deskripsi' => 'Deskripsi variabel uji coba',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerja_variabel_manual', [
            'kode' => 'VAR_TEST_BARU',
            'nama' => 'Variabel Uji Coba Baru',
            'satuan' => SatuanIndikator::PASIEN->value,
        ]);
    }

    public function test_store_manual_variable_validation_fails_on_duplicate_code(): void
    {
        $user = User::factory()->create();

        IndikatorKinerjaVariabelManual::create([
            'kode' => 'EXISTING_VAR',
            'nama' => 'Existing Variable',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store-variabel'), [
            'kode' => 'EXISTING_VAR',
            'nama' => 'Duplicate Code Variable',
            'satuan' => SatuanIndikator::ORANG->value,
        ]);

        $response->assertSessionHasErrors(['kode']);
    }

    public function test_store_manual_variable_validation_fails_when_nama_exceeds_100_chars(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store-variabel'), [
            'kode' => 'VAR_LONG_NAME',
            'nama' => str_repeat('A', 101),
            'satuan' => SatuanIndikator::ORANG->value,
        ]);

        $response->assertSessionHasErrors(['nama']);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->get(route('pengaturan.setting-indikator-kinerja.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);

        $viewerRole = Role::create([
            'kode' => 'viewer_only',
            'nama' => 'Viewer Only',
        ]);

        $user = User::factory()->create();
        $user->role_id = $viewerRole->id;
        $user->saveQuietly();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index'));
        $response->assertForbidden();
    }
}
