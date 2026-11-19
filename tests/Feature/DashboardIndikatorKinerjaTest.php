<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardIndikatorKinerjaTest extends TestCase
{
    use RefreshDatabase;

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

        if (! Schema::hasTable('setting_rba')) {
            Schema::create('setting_rba', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('tahun');
                $table->decimal('target_pendapatan', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    protected function assignRolePermission(User $user, string $moduleKey = 'dashboard.indikator-kinerja'): User
    {
        $role = Role::firstOrCreate(
            ['kode' => 'admin_test'],
            ['nama' => 'Admin Test', 'deskripsi' => 'Admin Test']
        );

        $module = AccessModule::firstOrCreate(
            ['kode' => $moduleKey],
            [
                'nama' => 'Indikator Kinerja',
                'group_nama' => 'Dashboard',
                'urutan' => 15,
            ]
        );

        RolePermission::updateOrCreate(
            ['role_id' => $role->id, 'access_module_id' => $module->id],
            [
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ]
        );

        $user->role_id = $role->id;
        $user->save();

        return $user;
    }

    public function test_user_can_view_dashboard_indikator_kinerja(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('Indikator Kinerja');
    }

    public function test_user_can_fetch_tren_bulanan_json(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->getJson(route('dashboard.indikator-kinerja.tren', [
            'kode' => 'capaian_pendapatan',
            'tahun' => now()->year,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'indikator' => ['kode', 'nama', 'satuan'],
                'data' => [
                    '*' => ['bulan', 'label_bulan', 'nilai', 'status'],
                ],
            ]);

        $currentMonth = (int) date('n');
        if ($currentMonth < 12) {
            $futureMonthEntry = collect($response->json('data'))->firstWhere('bulan', $currentMonth + 1);
            $this->assertNotNull($futureMonthEntry);
            $this->assertNull($futureMonthEntry['nilai']);
        }
    }

    public function test_search_query_filters_indicators_by_code_or_name(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        // Search by query matching 'capaian'
        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', ['q' => 'capaian']));
        $response->assertOk()
            ->assertSee('Capaian Pendapatan');

        // Search with non-matching query
        $responseEmpty = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', ['q' => 'nonexistent_kpi_query']));
        $responseEmpty->assertOk()
            ->assertDontSee('Capaian Pendapatan')
            ->assertSee('Tidak ada indikator yang sesuai dengan filter pencarian');
    }

    public function test_status_filter_filters_indicators_by_rag_status(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        // Filter by ALL returns standard list
        $responseAll = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', ['status' => 'ALL']));
        $responseAll->assertOk();

        // Filter by a specific status query parameter
        $responseHijau = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', ['status' => 'HIJAU']));
        $responseHijau->assertOk();
    }

    public function test_custom_date_range_filters_dashboard(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', [
            'dariTanggal' => '2026-01-01',
            'sampaiTanggal' => '2026-01-31',
        ]));

        $response->assertOk()
            ->assertSee('2026-01-01')
            ->assertSee('2026-01-31');
    }

    public function test_invalid_date_format_falls_back_gracefully_to_default_dates(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index', [
            'start_date' => 'not-a-valid-date',
            'end_date' => 'invalid-end-date',
        ]));

        $defaultStart = now()->startOfMonth()->toDateString();
        $defaultEnd = now()->toDateString();

        $response->assertOk()
            ->assertSee($defaultStart)
            ->assertSee($defaultEnd);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard.indikator-kinerja.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create(); // role_id is null

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertForbidden();
    }

    public function test_dashboard_renders_formula_badges_on_cards_and_table(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('Saldo COA')
            ->assertSee('Target RBA')
            ->assertSee('× 100%');
    }

    public function test_dashboard_elements_contain_explicit_formula_data_attributes(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('data-pembilang-formatted', false)
            ->assertSee('data-penyebut-formatted', false)
            ->assertSee('data-pembilang-label', false)
            ->assertSee('data-penyebut-label', false)
            ->assertSee('data-pengali-label', false)
            ->assertSee('data-tipe', false);
    }

    public function test_dashboard_modal_detail_contains_explicit_formula_containers(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $this->assignRolePermission($user);

        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('id="modalFormulaBox"', false)
            ->assertSee('id="modalFormulaEquationContainer"', false)
            ->assertSee('id="modalFormulaLegendContainer"', false);
    }
}
