# Dokumentasi Modul Dashboard — Indikator Kinerja

## Ringkasan Modul

Modul **Dashboard Indikator Kinerja** menyajikan visualisasi performa keuangan dan operasional rumah sakit berbasis 11 indikator kinerja utama (KPI) rumah sakit: Capaian Pendapatan, EBITDA, Profitabilitas, Beban Pegawai, Beban Farmasi, Beban Operasional, Collection Period, Kualitas Klaim JKN, Perputaran Persediaan, Beban Dana Sosial, dan Produktifitas Karyawan.

Fitur utama meliputi:
1. Monitoring eksekutif status ketercapaian target indikator dengan visual badge warna (Hijau / Kuning / Merah / Netral).
2. Agregasi data pembilang dan penyebut otomatis dari mutasi Buku Besar / Saldo Neraca COA, RBA Anggaran, dan input data manual operasional.
3. Drill-down visual grafik tren bulanan selama 12 bulan menggunakan Chart.js melalui endpoint AJAX.
4. Filter interaktif berdasarkan rentang tanggal dan pencarian indikator in-memory.

Implementasi utama modul ini berada di:
- `routes/web.php`
- `app/Http/Controllers/Dashboard/IndikatorKinerjaDashboardController.php`
- `app/Services/IndikatorKinerja/IndikatorKinerjaService.php`
- `app/Models/IndikatorKinerja.php`
- `app/Models/IndikatorKinerjaMappingCoa.php`
- `app/Models/IndikatorKinerjaVariabelManual.php`
- `app/Models/IndikatorKinerjaManualData.php`
- `resources/views/dashboard/indikator-kinerja/index.blade.php`

---

## Entry Point / API

Seluruh endpoint di bawah ini diproteksi oleh middleware `auth` dan `module.access:dashboard.indikator-kinerja,view`.

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/dashboard/indikator-kinerja` | `dashboard.indikator-kinerja.index` | `index(Request $request)` | `dashboard.indikator-kinerja,view` |
| `GET` | `/dashboard/indikator-kinerja/{kode}/tren` | `dashboard.indikator-kinerja.tren` | `tren(Request $request, string $kode)` | `dashboard.indikator-kinerja,view` |

---

## Parameter Request

1. **Endpoint Dashboard (`/dashboard/indikator-kinerja`)**:
   - `start_date` / `dariTanggal`: Tanggal awal periode evaluasi (format `YYYY-MM-DD`, default: tanggal 1 bulan berjalan).
   - `end_date` / `sampaiTanggal`: Tanggal akhir periode evaluasi (format `YYYY-MM-DD`, default: tanggal hari ini).
   - `q`: Kata kunci pencarian kode atau nama indikator (opsional, pencarian case-insensitive).
   - `status`: Filter status evaluasi RAG (`ALL`, `HIJAU`, `KUNING`, `MERAH`, default: `ALL`).

2. **Endpoint Tren Bulanan (`/dashboard/indikator-kinerja/{kode}/tren`)**:
   - `{kode}`: Kode unik indikator kinerja (misal: `capaian_pendapatan`, `ebitda`, `collection_period`, `beban_pegawai`).
   - `tahun`: Tahun kalender 12 bulan yang akan dievaluasi (integer, default: tahun berjalan).

---

## Alur 1: Evaluasi & Tampilan Dashboard Eksekutif

Alur ini memuat seluruh data indikator kinerja aktif, menghitung nilai riil pembilang dan penyebut, mengevaluasi status warna target, dan menampilkan kartu metrik serta ringkasan eksekutif. Tersedia 2 mode tampilan antarmuka: **Kartu Eksekutif** (Card Grid dengan baris formula badge terstruktur, teks definisi operasional naratif, dan interaktivitas klik seluruh kartu) dan **Tabel Rinci** (dengan kolom definisi operasional yang memuat visualisasi formula badge di atas teks naratif).

### Flowchart

```mermaid
flowchart TD
    A["User buka /dashboard/indikator-kinerja"] --> B["IndikatorKinerjaDashboardController::index()"]
    B --> C["resolveDateRange(): validasi start_date & end_date"]
    C --> D["IndikatorKinerjaService::evaluasiSemua(start, end)"]
    D --> E["Query IndikatorKinerja aktif + relasi mappingCoa & variabelManual"]
    E --> F["Loop indikator -> evaluasiIndikator()"]
    F --> G["Resolve Pembilang: Saldo Neraca / Mutasi COA / Surplus Periode"]
    F --> H["Resolve Penyebut: Saldo Neraca / Mutasi COA / RBA Prorata / Variabel Manual"]
    F --> I["Hitung Nilai Akhir: safeDivide() + Tipe Kalkulasi"]
    F --> J["Evaluasi Status Target: HIJAU / KUNING / MERAH / NETRAL"]
    J --> K["Koleksi hasil evaluasi lengkap terkumpul"]
    K --> L["Agregasi ringkasanStatus in-memory (total, hijau, kuning, merah, netral)"]
    L --> M{"Ada parameter filter status RAG (status != ALL)?"}
    M -- Ya --> N["Filter koleksi berdasarkan status evaluasi"]
    M -- Tidak --> O["Pertahankan koleksi"]
    N --> P{"Ada parameter filter pencarian q?"}
    O --> P
    P -- Ya --> Q["Filter koleksi berdasarkan kecocokan kode / nama indikator"]
    P -- Tidak --> R["Gunakan koleksi saat ini"]
    Q --> S["Render view dashboard.indikator-kinerja.index"]
    R --> S
```

### Algoritma

1. `IndikatorKinerjaDashboardController::index()` memanggil `resolveDateRange()` untuk memastikan tanggal awal dan akhir valid; jika parameter kosong atau tidak valid, sistem secara aman menggunakan fallback awal bulan s.d. hari ini.
2. Controller memanggil `IndikatorKinerjaService::evaluasiSemua($startDate, $endDate)`.
3. Service mengambil semua record `IndikatorKinerja` berstatus aktif beserta relasi `mappingCoa.coa` dan `variabelManual`.
4. Untuk setiap indikator:
   - Menghitung jumlah hari riil (`$hariRiil = start->diff(end)->days + 1`).
   - Mengambil seluruh leaf descendant COA IDs dengan fungsi memoized `kumpulkanLeafDescendantCoaIds()` untuk numerator dan denominator (termasuk filter akun eksklusi `is_excluded`).
   - Menghitung nilai pembilang: jika bertipe `TURNOVER`, mengambil saldo kumulatif per tanggal akhir via `ambilSaldoNeracaCoa()`. Jika berbasis `COA`, menggunakan fungsi cerdas `ambilNilaiCoa()` (akun Neraca ditarik saldo kumulatif per tanggal akhir dengan normalisasi kredit, akun Laba Rugi ditarik mutasi transaksi periode berjalan). Jika berbasis `SURPLUS`, mengambil laba bersih via `ambilSurplusPeriode()`, ditambah mapping `ADDITION` dan dikurangi `DEDUCTION` via `ambilNilaiCoa()`.
   - Menghitung nilai penyebut: jika berbasis `COA`, ditarik via `ambilNilaiCoa()` (mendukung saldo neraca, mutasi, serta agregasi multi-akun seperti Capital Employed = Ekuitas + Kewajiban Jangka Panjang), atau target RBA proporsional (`ambilRbaCoa()`), atau nilai variabel manual operasional terdekat (`ambilNilaiManualTerakhir()`). Untuk indikator bertipe `TURNOVER`, pembagian dilakukan terhadap rata-rata harian (dikali jumlah hari riil periode).
   - Menghitung rasio melalui `safeDivide($pembilang, $penyebut)`. Jika penyebut bernilai nol atau null, nilai diset `null` dan status dievaluasi sebagai `NETRAL`.
   - Melakukan evaluasi ambang batas target melalui `evaluasiStatus()` berdasarkan `pola_evaluasi` (`HIGHER_IS_BETTER`, `LOWER_IS_BETTER`, `TARGET_BAND`) dan rentang target warna (`HIJAU`, `KUNING`, `MERAH`, `NETRAL`), dengan pembulatan 2 desimal untuk mencegah celah nilai pecahan (*fractional gap*).
5. Controller mengagregasikan `ringkasanStatus` secara in-memory dari koleksi evaluasi lengkap tanpa query database tambahan untuk menjaga keakuratan strip kartu ringkasan eksekutif dan persentase ketercapaian.
6. Bila parameter `status` ditentukan (tidak sama dengan `ALL`), controller menyaring koleksi indikator berdasarkan status evaluasi RAG.
7. Bila parameter `q` diberikan, controller menyaring koleksi indikator berdasarkan kecocokan substring pada `kode` atau `nama` (case-insensitive).
8. Mengembalikan view `dashboard.indikator-kinerja.index` yang berisi widget ringkasan persentase eksekutif, toolbar filter server-side, view switcher (Kartu Eksekutif & Tabel Rinci dengan formula badge terstruktur), serta modal interaktif drill-down tren grafis 12 bulan yang menyajikan formula pecahan matematika ber-badge nilai riil (Variasi C).

### Fungsi yang Dipanggil

- `IndikatorKinerjaDashboardController::index()`
- `IndikatorKinerjaDashboardController::resolveDateRange()`
- `IndikatorKinerjaService::evaluasiSemua()`
- `IndikatorKinerjaService::evaluasiIndikator()`
- `IndikatorKinerjaService::kumpulkanLeafDescendantCoaIds()`
- `IndikatorKinerjaService::ambilNilaiCoa()`
- `IndikatorKinerjaService::ambilSaldoNeracaCoa()`
- `IndikatorKinerjaService::ambilMutasiCoa()`
- `IndikatorKinerjaService::klasifikasiTipeCoa()`
- `IndikatorKinerjaService::ambilRbaCoa()`
- `IndikatorKinerjaService::ambilSurplusPeriode()`
- `IndikatorKinerjaService::ambilNilaiManualTerakhir()`
- `IndikatorKinerjaService::safeDivide()`
- `IndikatorKinerjaService::evaluasiStatus()`

---

## Alur 2: Drill-down Tren Bulanan 12 Bulan (AJAX)

Alur ini dipicu saat pengguna mengklik tombol atau kartu indikator pada dashboard untuk melihat visualisasi pergerakan kinerja sepanjang tahun berjalan.

### Flowchart

```mermaid
flowchart TD
    A["User klik kartu indikator / tombol tren"] --> B["Front-end JavaScript memicu modal tren"]
    B --> C["GET /dashboard/indikator-kinerja/{kode}/tren?tahun=YYYY"]
    C --> D["IndikatorKinerjaDashboardController::tren()"]
    D --> E["IndikatorKinerjaService::getTrenBulanan(kode, tahun)"]
    E --> F["Query IndikatorKinerja by kode"]
    F --> G["Loop bulan 1 s.d. 12: tentukan awal & akhir bulan"]
    G --> H["evaluasiIndikator(indikator, awalBulan, akhirBulan)"]
    H --> I["Susun array tren bulanan (bulan, label, nilai, status, pembilang, penyebut)"]
    I --> J["Response JSON payload"]
    J --> K["Chart.js render Line Chart di modal popup"]
```

### Algoritma

1. Frontend memicu pemanggilan AJAX ke endpoint `dashboard.indikator-kinerja.tren` dengan menyertakan parameter `tahun`.
2. `IndikatorKinerjaDashboardController::tren()` menerima request dan meneruskan parameter ke `IndikatorKinerjaService::getTrenBulanan($kode, $tahun)`.
3. Service mencari record `IndikatorKinerja` berdasarkan kode. Jika tidak ditemukan, service melempar `ModelNotFoundException` (HTTP 404).
4. Service melakukan iterasi untuk setiap bulan $1$ sampai $12$ pada tahun terpilih:
   - Menghitung rentang tanggal awal bulan (`YYYY-MM-01`) dan akhir bulan (`YYYY-MM-t`).
   - Mengevaluasi indikator pada potongan waktu 1 bulan tersebut melalui `evaluasiIndikator()`.
   - Mengumpulkan data poin bulanan: nomor bulan, nama bulan singkat (Jan, Feb, ...), nilai riil hasil kalkulasi, badge status evaluasi, serta nominal pembilang dan penyebut.
5. Controller mengembalikan respons JSON berupa struktur tren bulanan beserta metadata indikator (kode, nama, satuan, batas target).
6. Script front-end merender modal detail & tren yang memuat 3 seksi utama:
   - **Seksi 1: Formula Kalkulasi Riil Periode (Variasi C — Pecahan Matematika Ber-Badge)**:
     - Header seksi: Judul `📐 Formula Kalkulasi Riil Periode`, badge tipe kalkulasi (`#modalIndicatorTipe`), dan badge rentang tanggal evaluasi riil (`#modalFormulaPeriodeText`, misal `01 Nov 2026 s.d. 19 Nov 2026 (19 Hari)`).
     - Kontainer Persamaan Matematika Pecahan (`#modalFormulaEquationContainer`): Menampilkan pembilang ber-badge biru lembut (`bg-primary-subtle text-primary`), garis pembagi horizontal, penyebut ber-badge cyan lembut (`bg-info-subtle text-info-emphasis`), faktor pengali (misal `× 100%` atau `× {hariRiil} Hari`), tanda `=`, dan nilai capaian akhir ber-badge status RAG evaluasi (`#modalIndicatorNilai`). Untuk tipe `MANUAL`, menampilkan badge entri nilai non-akuntansi tanpa garis pecahan. Untuk satuan `JUTA_PER_ORG_BLN`, menampilkan formulasi rata-rata per bulan per orang SDM non-organik.
     - Legenda Komponen Nilai (`#modalFormulaLegendContainer`): Menjelaskan arti dan nominal riil komponen pembilang (sumber COA/Surplus) dan penyebut (COA/Target RBA/Variabel Manual), serta faktor pengali hari riil.
     - Definisi Operasional (`#modalIndicatorDefinisi`): Keterangan naratif acuan baku indikator kinerja.
   - **Seksi 2: Panduan Ambang Batas Target (RAG)**: Kotak panduan batas target Hijau (`#modalTargetHijau`), Kuning (`#modalTargetKuning`), dan Merah (`#modalTargetMerah`).
   - **Seksi 3: Tren Historis Realisasi Bulanan**: Grafik garis interaktif 12 bulan Chart.js (`#trenChartCanvas`) dengan filter tahun anggaran (`#selectTahunTren`).
7. Script front-end menghancurkan instance Chart.js sebelumnya (jika ada) dan menggambar grafik garis interaktif yang menampilkan tren performa beserta garis ambang batas target.

### Fungsi yang Dipanggil

- `IndikatorKinerjaDashboardController::tren()`
- `IndikatorKinerjaService::getTrenBulanan()`
- `IndikatorKinerjaService::evaluasiIndikator()`

---

## Catatan Penting Bisnis

1. **Penanganan Pembagian dengan Nol (Safe Division)**:
   - Bila penyebut bernilai `0` atau data belum diinput, nilai indikator menjadi `null`. Nilai `null` ditampilkan sebagai tanda strip (`-`) dan statusnya adalah `NETRAL` (badge abu-abu), bukan error kalkulasi.
2. **Pencarian Rekursif Hierarki COA**:
   - Jika sebuah akun induk COA dipetakan pada indikator, sistem secara otomatis mengumpulkan seluruh akun anak (*leaf descendants*) yang postable menggunakan memoized tree (`getCachedCoaTree`) guna mencegah beban query berulang ke database.
   - Akun COA yang ditandai `is_excluded = true` secara eksplisit dikeluarkan dari daftar leaf descendant.
3. **Data Variabel Manual**:
   - Untuk indikator dengan penyebut berbasis `MANUAL`, sistem mencari data bulanan terakhir yang terisi pada atau sebelum tanggal akhir evaluasi (`ambilNilaiManualTerakhir()`). Jika tidak ada data yang terisi, rasio tidak dapat dihitung dan bernilai netral.
   - *Catatan Skalabilitas Arsitektur (Multi-Variabel)*: Saat ini mesin kalkulasi mengasumsikan 1 variabel manual per indikator via kolom `variabel_manual_id`. Analisis arsitektur komprehensif mengenai pemenuhan KPI rumah sakit majemuk (seperti BOR, BTO, atau agregasi SDM) beserta 3 opsi pendekatan solusi (Tabel Mapping Variabel, Variabel Komposit, Dynamic Expression Engine) didokumentasikan lengkap pada [Dokumentasi Setting Indikator Kinerja — Pembahasan Arsitektur: Dukungan Multi-Variabel Manual](pengaturan-setting-indikator-kinerja.md#pembahasan-arsitektur-dukungan-multi-variabel-manual-1-variabel).
4. **Alokasi RBA Proporsional**:
   - Untuk penyebut berbasis `RBA`, sistem menghitung porsi target anggaran RBA secara proporsional sesuai jumlah bulan dalam rentang filter tanggal (`ambilRbaCoa()` dan `hitungAlokasiBulanPerTahun()`).
5. **Widget Home Dashboard**:
   - Ringkasan status indikator kinerja juga dapat ditampilkan pada halaman utama (Home) melalui endpoint JSON `/home/ringkasan-indikator` yang memanggil `IndikatorKinerjaService::getRingkasanStatus()`.
6. **Daftar 11 Indikator Kinerja Baseline & Variabel Manual**:
   - `capaian_pendapatan`: Capaian Pendapatan (`%`, `RASIO`, COA / RBA, `HIGHER_IS_BETTER`)
   - `ebitda`: EBITDA Margin (`%`, `RASIO`, SURPLUS + Addition / COA, `HIGHER_IS_BETTER`)
   - `profitabilitas`: Profitabilitas / NPM (`%`, `RASIO`, SURPLUS / COA, `HIGHER_IS_BETTER`)
   - `beban_pegawai`: Beban Pegawai (`%`, `RASIO`, COA / COA, `TARGET_BAND`: 20%-25% Hijau, 25%-30% Kuning)
   - `beban_farmasi`: Beban Farmasi (`%`, `RASIO`, COA / COA, `LOWER_IS_BETTER`)
   - `beban_operasional`: Beban Operasional (`%`, `RASIO`, COA / COA, `LOWER_IS_BETTER`)
   - `collection_period`: Collection Period (`Hari`, `TURNOVER`, COA / COA, `LOWER_IS_BETTER`)
   - `kualitas_klaim_jkn`: Kualitas Klaim JKN (`%`, `MANUAL`, Variabel Manual, `HIGHER_IS_BETTER`)
   - `perputaran_persediaan`: Perputaran Persediaan (`Hari`, `TURNOVER`, COA / COA, `LOWER_IS_BETTER`)
   - `beban_dana_sosial`: Beban Dana Sosial (`%`, `RASIO`, COA / COA, `HIGHER_IS_BETTER`)
   - `produktifitas_karyawan`: Produktifitas Karyawan (`Juta/org/bln`, `RASIO`, COA / Variabel Manual, `HIGHER_IS_BETTER`)
   - Variabel Manual: `lolos_bahv_pertama_jkn` (Persentase Lolos BAHV Pertama JKN, `%`) dan `sdm_non_organik` (Jumlah SDM Non Organik, `Orang`).
