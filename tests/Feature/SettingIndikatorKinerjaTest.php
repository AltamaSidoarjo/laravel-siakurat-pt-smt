<?php

namespace Tests\Feature;

use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Models\AccessModule;
use App\Models\IndikatorKinerjaManualData;
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

    public function test_user_can_filter_indicators_by_search_query(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        // Search by name
        $responseSearch = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', ['q' => 'EBITDA']));
        $responseSearch->assertOk()
            ->assertSee('EBITDA');

        // Search by code
        $responseKode = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', ['q' => 'IK-01']));
        $responseKode->assertOk();
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

    public function test_unauthorized_user_cannot_update_variabel_manual(): void
    {
        $viewerRole = Role::create(['kode' => 'viewer_only', 'nama' => 'Viewer Only']);
        $user = User::factory()->create();
        $user->role_id = $viewerRole->id;
        $user->saveQuietly();

        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_UNAUTH',
            'nama' => 'Var Unauth',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        $response = $this->actingAs($user)->put(
            route('pengaturan.setting-indikator-kinerja.update-variabel', $variabel->id),
            [
                'kode' => 'VAR_UNAUTH_EDITED',
                'nama' => 'Edited Name',
                'satuan' => SatuanIndikator::ORANG->value,
            ]
        );

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_delete_variabel_manual(): void
    {
        $viewerRole = Role::create(['kode' => 'viewer_only_del', 'nama' => 'Viewer Only']);
        $user = User::factory()->create();
        $user->role_id = $viewerRole->id;
        $user->saveQuietly();

        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_UNAUTH_DEL',
            'nama' => 'Var Unauth Del',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        $response = $this->actingAs($user)->delete(
            route('pengaturan.setting-indikator-kinerja.destroy-variabel', $variabel->id)
        );

        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_variabel_manual(): void
    {
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_ORIGINAL',
            'nama' => 'Original Name',
            'satuan' => SatuanIndikator::PASIEN,
            'deskripsi' => 'Original Desc',
        ]);

        $response = $this->actingAs($user)->put(
            route('pengaturan.setting-indikator-kinerja.update-variabel', $variabel->id),
            [
                'kode' => 'VAR_UPDATED',
                'nama' => 'Updated Name',
                'satuan' => SatuanIndikator::ORANG->value,
                'deskripsi' => 'Updated Desc',
                'tahun' => 2026,
            ]
        );

        $response->assertRedirect(route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => 2026]));
        $this->assertDatabaseHas('indikator_kinerja_variabel_manual', [
            'id' => $variabel->id,
            'kode' => 'VAR_UPDATED',
            'nama' => 'Updated Name',
            'satuan' => SatuanIndikator::ORANG->value,
            'deskripsi' => 'Updated Desc',
        ]);
    }

    public function test_update_variabel_manual_validates_unique_code_ignoring_self(): void
    {
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_SELF',
            'nama' => 'Self Name',
            'satuan' => SatuanIndikator::PASIEN,
        ]);

        $response = $this->actingAs($user)->put(
            route('pengaturan.setting-indikator-kinerja.update-variabel', $variabel->id),
            [
                'kode' => 'VAR_SELF',
                'nama' => 'Updated Self Name',
                'satuan' => SatuanIndikator::PASIEN->value,
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('indikator_kinerja_variabel_manual', [
            'id' => $variabel->id,
            'kode' => 'VAR_SELF',
            'nama' => 'Updated Self Name',
        ]);
    }

    public function test_update_variabel_manual_validation_fails_on_duplicate_code_of_other_variable(): void
    {
        $user = User::factory()->create();
        $varA = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_A',
            'nama' => 'Var A',
            'satuan' => SatuanIndikator::PASIEN,
        ]);
        $varB = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_B',
            'nama' => 'Var B',
            'satuan' => SatuanIndikator::PASIEN,
        ]);

        $response = $this->actingAs($user)->put(
            route('pengaturan.setting-indikator-kinerja.update-variabel', $varB->id),
            [
                'kode' => 'VAR_A',
                'nama' => 'Var B Edited',
                'satuan' => SatuanIndikator::PASIEN->value,
            ]
        );

        $response->assertSessionHasErrors(['kode']);
    }

    public function test_update_variabel_manual_validation_fails_on_invalid_satuan(): void
    {
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_SATUAN',
            'nama' => 'Var Satuan',
            'satuan' => SatuanIndikator::PASIEN,
        ]);

        $response = $this->actingAs($user)->put(
            route('pengaturan.setting-indikator-kinerja.update-variabel', $variabel->id),
            [
                'kode' => 'VAR_SATUAN',
                'nama' => 'Var Satuan',
                'satuan' => 'INVALID_SATUAN_XYZ',
            ]
        );

        $response->assertSessionHasErrors(['satuan']);
    }

    public function test_cannot_delete_variabel_manual_used_by_indikator_kinerja(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        // Cari variabel yang digunakan oleh indikator
        $variabel = IndikatorKinerjaVariabelManual::whereHas('indikatorKinerja')->firstOrFail();

        $response = $this->actingAs($user)->delete(
            route('pengaturan.setting-indikator-kinerja.destroy-variabel', $variabel->id)
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('indikator_kinerja_variabel_manual', [
            'id' => $variabel->id,
        ]);
    }

    public function test_can_delete_unused_variabel_manual_and_its_monthly_data(): void
    {
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'VAR_UNUSED',
            'nama' => 'Unused Variable',
            'satuan' => SatuanIndikator::PASIEN,
        ]);

        IndikatorKinerjaManualData::create([
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 1,
            'nilai' => 10.0,
        ]);

        $response = $this->actingAs($user)->delete(
            route('pengaturan.setting-indikator-kinerja.destroy-variabel', $variabel->id)
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('indikator_kinerja_variabel_manual', ['id' => $variabel->id]);
        $this->assertDatabaseMissing('indikator_kinerja_manual_data', ['variabel_id' => $variabel->id]);
    }

    public function test_tab_1_renders_segmented_rag_bar_and_formula_tokens(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('rag-bar-wrapper')
            ->assertSee('settingIndikatorBanner')
            ->assertSee('btnDismissBanner')
            ->assertSee('data-bs-toggle="popover"', false)
            ->assertSee('sticky-col-action-tab1')
            ->assertDontSee('polaSelect');
    }

    public function test_tab_2_renders_contained_viewport_action_buttons_and_edit_modal(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', [
            'tab' => 'manual',
            'tahun' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('matrixScrollContainer')
            ->assertSee('matrixResizeHandle')
            ->assertSee('modalEditVariabel')
            ->assertSee('data-col-idx="1"', false)
            ->assertSee('sticky-col-action')
            ->assertSee('btn-edit-variabel')
            ->assertSee('btn-delete-variabel');
    }

    public function test_tab_2_matrix_input_navigation_prevents_accidental_form_submission_and_increments(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.index', [
            'tab' => 'manual',
            'tahun' => 2026,
        ]));

        $response->assertOk()
            ->assertSee("['ArrowUp', 'ArrowDown', 'Enter'].includes(e.key)", false);
    }

    public function test_panduan_glosarium_modal_partial_renders_all_tabs_and_case_studies(): void
    {
        $view = $this->view('pengaturan.setting-indikator-kinerja.partials.modal-panduan');

        $view->assertSee('modalPanduanSetting')
            ->assertSee('Panduan Konfigurasi Indikator Kinerja & Glosarium COA')
            ->assertSee('Logika Formula & Input')
            ->assertSee('Glosarium COA & 4 Kasus RS')
            ->assertSee('Target Ambang Batas (RAG)')
            ->assertSee('RASIO')
            ->assertSee('TURNOVER')
            ->assertSee('MANUAL')
            ->assertSee('Numerator')
            ->assertSee('Denominator')
            ->assertSee('Addition')
            ->assertSee('Deduction')
            ->assertSee('Exclusion')
            ->assertSee('Beban Pegawai')
            ->assertSee('EBITDA')
            ->assertSee('Perputaran Persediaan')
            ->assertSee('Produktivitas SDM')
            ->assertSee('Higher is Better')
            ->assertSee('Lower is Better')
            ->assertSee('Target Band');
    }
}
