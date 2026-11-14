<?php

namespace Tests\Feature;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndikatorKinerjaCrudTest extends TestCase
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

    public function test_can_view_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.create'));

        $response->assertOk();
        $response->assertSee('Tambah Indikator Kinerja');
        $response->assertSee('Identitas Dasar Indikator');
        $response->assertSee('Pemetaan Akun COA');
    }

    public function test_can_create_new_indicator_with_mapping_coa(): void
    {
        $user = User::factory()->create();
        $coa = Coa::create(['kode' => '401', 'nama' => 'Pendapatan Rawat Jalan', 'status_aktif' => 1]);

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store'), [
            'kode' => 'kpi_test',
            'nama' => 'KPI Test',
            'satuan' => SatuanIndikator::PERSEN->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'basis_pembilang' => BasisPembilang::COA->value,
            'basis_penyebut' => BasisPenyebut::COA->value,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER->value,
            'target_hijau_min' => 80.0,
            'status_aktif' => 1,
            'urutan' => 1,
            'mappings' => [
                [
                    'coa_id' => $coa->id,
                    'peran' => PeranCoa::NUMERATOR->value,
                    'is_excluded' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']));
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'kpi_test']);
        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'coa_id' => $coa->id,
            'peran' => PeranCoa::NUMERATOR->value,
        ]);
    }

    public function test_can_view_edit_page(): void
    {
        $user = User::factory()->create();
        $indikator = IndikatorKinerja::create([
            'kode' => 'cr_edit',
            'nama' => 'Current Ratio Edit',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
        ]);

        $response = $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.edit', $indikator->id));

        $response->assertOk();
        $response->assertSee('Edit Indikator Kinerja');
        $response->assertSee('Current Ratio Edit');
        $response->assertSee('cr_edit');
    }

    public function test_can_update_indicator_and_sync_mapping_coa(): void
    {
        $user = User::factory()->create();
        $coa1 = Coa::create(['kode' => '101', 'nama' => 'Kas Operasional', 'status_aktif' => 1]);
        $coa2 = Coa::create(['kode' => '201', 'nama' => 'Hutang Dagang', 'status_aktif' => 1]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'kpi_sync',
            'nama' => 'KPI Sync Test',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coa1->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        $response = $this->actingAs($user)->put(route('pengaturan.setting-indikator-kinerja.update', $indikator->id), [
            'kode' => 'kpi_sync',
            'nama' => 'KPI Sync Test Updated',
            'satuan' => SatuanIndikator::KALI->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'basis_pembilang' => BasisPembilang::COA->value,
            'basis_penyebut' => BasisPenyebut::COA->value,
            'pola_evaluasi' => PolaEvaluasi::TARGET_BAND->value,
            'target_hijau_min' => 1.5,
            'target_hijau_max' => 2.5,
            'status_aktif' => 1,
            'mappings' => [
                [
                    'coa_id' => $coa2->id,
                    'peran' => PeranCoa::DENOMINATOR->value,
                    'is_excluded' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']));
        $this->assertDatabaseHas('indikator_kinerja', [
            'id' => $indikator->id,
            'nama' => 'KPI Sync Test Updated',
            'satuan' => SatuanIndikator::KALI->value,
        ]);

        // Old mapping removed, new mapping exists
        $this->assertDatabaseMissing('indikator_kinerja_mapping_coa', [
            'indikator_id' => $indikator->id,
            'coa_id' => $coa1->id,
        ]);
        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $indikator->id,
            'coa_id' => $coa2->id,
            'peran' => PeranCoa::DENOMINATOR->value,
            'is_excluded' => 1,
        ]);
    }

    public function test_can_delete_indicator_and_cascade_mapping(): void
    {
        $user = User::factory()->create();
        $coa = Coa::create(['kode' => '102', 'nama' => 'Bank Mandiri', 'status_aktif' => 1]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'kpi_del',
            'nama' => 'KPI Delete Test',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
        ]);

        $mapping = IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coa->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('pengaturan.setting-indikator-kinerja.destroy', $indikator->id));

        $response->assertRedirect(route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']));
        $this->assertDatabaseMissing('indikator_kinerja', ['id' => $indikator->id]);
        $this->assertDatabaseMissing('indikator_kinerja_mapping_coa', ['id' => $mapping->id]);
    }

    public function test_validation_fails_on_duplicate_kode(): void
    {
        $user = User::factory()->create();
        IndikatorKinerja::create([
            'kode' => 'kpi_dup',
            'nama' => 'KPI Duplikat Asal',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
        ]);

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store'), [
            'kode' => 'kpi_dup',
            'nama' => 'KPI Duplikat Baru',
            'satuan' => SatuanIndikator::PERSEN->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER->value,
        ]);

        $response->assertSessionHasErrors(['kode']);
    }

    public function test_validation_fails_when_nama_exceeds_100_chars(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store'), [
            'kode' => 'kpi_long_name',
            'nama' => str_repeat('A', 101),
            'satuan' => SatuanIndikator::PERSEN->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER->value,
        ]);

        $response->assertSessionHasErrors(['nama']);
    }

    public function test_update_preserves_existing_urutan_when_null_or_omitted(): void
    {
        $user = User::factory()->create();
        $indikator = IndikatorKinerja::create([
            'kode' => 'kpi_urutan_test',
            'nama' => 'KPI Urutan Test',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'urutan' => 7,
        ]);

        $response = $this->actingAs($user)->put(route('pengaturan.setting-indikator-kinerja.update', $indikator->id), [
            'kode' => 'kpi_urutan_test',
            'nama' => 'KPI Urutan Test Updated',
            'satuan' => SatuanIndikator::PERSEN->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER->value,
            'urutan' => null,
        ]);

        $response->assertRedirect(route('pengaturan.setting-indikator-kinerja.index', ['tab' => 'indikator']));
        $indikator->refresh();
        $this->assertSame(7, $indikator->urutan);
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $roleTanpaAkses = Role::create([
            'kode' => 'no_access',
            'nama' => 'No Access',
        ]);
        $user = User::factory()->create(['role_id' => $roleTanpaAkses->id]);

        $this->actingAs($user)->get(route('pengaturan.setting-indikator-kinerja.create'))->assertForbidden();
        $this->actingAs($user)->post(route('pengaturan.setting-indikator-kinerja.store'), [])->assertForbidden();
    }
}
