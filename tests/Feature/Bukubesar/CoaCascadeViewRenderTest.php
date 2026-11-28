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

class CoaCascadeViewRenderTest extends TestCase
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
            'name' => 'Akuntan',
            'email' => 'akuntan@test.com',
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

    public function test_edit_parent_coa_displays_cascade_checkbox_and_confirmation_modal(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
        ]);

        Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.01',
            'nama' => 'Beban Gaji',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('bukubesar.coa.edit', $parent));

        $response->assertOk()
            ->assertSee('id="cascade_wrapper"', false)
            ->assertSee('id="cascade_to_children"', false)
            ->assertSee('id="cascade_mode"', false)
            ->assertSee('id="cascadeConfirmModal"', false)
            ->assertSee('id="modal_total_descendants"', false)
            ->assertSee('id="modal_empty_count"', false)
            ->assertSee('id="modal_existing_count"', false)
            ->assertSee('id="modal_existing_list"', false)
            ->assertSee('id="btnCascadeFillEmpty"', false)
            ->assertSee('id="btnCascadeOverwriteAll"', false)
            ->assertSee(json_encode(route('bukubesar.coa.cascade-preview', $parent->id)), false)
            ->assertSee('toggleCascadeAvailability', false)
            ->assertDontSee("$('#parent_id').on('change'", false);
    }

    public function test_edit_leaf_coa_does_not_display_cascade_checkbox_or_modal(): void
    {
        $parent = Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
        ]);

        $leaf = Coa::create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Beban',
            'kode' => '5.1.01',
            'nama' => 'Beban Gaji',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('bukubesar.coa.edit', $leaf));

        $response->assertOk()
            ->assertDontSee('id="cascade_to_children"', false)
            ->assertDontSee('id="cascade_wrapper"', false)
            ->assertDontSee('id="cascadeConfirmModal"', false);
    }

    public function test_create_coa_parent_options_include_data_aktivitas_and_data_kelompok_attributes(): void
    {
        Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.1',
            'nama' => 'Beban Operasional',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Beban Operasional',
        ]);

        Coa::create([
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'kode' => '5.2',
            'nama' => 'Beban Lainnya',
            'arus_kas_aktivitas' => null,
            'arus_kas_kelompok' => null,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('bukubesar.coa.create'));

        $response->assertOk()
            ->assertSee('data-aktivitas="operasi"', false)
            ->assertSee('data-kelompok="Pembayaran Beban Operasional"', false)
            ->assertSee('data-aktivitas=""', false)
            ->assertSee('data-kelompok=""', false)
            ->assertSee("$('#parent_id').on('change'", false)
            ->assertDontSee('id="cascade_to_children"', false)
            ->assertDontSee('toggleCascadeAvailability', false);
    }
}
