<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\AccessModule;
use App\Models\LayananInformasi;
use App\Models\LogAktifitas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LayananInformasiTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::query()->create([
            'name' => 'Tester Informasi',
            'email' => 'tester-info@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('layanan_informasi');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->string('profile_picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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

        Schema::create('layanan_informasi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('judul', 255);
            $table->text('isi');
            $table->string('tipe', 30)->default('info');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('tampilkan_popup')->default(false);
            $table->boolean('status_aktif')->default(true);
            $table->integer('urutan')->default(0);
            $table->unsignedInteger('created_by')->nullable();
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
    }

    public function test_user_can_view_layanan_informasi_index_page(): void
    {
        LayananInformasi::query()->create([
            'judul' => 'Pemeliharaan Jaringan',
            'isi' => 'Akan dilakukan maintenance jaringan pukul 23:00.',
            'tipe' => 'warning',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.layanan-informasi.index'));

        $response->assertOk()
            ->assertSee('Layanan Informasi')
            ->assertSee('Pemeliharaan Jaringan')
            ->assertSee('Peringatan');
    }

    public function test_user_can_search_layanan_informasi(): void
    {
        LayananInformasi::query()->create([
            'judul' => 'Pengumuman Libur Nasional',
            'isi' => 'Pelayanan poli tutup tanggal 1.',
            'tipe' => 'info',
            'status_aktif' => true,
        ]);

        LayananInformasi::query()->create([
            'judul' => 'Pembaruan Tarif Laboratorium',
            'isi' => 'Tarif lab diperbarui mulai besok.',
            'tipe' => 'success',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.layanan-informasi.index', ['search' => 'Libur']));

        $response->assertOk()
            ->assertSee('Pengumuman Libur Nasional')
            ->assertDontSee('Pembaruan Tarif Laboratorium');
    }

    public function test_user_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.layanan-informasi.create'));

        $response->assertOk()
            ->assertSee('Tambah Layanan Informasi')
            ->assertSee('Judul Informasi')
            ->assertSee('Tampilkan Popup Modal di Dashboard');
    }

    public function test_user_can_store_layanan_informasi_and_logs_activity(): void
    {
        $payload = [
            'judul' => 'Update Modul Farmasi',
            'isi' => 'Sistem resep elektronik telah diperbarui.',
            'tipe' => 'success',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addDays(5)->toDateString(),
            'tampilkan_popup' => '1',
            'status_aktif' => '1',
            'urutan' => '1',
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pengaturan.layanan-informasi.store'), $payload);

        $response->assertRedirect(route('pengaturan.layanan-informasi.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('layanan_informasi', [
            'judul' => 'Update Modul Farmasi',
            'tipe' => 'success',
            'tampilkan_popup' => 1,
            'status_aktif' => 1,
            'urutan' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'nama_user' => $this->user->name,
            'modul' => 'Layanan Informasi',
            'tipe' => 'create',
        ]);
    }

    public function test_validation_errors_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pengaturan.layanan-informasi.store'), []);

        $response->assertSessionHasErrors(['judul', 'isi', 'tipe']);
    }

    public function test_user_can_view_edit_page(): void
    {
        $item = LayananInformasi::query()->create([
            'judul' => 'Info Tes Edit',
            'isi' => 'Isi informasi awal.',
            'tipe' => 'info',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.layanan-informasi.edit', $item));

        $response->assertOk()
            ->assertSee('Edit Layanan Informasi')
            ->assertSee('Info Tes Edit')
            ->assertSee('Isi informasi awal.');
    }

    public function test_user_can_update_layanan_informasi_and_logs_activity(): void
    {
        $item = LayananInformasi::query()->create([
            'judul' => 'Judul Sebelum Update',
            'isi' => 'Isi lama.',
            'tipe' => 'info',
            'status_aktif' => true,
            'urutan' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('pengaturan.layanan-informasi.update', $item), [
                'judul' => 'Judul Sesudah Update',
                'isi' => 'Isi baru yang lebih lengkap.',
                'tipe' => 'warning',
                'status_aktif' => '1',
                'tampilkan_popup' => '1',
                'urutan' => '2',
            ]);

        $response->assertRedirect(route('pengaturan.layanan-informasi.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('layanan_informasi', [
            'id' => $item->id,
            'judul' => 'Judul Sesudah Update',
            'tipe' => 'warning',
            'tampilkan_popup' => 1,
            'urutan' => 2,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'nama_user' => $this->user->name,
            'modul' => 'Layanan Informasi',
            'tipe' => 'update',
        ]);
    }

    public function test_user_can_delete_layanan_informasi_and_logs_activity(): void
    {
        $item = LayananInformasi::query()->create([
            'judul' => 'Informasi Akan Dihapus',
            'isi' => 'Isi singkat.',
            'tipe' => 'danger',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pengaturan.layanan-informasi.destroy', $item));

        $response->assertRedirect(route('pengaturan.layanan-informasi.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('layanan_informasi', [
            'id' => $item->id,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'nama_user' => $this->user->name,
            'modul' => 'Layanan Informasi',
            'tipe' => 'delete',
        ]);
    }

    public function test_dashboard_shows_fallback_when_no_active_informasi(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('Layanan Informasi :')
            ->assertSee('Saat ini tidak ada informasi...');
    }

    public function test_dashboard_displays_active_informasi_badge_and_modal(): void
    {
        $item = LayananInformasi::query()->create([
            'judul' => 'Pemberitahuan Sistem Backup',
            'isi' => 'Backup rutin database akan dimulai pukul 02:00 dini hari.',
            'tipe' => 'warning',
            'tampilkan_popup' => true,
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('Layanan Informasi: Pemberitahuan Sistem Backup')
            ->assertSee('alert-warning')
            ->assertSee('Detail')
            ->assertSee('data-auto-popup="true"', false)
            ->assertSee('Backup rutin database akan dimulai pukul 02:00 dini hari.')
            ->assertDontSee('Saat ini tidak ada informasi...');
    }

    public function test_dashboard_ignores_inactive_or_expired_informasi(): void
    {
        // Item nonaktif
        LayananInformasi::query()->create([
            'judul' => 'Informasi Nonaktif',
            'isi' => 'Isi nonaktif.',
            'tipe' => 'info',
            'status_aktif' => false,
        ]);

        // Item expired (kemarin)
        LayananInformasi::query()->create([
            'judul' => 'Informasi Sudah Lewat',
            'isi' => 'Isi sudah kadaluarsa.',
            'tipe' => 'info',
            'tanggal_mulai' => now()->subDays(5)->toDateString(),
            'tanggal_selesai' => now()->subDay()->toDateString(),
            'status_aktif' => true,
        ]);

        // Item masa depan (belum mulai)
        LayananInformasi::query()->create([
            'judul' => 'Informasi Masa Depan',
            'isi' => 'Isi belum saatnya tayang.',
            'tipe' => 'info',
            'tanggal_mulai' => now()->addDays(2)->toDateString(),
            'tanggal_selesai' => now()->addDays(5)->toDateString(),
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('home'));

        $response->assertOk()
            ->assertDontSee('Informasi Nonaktif')
            ->assertDontSee('Informasi Sudah Lewat')
            ->assertDontSee('Informasi Masa Depan')
            ->assertSee('Saat ini tidak ada informasi...');
    }

    public function test_middleware_blocks_access_without_permission(): void
    {
        $role = Role::query()->create([
            'nama' => 'Staff Pengguna',
            'kode' => 'staff_user',
            'is_system' => false,
        ]);

        $this->user->update(['role_id' => $role->id]);

        $response = $this->actingAs($this->user)
            ->get(route('pengaturan.layanan-informasi.index'));

        $response->assertForbidden();
    }
}
