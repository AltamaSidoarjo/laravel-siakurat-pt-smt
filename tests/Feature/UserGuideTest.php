<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    private User $adminUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('nama_lengkap')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('profile_picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $staffRole = Role::create(['nama' => 'Staff', 'kode' => 'staff', 'is_system' => false]);
        $module = AccessModule::create(['kode' => 'panduan.user-guide', 'nama' => 'User Guide', 'group_nama' => 'Panduan', 'urutan' => 250]);

        RolePermission::create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role_id' => $staffRole->id,
        ]);

        $docsPath = resource_path('docs/panduan');
        File::ensureDirectoryExists($docsPath);
        if (! File::exists($docsPath.'/01-ringkasan-umum.md')) {
            File::put($docsPath.'/01-ringkasan-umum.md', "# Ringkasan Umum Sistem\n\nSelamat datang di panduan SiAkurat.");
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('panduan.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('panduan.index'));
        $response->assertStatus(403);
    }

    public function test_authorized_user_receives_200_and_sees_user_guide(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('panduan.index'));
        $response->assertStatus(200);
        $response->assertSee('User Guide');
        $response->assertSee('Ringkasan Umum Sistem');
    }

    public function test_querying_invalid_topic_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('panduan.index', ['topic' => 'unknown-topic-xyz']));
        $response->assertStatus(404);
    }

    public function test_authorized_user_can_view_specific_topic_via_query_parameter(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('panduan.index', ['topic' => '01-ringkasan-umum']));
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Umum Sistem');
    }

    public function test_sidebar_displays_user_guide_link_for_authorized_user(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('panduan.index'));
        $response->assertStatus(200);
        $response->assertSee(route('panduan.index'));
        $response->assertSee('bi bi-book-half');
    }
}
