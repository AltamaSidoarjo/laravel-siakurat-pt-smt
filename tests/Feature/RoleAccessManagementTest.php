<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\LogAktifitas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAccessManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

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

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        $timestamp = now();

        AccessModule::query()->insert(
            collect(AccessModuleRegistry::all())
                ->map(fn (array $module) => [
                    ...$module,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all()
        );
    }

    public function test_user_without_view_permission_gets_403_for_pengguna_index(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
        ]);

        $this->actingAs($user)
            ->get(route('pengaturan.pengguna.index'))
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_open_pengguna_page_and_hidden_menu_items_are_not_rendered(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.pengguna' => ['view' => true],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('pengaturan.pengguna.index'));

        $response
            ->assertOk()
            ->assertSee('Daftar User')
            ->assertSee('Pengguna')
            ->assertSee('Nama Lengkap')
            ->assertSee('Jabatan')
            ->assertDontSee('Role Akses');
    }

    public function test_admin_can_create_role_and_store_permission_matrix(): void
    {
        $admin = $this->makeAdminUser();
        $penggunaModule = AccessModule::query()->where('kode', 'pengaturan.pengguna')->firstOrFail();
        $laporanModule = AccessModule::query()->where('kode', 'laporan.keuangan')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->post(route('pengaturan.role-akses.store'), [
                'nama' => 'Operator',
                'kode' => 'operator',
                'deskripsi' => 'Role operator',
                'permissions' => [
                    $penggunaModule->id => [
                        'can_view' => '1',
                    ],
                    $laporanModule->id => [
                        'can_view' => '1',
                        'can_update' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('pengaturan.role-akses.index'));

        $role = Role::query()->where('kode', 'operator')->first();

        $this->assertNotNull($role);
        $this->assertTrue(
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('access_module_id', $penggunaModule->id)
                ->where('can_view', true)
                ->exists()
        );
        $this->assertTrue(
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('access_module_id', $laporanModule->id)
                ->where('can_update', true)
                ->exists()
        );
    }

    public function test_admin_can_assign_role_when_creating_user(): void
    {
        $admin = $this->makeAdminUser();
        $role = Role::query()->create([
            'nama' => 'Kasir',
            'kode' => 'kasir',
            'deskripsi' => 'Role kasir',
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('pengaturan.pengguna.store'), [
                'name' => 'User Baru',
                'nama_lengkap' => 'User Baru Lengkap',
                'jabatan' => 'Staf Administrasi',
                'email' => 'baru@example.com',
                'role_id' => $role->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('pengaturan.pengguna.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'baru@example.com',
            'nama_lengkap' => 'User Baru Lengkap',
            'jabatan' => 'Staf Administrasi',
            'role_id' => $role->id,
        ]);
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = $this->makeAdminUser();
        $role = Role::query()->create([
            'nama' => 'Supervisor',
            'kode' => 'supervisor',
            'deskripsi' => 'Role supervisor',
            'is_system' => false,
        ]);

        foreach (AccessModule::query()->get() as $module) {
            RolePermission::query()->create([
                'role_id' => $role->id,
                'access_module_id' => $module->id,
                'can_view' => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ]);
        }

        $penggunaModule = AccessModule::query()->where('kode', 'pengaturan.pengguna')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->put(route('pengaturan.role-akses.update', $role), [
                'nama' => 'Supervisor',
                'kode' => 'supervisor',
                'deskripsi' => 'Role supervisor update',
                'permissions' => [
                    $penggunaModule->id => [
                        'can_view' => '1',
                        'can_update' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('pengaturan.role-akses.index'));

        $this->assertTrue(
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('access_module_id', $penggunaModule->id)
                ->where('can_view', true)
                ->where('can_update', true)
                ->exists()
        );
    }

    public function test_admin_can_update_user_nama_lengkap_and_jabatan(): void
    {
        $admin = $this->makeAdminUser();
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.pengguna' => ['view' => true],
        ], 'pegawai@example.com');

        $response = $this
            ->actingAs($admin)
            ->put(route('pengaturan.pengguna.update', $user), [
                'name' => 'Pegawai',
                'nama_lengkap' => 'Pegawai Rumah Sakit',
                'jabatan' => 'Kepala Unit',
                'email' => 'pegawai@example.com',
                'role_id' => $user->role_id,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('pengaturan.pengguna.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nama_lengkap' => 'Pegawai Rumah Sakit',
            'jabatan' => 'Kepala Unit',
        ]);
    }

    public function test_user_with_delete_permission_can_delete_another_user_and_create_audit_log(): void
    {
        $admin = $this->makeAdminUser();
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
        ], 'hapus@example.com');

        $response = $this
            ->actingAs($admin)
            ->delete(route('pengaturan.pengguna.destroy', $user));

        $response
            ->assertRedirect(route('pengaturan.pengguna.index'))
            ->assertSessionHas('success', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $log = LogAktifitas::query()
            ->where('modul', 'User Management')
            ->where('tipe', 'delete')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->name, $log->nama_user);
        $this->assertSame([
            'old' => [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
            ],
        ], json_decode($log->payload, true));
    }

    public function test_user_without_delete_permission_cannot_delete_another_user(): void
    {
        $actor = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.pengguna' => ['view' => true],
        ]);
        $target = $this->makeUserWithPermissions([
            'home' => ['view' => true],
        ], 'target@example.com');

        $this
            ->actingAs($actor)
            ->delete(route('pengaturan.pengguna.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this
            ->actingAs($admin)
            ->delete(route('pengaturan.pengguna.destroy', $admin));

        $response
            ->assertRedirect(route('pengaturan.pengguna.index'))
            ->assertSessionHas('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('log_aktifitas', [
            'modul' => 'User Management',
            'tipe' => 'delete',
        ]);
    }

    public function test_delete_button_is_only_shown_for_other_users_when_actor_has_delete_permission(): void
    {
        $admin = $this->makeAdminUser();
        $target = $this->makeUserWithPermissions([
            'home' => ['view' => true],
        ], 'button-target@example.com');

        $response = $this
            ->actingAs($admin)
            ->get(route('pengaturan.pengguna.index'));

        $response
            ->assertOk()
            ->assertSee('action="'.route('pengaturan.pengguna.destroy', $target).'"', false)
            ->assertDontSee('action="'.route('pengaturan.pengguna.destroy', $admin).'"', false);

        $viewer = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.pengguna' => ['view' => true],
        ], 'viewer@example.com');

        $this
            ->actingAs($viewer)
            ->get(route('pengaturan.pengguna.index'))
            ->assertOk()
            ->assertDontSee('action="'.route('pengaturan.pengguna.destroy', $target).'"', false);
    }

    private function makeAdminUser(): User
    {
        return $this->makeUserWithPermissions(
            collect(AccessModule::query()->pluck('kode'))
                ->mapWithKeys(fn (string $kode) => [
                    $kode => [
                        'view' => true,
                        'create' => true,
                        'update' => true,
                        'delete' => true,
                    ],
                ])
                ->all(),
            'admin@example.com'
        );
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
