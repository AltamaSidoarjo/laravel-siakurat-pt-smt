<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\JurnalUmum;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JurnalUmumDataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['bukubesar', 'jurnal_umum_rinci', 'jurnal_umum', 'coa', 'log_aktifitas', 'role_permissions', 'access_modules', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('nama_lengkap')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('email')->unique();
            $table->unsignedInteger('role_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedInteger('tipe_coa_id')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
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

        $timestamp = now();
        AccessModule::query()->insert(
            collect(AccessModuleRegistry::all())
                ->map(fn (array $module) => [...$module, 'created_at' => $timestamp, 'updated_at' => $timestamp])
                ->all()
        );
    }

    public function test_index_displays_summary_metrics_and_unified_surface(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'bukubesar.jurnal-umum' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        JurnalUmum::query()->create([
            'nomer' => 'JU-001',
            'tanggal' => '2026-10-15',
            'keterangan' => 'Pembayaran Operasional',
            'debit' => 1500000,
            'kredit' => 1500000,
        ]);

        JurnalUmum::query()->create([
            'nomer' => 'JU-002',
            'tanggal' => '2026-10-20',
            'keterangan' => 'Pembelian ATK',
            'debit' => 250000,
            'kredit' => 250000,
        ]);

        $response = $this->actingAs($user)->get(route('bukubesar.jurnal-umum.index', [
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['total_count'] === 2 && (float) $summary['total_nominal'] === 1750000.0;
        });
        $response->assertSee('Total Transaksi');
        $response->assertSee('Total Perputaran Nominal');
        $response->assertSee('Rp 1.750.000');
    }

    public function test_load_data_returns_action_columns_and_permission_flags(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'bukubesar.jurnal-umum' => ['view' => true, 'update' => true, 'delete' => true],
        ]);

        $jurnal = JurnalUmum::query()->create([
            'nomer' => 'JU-TEST-01',
            'tanggal' => '2026-10-22',
            'keterangan' => 'Uji Coba Datatable Modern',
            'debit' => 500000,
            'kredit' => 500000,
        ]);

        $response = $this->actingAs($user)->getJson(route('bukubesar.jurnal-umum.load-data', [
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-31',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'id',
                    'nomer',
                    'tanggal',
                    'debit',
                    'keterangan',
                    'edit_url',
                    'print_url',
                    'destroy_url',
                    'can_update',
                    'can_delete',
                ],
            ],
        ]);

        $item = $response->json('data.0');
        $this->assertSame('JU-TEST-01', $item['nomer']);
        $this->assertSame('22-10-2026', $item['tanggal']);
        $this->assertSame('500.000', $item['debit']);
        $this->assertTrue($item['can_update']);
        $this->assertTrue($item['can_delete']);
        $this->assertStringContainsString('/bukubesar/jurnal-umum/'.$jurnal->id.'/edit', $item['edit_url']);
        $this->assertStringContainsString('/bukubesar/jurnal-umum/'.$jurnal->id.'/print', $item['print_url']);
    }

    public function test_load_data_supports_dynamic_sorting(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'bukubesar.jurnal-umum' => ['view' => true],
        ]);

        JurnalUmum::query()->create([
            'nomer' => 'JU-001',
            'tanggal' => '2026-10-10',
            'keterangan' => 'Transaksi B',
            'debit' => 1000000,
            'kredit' => 1000000,
        ]);

        JurnalUmum::query()->create([
            'nomer' => 'JU-002',
            'tanggal' => '2026-10-05',
            'keterangan' => 'Transaksi A',
            'debit' => 50000,
            'kredit' => 50000,
        ]);

        JurnalUmum::query()->create([
            'nomer' => 'JU-003',
            'tanggal' => '2026-10-20',
            'keterangan' => 'Transaksi C',
            'debit' => 250000,
            'kredit' => 250000,
        ]);

        // 1. Sort by tanggal ASC
        $resTanggalAsc = $this->actingAs($user)->getJson(route('bukubesar.jurnal-umum.load-data', [
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-31',
            'columns' => [
                0 => ['data' => 'nomer', 'name' => 'nomer'],
                1 => ['data' => 'tanggal', 'name' => 'tanggal'],
                2 => ['data' => 'debit', 'name' => 'debit'],
                3 => ['data' => 'keterangan', 'name' => 'keterangan'],
            ],
            'order' => [
                0 => ['column' => 1, 'dir' => 'asc'],
            ],
        ]));

        $resTanggalAsc->assertOk();
        $this->assertSame(['JU-002', 'JU-001', 'JU-003'], array_column($resTanggalAsc->json('data'), 'nomer'));

        // 2. Sort by debit DESC (numerical sort, 1.000.000 > 250.000 > 50.000)
        $resDebitDesc = $this->actingAs($user)->getJson(route('bukubesar.jurnal-umum.load-data', [
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-31',
            'columns' => [
                0 => ['data' => 'nomer', 'name' => 'nomer'],
                1 => ['data' => 'tanggal', 'name' => 'tanggal'],
                2 => ['data' => 'debit', 'name' => 'debit'],
                3 => ['data' => 'keterangan', 'name' => 'keterangan'],
            ],
            'order' => [
                0 => ['column' => 2, 'dir' => 'desc'],
            ],
        ]));

        $resDebitDesc->assertOk();
        $this->assertSame(['JU-001', 'JU-003', 'JU-002'], array_column($resDebitDesc->json('data'), 'nomer'));

        // 3. Sort by nomer DESC
        $resNomerDesc = $this->actingAs($user)->getJson(route('bukubesar.jurnal-umum.load-data', [
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-31',
            'columns' => [
                0 => ['data' => 'nomer', 'name' => 'nomer'],
                1 => ['data' => 'tanggal', 'name' => 'tanggal'],
                2 => ['data' => 'debit', 'name' => 'debit'],
                3 => ['data' => 'keterangan', 'name' => 'keterangan'],
            ],
            'order' => [
                0 => ['column' => 0, 'dir' => 'desc'],
            ],
        ]));

        $resNomerDesc->assertOk();
        $this->assertSame(['JU-003', 'JU-002', 'JU-001'], array_column($resNomerDesc->json('data'), 'nomer'));
    }

    private function makeUserWithPermissions(array $permissionsByModule, string $email = 'tester@example.com'): User
    {
        $role = Role::query()->create([
            'nama' => 'Role '.uniqid(),
            'kode' => 'role_'.uniqid(),
            'deskripsi' => null,
            'is_system' => false,
        ]);

        foreach (AccessModule::query()->get() as $module) {
            $selected = $permissionsByModule[$module->kode] ?? [];

            RolePermission::query()->create([
                'role_id' => $role->id,
                'access_module_id' => $module->id,
                'can_view' => (bool) ($selected['view'] ?? false),
                'can_create' => (bool) ($selected['create'] ?? false),
                'can_update' => (bool) ($selected['update'] ?? false),
                'can_delete' => (bool) ($selected['delete'] ?? false),
            ]);
        }

        return User::query()->create([
            'name' => 'Tester',
            'nama_lengkap' => 'Tester Lengkap',
            'jabatan' => 'Tester QA',
            'email' => $email,
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
    }
}
