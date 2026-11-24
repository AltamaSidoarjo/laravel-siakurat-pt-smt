# Standarisasi Kelompok Arus Kas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menstandarisasi input kelompok arus kas pada master COA melalui modul master referensi di Pengaturan, antarmuka chained autocomplete Select2 dengan tag baru terkonfirmasi, cascade update saat edit pos master, dan inisialisasi seeder 14 pos standar.

**Architecture:** Mempertahankan kolom string `coa.arus_kas_kelompok` untuk kompatibilitas penuh dengan `LaporanKeuanganService` tanpa breaking changes. Menambahkan tabel `referensi_kelompok_arus_kas` yang dikelola via modul master berotorisasi penuh di Pengaturan. Menghubungkan form COA dengan chained Select2 tags yang otomatis mendaftarkan pos baru ke master referensi setelah melalui sanitasi dan dialog konfirmasi.

**Tech Stack:** Laravel 11, PHP 8.2+, MySQL, Blade, Select2, Bootstrap 5, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-24-standarisasi-kelompok-arus-kas-design.md`

---

## Global Constraints
- Sesuai AGENTS.md: Controllers tipis (Thin Controllers), logika bisnis di Service class, validasi di Form Request, kepatuhan PSR-12.
- Mandatory Authorization Checklist 5 langkah: Registrasi di `AccessModuleRegistry`, migrasi modul & role permission, route middleware `module.access`, sidebar navigation check, dan dokumentasi.
- Database: MySQL-compatible, semua migration dapat di-rollback (`down()`).
- Tipe `coa.arus_kas_kelompok` tetap string `VARCHAR(150)`, tidak diubah menjadi Foreign Key ID integer.
- Pengurutan pos referensi secara alfabetis alami (`nama ASC`), kolom `urutan` ditiadakan.

## Review Focus
- **Input dengan spasi berlebih atau variasi kapitalisasi pada tag baru:** Sistem wajib menormalisasi spasi (`trim` dan collapse whitespace) dan mencocokkan case-insensitive sehingga tidak membuat duplikat pos di database. *(Diuji di Task 3 & Task 6)*
- **Upaya penghapusan pos master referensi yang sedang terikat pada akun COA:** Wajib ditolak (*Restricted Delete*) dengan flash error ramah dan tidak menghapus data. *(Diuji di Task 3 & Task 4)*
- **Pengeditan nama pos master:** Wajib memperbarui seluruh record COA yang menggunakan nama lama secara atomik (*Cascade Update*) dalam database transaction. *(Diuji di Task 3 & Task 4)*
- **Pengguna tanpa hak akses ke menu Pengaturan namun memiliki hak akses tulis COA:** Wajib tetap dapat menambahkan tag baru tanpa hambatan error 403. *(Diuji di Task 6)*
- **Seeder awal:** Wajib menyetel 14 pos standar draf Excel sebagai Aktif (`1`) dan pos historis database saat ini sebagai Nonaktif (`0`) tanpa memodifikasi record COA yang sudah ada. *(Diuji di Task 5)*

---

### Task 1: Database Migration & Eloquent Model `ReferensiKelompokArusKas`

**Files:**
- Create: `database/migrations/2026_11_24_000001_create_referensi_kelompok_arus_kas_table.php`
- Create: `app/Models/ReferensiKelompokArusKas.php`
- Create: `tests/Unit/ReferensiKelompokArusKasModelTest.php`

**Interfaces:**
- Produces: `App\Models\ReferensiKelompokArusKas` model dengan fillable `['aktivitas', 'nama', 'status_aktif']`, casts `['status_aktif' => 'boolean']`, scopes `scopeAktif($query)`, `scopeAktivitas($query, string $aktivitas)`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/ReferensiKelompokArusKasModelTest.php
namespace Tests\Unit;

use App\Models\ReferensiKelompokArusKas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
            $table->index(['aktivitas', 'nama']);
        });
    }

    public function test_can_create_and_query_scopes(): void
    {
        $aktif = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan klaim BPJS Kesehatan',
            'status_aktif' => true,
        ]);

        $nonaktif = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Lama',
            'status_aktif' => false,
        ]);

        $this->assertCount(1, ReferensiKelompokArusKas::aktif()->get());
        $this->assertCount(2, ReferensiKelompokArusKas::aktivitas('operasi')->get());
        $this->assertTrue($aktif->status_aktif);
        $this->assertFalse($nonaktif->status_aktif);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/ReferensiKelompokArusKasModelTest.php`  
Expected: FAIL with "Class App\Models\ReferensiKelompokArusKas not found".

- [ ] **Step 3: Implement Migration and Model**

Buat migrasi tabel `referensi_kelompok_arus_kas`:
```php
// database/migrations/2026_11_24_000001_create_referensi_kelompok_arus_kas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index(['aktivitas', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referensi_kelompok_arus_kas');
    }
};
```

Buat model:
```php
// app/Models/ReferensiKelompokArusKas.php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReferensiKelompokArusKas extends Model
{
    protected $table = 'referensi_kelompok_arus_kas';

    protected $fillable = [
        'aktivitas',
        'nama',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }

    public function scopeAktivitas(Builder $query, string $aktivitas): Builder
    {
        return $query->where('aktivitas', $aktivitas);
    }
}
```

- [ ] **Step 4: Run test and migration to verify it passes**

Run: `php artisan test tests/Unit/ReferensiKelompokArusKasModelTest.php`  
Expected: PASS.  
Run: `php artisan migrate`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_11_24_000001_create_referensi_kelompok_arus_kas_table.php app/Models/ReferensiKelompokArusKas.php tests/Unit/ReferensiKelompokArusKasModelTest.php
git commit -m "feat: create referensi_kelompok_arus_kas migration and model"
```

---

### Task 2: Mandatory Authorization Pipeline & Access Module Registration

**Files:**
- Modify: `app/Support/AccessModuleRegistry.php:30-36`
- Create: `database/migrations/2026_11_24_000002_add_referensi_kelompok_arus_kas_access_module.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar.blade.php`
- Create: `tests/Feature/Pengaturan/ReferensiKelompokArusKasAccessTest.php`

**Interfaces:**
- Consumes: `App\Support\AccessModuleRegistry`, `App\Http\Middleware\EnsureModuleAccess`
- Produces: Module code `pengaturan.referensi-kelompok-arus-kas` terdaftar, migrasi role permissions otomatis untuk role sistem (`is_system = 1`), route protected dengan middleware `module.access:pengaturan.referensi-kelompok-arus-kas,<action>`, dan navigasi sidebar.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Pengaturan/ReferensiKelompokArusKasAccessTest.php
namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasAccessTest extends TestCase
{
    private User $admin;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $staffRole = Role::create(['nama' => 'Staff', 'kode' => 'staff', 'is_system' => false]);

        $module = AccessModule::create([
            'kode' => 'pengaturan.referensi-kelompok-arus-kas',
            'nama' => 'Referensi Kelompok Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 205,
        ]);

        RolePermission::create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role_id' => $adminRole->id]);
        $this->unauthorizedUser = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => bcrypt('password'), 'role_id' => $staffRole->id]);
    }

    private function prepareSchema(): void
    {
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
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(403);
    }

    public function test_admin_user_can_access(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pengaturan.referensi-kelompok-arus-kas.index'));

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasAccessTest.php`  
Expected: FAIL with "Route [pengaturan.referensi-kelompok-arus-kas.index] not defined".

- [ ] **Step 3: Implement Authorization Pipeline**

1. Daftarkan di `App\Support\AccessModuleRegistry::all()`:
```php
['kode' => 'pengaturan.referensi-kelompok-arus-kas', 'nama' => 'Referensi Kelompok Arus Kas', 'group_nama' => 'Pengaturan', 'urutan' => 205],
```
2. Buat migrasi modul hak akses: `database/migrations/2026_11_24_000002_add_referensi_kelompok_arus_kas_access_module.php` mengikuti pola `2026_10_13_000301_add_layanan_informasi_access_module.php`.
3. Daftarkan routes di `routes/web.php` dalam grup middleware `auth`:
```php
Route::prefix('pengaturan/referensi-kelompok-arus-kas')
    ->name('pengaturan.referensi-kelompok-arus-kas.')
    ->group(function () {
        Route::get('/', [ReferensiKelompokArusKasController::class, 'index'])
            ->middleware('module.access:pengaturan.referensi-kelompok-arus-kas,view')
            ->name('index');
        Route::post('/', [ReferensiKelompokArusKasController::class, 'store'])
            ->middleware('module.access:pengaturan.referensi-kelompok-arus-kas,create')
            ->name('store');
        Route::put('/{referensi}', [ReferensiKelompokArusKasController::class, 'update'])
            ->middleware('module.access:pengaturan.referensi-kelompok-arus-kas,update')
            ->name('update');
        Route::delete('/{referensi}', [ReferensiKelompokArusKasController::class, 'destroy'])
            ->middleware('module.access:pengaturan.referensi-kelompok-arus-kas,delete')
            ->name('destroy');
        Route::patch('/{referensi}/toggle', [ReferensiKelompokArusKasController::class, 'toggleStatus'])
            ->middleware('module.access:pengaturan.referensi-kelompok-arus-kas,update')
            ->name('toggle');
    });
```
4. Tambahkan menu di `resources/views/partials/sidebar.blade.php` di bawah menu Pengaturan:
```html
@if ($can('pengaturan.referensi-kelompok-arus-kas'))
    <li>
        <a class="dropdown-item {{ request()->routeIs('pengaturan.referensi-kelompok-arus-kas.*') ? 'active' : '' }}" href="{{ route('pengaturan.referensi-kelompok-arus-kas.index') }}">
            Referensi Kelompok Arus Kas
        </a>
    </li>
@endif
```
*(Buat stub controller kosong untuk `ReferensiKelompokArusKasController` yang mengembalikan response dummy `response('OK', 200)` untuk method `index` agar route dapat di-resolve)*

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasAccessTest.php`  
Expected: PASS.  
Run: `php artisan migrate`

- [ ] **Step 5: Commit**

```bash
git add app/Support/AccessModuleRegistry.php database/migrations/2026_11_24_000002_add_referensi_kelompok_arus_kas_access_module.php routes/web.php resources/views/partials/sidebar.blade.php tests/Feature/Pengaturan/ReferensiKelompokArusKasAccessTest.php
git commit -m "feat: implement authorization pipeline for referensi kelompok arus kas"
```

---

### Task 3: Form Requests & Business Logic Service (Sanitasi, Cascade Update, & Restricted Delete)

**Files:**
- Create: `app/Http/Requests/Pengaturan/StoreReferensiKelompokArusKasRequest.php`
- Create: `app/Http/Requests/Pengaturan/UpdateReferensiKelompokArusKasRequest.php`
- Create: `app/Services/Pengaturan/ReferensiKelompokArusKasService.php`
- Create: `tests/Unit/Services/ReferensiKelompokArusKasServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\ReferensiKelompokArusKas`, `App\Models\Coa`
- Produces:
  - `ReferensiKelompokArusKasService::getGroupedActive(): Collection`
  - `ReferensiKelompokArusKasService::store(array $data): ReferensiKelompokArusKas`
  - `ReferensiKelompokArusKasService::update(ReferensiKelompokArusKas $ref, array $data): ReferensiKelompokArusKas` (dengan cascade update ke `coa.arus_kas_kelompok`)
  - `ReferensiKelompokArusKasService::canDelete(ReferensiKelompokArusKas $ref): array` `['allowed' => bool, 'reason' => ?string, 'coa_count' => int]`
  - `ReferensiKelompokArusKasService::delete(ReferensiKelompokArusKas $ref): void`
  - `ReferensiKelompokArusKasService::toggleStatus(ReferensiKelompokArusKas $ref): bool`
  - `ReferensiKelompokArusKasService::ensureExists(string $aktivitas, string $nama): ReferensiKelompokArusKas` (sanitasi string & case-insensitive matching)

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Services/ReferensiKelompokArusKasServiceTest.php
namespace Tests\Unit\Services;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Services\Pengaturan\ReferensiKelompokArusKasService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasServiceTest extends TestCase
{
    private ReferensiKelompokArusKasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');

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
            $table->string('nama', 100);
            $table->string('tipe_coa', 50);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->timestamps();
        });

        $this->service = app(ReferensiKelompokArusKasService::class);
    }

    public function test_sanitization_and_case_insensitive_ensure_exists(): void
    {
        $first = $this->service->ensureExists('operasi', '  Pembayaran   listrik, air,  internet  ');
        $this->assertEquals('Pembayaran listrik, air, internet', $first->nama);

        // Case-insensitive match should reuse existing without creating duplicate
        $second = $this->service->ensureExists('operasi', 'pembayaran listrik, air, internet');
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, ReferensiKelompokArusKas::count());
    }

    public function test_update_cascade_updates_associated_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Listrk',
            'status_aktif' => true,
        ]);

        $coa = Coa::create([
            'kode' => '5.1.01',
            'nama' => 'Beban Listrik',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Listrk',
        ]);

        $this->service->update($ref, [
            'nama' => 'Pembayaran listrik, air, internet',
            'status_aktif' => true,
        ]);

        $this->assertEquals('Pembayaran listrik, air, internet', $ref->fresh()->nama);
        $this->assertEquals('Pembayaran listrik, air, internet', $coa->fresh()->arus_kas_kelompok);
    }

    public function test_restricted_delete_when_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Gaji',
            'status_aktif' => true,
        ]);

        Coa::create([
            'kode' => '5.1.02',
            'nama' => 'Gaji Dokter',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Gaji',
        ]);

        $check = $this->service->canDelete($ref);
        $this->assertFalse($check['allowed']);
        $this->assertEquals(1, $check['coa_count']);

        $this->expectException(DomainException::class);
        $this->service->delete($ref);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/ReferensiKelompokArusKasServiceTest.php`  
Expected: FAIL with "Target class [App\Services\Pengaturan\ReferensiKelompokArusKasService] does not exist".

- [ ] **Step 3: Implement Form Requests and Service Class**

1. Buat `StoreReferensiKelompokArusKasRequest`:
   - Validasi: `aktivitas` in: `operasi,investasi,pendanaan`, `nama` required string max:150.
   - Pengecekan keunikan case-insensitive: tidak boleh ada nama yang sama pada aktivitas yang sama.
2. Buat `UpdateReferensiKelompokArusKasRequest`:
   - Validasi: `nama` required string max:150, `status_aktif` required boolean.
   - Pengecekan keunikan case-insensitive ignore current id.
3. Buat `ReferensiKelompokArusKasService`:
   - `sanitizeName(string $name): string`: `trim(preg_replace('/\s+/', ' ', $name))`
   - `ensureExists(string $aktivitas, string $nama): ReferensiKelompokArusKas`:
     Cari dengan `where('aktivitas', $aktivitas)->whereRaw('LOWER(nama) = ?', [strtolower($cleanName)])->first()`. Jika ada, kembalikan. Jika tidak ada, create dengan `status_aktif = true`.
   - `update(ReferensiKelompokArusKas $ref, array $data): ReferensiKelompokArusKas`:
     Jalankan dalam `DB::transaction`. Simpan nama lama. Update record `$ref`. Jika nama berubah, eksekusi `Coa::where('arus_kas_aktivitas', $ref->aktivitas)->where('arus_kas_kelompok', $oldName)->update(['arus_kas_kelompok' => $cleanName])`.
   - `canDelete(ReferensiKelompokArusKas $ref): array`:
     Hitung `Coa::where('arus_kas_aktivitas', $ref->aktivitas)->where('arus_kas_kelompok', $ref->nama)->count()`. Jika count > 0, return `['allowed' => false, 'coa_count' => $count, 'reason' => "..."]`.
   - `delete(ReferensiKelompokArusKas $ref): void`:
     Cek `canDelete`. Jika tidak boleh, `throw new DomainException(...)`. Jika boleh, `$ref->delete()`.
   - `toggleStatus(ReferensiKelompokArusKas $ref): bool`:
     Toggle boolean `status_aktif` dan save.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/ReferensiKelompokArusKasServiceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Pengaturan/StoreReferensiKelompokArusKasRequest.php app/Http/Requests/Pengaturan/UpdateReferensiKelompokArusKasRequest.php app/Services/Pengaturan/ReferensiKelompokArusKasService.php tests/Unit/Services/ReferensiKelompokArusKasServiceTest.php
git commit -m "feat: implement ReferensiKelompokArusKasService and form requests with cascade and guardrails"
```

---

### Task 4: Controller & UI Blade Views (CRUD, Modal Edit, Restricted Delete Alert, & Status Toggle)

**Files:**
- Create: `app/Http/Controllers/Pengaturan/ReferensiKelompokArusKasController.php`
- Create: `resources/views/pengaturan/referensi-kelompok-arus-kas/index.blade.php`
- Create: `tests/Feature/Pengaturan/ReferensiKelompokArusKasCrudTest.php`

**Interfaces:**
- Consumes: `ReferensiKelompokArusKasService`, `StoreReferensiKelompokArusKasRequest`, `UpdateReferensiKelompokArusKasRequest`
- Produces: Halaman manajemen master referensi dengan tabel terurut alfabetis (`nama ASC`), filter tab aktivitas (Semua, Operasi, Investasi, Pendanaan), modal Tambah & Ubah, tombol toggle status aktif, dan tombol hapus dengan konfirmasi & penolakan restricted delete.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Pengaturan/ReferensiKelompokArusKasCrudTest.php
namespace Tests\Feature\Pengaturan;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasCrudTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $adminRole = Role::create(['nama' => 'Admin', 'kode' => 'admin', 'is_system' => true]);
        $module = AccessModule::create([
            'kode' => 'pengaturan.referensi-kelompok-arus-kas',
            'nama' => 'Referensi Kelompok Arus Kas',
            'group_nama' => 'Pengaturan',
            'urutan' => 205,
        ]);

        RolePermission::create([
            'role_id' => $adminRole->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role_id' => $adminRole->id]);
    }

    private function prepareSchema(): void
    {
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
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
            $table->string('nama', 100);
            $table->string('tipe_coa', 50);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->timestamps();
        });
    }

    public function test_store_creates_sanitized_reference(): void
    {
        $response = $this->actingAs($this->admin)->post(route('pengaturan.referensi-kelompok-arus-kas.store'), [
            'aktivitas' => 'operasi',
            'nama' => '  Penerimaan   Klaim BPJS  ',
        ]);

        $response->assertRedirect(route('pengaturan.referensi-kelompok-arus-kas.index'));
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan Klaim BPJS',
            'status_aktif' => true,
        ]);
    }

    public function test_delete_blocked_when_used_by_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Operasional',
            'status_aktif' => true,
        ]);

        Coa::create([
            'kode' => '5.2.01',
            'nama' => 'Operasional Kantor',
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Operasional',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('pengaturan.referensi-kelompok-arus-kas.destroy', $ref));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', ['id' => $ref->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasCrudTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement Controller and Blade Views**

1. Implementasikan `App\Http\Controllers\Pengaturan\ReferensiKelompokArusKasController`:
   - `index()`: Ambil query dengan filter aktivitas & pencarian kata kunci, urutkan `orderBy('nama', 'asc')`, paginasi atau list.
   - `store(StoreReferensiKelompokArusKasRequest $request)`: Panggil `service->store()`, redirect with flash success.
   - `update(UpdateReferensiKelompokArusKasRequest $request, ReferensiKelompokArusKas $referensi)`: Panggil `service->update()`, redirect with flash success.
   - `destroy(ReferensiKelompokArusKas $referensi)`: Cek `service->canDelete()`. Jika tidak boleh, redirect back with flash error. Jika boleh, `service->delete()`, redirect with flash success.
   - `toggleStatus(ReferensiKelompokArusKas $referensi)`: Panggil `service->toggleStatus()`, redirect back with flash success.
2. Buat view `resources/views/pengaturan/referensi-kelompok-arus-kas/index.blade.php`:
   - Gunakan layout `@extends('layouts.app')`.
   - Card styling konsisten SIAKURAT (`border-muhammadiyah`).
   - Tombol "Tambah Pos Referensi" yang membuka Modal Create.
   - Tabel: Kolom No, Aktivitas (Badge warna per aktivitas: Operasi=Primary, Investasi=Success, Pendanaan=Info), Nama Kelompok Arus Kas, Status Aktif (Badge + Toggle button), Jumlah COA Terkait, Aksi (Tombol Edit Modal & Tombol Hapus dengan konfirmasi).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasCrudTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Pengaturan/ReferensiKelompokArusKasController.php resources/views/pengaturan/referensi-kelompok-arus-kas/index.blade.php tests/Feature/Pengaturan/ReferensiKelompokArusKasCrudTest.php
git commit -m "feat: implement controller and view for referensi kelompok arus kas"
```

---

### Task 5: Database Seeder `ReferensiKelompokArusKasSeeder`

**Files:**
- Create: `database/seeders/ReferensiKelompokArusKasSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/Pengaturan/ReferensiKelompokArusKasSeederTest.php`

**Interfaces:**
- Consumes: 14 pos standar draf Excel, tabel `coa` eksisting
- Produces: 14 pos standar ter-seed dengan `status_aktif = true`, dan seluruh pos lama yang belum terdaftar di-seed dengan `status_aktif = false`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Pengaturan/ReferensiKelompokArusKasSeederTest.php
namespace Tests\Feature\Pengaturan;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use Database\Seeders\ReferensiKelompokArusKasSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');

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
            $table->string('nama', 100);
            $table->string('tipe_coa', 50);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->timestamps();
        });

        // Insert legacy unstandardized data
        Coa::create([
            'kode' => '1.1.01',
            'nama' => 'Kas Operasional',
            'tipe_coa' => 'Kas',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan dari pasien dan pelanggan',
        ]);
    }

    public function test_seeder_populates_14_standard_active_and_legacy_inactive(): void
    {
        $this->seed(ReferensiKelompokArusKasSeeder::class);

        // 14 standard items should be active
        $this->assertEquals(14, ReferensiKelompokArusKas::where('status_aktif', true)->count());

        // Legacy pos should be seeded as inactive
        $legacy = ReferensiKelompokArusKas::where('nama', 'Penerimaan dari pasien dan pelanggan')->first();
        $this->assertNotNull($legacy);
        $this->assertFalse($legacy->status_aktif);

        // Verify idempotency (re-running does not duplicate)
        $this->seed(ReferensiKelompokArusKasSeeder::class);
        $this->assertEquals(14, ReferensiKelompokArusKas::where('status_aktif', true)->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasSeederTest.php`  
Expected: FAIL with "Target class [Database\Seeders\ReferensiKelompokArusKasSeeder] does not exist".

- [ ] **Step 3: Implement `ReferensiKelompokArusKasSeeder`**

Implementasikan seeder dengan array 14 pos standar:
- Operasi (10):
  - Penerimaan kas dari pasien non BPJS Kesehatan
  - Penerimaan klaim BPJS Kesehatan
  - Penerimaan lainnya
  - Pembayaran gaji karyawan & jasa medis dokter
  - Pembayaran pemasok obat, alat kesehatan & BHP
  - Pembayaran listrik, air, internet
  - Pembayaran marketing & PKRS
  - Pembayaran dana dakwah
  - Pembayaran pajak
  - Pembayaran beban administrasi, umum & operasional lainnya
- Investasi (2):
  - Pembelian & pembayaran hutang aset tetap
  - Penerimaan penjualan aset tetap bekas
- Pendanaan (2):
  - Penerimaan pinjaman/pembiayaan bank
  - Pembayaran angsuran pokok & bunga pinjaman
Loop pos standar dan gunakan `updateOrCreate(['aktivitas' => ..., 'nama' => ...], ['status_aktif' => true])`.  
Lalu query `Coa::whereNotNull('arus_kas_kelompok')->whereNotNull('arus_kas_aktivitas')->select('arus_kas_aktivitas', 'arus_kas_kelompok')->distinct()->get()`. Untuk setiap baris yang belum ada di `referensi_kelompok_arus_kas`, masukkan dengan `status_aktif => false`.  
Daftarkan pemanggilan seeder di `database/seeders/DatabaseSeeder.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Pengaturan/ReferensiKelompokArusKasSeederTest.php`  
Expected: PASS.  
Run: `php artisan db:seed --class=ReferensiKelompokArusKasSeeder`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ReferensiKelompokArusKasSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Pengaturan/ReferensiKelompokArusKasSeederTest.php
git commit -m "feat: add ReferensiKelompokArusKasSeeder with 14 standard pos and inactive legacy records"
```

---

### Task 6: COA Form Enhancement (Chained Select2, Tag Confirmation, & Backend Auto-Insert)

**Files:**
- Modify: `app/Http/Controllers/Bukubesar/CoaController.php:35-85`
- Modify: `resources/views/bukubesar/coa/_form.blade.php:80-90`
- Modify: `resources/views/bukubesar/coa/create.blade.php:30-55`
- Modify: `resources/views/bukubesar/coa/edit.blade.php:30-55`
- Modify: `app/Services/Bukubesar/CoaService.php:140-205`
- Create: `tests/Feature/Bukubesar/CoaArusKasTaggingTest.php`

**Interfaces:**
- Consumes: `ReferensiKelompokArusKasService::getGroupedActive()`, `ReferensiKelompokArusKasService::ensureExists()`
- Produces: Form COA menyajikan dropdown chained Select2 dengan opsi tersaring sesuai `arus_kas_aktivitas`. User dapat membuat tag baru dengan konfirmasi JavaScript, dan backend otomatis mendaftarkan tag baru ke `referensi_kelompok_arus_kas` saat COA disimpan.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Bukubesar/CoaArusKasTaggingTest.php
namespace Tests\Feature\Bukubesar;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaArusKasTaggingTest extends TestCase
{
    private User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSchema();

        $role = Role::create(['nama' => 'Accounting', 'kode' => 'akunting', 'is_system' => false]);
        $module = AccessModule::create([
            'kode' => 'bukubesar.coa',
            'nama' => 'COA',
            'group_nama' => 'Bukubesar',
            'urutan' => 30,
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->accountingUser = User::create([
            'name' => 'Akuntan',
            'email' => 'akuntan@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);
    }

    private function prepareSchema(): void
    {
        Schema::dropIfExists('coa');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
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
    }

    public function test_saving_coa_with_new_tag_auto_creates_master_reference(): void
    {
        $response = $this->actingAs($this->accountingUser)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Pembayaran Biaya Riset & Pengembangan',
            'kode' => '5.9.01',
            'nama' => 'Biaya Litbang',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coa', [
            'kode' => '5.9.01',
            'arus_kas_kelompok' => 'Pembayaran Biaya Riset & Pengembangan',
        ]);

        // Auto-insert to master referensi
        $this->assertDatabaseHas('referensi_kelompok_arus_kas', [
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Biaya Riset & Pengembangan',
            'status_aktif' => true,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Bukubesar/CoaArusKasTaggingTest.php`  
Expected: FAIL (tabel `referensi_kelompok_arus_kas` belum memiliki record tag baru).

- [ ] **Step 3: Implement COA Form & Service Changes**

1. Pada `CoaController@create` dan `@edit`, oper data referensi kelompok arus kas aktif yang dikelompokkan berdasarkan aktivitas:
   ```php
   $referensiKelompok = ReferensiKelompokArusKas::aktif()->orderBy('nama')->get()->groupBy('aktivitas');
   ```
2. Pada `resources/views/bukubesar/coa/_form.blade.php`:
   Ubah input teks `arus_kas_kelompok` menjadi elemen `<select>` Select2 dengan opsi `<option value="">Pilih atau ketik baru...</option>`. Simpan mapping referensi dalam script / data attribute JSON.
3. Pada `create.blade.php` & `edit.blade.php`:
   - Inisialisasi Select2 pada `#arus_kas_kelompok` dengan opsi `tags: true`.
   - Pasang event handler `change` pada `#arus_kas_aktivitas` untuk memperbarui opsi `#arus_kas_kelompok` sesuai aktivitas yang dipilih (*chained autocomplete*).
   - Pasang event `select2:selecting` pada `#arus_kas_kelompok`: Jika user memilih opsi yang baru diketik (bukan dari daftar preset yang ada), munculkan konfirmasi JavaScript (dialog `confirm()` atau modal) untuk memastikan user benar-benar ingin menambahkan pos baru.
4. Pada `CoaService@create` dan `@update`:
   Sebelum/sesudah save, jika `arus_kas_kelompok` terisi dan bukan Kasbank, panggil `app(ReferensiKelompokArusKasService::class)->ensureExists($aktivitas, $kelompok)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Bukubesar/CoaArusKasTaggingTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Bukubesar/CoaController.php resources/views/bukubesar/coa/_form.blade.php resources/views/bukubesar/coa/create.blade.php resources/views/bukubesar/coa/edit.blade.php app/Services/Bukubesar/CoaService.php tests/Feature/Bukubesar/CoaArusKasTaggingTest.php
git commit -m "feat: enhance COA form with chained select2 tags and auto-insert to master referensi"
```

---

### Task 7: Technical Documentation & Final Verification

**Files:**
- Create: `dokumentasi/pengaturan-referensi-kelompok-arus-kas.md`
- Modify: `dokumentasi/README.md`
- Modify: `dokumentasi/fondasi/sistem-otorisasi.md`
- Modify: `AGENTS.md`

**Interfaces:**
- Consumes: Real implementation details of routes, permissions, and service methods.
- Produces: Dokumentasi modul yang sinkron dengan kode dan konvensi dokumentasi SIAKURAT.

- [ ] **Step 1: Create Module Documentation**

Buat `dokumentasi/pengaturan-referensi-kelompok-arus-kas.md` menggunakan panduan `_template-modul.md`:
- Ringkasan Modul & Tujuan Bisnis
- Entry Point / Tabel API (termasuk permission middleware)
- Validasi Request (`StoreReferensiKelompokArusKasRequest`, `UpdateReferensiKelompokArusKasRequest`)
- Flowchart & Algoritma (Create, Cascade Update, Restricted Delete, Auto-tagging dari COA)
- Fungsi yang Dipanggil & Efek Samping Database

- [ ] **Step 2: Update Foundation Documentation & Registry Index**

1. Tambahkan baris modul `pengaturan.referensi-kelompok-arus-kas` pada daftar tabel modul di `dokumentasi/fondasi/sistem-otorisasi.md`.
2. Tambahkan baris modul pada daftar tabel di `dokumentasi/README.md`.
3. Tambahkan baris pemetaan di tabel *Code → Documentation Mapping* pada `AGENTS.md`.

- [ ] **Step 3: Run Full Regression Tests & Code Formatter**

Run:
```bash
./vendor/bin/pint --test
php artisan test
```
Pastikan seluruh test pass tanpa error atau degradasi performa.

- [ ] **Step 4: Commit Documentation & Cleanup**

```bash
git add dokumentasi/pengaturan-referensi-kelompok-arus-kas.md dokumentasi/README.md dokumentasi/fondasi/sistem-otorisasi.md AGENTS.md
git commit -m "docs: add technical documentation for referensi kelompok arus kas module"
```
