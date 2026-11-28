<?php

namespace Tests\Feature\Bukubesar;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TipeCoa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaCascadeValidationAndPreviewTest extends TestCase
{
    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $accountingRole = Role::create(['nama' => 'Accounting', 'kode' => 'akunting', 'is_system' => false]);
        $guestRole = Role::create(['nama' => 'Guest', 'kode' => 'guest', 'is_system' => false]);

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

        RolePermission::create([
            'role_id' => $guestRole->id,
            'access_module_id' => $module->id,
            'can_view' => false,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $this->authorizedUser = User::create([
            'name' => 'Akuntan',
            'email' => 'akuntan@test.com',
            'password' => bcrypt('password'),
            'role_id' => $accountingRole->id,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Tamu',
            'email' => 'tamu@test.com',
            'password' => bcrypt('password'),
            'role_id' => $guestRole->id,
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

    public function test_cascade_preview_returns_200_with_expected_json_schema_for_authorized_user(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.01',
            'nama' => 'Beban Gaji',
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);

        Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.02',
            'nama' => 'Beban Listrik',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Utilitas',
        ]);

        // Kasbank descendant must be excluded from preview
        Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '5.1.99',
            'nama' => 'Kas Sub',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson(route('bukubesar.coa.cascade-preview', $parent));

        $response->assertOk()
            ->assertExactJson([
                'total_descendants' => 2,
                'empty_count' => 1,
                'existing_count' => 1,
                'existing_samples' => [
                    [
                        'kode' => '5.1.02',
                        'nama' => 'Beban Listrik',
                        'aktivitas' => 'operasi',
                        'kelompok' => 'Pembayaran Utilitas',
                    ],
                ],
            ]);
    }

    public function test_cascade_preview_returns_403_when_user_lacks_view_permission(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->getJson(route('bukubesar.coa.cascade-preview', $parent));

        $response->assertForbidden();
    }

    public function test_update_with_cascade_to_children_and_empty_arus_kas_aktivitas_fails_validation(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->putJson(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => '',
                'arus_kas_kelompok' => '',
                'kode' => '5.1',
                'nama' => 'Beban Operasional',
                'cascade_to_children' => 1,
                'cascade_mode' => 'overwrite_all',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['arus_kas_aktivitas']);
    }

    public function test_update_with_cascade_to_children_and_missing_cascade_mode_fails_validation(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->putJson(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
                'kode' => '5.1',
                'nama' => 'Beban Operasional',
                'cascade_to_children' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cascade_mode']);
    }

    public function test_update_with_boolean_true_cascade_to_children_and_empty_arus_kas_aktivitas_fails_validation(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->putJson(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => '',
                'arus_kas_kelompok' => '',
                'kode' => '5.1',
                'nama' => 'Beban Operasional',
                'cascade_to_children' => true,
                'cascade_mode' => 'overwrite_all',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['arus_kas_aktivitas']);
    }

    public function test_update_with_boolean_true_cascade_to_children_and_missing_cascade_mode_fails_validation(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->putJson(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'tipe_coa' => 'Beban',
                'arus_kas_aktivitas' => 'operasi',
                'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
                'kode' => '5.1',
                'nama' => 'Beban Operasional',
                'cascade_to_children' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cascade_mode']);
    }

    public function test_update_with_valid_cascade_overwrite_all_updates_parent_and_all_children(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $emptyChild = Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.01',
            'nama' => 'Beban Gaji',
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);

        $existingChild = Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.02',
            'nama' => 'Beban Listrik',
            'arus_kas_aktivitas' => 'investasi',
            'arus_kas_kelompok' => 'Kelompok Lama',
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

        $this->assertDatabaseHas('coa', [
            'id' => $parent->id,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
        ]);

        $this->assertDatabaseHas('coa', [
            'id' => $emptyChild->id,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
        ]);

        $this->assertDatabaseHas('coa', [
            'id' => $existingChild->id,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
        ]);
    }
}
