<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KonversiFileTest extends TestCase
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

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
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

    public function test_user_without_view_permission_gets_403_for_konversi_file_page(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
        ]);

        $this->actingAs($user)
            ->get(route('pengaturan.konversi-file.index'))
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_open_konversi_file_page(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.konversi-file' => ['view' => true],
        ]);

        $this->actingAs($user)
            ->get(route('pengaturan.konversi-file.index'))
            ->assertOk()
            ->assertSee('Konversi File')
            ->assertSee('CSV ke XLSX');
    }

    public function test_user_without_create_permission_cannot_submit_conversion(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.konversi-file' => ['view' => true],
        ]);

        $file = UploadedFile::fake()->createWithContent('sample.csv', "kode,nama\n00123,Alpha\n");

        $this->actingAs($user)
            ->post(route('pengaturan.konversi-file.csv-ke-xlsx'), [
                'source_file' => $file,
            ])
            ->assertForbidden();
    }

    public function test_valid_csv_upload_returns_xlsx_download(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.konversi-file' => [
                'view' => true,
                'create' => true,
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('laporan.csv', "kode,nama\n00123,Alpha\n");

        $this->actingAs($user)
            ->post(route('pengaturan.konversi-file.csv-ke-xlsx'), [
                'source_file' => $file,
            ])
            ->assertOk()
            ->assertDownload('laporan.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_non_csv_upload_is_rejected(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'pengaturan.konversi-file' => [
                'view' => true,
                'create' => true,
            ],
        ]);

        $file = UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf');

        $this->actingAs($user)
            ->from(route('pengaturan.konversi-file.index'))
            ->post(route('pengaturan.konversi-file.csv-ke-xlsx'), [
                'source_file' => $file,
            ])
            ->assertRedirect(route('pengaturan.konversi-file.index'))
            ->assertSessionHasErrors('source_file');
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
