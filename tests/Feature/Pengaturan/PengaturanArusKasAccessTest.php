<?php

namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasPengecualian;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengaturanArusKasAccessTest extends TestCase
{
    private User $admin;

    private User $viewerOnly;

    private User $unauthorizedUser;

    private SettingArusKas $pos;

    private SettingArusKasPengecualian $pengecualian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $viewerRole = Role::create(['nama' => 'Viewer', 'kode' => 'viewer', 'is_system' => false]);
        $staffRole = Role::create(['nama' => 'Staff', 'kode' => 'staff', 'is_system' => false]);

        $module = AccessModule::create([
            'kode' => 'pengaturan.arus-kas',
            'nama' => 'Pengaturan Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 206,
        ]);

        RolePermission::create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        RolePermission::create([
            'role_id' => $viewerRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->viewerOnly = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@test.com',
            'password' => bcrypt('password'),
            'role_id' => $viewerRole->id,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Staff',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role_id' => $staffRole->id,
        ]);

        $this->pos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => true,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $coa = Coa::create([
            'kode' => '112.09',
            'nama' => 'Cadangan Kerugian Piutang',
            'tipe_coa' => 'Piutang Usaha',
            'status_aktif' => 1,
        ]);

        $this->pengecualian = SettingArusKasPengecualian::create([
            'coa_id' => $coa->id,
            'alasan' => 'Akun kontra',
        ]);
    }

    private function prepareSchema(): void
    {
        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'coa',
            'role_permissions',
            'access_modules',
            'roles',
            'users',
            'log_aktifitas',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php'))->up();
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get(route('pengaturan.arus-kas.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_receives_403_across_all_9_routes(): void
    {
        $this->actingAs($this->unauthorizedUser)->get(route('pengaturan.arus-kas.index'))->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->get(route('pengaturan.arus-kas.parent-coa-leaves', ['parent_id' => 1]))->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->post(route('pengaturan.arus-kas.store'), [])->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->put(route('pengaturan.arus-kas.update', $this->pos), [])->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->patch(route('pengaturan.arus-kas.toggle', $this->pos))->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->patch(route('pengaturan.arus-kas.reorder'), [])->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->delete(route('pengaturan.arus-kas.destroy', $this->pos))->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->post(route('pengaturan.arus-kas.pengecualian.store'), [])->assertStatus(403);
        $this->actingAs($this->unauthorizedUser)->delete(route('pengaturan.arus-kas.pengecualian.destroy', $this->pengecualian))->assertStatus(403);
    }

    public function test_partial_permission_view_only_allows_reads_and_blocks_mutations(): void
    {
        $indexResponse = $this->actingAs($this->viewerOnly)->get(route('pengaturan.arus-kas.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Tambah Pos');
        $indexResponse->assertDontSee('Tambah Pengecualian');
        $indexResponse->assertDontSee('btn-reorder-pos');
        $indexResponse->assertDontSee(route('pengaturan.arus-kas.toggle', $this->pos));
        $indexResponse->assertDontSee(route('pengaturan.arus-kas.destroy', $this->pos));

        $this->actingAs($this->viewerOnly)
            ->get(route('pengaturan.arus-kas.parent-coa-leaves', ['parent_id' => 1]))
            ->assertStatus(200);

        $this->actingAs($this->viewerOnly)->post(route('pengaturan.arus-kas.store'), [])->assertStatus(403);
        $this->actingAs($this->viewerOnly)->put(route('pengaturan.arus-kas.update', $this->pos), [])->assertStatus(403);
        $this->actingAs($this->viewerOnly)->patch(route('pengaturan.arus-kas.reorder'), [])->assertStatus(403);
        $this->actingAs($this->viewerOnly)->patch(route('pengaturan.arus-kas.toggle', $this->pos))->assertStatus(403);
        $this->actingAs($this->viewerOnly)->delete(route('pengaturan.arus-kas.destroy', $this->pos))->assertStatus(403);
        $this->actingAs($this->viewerOnly)->post(route('pengaturan.arus-kas.pengecualian.store'), [])->assertStatus(403);
        $this->actingAs($this->viewerOnly)->delete(route('pengaturan.arus-kas.pengecualian.destroy', $this->pengecualian))->assertStatus(403);
    }

    public function test_permitted_user_can_access_and_sidebar_renders_link_only_for_permitted_users(): void
    {
        $response = $this->actingAs($this->admin)->get(route('pengaturan.arus-kas.index'));
        $response->assertStatus(200);
        $response->assertSee('Pengaturan Arus Kas');
        $response->assertSee(route('pengaturan.arus-kas.index'));

        $this->actingAs($this->admin)
            ->get(route('pengaturan.arus-kas.parent-coa-leaves', ['parent_id' => 1]))
            ->assertStatus(200)
            ->assertJsonStructure(['eligible', 'skipped']);

        Auth::login($this->unauthorizedUser);
        $unauthorizedSidebar = view('partials.sidebar')->render();
        $this->assertStringNotContainsString('Pengaturan Arus Kas', $unauthorizedSidebar);
        $this->assertStringNotContainsString('/pengaturan/arus-kas', $unauthorizedSidebar);
    }
}
