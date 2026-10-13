<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\AccessModule;
use App\Models\FakturPembelian;
use App\Models\LogAktifitas;
use App\Models\PembayaranPembelian;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::query()->create([
            'name' => 'Tester Supplier',
            'email' => 'tester-supplier@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('pembayaran_pembelian');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
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

        Schema::create('supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->unique();
            $table->string('nama_supplier');
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('nomer_pembayaran');
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul')->nullable();
            $table->string('tipe')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_can_view_supplier_index_page(): void
    {
        Supplier::query()->create([
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Kimia Farma',
            'kategori_supplier' => 'Farmasi',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.supplier.index'));

        $response->assertOk()
            ->assertSee('Master Supplier')
            ->assertSee('SUP-001')
            ->assertSee('PT Kimia Farma')
            ->assertSee('Farmasi');
    }

    public function test_user_can_search_supplier(): void
    {
        Supplier::query()->create([
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Kimia Farma',
            'kategori_supplier' => 'Farmasi',
            'status_aktif' => true,
        ]);

        Supplier::query()->create([
            'kode_supplier' => 'SUP-002',
            'nama_supplier' => 'PT Kalbe Farma',
            'kategori_supplier' => 'Obat',
            'status_aktif' => true,
        ]);

        Supplier::query()->create([
            'kode_supplier' => 'SUP-003',
            'nama_supplier' => 'CV Sumber Makmur',
            'kategori_supplier' => 'ATK',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.supplier.index', ['search' => 'Kalbe']));

        $response->assertOk()
            ->assertSee('PT Kalbe Farma')
            ->assertDontSee('CV Sumber Makmur');
    }

    public function test_user_can_view_create_supplier_page(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.supplier.create'));

        $response->assertOk()
            ->assertSee('Tambah Supplier')
            ->assertSee('Kode Supplier')
            ->assertSee('Nama Supplier');
    }

    public function test_user_can_store_supplier_and_logs_activity(): void
    {
        $payload = [
            'kode_supplier' => 'SUP-NEW-01',
            'nama_supplier' => 'PT Medika Jaya',
            'kategori_supplier' => 'Alat Kesehatan',
            'email_supplier' => 'medika@example.com',
            'alamat_supplier' => 'Jl. Veteran No. 10',
            'status_aktif' => '1',
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pengaturan.supplier.store'), $payload);

        $response->assertRedirect(route('pengaturan.supplier.index'))
            ->assertSessionHas('success', 'Supplier berhasil disimpan.');

        $this->assertDatabaseHas('supplier', [
            'kode_supplier' => 'SUP-NEW-01',
            'nama_supplier' => 'PT Medika Jaya',
            'kategori_supplier' => 'Alat Kesehatan',
            'email_supplier' => 'medika@example.com',
            'alamat_supplier' => 'Jl. Veteran No. 10',
            'status_aktif' => 1,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Master Supplier',
            'tipe' => 'create',
        ]);
    }

    public function test_store_supplier_fails_with_duplicate_kode(): void
    {
        Supplier::query()->create([
            'kode_supplier' => 'SUP-DUP',
            'nama_supplier' => 'PT Supplier Pertama',
            'status_aktif' => true,
        ]);

        $payload = [
            'kode_supplier' => 'SUP-DUP',
            'nama_supplier' => 'PT Supplier Kedua',
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->from(route('pengaturan.supplier.create'))
            ->post(route('pengaturan.supplier.store'), $payload);

        $response->assertRedirect(route('pengaturan.supplier.create'))
            ->assertSessionHasErrors(['kode_supplier']);
    }

    public function test_user_can_view_edit_supplier_page(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-EDIT',
            'nama_supplier' => 'PT Supplier Edit',
            'kategori_supplier' => 'Obat',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pengaturan.supplier.edit', $supplier));

        $response->assertOk()
            ->assertSee('Edit Supplier')
            ->assertSee('SUP-EDIT')
            ->assertSee('PT Supplier Edit');
    }

    public function test_user_can_update_supplier_and_logs_activity(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-ORIGINAL',
            'nama_supplier' => 'PT Original',
            'status_aktif' => true,
        ]);

        $payload = [
            'kode_supplier' => 'SUP-UPDATED',
            'nama_supplier' => 'PT Terupdate',
            'kategori_supplier' => 'Alkes Baru',
            'email_supplier' => 'info@terupdate.com',
            'alamat_supplier' => 'Gedung Baru Lt. 2',
            'status_aktif' => '0',
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('pengaturan.supplier.update', $supplier), $payload);

        $response->assertRedirect(route('pengaturan.supplier.index'))
            ->assertSessionHas('success', 'Supplier berhasil diperbarui.');

        $this->assertDatabaseHas('supplier', [
            'id' => $supplier->id,
            'kode_supplier' => 'SUP-UPDATED',
            'nama_supplier' => 'PT Terupdate',
            'kategori_supplier' => 'Alkes Baru',
            'email_supplier' => 'info@terupdate.com',
            'status_aktif' => 0,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Master Supplier',
            'tipe' => 'update',
        ]);
    }

    public function test_user_can_delete_unused_supplier_and_logs_activity(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-DEL',
            'nama_supplier' => 'PT Hapus Saya',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pengaturan.supplier.destroy', $supplier));

        $response->assertRedirect(route('pengaturan.supplier.index'))
            ->assertSessionHas('success', 'Supplier berhasil dihapus.');

        $this->assertDatabaseMissing('supplier', [
            'id' => $supplier->id,
        ]);

        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Master Supplier',
            'tipe' => 'delete',
        ]);
    }

    public function test_user_cannot_delete_supplier_used_in_faktur_pembelian(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-USED-INV',
            'nama_supplier' => 'PT Supplier Terpakai Faktur',
            'status_aktif' => true,
        ]);

        FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'nomer_faktur' => 'FP-001',
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pengaturan.supplier.destroy', $supplier));

        $response->assertRedirect(route('pengaturan.supplier.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('supplier', [
            'id' => $supplier->id,
        ]);
    }

    public function test_user_cannot_delete_supplier_used_in_pembayaran_pembelian(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-USED-PAY',
            'nama_supplier' => 'PT Supplier Terpakai Bayar',
            'status_aktif' => true,
        ]);

        PembayaranPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'nomer_pembayaran' => 'PAY-001',
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pengaturan.supplier.destroy', $supplier));

        $response->assertRedirect(route('pengaturan.supplier.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('supplier', [
            'id' => $supplier->id,
        ]);
    }

    public function test_middleware_blocks_access_without_module_permission(): void
    {
        $role = Role::query()->create([
            'nama' => 'Staff Biasa',
            'kode' => 'staff',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'pengaturan.supplier',
            'nama' => 'Master Supplier',
            'group_nama' => 'Pengaturan',
            'urutan' => 225,
        ]);

        $this->user->update(['role_id' => $role->id]);

        // No permissions in role_permissions for this module
        $response = $this->actingAs($this->user)
            ->get(route('pengaturan.supplier.index'));

        $response->assertForbidden();

        // Grant view permission
        RolePermission::query()->create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $responseAllowed = $this->actingAs($this->user)
            ->get(route('pengaturan.supplier.index'));

        $responseAllowed->assertOk();

        // But create is still forbidden
        $responseCreateForbidden = $this->actingAs($this->user)
            ->get(route('pengaturan.supplier.create'));

        $responseCreateForbidden->assertForbidden();
    }
}
