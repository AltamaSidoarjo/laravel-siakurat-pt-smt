<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\KasbankPembayaran;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Kasbank\KasbankPembayaranService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KasbankPembayaranIndexTest extends TestCase
{
    private User $user;
    private Coa $coaBank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $role = Role::query()->create([
            'nama' => 'Staff Keuangan',
            'kode' => 'staff-keuangan',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'kasbank.pembayaran',
            'nama' => 'Kasbank Pembayaran',
            'group_nama' => 'Kasbank',
            'urutan' => 50,
        ]);

        RolePermission::query()->create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->user = User::query()->create([
            'name' => 'Kasir Pembayaran',
            'email' => 'bayar@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $this->coaBank = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Operasional',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('kasbank_pembayaran_rinci');
        Schema::dropIfExists('kasbank_pembayaran');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

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

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->tinyInteger('status_aktif')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_index_page_loads_with_summary_and_modern_layout(): void
    {
        KasbankPembayaran::query()->create([
            'coa_id' => $this->coaBank->id,
            'nomer' => 'BKK-2026-001',
            'tanggal' => now()->toDateString(),
            'total' => 500000,
            'keterangan' => 'Beli ATK',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('kasbank.pembayaran.index'));

        $response->assertOk();
        $response->assertSee('Kasbank Pembayaran');
        $response->assertSee('Total Pembayaran');
        $response->assertSee('Total Nominal Dibayar');
        $response->assertSee('Periode Terpilih');
        $response->assertSee('Template Pembayaran');
        $response->assertSee('Tambah Pembayaran');
        $response->assertSee('Preset Cepat:');
        $response->assertSee('dt-surface');
    }

    public function test_load_data_returns_expected_columns_and_actions(): void
    {
        $item = KasbankPembayaran::query()->create([
            'coa_id' => $this->coaBank->id,
            'nomer' => 'BKK-2026-002',
            'tanggal' => '2026-10-18',
            'total' => 850000,
            'keterangan' => 'Biaya konsumsi rapat',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('kasbank.pembayaran.load-data', [
                'startDate' => '2026-10-01',
                'endDate' => '2026-10-31',
            ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('BKK-2026-002', $data[0]['nomer']);
        $this->assertSame('18-10-2026', $data[0]['tanggal']);
        $this->assertSame('850.000', $data[0]['total']);
        $this->assertSame('1101 - Kas Operasional', $data[0]['coa_display']);
        $this->assertTrue($data[0]['can_update']);
        $this->assertTrue($data[0]['can_delete']);
        $this->assertStringContainsString('pembayaran/1/edit', $data[0]['edit_url']);
        $this->assertStringContainsString('pembayaran/1/print', $data[0]['print_url']);
    }

    public function test_service_get_summary_calculates_correct_totals(): void
    {
        KasbankPembayaran::query()->create([
            'coa_id' => $this->coaBank->id,
            'nomer' => 'BKK-001',
            'tanggal' => '2026-10-02',
            'total' => 400000,
        ]);
        KasbankPembayaran::query()->create([
            'coa_id' => $this->coaBank->id,
            'nomer' => 'BKK-002',
            'tanggal' => '2026-10-12',
            'total' => 600000,
        ]);

        $service = app(KasbankPembayaranService::class);
        $summary = $service->getSummary('2026-10-01', '2026-10-31');

        $this->assertSame(2, $summary['total_count']);
        $this->assertSame(1000000.0, $summary['total_nominal']);
    }
}
