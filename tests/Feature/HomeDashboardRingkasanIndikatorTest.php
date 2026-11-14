<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessModuleRegistry;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeDashboardRingkasanIndikatorTest extends TestCase
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

    public function test_can_fetch_ringkasan_indikator_as_json(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->make();

        $response = $this->actingAs($user)->getJson(route('home.ringkasan-indikator', [
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'hijau',
                'kuning',
                'merah',
                'netral',
            ])
            ->assertJson([
                'total' => 11,
            ]);
    }

    public function test_can_fetch_ringkasan_indikator_with_indonesian_query_params(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->make();

        $response = $this->actingAs($user)->getJson(route('home.ringkasan-indikator', [
            'dariTanggal' => now()->startOfMonth()->format('Y-m-d'),
            'sampaiTanggal' => now()->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'hijau',
                'kuning',
                'merah',
                'netral',
            ])
            ->assertJson([
                'total' => 11,
            ]);
    }

    public function test_guest_cannot_fetch_ringkasan_indikator(): void
    {
        $response = $this->getJson(route('home.ringkasan-indikator'));

        $response->assertUnauthorized();
    }

    public function test_access_module_registry_registers_kpi_modules(): void
    {
        $modules = collect(AccessModuleRegistry::all());

        $dashboardModule = $modules->firstWhere('kode', 'dashboard.indikator-kinerja');
        $this->assertNotNull($dashboardModule, 'Modul dashboard.indikator-kinerja harus terdaftar');
        $this->assertSame('Indikator Kinerja', $dashboardModule['nama']);
        $this->assertSame('Dashboard', $dashboardModule['group_nama']);
        $this->assertSame(15, $dashboardModule['urutan']);

        $settingModule = $modules->firstWhere('kode', 'pengaturan.setting-indikator-kinerja');
        $this->assertNotNull($settingModule, 'Modul pengaturan.setting-indikator-kinerja harus terdaftar');
        $this->assertSame('Setting Indikator Kinerja', $settingModule['nama']);
        $this->assertSame('Pengaturan', $settingModule['group_nama']);
        $this->assertSame(175, $settingModule['urutan']);
    }

    public function test_home_dashboard_view_contains_kpi_status_strip_and_cta(): void
    {
        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('Status Indikator Kinerja (KPI)')
            ->assertSee('Lihat Dashboard Indikator Selengkapnya')
            ->assertSee('kpiTotalCount')
            ->assertSee('kpiHijauCount')
            ->assertSee('kpiKuningCount')
            ->assertSee('kpiMerahCount');
    }
}
