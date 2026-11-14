# Indikator Kinerja Eksekutif & Pengaturan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Menghadirkan sistem monitoring eksekutif otomatis berbasis 11 Indikator Kinerja Kunci (RAG + Netral), strip ringkasan cepat di Home Dashboard (`/home`), dashboard dedikasi interaktif (`/dashboard/indikator-kinerja`), dan menu konfigurasi terpadu (`/pengaturan/setting-indikator-kinerja`).

**Architecture:** Dibangun di atas *Dedicated Service* tunggal `App\Services\IndikatorKinerja\IndikatorKinerjaService` yang kohesif dan terisolasi, didukung PHP 8.2 Backed Enums di layer aplikasi, skema tabel fleksibel berbasis `VARCHAR(30)` tanpa MySQL native ENUM, serta thin controller dengan form dedikasi (`create.blade.php` & `edit.blade.php`) dan proteksi RBAC `AccessModuleRegistry`.

**Tech Stack:** Laravel 12, PHP 8.2 Backed Enums, MySQL, Blade, Bootstrap 5 / Tailwind CSS, Chart.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-12-indikator-kinerja-design.md`

## Global Constraints
- Laravel 12 & PHP 8.2: Seluruh enum menggunakan PHP 8.2 Backed Enum bertipe string.
- Database: Seluruh kolom pilihan di database menggunakan tipe `VARCHAR(30)`, dilarang menggunakan MySQL native `ENUM`.
- Thin Controller: Logika bisnis dan kalkulasi 100% berada di `IndikatorKinerjaService`, controller hanya mengelola request, validasi, dan response.
- Safe Division: Pembagian dengan penyebut nol atau null tidak boleh memicu error 500 dan harus menghasilkan `StatusEvaluasi::NETRAL`.
- Performance Home Dashboard: Strip 4 kotak RAG di `/home` dimuat secara asinkron via AJAX (`/home/ringkasan-indikator`) agar tidak memperlambat initial render `/home`.
- Git commits: Tambahkan flag `-f` bila melakukan commit file di dalam direktori `docs/` (`git add -f docs/...`).

## Review Focus
1. **Division by Zero & Data Nihil:** Ketika nilai penyebut bernilai `0` atau transaksi belum ada, fungsi `safeDivide()` mengembalikan `null` dan evaluasi menghasilkan `StatusEvaluasi::NETRAL`. (Diverifikasi di Task 3)
2. **Saldo Neraca vs Mutasi Laba Rugi pada Turnover:** Indikator *Collection Period* (`COA 112`) dan *Perputaran Persediaan* (`COA 113`) mengambil saldo kumulatif per `endDate`, sedangkan penyebutnya (`COA 4`) mengambil mutasi periode `[startDate, endDate]`. (Diverifikasi di Task 3)
3. **Penyaringan Akun Excluded (Leaf Resolver):** Akun cabang yang ditandai `is_excluded = true` (seperti `COA 113.04` Logistik RT) harus secara akurat tereliminasi dari kalkulasi leaf parent `COA 113`. (Diverifikasi di Task 3)
4. **Skala Nominal Satuan Juta:** Indikator *Produktivitas Karyawan* menormalisasi nilai rupiah ke satuan Juta (dibagi 1.000.000) sebelum dievaluasi terhadap target desimal `22.00`. (Diverifikasi di Task 3)
5. **Fallback Entri Data Manual Terakhir:** Jika data bulan berjalan belum diisi di `indikator_kinerja_manual_data`, sistem mengambil data entri terakhir yang tersedia sebelum/pada periode berjalan. (Diverifikasi di Task 3)
6. **Bulan Masa Depan pada Grafik Tren:** Untuk tahun berjalan, bulan-bulan setelah bulan berjalan mengembalikan `nilai: null` pada endpoint tren JSON agar garis grafik tidak jatuh ke 0. (Diverifikasi di Task 3 dan Task 5)

---

## File Structure & Responsibilities

```text
app/
├── Enums/IndikatorKinerja/
│   ├── SatuanIndikator.php         # Enum satuan (%, Hari, Juta/org/bln, Rp, Kali)
│   ├── TipeKalkulasi.php           # Enum tipe kalkulasi (RASIO, TURNOVER, MANUAL)
│   ├── BasisPembilang.php          # Enum basis pembilang (COA, SURPLUS, NONE)
│   ├── BasisPenyebut.php           # Enum basis penyebut (COA, RBA, MANUAL, NONE)
│   ├── PolaEvaluasi.php            # Enum pola evaluasi (HIGHER_IS_BETTER, LOWER_IS_BETTER, TARGET_BAND)
│   ├── PeranCoa.php                # Enum peran mapping COA (numerator, denominator, addition, deduction)
│   └── StatusEvaluasi.php          # Enum status hasil evaluasi (hijau, kuning, merah, netral)
├── Models/
│   ├── IndikatorKinerjaVariabelManual.php # Master variabel manual (BAHV JKN, SDM Non Organik)
│   ├── IndikatorKinerjaManualData.php     # Matriks nilai bulanan per variabel manual
│   ├── IndikatorKinerja.php               # Master indikator kinerja
│   └── IndikatorKinerjaMappingCoa.php     # Mapping relasi COA per indikator
├── Services/IndikatorKinerja/
│   └── IndikatorKinerjaService.php # Business logic, query agregasi in-memory, evaluasi RAG
├── Http/
│   ├── Controllers/
│   │   ├── Dashboard/
│   │   │   └── IndikatorKinerjaDashboardController.php # Controller dashboard indikator & endpoint tren JSON
│   │   └── Pengaturan/
│   │       └── SettingIndikatorKinerjaController.php   # Controller konfigurasi indikator, create/edit, & entri data
│   └── Requests/Pengaturan/
│       ├── StoreIndikatorKinerjaRequest.php  # Validasi form create indikator
│       ├── UpdateIndikatorKinerjaRequest.php # Validasi form edit indikator
│       ├── StoreVariabelManualRequest.php    # Validasi tambah variabel manual baru
│       └── SimpanManualDataRequest.php       # Validasi entri spreadsheet data manual
resources/views/
├── dashboard/indikator-kinerja/
│   └── index.blade.php                       # Dashboard dedikasi: filter, card grid, table view, modal Chart.js
├── pengaturan/setting-indikator-kinerja/
│   ├── index.blade.php                       # Halaman tab 1 (indikator) & tab 2 (entri data manual)
│   ├── create.blade.php                      # Halaman tersendiri form tambah indikator & mapping COA
│   └── edit.blade.php                        # Halaman tersendiri form edit indikator & mapping COA
└── home.blade.php                            # Blade home diperbarui dengan status strip AJAX EWS
```

---

## Tasks

### Task 1: Enums & Skema Database (Migrations & Models)

**Files:**
- Create: `app/Enums/IndikatorKinerja/SatuanIndikator.php`
- Create: `app/Enums/IndikatorKinerja/TipeKalkulasi.php`
- Create: `app/Enums/IndikatorKinerja/BasisPembilang.php`
- Create: `app/Enums/IndikatorKinerja/BasisPenyebut.php`
- Create: `app/Enums/IndikatorKinerja/PolaEvaluasi.php`
- Create: `app/Enums/IndikatorKinerja/PeranCoa.php`
- Create: `app/Enums/IndikatorKinerja/StatusEvaluasi.php`
- Create: `database/migrations/2026_11_13_000001_create_indikator_kinerja_variabel_manual_table.php`
- Create: `database/migrations/2026_11_13_000002_create_indikator_kinerja_table.php`
- Create: `database/migrations/2026_11_13_000003_create_indikator_kinerja_mapping_coa_table.php`
- Create: `database/migrations/2026_11_13_000004_create_indikator_kinerja_manual_data_table.php`
- Create: `app/Models/IndikatorKinerjaVariabelManual.php`
- Create: `app/Models/IndikatorKinerjaManualData.php`
- Create: `app/Models/IndikatorKinerja.php`
- Create: `app/Models/IndikatorKinerjaMappingCoa.php`
- Test: `tests/Unit/IndikatorKinerjaModelTest.php`

**Interfaces:**
- Produces:
  - Backed Enums di namespace `App\Enums\IndikatorKinerja`
  - Eloquent Models `IndikatorKinerja`, `IndikatorKinerjaMappingCoa`, `IndikatorKinerjaVariabelManual`, `IndikatorKinerjaManualData` dengan casts yang tepat.

- [x] **Step 1: Tulis failing test model & casting**

File: `tests/Unit/IndikatorKinerjaModelTest.php`
```php
<?php

namespace Tests\Unit;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\IndikatorKinerjaVariabelManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKinerjaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_indikator_with_enum_casts(): void
    {
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'sdm_non_organik',
            'nama' => 'Jumlah SDM Non Organik',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'capaian_pendapatan',
            'nama' => 'Capaian Pendapatan',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::RBA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.00,
            'status_aktif' => true,
        ]);

        $this->assertInstanceOf(SatuanIndikator::class, $variabel->satuan);
        $this->assertEquals(SatuanIndikator::ORANG, $variabel->satuan);
        $this->assertInstanceOf(SatuanIndikator::class, $indikator->satuan);
        $this->assertEquals(SatuanIndikator::PERSEN, $indikator->satuan);
        $this->assertInstanceOf(TipeKalkulasi::class, $indikator->tipe_kalkulasi);
        $this->assertInstanceOf(BasisPembilang::class, $indikator->basis_pembilang);
        $this->assertInstanceOf(BasisPenyebut::class, $indikator->basis_penyebut);
        $this->assertInstanceOf(PolaEvaluasi::class, $indikator->pola_evaluasi);
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Unit/IndikatorKinerjaModelTest.php`
Expected: FAIL (Class enum/model tidak ditemukan).

- [x] **Step 3: Implementasikan Enums, Migrations, dan Models**
1. Buat enum `SatuanIndikator`, `TipeKalkulasi`, `BasisPembilang`, `BasisPenyebut`, `PolaEvaluasi`, `PeranCoa`, `StatusEvaluasi` di `app/Enums/IndikatorKinerja/`.
2. Buat migration skema database sesuai urutan:
   - `indikator_kinerja_variabel_manual` (id, kode, nama, satuan, deskripsi, timestamps)
   - `indikator_kinerja` (id, kode, nama, satuan VARCHAR(20), tipe_kalkulasi VARCHAR(30), basis_pembilang VARCHAR(30) NULL, basis_penyebut VARCHAR(30) NULL, variabel_manual_id INT NULL FK, definisi_operasional TEXT, urutan INT, status_aktif BOOLEAN, pola_evaluasi VARCHAR(30), target decimals, timestamps)
   - `indikator_kinerja_mapping_coa` (id, indikator_id FK CASCADE, coa_id FK CASCADE, peran VARCHAR(30), is_excluded BOOLEAN, timestamps)
   - `indikator_kinerja_manual_data` (id, variabel_id FK CASCADE, tahun INT, bulan TINYINT, nilai DECIMAL(15,2), catatan VARCHAR(255), UNIQUE(variabel_id, tahun, bulan), timestamps)
3. Buat model Eloquent `IndikatorKinerjaVariabelManual`, `IndikatorKinerjaManualData`, `IndikatorKinerja`, dan `IndikatorKinerjaMappingCoa` lengkap dengan relasi dan casts.

- [x] **Step 4: Jalankan test kembali untuk memverifikasi kelulusan**
Command: `php artisan test tests/Unit/IndikatorKinerjaModelTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Enums/IndikatorKinerja app/Models/IndikatorKinerja* database/migrations/*indikator_kinerja* tests/Unit/IndikatorKinerjaModelTest.php
rtk git commit -m "feat(kpi): add indicator enums, models, and migrations"
```

---

### Task 2: Seeder 11 Indikator Baseline & Master Variabel Manual

**Files:**
- Create: `database/seeders/IndikatorKinerjaSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/IndikatorKinerjaSeederTest.php`

**Interfaces:**
- Consumes: Models `IndikatorKinerja`, `IndikatorKinerjaMappingCoa`, `IndikatorKinerjaVariabelManual`, `Coa`
- Produces: 2 data master variabel manual (`bahv_jkn`, `sdm_non_organik`), 11 data master indikator baseline, dan default mapping COA (misal: `COA 4` untuk pendapatan operasional, `COA 6` untuk SDI, `COA 113` dengan exclude `COA 113.04`, dll.).

- [x] **Step 1: Tulis failing test seeder**

File: `tests/Feature/IndikatorKinerjaSeederTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaVariabelManual;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKinerjaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_11_baseline_indicators_and_manual_variables(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);

        $this->assertEquals(2, IndikatorKinerjaVariabelManual::count());
        $this->assertEquals(11, IndikatorKinerja::count());
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'capaian_pendapatan']);
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'ebitda']);
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'collection_period']);
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'kualitas_klaim_jkn']);
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/IndikatorKinerjaSeederTest.php`
Expected: FAIL (IndikatorKinerjaSeeder does not exist).

- [x] **Step 3: Implementasikan `IndikatorKinerjaSeeder`**
1. Daftarkan variabel manual:
   - `bahv_jkn` (Satuan: `%`, Nama: `Persentase Lolos BAHV Pertama JKN`)
   - `sdm_non_organik` (Satuan: `Orang`, Nama: `Jumlah SDM Non Organik`)
2. Daftarkan 11 indikator baseline sesuai tabel 5.1 spesifikasi dengan parameter ambang batas Hijau, Kuning, Merah, tipe kalkulasi, basis pembilang, basis penyebut, dan deskripsi operasional.
3. Sambungkan mapping COA default jika record COA dengan kode terkait ditemukan di database (menggunakan query `firstWhere('kode', ...)`).

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/IndikatorKinerjaSeederTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add database/seeders/IndikatorKinerjaSeeder.php tests/Feature/IndikatorKinerjaSeederTest.php
rtk git commit -m "feat(kpi): add database seeder for 11 baseline executive indicators"
```

---

### Task 3: Core Service (`IndikatorKinerjaService`) & Business Calculations

**Files:**
- Create: `app/Services/IndikatorKinerja/IndikatorKinerjaService.php`
- Test: `tests/Unit/IndikatorKinerjaServiceTest.php`

**Interfaces:**
- Consumes: Models `IndikatorKinerja`, `Coa`, `BukuBesar`, `SettingRba`, `IndikatorKinerjaManualData`
- Produces:
  ```php
  public function evaluasiSemua(string $startDate, string $endDate): Collection;
  public function evaluasiIndikator(IndikatorKinerja $indikator, string $startDate, string $endDate, ?array $preloadedData = null): array;
  public function getRingkasanStatus(string $startDate, string $endDate): array;
  public function getTrenBulanan(string $kode, int $tahun): array;
  ```

- [x] **Step 1: Tulis failing unit test untuk kalkulasi, safe division, turnover, dan scaling**

File: `tests/Unit/IndikatorKinerjaServiceTest.php`
```php
<?php

namespace Tests\Unit;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\StatusEvaluasi;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\IndikatorKinerja;
use App\Services\IndikatorKinerja\IndikatorKinerjaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKinerjaServiceTest extends TestCase
{
    use RefreshDatabase;

    private IndikatorKinerjaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IndikatorKinerjaService::class);
    }

    public function test_safe_divide_returns_null_on_zero_or_null_denominator(): void
    {
        $this->assertNull($this->service->safeDivide(100, 0));
        $this->assertNull($this->service->safeDivide(100, null));
        $this->assertEquals(50.0, $this->service->safeDivide(100, 2));
    }

    public function test_evaluasi_status_higher_is_better(): void
    {
        $indikator = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.0,
            'target_kuning_min' => 90.0,
        ]);

        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(105.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(95.0, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(85.0, $indikator));
        $this->assertEquals(StatusEvaluasi::NETRAL, $this->service->evaluasiStatus(null, $indikator));
    }

    public function test_evaluasi_status_target_band_with_fallback(): void
    {
        $indikator = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::TARGET_BAND,
            'target_hijau_min' => 20.0,
            'target_hijau_max' => 25.0,
            'target_kuning_min' => 25.01,
            'target_kuning_max' => 30.0,
        ]);

        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(22.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(28.0, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(35.0, $indikator)); // >30
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(15.0, $indikator)); // <20
    }

    public function test_tren_bulanan_returns_null_for_future_months_in_current_year(): void
    {
        $indikator = IndikatorKinerja::create([
            'kode' => 'capaian_pendapatan',
            'nama' => 'Capaian Pendapatan',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::RBA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.0,
        ]);

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $tren = $this->service->getTrenBulanan('capaian_pendapatan', $currentYear);

        if ($currentMonth < 12) {
            $futureMonth = $currentMonth + 1;
            $entry = collect($tren['data'])->firstWhere('bulan', $futureMonth);
            $this->assertNotNull($entry);
            $this->assertNull($entry['nilai']);
            $this->assertEquals(StatusEvaluasi::NETRAL, $entry['status']);
        }
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Unit/IndikatorKinerjaServiceTest.php`
Expected: FAIL (Service class not found).

- [x] **Step 3: Implementasikan `IndikatorKinerjaService`**
1. Implementasikan fungsi safe math:
   - `safeDivide(?float $num, ?float $denom): ?float`
   - `evaluasiStatus(?float $nilai, IndikatorKinerja $indikator): StatusEvaluasi`
2. Implementasikan Leaf Resolver & Query Agregasi:
   - `kumpulkanLeafDescendantCoaIds(array $coaIds, array $excludedIds = []): array`
   - `ambilMutasiCoa(array $coaIds, string $startDate, string $endDate): float` (untuk akun Laba Rugi)
   - `ambilSaldoNeracaCoa(array $coaIds, string $perDate): float` (untuk akun Neraca kumulatif per `endDate`)
   - `ambilRbaCoa(array $coaIds, string $startDate, string $endDate): float`
   - `ambilSurplusPeriode(string $startDate, string $endDate): float`
   - `ambilNilaiManualTerakhir(int $variabelId, string $endDate): array` (mengembalikan `['nilai' => float|null, 'periode' => string]`)
3. Implementasikan fungsi kalkulasi per tipe (`RASIO`, `TURNOVER`, `MANUAL`) serta penanganan normalisasi skala `/ 1.000.000` untuk satuan `Juta/org/bln`.
4. Implementasikan `evaluasiSemua($startDate, $endDate)`, `getRingkasanStatus($startDate, $endDate)`, dan `getTrenBulanan($kode, $tahun)`:
   - Pada `getTrenBulanan`: iterasi bulan 1 sampai 12. Untuk tahun berjalan (`$tahun === (int) date('Y')`), bulan yang lebih besar dari bulan berjalan (`$bulan > (int) date('n')`) mengembalikan `'nilai' => null` dan `'status' => StatusEvaluasi::NETRAL` agar visualisasi Chart.js berhenti di bulan aktual.

- [x] **Step 4: Jalankan test unit untuk memverifikasi kelulusan**
Command: `php artisan test tests/Unit/IndikatorKinerjaServiceTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Services/IndikatorKinerja/IndikatorKinerjaService.php tests/Unit/IndikatorKinerjaServiceTest.php
rtk git commit -m "feat(kpi): implement core IndikatorKinerjaService with RAG evaluation engine"
```

---

### Task 4: RBAC & Integrasi Status Strip di Home Dashboard (`/home`)

**Files:**
- Modify: `app/Support/AccessModuleRegistry.php`
- Modify: `app/Http/Controllers/HomeController.php`
- Modify: `resources/views/home.blade.php`
- Test: `tests/Feature/HomeDashboardRingkasanIndikatorTest.php`

**Interfaces:**
- Consumes: `IndikatorKinerjaService::getRingkasanStatus()`
- Produces:
  - Route: `GET /home/ringkasan-indikator` returning JSON status counter RAG
  - Modul otorisasi: `dashboard.indikator-kinerja` dan `pengaturan.setting-indikator-kinerja` di `AccessModuleRegistry`
  - Strip visual 4 kartu status RAG di `home.blade.php` yang di-load via AJAX.

- [x] **Step 1: Tulis failing feature test untuk endpoint `/home/ringkasan-indikator` dan RBAC**

File: `tests/Feature/HomeDashboardRingkasanIndikatorTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeDashboardRingkasanIndikatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_ringkasan_indikator_as_json(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('home.ringkasan-indikator', [
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'hijau',
                'kuning',
                'merah',
                'netral',
            ]);
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/HomeDashboardRingkasanIndikatorTest.php`
Expected: FAIL (Route `home.ringkasan-indikator` not defined).

- [x] **Step 3: Implementasikan endpoint AJAX dan komponen Blade di `/home`**
1. Daftarkan modul di `app/Support/AccessModuleRegistry.php`:
   - `['kode' => 'dashboard.indikator-kinerja', 'nama' => 'Indikator Kinerja', 'group_nama' => 'Dashboard', 'urutan' => 15]`
   - `['kode' => 'pengaturan.setting-indikator-kinerja', 'nama' => 'Setting Indikator Kinerja', 'group_nama' => 'Pengaturan', 'urutan' => 175]`
2. Tambahkan route di `routes/web.php`:
   `Route::get('/home/ringkasan-indikator', [HomeController::class, 'ringkasanIndikator'])->name('home.ringkasan-indikator');`
3. Tambahkan method `ringkasanIndikator(Request $request)` di `HomeController.php` yang memanggil `IndikatorKinerjaService::getRingkasanStatus()`.
4. Tambahkan strip HTML & script AJAX loader di `resources/views/home.blade.php` tepat di bawah filter tanggal dengan 4 kartu counter (Total, Hijau, Kuning, Merah) dan link CTA ke `/dashboard/indikator-kinerja`.

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/HomeDashboardRingkasanIndikatorTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Support/AccessModuleRegistry.php app/Http/Controllers/HomeController.php routes/web.php resources/views/home.blade.php tests/Feature/HomeDashboardRingkasanIndikatorTest.php
rtk git commit -m "feat(kpi): register RBAC modules and integrate executive status strip in home dashboard"
```

---

### Task 5: Halaman Dedicated Dashboard Indikator Kinerja (`/dashboard/indikator-kinerja`)

**Files:**
- Create: `app/Http/Controllers/Dashboard/IndikatorKinerjaDashboardController.php`
- Create: `resources/views/dashboard/indikator-kinerja/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardIndikatorKinerjaTest.php`

**Interfaces:**
- Consumes: `IndikatorKinerjaService::evaluasiSemua()`, `IndikatorKinerjaService::getTrenBulanan()`
- Produces:
  - Route: `GET /dashboard/indikator-kinerja` (View with Card Grid & Detailed Table view switcher)
  - Route: `GET /dashboard/indikator-kinerja/{kode}/tren` (JSON response 12 bulan untuk modal Chart.js)

- [x] **Step 1: Tulis failing test dashboard dan endpoint tren bulanan**

File: `tests/Feature/DashboardIndikatorKinerjaTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIndikatorKinerjaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_dashboard_indikator_kinerja(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        // Bypass or assign RBAC permission if required
        $response = $this->actingAs($user)->get(route('dashboard.indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('Indikator Kinerja');
    }

    public function test_user_can_fetch_tren_bulanan_json(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.indikator-kinerja.tren', [
            'kode' => 'capaian_pendapatan',
            'tahun' => now()->year,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'indikator' => ['kode', 'nama', 'satuan'],
                'data' => [
                    '*' => ['bulan', 'label_bulan', 'nilai', 'status'],
                ],
            ]);

        $currentMonth = (int) date('n');
        if ($currentMonth < 12) {
            $futureMonthEntry = collect($response->json('data'))->firstWhere('bulan', $currentMonth + 1);
            $this->assertNotNull($futureMonthEntry);
            $this->assertNull($futureMonthEntry['nilai']);
        }
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/DashboardIndikatorKinerjaTest.php`
Expected: FAIL (Route/Controller not found).

- [x] **Step 3: Implementasikan Controller dan Blade Dashboard**
1. Buat `IndikatorKinerjaDashboardController` dengan method:
   - `index(Request $request)`: mengevaluasi seluruh indikator via service dan merender `dashboard.indikator-kinerja.index`.
   - `tren(Request $request, string $kode)`: memanggil `service->getTrenBulanan($kode, $tahun)` dan mengembalikan `JsonResponse`.
2. Daftarkan routes dengan proteksi middleware otorisasi: `module.access:dashboard.indikator-kinerja,view`.
3. Buat view `resources/views/dashboard/indikator-kinerja/index.blade.php`:
   - Filter tanggal & search bar nama indikator.
   - Header 4 counter RAG (Total, Hijau, Kuning, Merah).
   - View switcher toggle: **Mode Kartu Eksekutif (Card Grid)** vs **Mode Tabel Rinci (Detailed Table)**.
   - Modal Drill-down: Menampilkan Definisi Operasional, acuan rumus, target status, dan canvas Chart.js untuk menampilkan grafik garis tren 12 bulan.

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/DashboardIndikatorKinerjaTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Http/Controllers/Dashboard/IndikatorKinerjaDashboardController.php resources/views/dashboard/indikator-kinerja/ routes/web.php tests/Feature/DashboardIndikatorKinerjaTest.php
rtk git commit -m "feat(kpi): create dedicated executive indicator dashboard with drill-down modal and Chart.js trend"
```

---

### Task 6: Menu Pengaturan Indikator Kinerja (`/pengaturan/setting-indikator-kinerja`) — Index 2 Tab & Form Requests

**Files:**
- Create: `app/Http/Requests/Pengaturan/StoreIndikatorKinerjaRequest.php`
- Create: `app/Http/Requests/Pengaturan/UpdateIndikatorKinerjaRequest.php`
- Create: `app/Http/Requests/Pengaturan/StoreVariabelManualRequest.php`
- Create: `app/Http/Requests/Pengaturan/SimpanManualDataRequest.php`
- Create: `app/Http/Controllers/Pengaturan/SettingIndikatorKinerjaController.php`
- Create: `resources/views/pengaturan/setting-indikator-kinerja/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SettingIndikatorKinerjaTest.php`

**Interfaces:**
- Consumes: Models `IndikatorKinerja`, `IndikatorKinerjaVariabelManual`, `IndikatorKinerjaManualData`
- Produces:
  - Halaman Index dengan Tab 1 (Manajemen Indikator) dan Tab 2 (Entri Data Manual Bulanan 12 bulan).
  - Endpoint simpan data spreadsheet bulanan & endpoint tambah variabel manual.

- [x] **Step 1: Tulis failing test untuk halaman index setting dan penyimpanan data manual**

File: `tests/Feature/SettingIndikatorKinerjaTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerjaVariabelManual;
use App\Models\User;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingIndikatorKinerjaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_setting_index_with_tabs(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('setting-indikator-kinerja.index'));
        $response->assertOk()
            ->assertSee('Manajemen Indikator')
            ->assertSee('Entri Data Manual');
    }

    public function test_user_can_save_manual_matrix_data(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $user = User::factory()->create();
        $variabel = IndikatorKinerjaVariabelManual::first();

        $response = $this->actingAs($user)->post(route('setting-indikator-kinerja.simpan-manual-data'), [
            'tahun' => 2026,
            'data' => [
                $variabel->id => [
                    1 => 92.5,
                    2 => 94.0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerja_manual_data', [
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 1,
            'nilai' => 92.50,
        ]);
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/SettingIndikatorKinerjaTest.php`
Expected: FAIL (Controller / Routes not found).

- [x] **Step 3: Implementasikan Form Requests, Controller Index, dan View Index**
1. Buat Form Requests: `SimpanManualDataRequest` dan `StoreVariabelManualRequest`.
2. Buat `SettingIndikatorKinerjaController`:
   - `index(Request $request)`: memuat master indikator dan matriks variabel manual untuk tahun yang dipilih (default `now()->year`).
   - `storeVariabel(StoreVariabelManualRequest $request)`: menambahkan variabel manual baru via modal ringkas.
   - `simpanManualData(SimpanManualDataRequest $request)`: menyimpan/memperbarui data bulanan secara batch dengan `updateOrCreate`.
3. Daftarkan routes dengan proteksi middleware RBAC: `module.access:pengaturan.setting-indikator-kinerja,view/create/update/delete`.
4. Buat view `resources/views/pengaturan/setting-indikator-kinerja/index.blade.php`:
   - Tab switching script berbasis URL parameter `?tab=indikator` atau `?tab=manual`.
   - Tab 1: Tabel master indikator dengan tombol [+ Tambah Indikator Baru], tombol Edit, dan Hapus.
   - Tab 2: Filter tahun, tombol [+ Tambah Variabel], tabel spreadsheet matriks 12 bulan (Januari s.d. Desember), dan tombol Simpan Data Bulanan.

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/SettingIndikatorKinerjaTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Http/Requests/Pengaturan/SimpanManualDataRequest.php app/Http/Requests/Pengaturan/StoreVariabelManualRequest.php app/Http/Controllers/Pengaturan/SettingIndikatorKinerjaController.php resources/views/pengaturan/setting-indikator-kinerja/index.blade.php routes/web.php tests/Feature/SettingIndikatorKinerjaTest.php
rtk git commit -m "feat(kpi): create setting indicator index with 2 tabs and manual matrix data entry"
```

---

### Task 7: Halaman Form Dedikasi Create & Edit Indikator Kinerja (`create.blade.php` & `edit.blade.php`)

**Files:**
- Create: `app/Http/Requests/Pengaturan/StoreIndikatorKinerjaRequest.php`
- Create: `app/Http/Requests/Pengaturan/UpdateIndikatorKinerjaRequest.php`
- Modify: `app/Http/Controllers/Pengaturan/SettingIndikatorKinerjaController.php`
- Create: `resources/views/pengaturan/setting-indikator-kinerja/create.blade.php`
- Create: `resources/views/pengaturan/setting-indikator-kinerja/edit.blade.php`
- Test: `tests/Feature/IndikatorKinerjaCrudTest.php`

**Interfaces:**
- Consumes: Models `IndikatorKinerja`, `IndikatorKinerjaMappingCoa`, `Coa`, `IndikatorKinerjaVariabelManual`
- Produces:
  - Form create di `/pengaturan/setting-indikator-kinerja/create`
  - Form edit di `/pengaturan/setting-indikator-kinerja/{indikator}/edit`
  - Alur simpan dengan transaksi database (`DB::transaction`) dan redirect ke index `?tab=indikator`.

- [x] **Step 1: Tulis failing test untuk create, update, dan delete indikator beserta mapping COA**

File: `tests/Feature/IndikatorKinerjaCrudTest.php`
```php
<?php

namespace Tests\Feature;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKinerjaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_indicator_with_mapping_coa(): void
    {
        $user = User::factory()->create();
        $coa = Coa::factory()->create(['kode' => '401', 'nama' => 'Pendapatan Rawat Jalan']);

        $response = $this->actingAs($user)->post(route('setting-indikator-kinerja.store'), [
            'kode' => 'kpi_test',
            'nama' => 'KPI Test',
            'satuan' => SatuanIndikator::PERSEN->value,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO->value,
            'basis_pembilang' => BasisPembilang::COA->value,
            'basis_penyebut' => BasisPenyebut::COA->value,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER->value,
            'target_hijau_min' => 80.0,
            'mappings' => [
                [
                    'coa_id' => $coa->id,
                    'peran' => PeranCoa::NUMERATOR->value,
                    'is_excluded' => false,
                ],
            ],
        ]);

        $response->assertRedirect(route('setting-indikator-kinerja.index', ['tab' => 'indikator']));
        $this->assertDatabaseHas('indikator_kinerja', ['kode' => 'kpi_test']);
        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'coa_id' => $coa->id,
            'peran' => PeranCoa::NUMERATOR->value,
        ]);
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/IndikatorKinerjaCrudTest.php`
Expected: FAIL (`create`, `edit`, `store`, `update`, `destroy` methods/routes not implemented).

- [x] **Step 3: Implementasikan Form Requests, Controller Methods, dan Views Create/Edit**
1. Buat `StoreIndikatorKinerjaRequest` dan `UpdateIndikatorKinerjaRequest` dengan validasi:
   - `kode` (required, unique/ignore), `nama` (required), `satuan` (`Rule::enum(SatuanIndikator::class)`), `tipe_kalkulasi` (`Rule::enum(TipeKalkulasi::class)`), `basis_pembilang` (nullable, `Rule::enum(BasisPembilang::class)`), `basis_penyebut` (nullable, `Rule::enum(BasisPenyebut::class)`), `pola_evaluasi` (`Rule::enum(PolaEvaluasi::class)`), target desimal, dan array `mappings.*`.
2. Implementasikan di `SettingIndikatorKinerjaController`:
   - `create()`: memuat list COA aktif dan master variabel manual, merender `create.blade.php`.
   - `store()`: menyimpan master indikator dan mapping COA dalam `DB::transaction()`, lalu `redirect()->route('setting-indikator-kinerja.index', ['tab' => 'indikator'])->with('success', ...)`.
   - `edit(IndikatorKinerja $indikator)`: memuat relasi mapping COA, merender `edit.blade.php`.
   - `update(UpdateIndikatorKinerjaRequest $request, IndikatorKinerja $indikator)`: mengupdate master dan sinkronisasi mapping COA dalam `DB::transaction()`, lalu redirect ke `?tab=indikator`.
   - `destroy(IndikatorKinerja $indikator)`: menghapus indikator (mapping COA terhapus via database cascade).
3. Buat views `resources/views/pengaturan/setting-indikator-kinerja/create.blade.php` dan `edit.blade.php` dengan 4 bagian terstruktur:
   - Bagian A: Identitas Dasar Indikator
   - Bagian B: Deklarasi Basis Sumber Data (interaktif: pembilang COA/Surplus, penyebut COA/RBA/Manual)
   - Bagian C: Parameter Ambang Batas Target (RAG)
   - Bagian D: Tabel Dinamis Pemetaan Akun COA (tambah baris, autocomplete/Select2 COA, pilihan PeranCoa, checklist Exclude, hapus baris).

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/IndikatorKinerjaCrudTest.php`
Expected: PASS.

- [x] **Step 5: Commit**
```bash
rtk git add app/Http/Requests/Pengaturan/*IndikatorKinerjaRequest.php app/Http/Controllers/Pengaturan/SettingIndikatorKinerjaController.php resources/views/pengaturan/setting-indikator-kinerja/create.blade.php resources/views/pengaturan/setting-indikator-kinerja/edit.blade.php tests/Feature/IndikatorKinerjaCrudTest.php
rtk git commit -m "feat(kpi): implement dedicated create and edit pages with dynamic COA mapping table"
```

---

### Task 8: Navigasi Sidebar, Dokumentasi Sistem, & Pemetaan AGENTS.md

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (atau file navigasi menu terkait)
- Create: `dokumentasi/indikator-kinerja.md`
- Create: `dokumentasi/pengaturan-setting-indikator-kinerja.md`
- Modify: `dokumentasi/fondasi/sistem-otorisasi.md`
- Modify: `dokumentasi/README.md`
- Modify: `AGENTS.md`
- Test: `tests/Feature/IndikatorKinerjaNavigationTest.php`

**Interfaces:**
- Consumes: Module keys `dashboard.indikator-kinerja` dan `pengaturan.setting-indikator-kinerja`
- Produces:
  - Menu sidebar di bawah Dashboard dan Pengaturan
  - Dokumentasi teknis & modul yang sinkron sesuai standar proyek di `AGENTS.md`.

- [x] **Step 1: Tulis failing test untuk navigasi menu sidebar**

File: `tests/Feature/IndikatorKinerjaNavigationTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKinerjaNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_contains_indicator_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));
        $response->assertOk()
            ->assertSee(route('dashboard.indikator-kinerja.index'))
            ->assertSee(route('setting-indikator-kinerja.index'));
    }
}
```

- [x] **Step 2: Jalankan test untuk memverifikasi kegagalan**
Command: `php artisan test tests/Feature/IndikatorKinerjaNavigationTest.php`
Expected: FAIL (Links not present in navigation view).

- [x] **Step 3: Tambahkan item menu sidebar dan buat dokumentasi lengkap**
1. Tambahkan menu `Indikator Kinerja` di dropdown/menu Dashboard dan `Setting Indikator Kinerja` di menu Pengaturan pada layout sidebar Blade.
2. Buat `dokumentasi/indikator-kinerja.md` mengikuti template `_template-modul.md` (Ringkasan, Entry Point/API, Otorisasi, Flowchart Mermaid, Algoritma Agregasi In-Memory).
3. Buat `dokumentasi/pengaturan-setting-indikator-kinerja.md` (Ringkasan, Entry Point/API, Validasi Form Request, Flowchart alur create/edit dedikasi & simpan matriks manual).
4. Perbarui `dokumentasi/fondasi/sistem-otorisasi.md` dengan mendaftarkan modul `dashboard.indikator-kinerja` dan `pengaturan.setting-indikator-kinerja`.
5. Perbarui `dokumentasi/README.md` dengan menautkan dokumen baru di indeks modul.
6. Perbarui tabel *Code → Documentation Mapping* di `AGENTS.md` sesuai konvensi repositori.

- [x] **Step 4: Jalankan test untuk memverifikasi kelulusan**
Command: `php artisan test tests/Feature/IndikatorKinerjaNavigationTest.php`
Expected: PASS.

- [x] **Step 5: Jalankan seluruh test suite terkait modul indikator kinerja**
Command: `php artisan test --filter=IndikatorKinerja`
Expected: All tests PASS.

- [x] **Step 6: Commit**
```bash
rtk git add resources/views/layouts/ dokumentasi/ AGENTS.md tests/Feature/IndikatorKinerjaNavigationTest.php
rtk git commit -m "docs(kpi): add system documentation, update AGENTS.md, and integrate sidebar navigation"
```
