# Dokumentasi Modul Pengaturan — Pengaturan Arus Kas

## Ringkasan Modul

Modul **Pengaturan — Pengaturan Arus Kas** merupakan pusat konfigurasi pelaporan arus kas terpadu di SiAkurat yang menyatukan pengaturan untuk dua metode standar akuntansi keuangan:

1. **Metode Langsung (*Direct Cash Flow*)**:
   - Mengelola master pos referensi kelompok arus kas terstandarisasi untuk 3 pilar aktivitas (`operasi`, `investasi`, dan `pendanaan`) yang terikat langsung pada bagan akun COA (*Chart of Accounts*) melalui kolom `arus_kas_aktivitas` dan `arus_kas_kelompok`.
   - **Pemetaan Multi-COA (*Leaf-Only Mapping*)**: Memungkinkan satu pos referensi kelompok memetakan banyak akun detail (leaf) buku besar secara serentak, dengan validasi ketat yang melarang akun induk (*parent COA*) maupun akun bertipe `Kasbank` dipetakan langsung.
   - **Pilih Cepat Akun Induk (*Parent COA Expansion*)**: Memungkinkan pengguna memilih akun induk pada modal tambah/edit pos, lalu mengekspansi seluruh turunan daun (*leaf descendants*) secara otomatis dan rekursif dengan klasifikasi cerdas:
     - `eligible`: akun daun yang belum dipetakan (atau milik pos ini sendiri).
     - `reassignable`: akun daun yang saat ini telah terpetakan pada pos kelompok lain.
     - `skipped`: akun `Kasbank` yang secara otomatis dilewati disertai alasan penolakan.
   - **Pindah Kelompok dengan Konfirmasi**: Mendeteksi jika ada akun COA terpilih yang sebelumnya telah terpetakan di pos referensi lain, dan menampilkan modal konfirmasi pemindahan pemetaan (`#modalKonfirmasiPindahCoaLangsung`) yang merinci nama akun dan pos asalnya sebelum transaksi disimpan.
   - **Pemindai Akun Lawan Kasbank Belum Dipetakan (*Cash Counterpart Scanner*)**: Memindai seluruh transaksi buku besar pada periode tahun berjalan yang melibatkan mutasi akun `Kasbank` untuk mendeteksi akun lawan non-Kasbank yang belum dipetakan ke pos kelompok arus kas, lengkap dengan indikator debit, kredit, frekuensi transaksi, dan dampak kas (`kredit - debit`).
   - **Pintasan Aksi Cepat (*Quick-Map*)**: Pengguna dapat memetakan akun lawan yang terdeteksi ke pos referensi aktif secara langsung melalui modal quick-map (`#modalQuickMapLangsung`) atau membuka modal tambah pos baru dengan akun bersangkutan otomatis terpilih.
   - **Modal Detail COA Terpetakan**: Menyediakan tampilan rincian akun COA daun (*leaf*) yang terpetakan ke pos referensi, serta memisahkan catatan akun induk (*parent template*) yang memiliki tag di Master COA sebagai template pewarisan untuk sub-akun baru.
   - **Pencarian Lintas Nama Pos & Akun COA Leaf**: Toolbar filter pencarian pada tab Metode Langsung mencocokkan kata kunci terhadap nama pos referensi maupun kode/nama akun COA leaf yang terpetakan tanpa memicu *N+1 query*.
   - Mengeliminasi inkonsistensi pengetikan manual (typo, spasi berlebih, inkonsistensi kapitalisasi) melalui sanitasi nama dan validasi keunikan *case-insensitive*.
   - Melakukan pembaruan massal berantai (*cascade update*) otomatis ke akun-akun COA terkait ketika nama kelompok arus kas diperbarui di dalam satu transaksi database atomik.
   - Melindungi integritas data keuangan dengan memblokir penghapusan (*restricted delete*) apabila suatu pos referensi masih terikat pada akun daun COA (`leaf_count`) atau akun induk template (`parent_count`).
   - Menyediakan sinkronisasi otomatis (*auto-insert* / *auto-tagging*) dengan konfirmasi pengguna saat menginput nama kelompok baru langsung melalui form master COA (`CoaService::create` / `update`).
   - Menyediakan data preset kelompok aktif terkelompok per aktivitas (`getGroupedActive()`) untuk dropdown Select2 interaktif pada form master COA.
   - Mencatat seluruh mutasi konfigurasi ke jejak audit log (`LogAktifitasService`) untuk aksi `create_langsung`, `update_langsung`, `delete_langsung`, `toggle_langsung`, dan `quick_map_langsung`.
2. **Metode Tidak Langsung (*Indirect Cash Flow*)**:
   - Mengelola konfigurasi dinamis pos laporan arus kas tidak langsung yang dikelompokkan ke dalam 4 bagian laporan:
     - Penyesuaian Laba (`penyesuaian_laba`),
     - Modal Kerja Operasi (`modal_kerja`),
     - Aktivitas Investasi (`investasi`), dan
     - Aktivitas Pendanaan (`pendanaan`).
   - Pemetaan akun buku besar detail (*leaf-only mapping*) ke pos arus kas dengan jaminan keunikan eksklusif satu akun per pos (`UNIQUE(coa_id)`).
   - Fitur **Pilih Cepat Akun Induk (*Parent COA Expansion*)** untuk mengekspansi seluruh turunan daun (*leaf descendants*) secara rekursif otomatis dengan pemisahan akun yang memenuhi syarat (*eligible*) dan yang dilewati (*skipped*).
   - Penyusunan dan penataan urutan pos (*reordering*) di setiap kelompok bagian laporan secara instan melalui tombol kontrol naik/turun dan API AJAX.
   - Pengelolaan daftar akun Neraca non-Kasbank yang dikecualikan (*exclusion list*) pada tabel `setting_arus_kas_pengecualian` (seperti akumulasi penyusutan atau penyisihan piutang) agar tidak memicu peringatan selisih pemetaan.
   - Pemindaian otomatis akun Neraca bermutasi yang belum dipetakan (*unmapped mutated Neraca accounts scanner*) lengkap dengan pintasan **Tindakan Cepat (Petakan / Kecualikan)**.
3. **Antarmuka Terpadu Dua Tab & Pengendalian Hak Akses Tunggal**:
   - Seluruh pengelolaan disajikan dalam satu halaman indeks terpadu dengan navigasi tab utama:
     - Tab **Metode Langsung** (`?metode=langsung` / default)
     - Tab **Metode Tidak Langsung** (`?metode=tidak_langsung`)
   - Masing-masing tab memiliki toolbar filter tersendiri (filter dropdown `aktivitas` untuk metode langsung, filter dropdown `bagian` untuk metode tidak langsung, dan input pencarian teks fleksibel).
   - Seluruh endpoint diamankan menggunakan **satu modul otorisasi tunggal** di RBAC: `pengaturan.arus-kas` (dengan aksi `view`, `create`, `update`, `delete`).

Implementasi utama modul ini berada di:

- `routes/web.php`
- `app/Http/Controllers/Pengaturan/PengaturanArusKasController.php`
- `app/Http/Requests/Pengaturan/StoreReferensiKelompokArusKasRequest.php`
- `app/Http/Requests/Pengaturan/UpdateReferensiKelompokArusKasRequest.php`
- `app/Http/Requests/Pengaturan/QuickMapReferensiKelompokArusKasRequest.php`
- `app/Http/Requests/Pengaturan/StoreSettingArusKasRequest.php`
- `app/Http/Requests/Pengaturan/UpdateSettingArusKasRequest.php`
- `app/Http/Requests/Pengaturan/ReorderSettingArusKasRequest.php`
- `app/Http/Requests/Pengaturan/StorePengecualianArusKasRequest.php`
- `app/Services/Pengaturan/ReferensiKelompokArusKasService.php`
- `app/Services/Pengaturan/PengaturanArusKasService.php`
- `app/Support/CoaTreeHelper.php`
- `app/Models/ReferensiKelompokArusKas.php`
- `app/Models/SettingArusKas.php`
- `app/Models/SettingArusKasCoa.php`
- `app/Models/SettingArusKasPengecualian.php`
- `app/Support/AccessModuleRegistry.php` (kode: `pengaturan.arus-kas`, urutan: 206)
- `database/migrations/2026_11_24_000001_create_referensi_kelompok_arus_kas_table.php`
- `database/migrations/2026_11_24_000002_add_referensi_kelompok_arus_kas_access_module.php`
- `database/migrations/2026_11_25_000001_create_setting_arus_kas_tables.php`
- `database/migrations/2026_11_25_000002_add_pengaturan_arus_kas_access_module.php`
- `database/migrations/2026_11_26_000001_merge_referensi_kelompok_arus_kas_into_pengaturan_arus_kas_module.php`
- `database/seeders/ReferensiKelompokArusKasSeeder.php`
- `database/seeders/SettingArusKasSeeder.php`
- `resources/views/pengaturan/arus-kas/index.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_tab-langsung.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_tab-tidak-langsung.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_modal-panduan.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_modals-langsung.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_modals.blade.php`
- `resources/views/pengaturan/arus-kas/partials/_scripts.blade.php`

Komponen terkait di modul lain:
- `app/Http/Controllers/Bukubesar/CoaController.php` (injeksi preset referensi aktif ke form create & edit COA)
- `app/Services/Bukubesar/CoaService.php` (konsumsi auto-insert `ensureExists()` dan proteksi akun Kasbank)
- `resources/views/bukubesar/coa/_form.blade.php` & `_scripts.blade.php` (antarmuka Select2 tagging dengan konfirmasi penambahan pos baru)
- `app/Services/Laporan/LaporanKeuanganService.php` (penyusunan Laporan Arus Kas Metode Langsung via COA tags & Metode Tidak Langsung dinamis via `SettingArusKas`)
- `resources/views/laporan/keuangan/arus-kas-tidak-langsung.blade.php` (warning banner deteksi unmapped accounts dengan tautan langsung ke `?metode=tidak_langsung`)

---

## Entry Point / API

Seluruh 15 endpoint dilindungi oleh middleware otorisasi berbasis modul `module.access:pengaturan.arus-kas,<aksi>`.

| Method | Path | Route Name | Controller Method | Izin Diperlukan | Keterangan |
| --- | --- | --- | --- | --- | --- |
| `GET` | `/pengaturan/arus-kas` | `pengaturan.arus-kas.index` | `index()` | `pengaturan.arus-kas,view` | Halaman utama (tab Langsung / Tidak Langsung) |
| `POST` | `/pengaturan/arus-kas/langsung` | `pengaturan.arus-kas.langsung.store` | `storeLangsung()` | `pengaturan.arus-kas,create` | Tambah pos referensi kelompok baru & mapping COA (Langsung) |
| `GET` | `/pengaturan/arus-kas/langsung/parent-coa-leaves` | `pengaturan.arus-kas.langsung.parent-coa-leaves` | `parentCoaLeavesLangsung()` | `pengaturan.arus-kas,view` | AJAX ekspansi child COA daun dari parent (Langsung) |
| `POST` | `/pengaturan/arus-kas/langsung/quick-map` | `pengaturan.arus-kas.langsung.quick-map` | `quickMapLangsung()` | `pengaturan.arus-kas,update` | Aksi cepat petakan akun lawan transaksi kasbank ke pos referensi (Langsung) |
| `PUT` | `/pengaturan/arus-kas/langsung/{referensi}` | `pengaturan.arus-kas.langsung.update` | `updateLangsung()` | `pengaturan.arus-kas,update` | Edit pos referensi kelompok, sync COA & cascade rename (Langsung) |
| `DELETE` | `/pengaturan/arus-kas/langsung/{referensi}` | `pengaturan.arus-kas.langsung.destroy` | `destroyLangsung()` | `pengaturan.arus-kas,delete` | Hapus pos referensi kelompok (Restricted Delete) |
| `PATCH` | `/pengaturan/arus-kas/langsung/{referensi}/toggle` | `pengaturan.arus-kas.langsung.toggle` | `toggleStatusLangsung()` | `pengaturan.arus-kas,update` | Toggle status aktif/nonaktif pos referensi |
| `POST` | `/pengaturan/arus-kas/tidak-langsung` | `pengaturan.arus-kas.tidak-langsung.store` | `store()` | `pengaturan.arus-kas,create` | Tambah pos arus kas baru (Tidak Langsung) |
| `GET` | `/pengaturan/arus-kas/tidak-langsung/parent-coa-leaves` | `pengaturan.arus-kas.tidak-langsung.parent-coa-leaves` | `parentCoaLeaves()` | `pengaturan.arus-kas,view` | AJAX ekspansi child COA daun dari parent (Tidak Langsung) |
| `POST` | `/pengaturan/arus-kas/tidak-langsung/pengecualian` | `pengaturan.arus-kas.tidak-langsung.pengecualian.store` | `storePengecualian()` | `pengaturan.arus-kas,create` | Tambah akun Neraca ke daftar pengecualian |
| `DELETE` | `/pengaturan/arus-kas/tidak-langsung/pengecualian/{pengecualian}` | `pengaturan.arus-kas.tidak-langsung.pengecualian.destroy` | `destroyPengecualian()` | `pengaturan.arus-kas,delete` | Hapus akun dari daftar pengecualian |
| `PATCH` | `/pengaturan/arus-kas/tidak-langsung/reorder` | `pengaturan.arus-kas.tidak-langsung.reorder` | `reorder()` | `pengaturan.arus-kas,update` | AJAX reordering urutan pos per bagian laporan |
| `PUT` | `/pengaturan/arus-kas/tidak-langsung/{settingArusKas}` | `pengaturan.arus-kas.tidak-langsung.update` | `update()` | `pengaturan.arus-kas,update` | Edit pos arus kas & sync COA (Tidak Langsung) |
| `DELETE` | `/pengaturan/arus-kas/tidak-langsung/{settingArusKas}` | `pengaturan.arus-kas.tidak-langsung.destroy` | `destroy()` | `pengaturan.arus-kas,delete` | Hapus pos arus kas dinamis (Tidak Langsung) |
| `PATCH` | `/pengaturan/arus-kas/tidak-langsung/{settingArusKas}/toggle` | `pengaturan.arus-kas.tidak-langsung.toggle` | `toggleStatus()` | `pengaturan.arus-kas,update` | Toggle status aktif pos arus kas (Tidak Langsung) |

---

## Struktur Antarmuka & Alur Navigasi Utama

Halaman utama menyajikan antarmuka terpadu yang memadukan Header Card, tab navigasi metode pelaporan, filter toolbar dinamis, banner peringatan deteksi akun belum dipetakan, serta modal interaktif.

### Fitur Antarmuka & UX

1. **Header Card Terpadu**:
   - Tombol kembali melingkar (`rounded-circle`) yang mengarah ke dashboard (`route('home')`).
   - Judul modul: **Pengaturan Arus Kas**, dilengkapi badge status `Master & Pemetaan` dan ringkasan fungsi modul.
   - Tautan kontekstual **Laporan Arus Kas** (tampil bila pengguna memiliki izin `laporan.keuangan,view`): mengarah ke `/laporan/keuangan/arus-kas` (`route('laporan.keuangan.arus-kas')`) saat tab Metode Langsung aktif, atau ke `/laporan/keuangan/arus-kas-tidak-langsung` (`route('laporan.keuangan.arus-kas-tidak-langsung')`) saat tab Metode Tidak Langsung aktif.
   - Dropdown **Bantuan & Panduan** (`#btnBantuanDropdown`) dengan opsi:
     - **Mulai Tur Panduan** (`#btnStartTour`): menampilkan alert placeholder `#tourPlaceholderAlert`.
     - **Panduan & Glosarium** (`#btnOpenModalPanduan`): membuka modal `#modalPanduanArusKas` (`partials/_modal-panduan.blade.php`) yang saat ini menampilkan placeholder `"Panduan & Glosarium belum tersedia."`.
2. **Tab Navigasi Utama (`#mainArusKasTabs`)**:
   - **Pengaturan Arus Kas Metode Langsung** (`route('pengaturan.arus-kas.index', ['metode' => 'langsung'])`): Menampilkan master referensi kelompok arus kas 3 pilar aktivitas beserta badge counter `$referensiTabCounts['semua']`.
   - **Pengaturan Arus Kas Metode Tidak Langsung** (`route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung'])`): Menampilkan konfigurasi dinamis pos arus kas 4 bagian laporan, pengecualian, serta banner deteksi unmapped accounts.
   - Navigasi tab didukung query parameter URL `metode`, sehingga tautan langsung maupun refresh browser tetap mempertahankan tab yang aktif.
3. **Filter Toolbar Spesifik Tab**:
   - **Pada Tab Metode Langsung**: Memuat dropdown filter klasifikasi aktivitas (`Semua Aktivitas`, `Aktivitas Operasi`, `Aktivitas Investasi`, `Aktivitas Pendanaan`) lengkap dengan counter, input teks pencarian fleksibel (mencocokkan nama pos maupun kode/nama akun COA daun), tombol reset pencarian, dan tombol `+ Tambah Pos Referensi`.
   - **Pada Tab Metode Tidak Langsung**: Memuat dropdown filter bagian laporan (`Semua Pos`, `Penyesuaian Laba`, `Modal Kerja Operasi`, `Aktivitas Investasi`, `Aktivitas Pendanaan`, `Akun Dikecualikan`) lengkap dengan counter, input teks pencarian fleksibel, tombol reset pencarian, tombol tambah pos/pengecualian, serta view switcher Mode Tab vs Mode Struktur Laporan.
4. **Banner Peringatan Akun Belum Dipetakan**:
   - **Pada Tab Metode Langsung**: Jika ditemukan akun lawan transaksi kas/bank buku besar periode tahun berjalan yang belum memiliki pemetaan kelompok arus kas langsung, sistem menampilkan banner peringatan kuning `#unmappedLangsungAccountsCollapse`. Pengguna dapat mengklik tombol **Petakan** (`.btn-quick-map-langsung`) untuk membuka Modal Quick-Map (`#modalQuickMapLangsung`) atau membuka modal tambah pos baru dengan akun tersebut terpilih.
   - **Pada Tab Metode Tidak Langsung**: Jika ditemukan akun Neraca non-Kasbank yang memiliki mutasi saldo pada buku besar periode tahun berjalan namun belum masuk pos aktif maupun pengecualian, sistem menampilkan banner peringatan kuning `#unmappedAccountsCollapse` lengkap dengan tombol **Petakan** (`.btn-quick-map-unmapped`) dan **Kecualikan** (`.btn-quick-exclude-unmapped`).
5. **Modal-Modal Interaktif**:
   - **Modal Metode Langsung (`_modals-langsung.blade.php`)**:
     - `#modalTambahReferensi` (`modal-lg`): Formulir tambah pos referensi baru lengkap dengan panel pemetaan multi-COA leaf, Select2 tagging/filter, dan pemilih cepat akun induk (*Parent COA Expansion*).
     - `#modalEditReferensi` (`modal-lg`): Formulir edit pos referensi dengan panel pemetaan multi-COA leaf, deteksi perpindahan kelompok, dan opsi status aktif.
     - `#modalDetailCoaLangsung`: Modal detail akun COA terpetakan yang menyajikan daftar akun leaf dan catatan akun parent template Master COA.
     - `#modalQuickMapLangsung`: Modal aksi cepat memetakan satu akun lawan kasbank ke pos referensi aktif atau opsi membuka formulir pembuatan pos baru.
     - `#modalKonfirmasiPindahCoaLangsung`: Modal konfirmasi jika akun yang dipilih saat tambah/edit pos sedang digunakan oleh pos kelompok arus kas lain.
   - **Modal Metode Tidak Langsung (`_modals.blade.php`)**:
     - `#modalPosArusKas`: Formulir tambah/edit pos arus kas tidak langsung dengan panel pemetaan COA dan parent expansion.
     - `#modalPengecualian`: Formulir penambahan akun ke daftar pengecualian.
     - `#modalDetailCoaPos`: Modal detail daftar COA terpetakan per pos tidak langsung.
     - `#modalKonfirmasiHapusPos`: Modal konfirmasi hapus pos arus kas tidak langsung.
     - `#modalKonfirmasiHapusPengecualian`: Modal konfirmasi hapus akun pengecualian.

### Flowchart Navigasi Utama

```mermaid
flowchart TD
    A["User mengakses /pengaturan/arus-kas"] --> B["PengaturanArusKasController::index(request)"]
    B --> C{"Cek query param 'metode'"}
    C -- "langsung (default)" --> D["PengaturanArusKasController::buildLangsungViewData()"]
    D --> E["Filter aktivitas ('operasi'/'investasi'/'pendanaan') & search"]
    E --> F["Pencarian search: cocokkan nama pos ATAU kode/nama leaf COA (whereExists)"]
    F --> G["Paginate ReferensiKelompokArusKas (25/halaman)"]
    G --> H["Eager load COA terpetakan 1 query batch (mencegah N+1)"]
    H --> I["Pisahkan mapped_leaf_coas vs mapped_parent_coas (via parent_coa)"]
    I --> J["Hitung unmappedLangsungAccounts via getUnmappedCashCounterpartAccounts()"]
    J --> K["Muat langsungCoaOptionsData & activeReferensiOptions"]
    C -- "tidak_langsung" --> L["PengaturanArusKasController::buildTidakLangsungViewData()"]
    L --> M["Muat data SettingArusKas::urut() & SettingArusKasPengecualian"]
    M --> N["Hitung tabCounts bagian laporan & pengecualian"]
    N --> O["PengaturanArusKasService::getUnmappedMutatedNeracaAccounts()"]
    O --> P["PengaturanArusKasService::getCoaOptionsData()"]
    K --> Q["Render view pengaturan.arus-kas.index dengan partial _tab-langsung & _modals-langsung"]
    P --> R["Render view pengaturan.arus-kas.index dengan partial _tab-tidak-langsung & _modals"]
```

### Algoritma `index()`

1. `PengaturanArusKasController::index()` membaca query parameter `metode` (default: `'langsung'`), serta selalu menghitung `$referensiTabCounts` (`semua`, `operasi`, `investasi`, `pendanaan`) menggunakan query agregat cepat.
2. Jika `metode === 'langsung'`:
   - Memanggil `buildLangsungViewData($request)`.
   - Membaca filter `aktivitas` (default: `'semua'`) dan string `search`.
   - Jika `search` terisi, query referensi memfilter `nama LIKE %search%` ATAU melakukan subquery `whereExists` ke tabel `coa` untuk akun daun (*leaf*) non-anak (`NOT EXISTS child_coa`) yang memiliki `kode LIKE %search%` atau `nama LIKE %search%`.
   - Mengambil data `ReferensiKelompokArusKas` berpaginasi (25 baris/halaman) via `paginate(25)->withQueryString()`.
   - Jika ada data referensi pada halaman aktif:
     - Mengumpulkan seluruh pasangan `(aktivitas, nama)`.
     - Mendeteksi ID seluruh akun induk (*parent COA*) melalui kolom `coa.parent_coa`.
     - Menjalankan **satu query batch** ke tabel `coa` untuk mengambil seluruh akun yang memiliki tag `(arus_kas_aktivitas, arus_kas_kelompok)` terkait.
     - Mengelompokkan hasil query dan memisahkannya menjadi `$ref->mapped_leaf_coas` (`coa_count`) dan `$ref->mapped_parent_coas` (`parent_coa_count`).
   - Memanggil `ReferensiKelompokArusKasService::getUnmappedCashCounterpartAccounts()` untuk memindai akun lawan transaksi kas/bank tahun berjalan yang belum dipetakan.
   - Memanggil `ReferensiKelompokArusKasService::getCoaOptionsData()` untuk menyiapkan opsi `leafCoas`, `parentCoas`, dan `mappedByCoaId`.
   - Mengambil daftar pos referensi aktif (`activeReferensiOptions`) untuk dropdown quick-map.
   - Menghitung ringkasan `$tidakLangsungTabCounts` untuk badge tab sebelah.
3. Jika `metode === 'tidak_langsung'`:
   - Memanggil `buildTidakLangsungViewData($request)`.
   - Membaca parameter `bagian` (atau alias legacy `tab`, valid: `semua`, `penyesuaian_laba`, `modal_kerja`, `investasi`, `pendanaan`, `pengecualian`; default `'semua'`).
   - Mengambil seluruh data `SettingArusKas` terurut (`urut()`) beserta relasi `coas`, serta seluruh record `SettingArusKasPengecualian` beserta relasi `coa`.
   - Menghitung `$tidakLangsungTabCounts` (dan alias `$tabCounts`) untuk keenam kategori bagian laporan.
   - Memanggil `PengaturanArusKasService::getUnmappedMutatedNeracaAccounts()` untuk memindai akun Neraca non-Kasbank bermutasi pada tahun berjalan yang belum dipetakan.
   - Memanggil `PengaturanArusKasService::getCoaOptionsData()` untuk menyiapkan metadata COA leaf, parent, akun terpetakan, dan akun pengecualian.
4. Mengembalikan view `pengaturan.arus-kas.index` yang merender header card terpadu, `_modal-panduan.blade.php`, `_modals-langsung.blade.php`, `_modals.blade.php`, dan partial tab yang sesuai (`_tab-langsung.blade.php` atau `_tab-tidak-langsung.blade.php`).

---

## Bagian A: Alur-Alur Metode Langsung

### Alur L1: Menampilkan Daftar Pos Referensi Kelompok Arus Kas & Detail Akun COA

Pengguna dapat melihat daftar master pos referensi kelompok arus kas untuk metode langsung dengan format tabel modern:
- Penomoran berurutan lintas halaman paginasi (25 baris/halaman via `withQueryString()`).
- Badge pilar aktivitas: `Operasi` (`primary-subtle`), `Investasi` (`success-subtle`), dan `Pendanaan` (`info-subtle`).
- Badge status statis: `Aktif` (hijau subtle) / `Nonaktif` (abu-abu subtle).
- **Badge Jumlah Akun COA Terkait**:
  - `[X Akun]` (badge hijau/biru dengan ikon diagram `bi-diagram-3-fill`): menampilkan jumlah akun detail (*leaf*) yang terpetakan ke pos ini. Jika belum ada akun daun terikat, tampil badge abu-abu netral `0 Akun`.
  - `+Y Induk` (badge sekunder info-subtle): tampil otomatis jika terdapat satu atau lebih akun induk di Master COA yang memiliki tag kelompok ini sebagai template pewarisan untuk sub-akun baru.
- **Pencarian Lintas Nama Pos & Leaf COA**: Input pencarian fleksibel pada toolbar Metode Langsung mencari kecocokan pada `nama` pos referensi maupun kode/nama akun COA daun (*leaf*) yang terpetakan via subquery `whereExists` (dengan pengaman `NOT EXISTS child_coa` agar akun induk tidak memicu false positive).
- **Pemuatan Eager Efisien (Bebas N+1)**: Pemuatan seluruh akun COA terpetakan untuk seluruh baris pos pada halaman aktif dilakukan dalam **satu query batch** gabungan, lalu diklasifikasikan ke dalam `$ref->mapped_leaf_coas` dan `$ref->mapped_parent_coas`.
- **Modal Detail COA Terpetakan (`#modalDetailCoaLangsung`)**:
  - Mengklik badge `[X Akun]` membuka modal `#modalDetailCoaLangsung`.
  - Menampilkan nama pos dan pilar aktivitas pada `#modalDetailLangsungSubtitle`.
  - Menampilkan daftar akun daun pada `#modalDetailLangsungLeafList` lengkap dengan kode akun, nama, badge tipe COA, dan badge status aktif.
  - Jika terdapat akun induk template, section `#modalDetailLangsungParentSection` akan ditampilkan dengan rincian akun induk pada `#modalDetailLangsungParentList`.
- Tombol aksi per baris: Edit (membuka `#modalEditReferensi`) dan Hapus (dilengkapi konfirmasi cerdas yang menolak jika pos sedang digunakan COA).

#### Flowchart

```mermaid
flowchart TD
    A["User buka tab Metode Langsung"] --> B["GET /pengaturan/arus-kas?metode=langsung"]
    B --> C["PengaturanArusKasController::index() -> buildLangsungViewData()"]
    C --> D["Filter aktivitas & search kata kunci"]
    D --> E["Search mencocokkan nama pos ATAU kode/nama leaf COA via whereExists"]
    E --> F["Paginate ReferensiKelompokArusKas (25/halaman)"]
    F --> G["Batch query COA terikat 1 query untuk seluruh pos di halaman aktif"]
    G --> H["Pisahkan mapped_leaf_coas (coa_count) & mapped_parent_coas (parent_coa_count)"]
    H --> I["Muat unmappedLangsungAccounts via getUnmappedCashCounterpartAccounts()"]
    I --> J["Render _tab-langsung.blade.php & _modals-langsung.blade.php"]
    J --> K["User klik badge [X Akun]"]
    K --> L["Modal #modalDetailCoaLangsung menampilkan rincian leaf & parent template"]
```

#### Fungsi yang Dipanggil
- `PengaturanArusKasController::index(Request $request)`
- `PengaturanArusKasController::buildLangsungViewData(Request $request)`
- `ReferensiKelompokArusKas::query()`
- `ReferensiKelompokArusKasService::getUnmappedCashCounterpartAccounts()`
- `ReferensiKelompokArusKasService::getCoaOptionsData()`
- `CoaTreeHelper::partitionParentAndLeafIds()`

---

### Alur L2: Menambah Pos Referensi Kelompok Baru & Pemetaan COA

Pengguna dengan hak akses `create` dapat menambahkan pos referensi kelompok arus kas baru sekaligus memetakan akun-akun COA daun (*leaf*) yang relevan melalui modal `#modalTambahReferensi` (`modal-lg`).

#### Validasi Request
`StoreReferensiKelompokArusKasRequest` memvalidasi input:
- **Sanitasi Pra-Validasi (`prepareForValidation`)**:
  - Membersihkan spasi berlebih pada `nama` via `trim(preg_replace('/\s+/', ' ', $nama))`.
  - Mengonversi `status_aktif` ke boolean via `$this->boolean('status_aktif')`.
  - Normalisasi sentinel `coa_ids`: Jika `coa_ids` bernilai string kosong `""` atau `null`, diubah menjadi array kosong `[]`.
- **Aturan Validasi (`rules`)**:
  - `aktivitas`: Wajib (`required`), string, bernilai salah satu dari: `operasi`, `investasi`, `pendanaan`.
  - `nama`: Wajib (`required`), string, maksimal 150 karakter.
  - `status_aktif`: Opsional (`nullable`), bernilai boolean.
  - `coa_ids`: Opsional (`nullable`), array.
  - `coa_ids.*`: Integer, `distinct`, `exists:coa,id`.
- **Validasi Kustom (`after`)**:
  - Memastikan kombinasi `aktivitas` dan `LOWER(nama)` unik secara *case-insensitive* di tabel `referensi_kelompok_arus_kas`.
  - Memanggil `ReferensiKelompokArusKasService::validateCoaIdsForValidator()` untuk memastikan seluruh akun berstatus daun (*leaf* / `children_count == 0`) dan bukan akun bertipe `Kasbank`.

#### Fitur Antarmuka & UX
1. **Panel Pemilihan COA (`#createCoaSelect`)**: Komponen Select2 multi-select yang memuat seluruh akun daun non-Kasbank.
2. **Pilih Cepat Akun Induk (*Parent COA Expansion*)**:
   - Pengguna memilih akun induk pada `#createParentCoaSelect` dan menekan tombol **Tambah Sub-Akun** (`#btnCreateAddParentLeaves`).
   - Klien mengirim request AJAX ke `GET /pengaturan/arus-kas/langsung/parent-coa-leaves?parent_id=...`.
   - Endpoint mengembalikan klasifikasi:
     - `eligible`: Akun daun belum terpetakan, otomatis dimasukkan ke pilihan Select2.
     - `reassignable`: Akun daun yang saat ini sudah terpetakan di pos lain, ditampung dan akan memunculkan modal konfirmasi pemindahan (`#modalKonfirmasiPindahCoaLangsung`) sebelum formulir disubmit.
     - `skipped`: Akun `Kasbank` yang dilewati secara otomatis disertai pesan informatif.
3. **Konfirmasi Pemindahan Akun COA**: Jika pengguna memilih akun yang sudah memiliki pos kelompok lain, saat tombol Simpan ditekan sistem membuka modal `#modalKonfirmasiPindahCoaLangsung` yang merinci nama akun dan pos asalnya untuk persetujuan pengguna.

#### Flowchart

```mermaid
flowchart TD
    A["User buka modal Tambah Pos Referensi (#modalTambahReferensi)"] --> B["Input aktivitas, nama, dan pilih leaf COA / ekspansi parent COA"]
    B --> C{"Ada akun reassignable dari pos lain?"}
    C -- Ya --> D["User tekan Simpan -> Muncul #modalKonfirmasiPindahCoaLangsung"]
    D --> E["User konfirmasi pemindahan akun"]
    C -- Tidak --> F["Submit POST /pengaturan/arus-kas/langsung"]
    E --> F
    F --> G["StoreReferensiKelompokArusKasRequest validasi & sanitasi"]
    G --> H["PengaturanArusKasController::storeLangsung()"]
    H --> I["ReferensiKelompokArusKasService::store(data) dalam DB::transaction()"]
    I --> J["Insert ReferensiKelompokArusKas"]
    J --> K{"coa_ids disertakan?"}
    K -- Ya --> L["syncCoaMappings(): update arus_kas_aktivitas & arus_kas_kelompok"]
    K -- Tidak --> M["Lewati sync COA"]
    L --> N["Record audit log: create_langsung via LogAktifitasService"]
    M --> N
    N --> O["Redirect ke index (?metode=langsung) dengan flash success"]
```

#### Algoritma
1. Form disubmit via `POST /pengaturan/arus-kas/langsung`.
2. `StoreReferensiKelompokArusKasRequest` menormalisasi input, memvalidasi keunikan nama pos per aktivitas, dan memvalidasi keabsahan akun daun non-Kasbank.
3. Controller memanggil `ReferensiKelompokArusKasService::store($request->validated())`.
4. Service menjalankan blok `DB::transaction()`:
   - Membersihkan nama dengan `sanitizeName()`.
   - Membuat record master: `ReferensiKelompokArusKas::create(...)`.
   - Jika key `coa_ids` ada pada array data, panggil `syncCoaMappings($ref, $data['coa_ids'])`:
     - Memvalidasi seluruh ID berstatus leaf non-Kasbank.
     - Menetapkan `arus_kas_aktivitas = $ref->aktivitas` dan `arus_kas_kelompok = $ref->nama` pada seluruh akun terpilih.
     - Mengidentifikasi akun yang berpindah kelompok (`moved_from`).
   - Membangun snapshot data dan mencatat audit log `'create_langsung'` via `LogAktifitasService`.
5. Controller mengembalikan response redirect ke halaman index tab Metode Langsung dengan flash success.

#### Fungsi yang Dipanggil
- `StoreReferensiKelompokArusKasRequest::prepareForValidation()`, `rules()`, `after()`
- `PengaturanArusKasController::storeLangsung(StoreReferensiKelompokArusKasRequest $request)`
- `PengaturanArusKasController::parentCoaLeavesLangsung(Request $request)`
- `ReferensiKelompokArusKasService::store(array $data)`
- `ReferensiKelompokArusKasService::syncCoaMappings(ReferensiKelompokArusKas $ref, array $coaIds)`
- `ReferensiKelompokArusKasService::getLeafDescendantsByParent(int $parentId, ?int $ignoreReferensiId)`
- `LogAktifitasService::log(...)`

---

### Alur L3: Mengubah Pos Referensi Kelompok, Sinkronisasi COA & Cascade Rename

Mengubah nama pos master akan secara otomatis memperbarui seluruh akun COA yang saat ini ditandai dengan nama lama pos tersebut di dalam satu transaksi database atomik (*cascade rename*), sekaligus menyinkronkan daftar akun daun yang terpetakan.

#### Validasi Request
`UpdateReferensiKelompokArusKasRequest` memvalidasi:
- **Sanitasi Pra-Validasi (`prepareForValidation`)**:
  - Mereduksi whitespace ganda dan trimming pada field `nama`.
  - Normalisasi sentinel `coa_ids`: Jika bernilai string kosong `""` atau `null`, dikonversi menjadi array kosong `[]`.
- **Aturan Validasi (`rules`)**:
  - `nama`: Wajib (`required`), string, maksimal 150 karakter.
  - `status_aktif`: Wajib (`required`), boolean.
  - `coa_ids`: Opsional (`nullable`), array.
  - `coa_ids.*`: Integer, `distinct`, `exists:coa,id`.
- **Validasi Kustom (`after`)**:
  - Mengambil aktivitas pos dari model referensi yang sedang diedit (aktivitas bersifat *immutable* / tidak dapat diubah).
  - Memastikan nama baru unik secara *case-insensitive* terhadap aktivitas pos tersebut, mengabaikan record pos yang sedang diedit (`id != $currentId`).
  - Memanggil `ReferensiKelompokArusKasService::validateCoaIdsForValidator()` untuk memastikan seluruh akun berstatus daun non-Kasbank.

#### Fitur Antarmuka & UX
1. Formulir modal `#modalEditReferensi` (`modal-lg`) memuat data nama pos, badge aktivitas (bersifat tetap / *read-only*), status aktif, serta daftar akun daun yang sedang terpetakan pada `#editCoaSelect`.
2. Tombol **Tambah Sub-Akun** (`#btnEditAddParentLeaves`) memanggil `parentCoaLeavesLangsung` dengan menyertakan `ignore_referensi_id = $ref->id`, sehingga akun-akun daun yang sudah menjadi anggota pos ini diklasifikasikan sebagai `eligible` (bukan `reassignable`).
3. Jika pengguna memilih akun baru yang saat ini dimiliki pos lain, saat tombol Simpan ditekan sistem membuka modal `#modalKonfirmasiPindahCoaLangsung` yang merinci daftar akun yang akan dipindahkan.

#### Flowchart

```mermaid
flowchart TD
    A["User submit form Modal Edit Pos Referensi (#modalEditReferensi)"] --> B["PUT /pengaturan/arus-kas/langsung/{id}"]
    B --> C["UpdateReferensiKelompokArusKasRequest validasi & sanitasi"]
    C --> D["PengaturanArusKasController::updateLangsung()"]
    D --> E["ReferensiKelompokArusKasService::update(referensi, data)"]
    E --> F["Mulai DB::transaction()"]
    F --> G["Catat oldSnapshot & oldLeafIds"]
    G --> H["Update record referensi_kelompok_arus_kas (nama & status_aktif)"]
    H --> I{"Nama pos berubah (cleanName != oldName)?"}
    I -- Ya --> J["Cascade Rename: Update seluruh COA (leaf & parent template)"]
    I -- Tidak --> K{"coa_ids disertakan dalam request?"}
    J --> K
    K -- Ya --> L["syncCoaMappings(): Lepas removed_coa_ids, pasang added_coa_ids & moved_from"]
    K -- Tidak --> M["Pertahankan pemetaan leaf existing"]
    L --> N["Record audit log: update_langsung via LogAktifitasService"]
    M --> N
    N --> O["Commit transaksi & redirect ke index dengan flash success"]
```

#### Algoritma
1. Request diterima dengan data perubahan nama, status aktif, dan opsi `coa_ids` (`PUT /pengaturan/arus-kas/langsung/{referensi}`).
2. `UpdateReferensiKelompokArusKasRequest` memvalidasi input.
3. Controller memanggil `ReferensiKelompokArusKasService::update($referensi, $request->validated())`.
4. Service menjalankan blok `DB::transaction()`:
   - Mencatat `$oldName`, `$oldAktivitas`, `$oldStatus`, dan `$oldLeafIds` untuk snapshot jejak audit awal (`oldSnapshot`).
   - Membersihkan nama pos dengan `sanitizeName()`.
   - Memperbarui model referensi `$ref->update(...)`.
   - **Cascade Rename**: Jika nama pos berubah (`$cleanName !== $oldName`), jalankan pembaruan massal ke seluruh record COA (baik akun daun maupun akun template induk Master COA):
     `Coa::whereRaw('LOWER(arus_kas_aktivitas) = ?', [mb_strtolower($oldAktivitas)])->whereRaw('LOWER(arus_kas_kelompok) = ?', [mb_strtolower($oldName)])->update(['arus_kas_kelompok' => $cleanName]);`
   - **Sinkronisasi Pemetaan Daun (`syncCoaMappings`)**: Jika key `coa_ids` ada pada `$data`:
     - Mengidentifikasi akun leaf yang dicabut (`removed_coa_ids`) dan mengosongkannya: `update(['arus_kas_aktivitas' => null, 'arus_kas_kelompok' => null])`.
     - Mengidentifikasi akun leaf yang baru ditambahkan (`added_coa_ids`) dan akun yang berpindah kelompok (`moved_from`).
     - Menetapkan `arus_kas_aktivitas = $ref->aktivitas` dan `arus_kas_kelompok = $ref->nama` pada seluruh `normalizedIds`.
     - *Penting*: Akun induk template Master COA tidak pernah dicabut atau diubah oleh `syncCoaMappings`.
   - Mencatat audit log `'update_langsung'` via `LogAktifitasService` dengan snapshot baru yang merinci `added_coa_ids`, `removed_coa_ids`, dan `moved_from`.
5. Mengembalikan model yang diperbarui dan melakukan redirect ke index dengan notifikasi sukses.

#### Fungsi yang Dipanggil
- `UpdateReferensiKelompokArusKasRequest::prepareForValidation()`, `rules()`, `after()`
- `PengaturanArusKasController::updateLangsung(UpdateReferensiKelompokArusKasRequest $request, ReferensiKelompokArusKas $referensi)`
- `ReferensiKelompokArusKasService::update(ReferensiKelompokArusKas $ref, array $data)`
- `ReferensiKelompokArusKasService::syncCoaMappings(ReferensiKelompokArusKas $ref, array $coaIds)`
- `LogAktifitasService::log(...)`

---

### Alur L4: Menghapus Pos Referensi Kelompok (Restricted Delete)

Pos referensi yang masih digunakan oleh akun COA (baik akun daun maupun akun induk template) **tidak boleh dihapus** untuk menjaga integritas data akuntansi dan konsistensi laporan keuangan.

#### Flowchart

```mermaid
flowchart TD
    A["User menekan tombol Hapus Pos di baris tabel"] --> B["DELETE /pengaturan/arus-kas/langsung/{id}"]
    B --> C["PengaturanArusKasController::destroyLangsung()"]
    C --> D["ReferensiKelompokArusKasService::canDelete(referensi)"]
    D --> E["Cari seluruh COA terikat (case-insensitive)"]
    E --> F["Hitung leaf_count (akun detail) & parent_count (akun template Master COA)"]
    F --> G{"coa_count (leaf + parent) > 0?"}
    G -- Ya --> H["Return redirect back dengan flash error & pesan spesifik kategori akun"]
    G -- Tidak --> I["ReferensiKelompokArusKasService::delete(referensi)"]
    I --> J["DB::transaction(): Record audit log delete_langsung & referensi->delete()"]
    J --> K["Redirect ke index dengan flash success"]
```

#### Algoritma
1. User mengonfirmasi penghapusan pos pada tombol hapus tabel (didukung dialog konfirmasi JavaScript).
2. Controller memanggil `ReferensiKelompokArusKasService::canDelete($referensi)`.
3. Method `canDelete()` memeriksa seluruh akun COA yang memakai `arus_kas_kelompok = $referensi->nama` pada aktivitas terkait secara *case-insensitive*:
   - Mendeteksi set ID akun induk via `loadParentCoaIdSet()`.
   - Memisahkan penghitungan akun daun (`leaf_count`) dan akun induk (`parent_count`).
   - Jika `leaf_count > 0` dan `parent_count === 0`: `allowed = false`, alasan: `"Pos ini sedang digunakan oleh {$leafCount} akun COA."`.
   - Jika `leaf_count === 0` dan `parent_count > 0`: `allowed = false`, alasan: `"Pos ini sedang digunakan oleh {$parentCount} akun induk (template Master COA)."`.
   - Jika keduanya `> 0`: `allowed = false`, alasan: `"Pos ini sedang digunakan oleh {$leafCount} akun COA dan {$parentCount} akun induk (template Master COA)."`.
   - Jika `coa_count === 0`: `allowed = true`.
4. Di controller, jika `allowed === false`, batalkan proses dan kembalikan redirect `back()` dengan pesan error informatif.
5. Jika `allowed === true`, panggil `ReferensiKelompokArusKasService::delete($referensi)` yang di dalam `DB::transaction()` mencatat snapshot ke audit log `'delete_langsung'` lalu menghapus record pos dari database via `$ref->delete()`.
6. Redirect ke halaman index dengan pesan sukses: `"Pos referensi kelompok arus kas berhasil dihapus."`

#### Fungsi yang Dipanggil
- `PengaturanArusKasController::destroyLangsung(ReferensiKelompokArusKas $referensi)`
- `ReferensiKelompokArusKasService::canDelete(ReferensiKelompokArusKas $ref)`
- `ReferensiKelompokArusKasService::delete(ReferensiKelompokArusKas $ref)`
- `LogAktifitasService::log(...)`

---

### Alur L5: Status Pos Referensi (Pembaruan via Modal Edit & Endpoint Toggle Backend)

Pos yang berstatus **Nonaktif** tidak akan muncul pada daftar dropdown pilihan cepat (*presets*) saat pengguna membuat atau mengedit COA maupun pada modal Quick-Map, namun pemetaan akun COA yang sudah menggunakannya tetap terjaga secara utuh.

1. **Antarmuka Pengguna (Modal Edit)**: Dilakukan melalui Modal Edit dengan memilih dropdown `Status Aktif` (`1: Aktif` atau `0: Nonaktif`), mengeksekusi alur update standar (`PUT`).
2. **Endpoint Aksi Cepat Backend (`PATCH .../toggle`)**: Endpoint `PATCH /pengaturan/arus-kas/langsung/{referensi}/toggle` disediakan untuk membalik nilai boolean `status_aktif` secara instan (`!status_aktif`) untuk pengujian dan otomasi.

#### Algoritma Endpoint Toggle
1. Request `PATCH` diterima oleh controller `toggleStatusLangsung()`.
2. Controller mengeksekusi `ReferensiKelompokArusKasService::toggleStatus($referensi)`.
3. Service menjalankan transaksi database atomik:
   - Membalik nilai boolean `$ref->status_aktif = ! $ref->status_aktif`.
   - Menyimpan record via `$ref->save()`.
   - Mencatat audit log `'toggle_langsung'` via `LogAktifitasService` dengan snapshot `old` dan `new`.
4. Controller memeriksa nilai kembalian boolean dan mengembalikan redirect `back()` dengan pesan sukses:
   `"Pos referensi berhasil diaktifkan."` atau `"Pos referensi berhasil dinonaktifkan."`.

#### Fungsi yang Dipanggil
- `PengaturanArusKasController::toggleStatusLangsung(ReferensiKelompokArusKas $referensi)`
- `ReferensiKelompokArusKasService::toggleStatus(ReferensiKelompokArusKas $ref)`
- `LogAktifitasService::log(...)`

---

### Alur L6: Integrasi Master COA (Dropdown Presets & Sinkronisasi Auto-Insert)

Modul Pengaturan Arus Kas (Metode Langsung) terintegrasi erat dengan modul Bagan Akun (COA):

1. **Pilihan Dropdown Terkelompok (*Grouped Presets*)**:
   - Controller COA (`CoaController::create` & `CoaController::edit`) memanggil `ReferensiKelompokArusKasService::getGroupedActive()`.
   - Data kelompok yang aktif dikirimkan ke view COA dan dipetakan ke atribut `data-referensi` pada elemen select `arus_kas_kelompok`.
   - Komponen Select2 dengan fitur tagging (`tags: true`) menyaring daftar pos sesuai nilai `arus_kas_aktivitas` yang dipilih pengguna.
2. **Konfirmasi & Auto-Insert Pos Baru**:
   - Bila pengguna mengetikkan nama kelompok baru yang belum terdaftar pada master referensi, JavaScript menampilkan dialog konfirmasi penambahan pos baru.
   - Saat form COA disimpan, `CoaService` memanggil `ReferensiKelompokArusKasService::ensureExists($aktivitas, $kelompok)`.
   - Sistem secara otomatis mendaftarkan pos baru tersebut ke master referensi dengan `status_aktif = true` tanpa memutus alur kerja pengguna, dan mengadopsi nama kanoniknya ke record COA.

#### Flowchart

```mermaid
flowchart TD
    A["User membuka form Tambah/Edit COA di modul Bukubesar"] --> B["CoaController memanggil ReferensiKelompokArusKasService::getGroupedActive()"]
    B --> C["Select2 inisialisasi dengan tags: true & filter opsi sesuai aktivitas"]
    C --> D["User memilih pos eksisting ATAU mengetik nama pos baru"]
    D --> E{"Nama baru belum terdaftar di preset?"}
    E -- Ya --> F["JavaScript window.confirm konfirmasi pendaftaran pos baru"]
    E -- Tidak / Konfirmasi OK --> G["User submit form simpan COA"]
    G --> H["CoaService::create() atau CoaService::update()"]
    H --> I{"tipe_coa !== 'Kasbank' & ada aktivitas & kelompok?"}
    I -- Tidak --> J["Kosongkan arus_kas_aktivitas & arus_kas_kelompok = null"]
    I -- Ya --> K["Panggil ReferensiKelompokArusKasService::ensureExists(aktivitas, kelompok)"]
    K --> L["Sanitasi nama pos via sanitizeName()"]
    L --> M{"Pos dengan aktivitas & LOWER(nama) sudah ada di master?"}
    M -- Ya --> N["Gunakan record eksisting ($existing)"]
    M -- Tidak --> O["Insert pos baru via ReferensiKelompokArusKas::create(...)"]
    N --> P["Gunakan nama kanonik ($ref->nama) pada field arus_kas_kelompok COA"]
    O --> P
    P --> Q["Simpan record COA ke database & catat log aktivitas"]
```

#### Fungsi yang Dipanggil
- `CoaController::create()`, `edit(Coa $coa)`
- `ReferensiKelompokArusKasService::getGroupedActive()`
- `CoaService::create(array $data)`, `update(Coa $coa, array $data)`
- `ReferensiKelompokArusKasService::ensureExists(string $aktivitas, string $nama)`
- `ReferensiKelompokArusKasService::sanitizeName(string $name)`

---

### Alur L7: Seeder Data Awal & Migrasi Legacy

Modul dilengkapi dengan seeder `ReferensiKelompokArusKasSeeder` yang menjalankan dua tugas:
1. **Populasi Pos Standar Awal**: Mendaftarkan 14 pos standar industri rumah sakit / klinik:
   - **Aktivitas Operasi (10 pos)**: Penerimaan pasien non BPJS, klaim BPJS, penerimaan lainnya, pembayaran gaji & jasa medis dokter, pembayaran obat & BHP, pembayaran utilitas (listrik/air/internet), pembayaran marketing & PKRS, dana dakwah, pajak, serta beban umum & administrasi lainnya.
   - **Aktivitas Investasi (2 pos)**: Pembelian/pembayaran hutang aset tetap, penerimaan penjualan aset tetap bekas.
   - **Aktivitas Pendanaan (2 pos)**: Penerimaan pinjaman/pembiayaan bank, pembayaran angsuran pokok & bunga pinjaman.
2. **Migrasi Data Legacy COA Eksisting**: Memindai seluruh akun COA yang telah memiliki `arus_kas_aktivitas` dan `arus_kas_kelompok` unik, kemudian secara otomatis mendaftarkannya ke tabel master referensi menggunakan `updateOrCreate` agar tidak terjadi duplikasi.

---

### Alur L8: Deteksi Akun Lawan Transaksi Kasbank Belum Dipetakan & Aksi Cepat (Quick-Map)

Pada laporan arus kas metode langsung, setiap penerimaan atau pengeluaran kas diklasifikasikan berdasarkan akun lawan transaksinya. Jika suatu jurnal transaksi kas/bank memiliki akun lawan non-Kasbank yang belum dipetakan ke pos kelompok arus kas langsung, transaksi kas tersebut berisiko tidak terklasifikasi pada laporan arus kas. Modul menyediakan pemindai otomatis transaksi kas/bank dan fasilitas aksi cepat (*quick-map*).

#### Algoritma Pemindaian Akun Lawan Kasbank
Method `ReferensiKelompokArusKasService::getUnmappedCashCounterpartAccounts(?string $startDate, ?string $endDate)` memindai buku besar:
1. Membaca periode tanggal (default: 1 Januari s.d. 31 Desember tahun berjalan).
2. Mengambil seluruh mutasi buku besar pada rentang tanggal tersebut yang bergabung dengan tabel `coa`.
3. Mengelompokkan baris buku besar berdasarkan kunci unik transaksi:
   - Jika `sumber_id` terisi: `sumber_transaksi | sumber_id`.
   - Jika `sumber_id` kosong: `sumber_transaksi | nomer | tanggal(YYYY-MM-DD)`.
4. Untuk setiap kelompok transaksi:
   - Memeriksa baris mutasi bertipe `Kasbank`. Jika tidak ada atau total mutasi kas net bernilai nol (`abs(sum) < 0.005`), transaksi diabaikan (karena merupakan jurnal memo non-kas).
   - Mengambil baris mutasi akun lawan (akun non-Kasbank).
   - Mengabaikan akun induk (*parent COA*) dan baris dengan nominal mendekati nol (`< 0.005`).
   - Memeriksa apakah akun lawan telah dipetakan: akun dianggap **terpetakan** jika memiliki `arus_kas_aktivitas` yang valid (`operasi`, `investasi`, `pendanaan`) dan `arus_kas_kelompok` tidak kosong.
   - Jika **belum terpetakan**, akun lawan dimasukkan ke dalam daftar unmapped.
   - Mengakumulasi mutasi debit, kredit, frekuensi transaksi (`trx_count`), dan menghitung `dampak_kas = kredit - debit`.
5. Mengembalikan koleksi akun lawan belum dipetakan yang terurut berdasarkan kode COA.

#### Fitur Antarmuka & UX
1. **Banner Peringatan Kuning (`#unmappedLangsungAccountsCollapse`)**:
   - Ditampilkan pada Tab Metode Langsung jika terdapat satu atau lebih akun lawan kasbank yang belum dipetakan.
   - Menampilkan tabel ringkasan: Kode Akun, Nama Akun, Tipe COA, Total Debit, Total Kredit, Frekuensi Transaksi, dan Dampak Kas.
2. **Tombol Tindakan Cepat**:
   - **Petakan** (`.btn-quick-map-langsung`): Membuka Modal Quick-Map (`#modalQuickMapLangsung`) dengan data akun COA bersangkutan langsung terisi.
   - Pada modal quick-map, pengguna dapat memilih pos referensi tujuan dari dropdown terkelompok per aktivitas (`optgroup`), atau menekan tombol **Buat Pos Baru dengan Akun Ini** (`#btnQuickMapCreatePosBaru`) yang menutup modal quick-map dan membuka modal `#modalTambahReferensi` dengan akun tersebut otomatis terpilih di panel COA.

#### Validasi Request Quick-Map
`QuickMapReferensiKelompokArusKasRequest` memvalidasi:
- `coa_id`: Wajib (`required`), integer, `exists:coa,id`.
- `referensi_id`: Wajib (`required`), integer, `exists:referensi_kelompok_arus_kas,id`.
- **Validasi Kustom (`after`)**: Memanggil `validateCoaIdsForValidator` untuk memastikan akun COA berstatus daun (*leaf*) dan bukan akun bertipe `Kasbank`.

#### Flowchart

```mermaid
flowchart TD
    A["User melihat banner #unmappedLangsungAccountsCollapse di tab Langsung"] --> B["Klik tombol 'Petakan' pada salah satu baris akun"]
    B --> C["Modal #modalQuickMapLangsung terbuka dengan akun terpilih"]
    C --> D{"Pilihan tindakan user"}
    D -- "Pilih pos referensi tujuan & Simpan" --> E["POST /pengaturan/arus-kas/langsung/quick-map"]
    D -- "Klik 'Buat Pos Baru dengan Akun Ini'" --> F["Tutup modal quick-map & buka #modalTambahReferensi dengan akun terisi"]
    E --> G["QuickMapReferensiKelompokArusKasRequest validasi"]
    G --> H["PengaturanArusKasController::quickMapLangsung()"]
    H --> I["ReferensiKelompokArusKasService::quickMapCoa(coaId, referensiId)"]
    I --> J["DB::transaction(): Update arus_kas_aktivitas & arus_kas_kelompok pada akun COA"]
    J --> K["Record audit log: quick_map_langsung via LogAktifitasService"]
    K --> L["Redirect ke index (?metode=langsung) dengan flash success"]
```

#### Algoritma Quick-Map
1. Request dikirimkan via `POST /pengaturan/arus-kas/langsung/quick-map` dengan parameter `coa_id` dan `referensi_id`.
2. `QuickMapReferensiKelompokArusKasRequest` memvalidasi keberadaan pos referensi dan memastikan akun COA adalah akun leaf non-Kasbank.
3. Controller memanggil `ReferensiKelompokArusKasService::quickMapCoa($coaId, $referensiId)`.
4. Service menjalankan blok `DB::transaction()`:
   - Mengambil model `ReferensiKelompokArusKas` tujuan.
   - Mengambil model `Coa` dan mencatat data lamanya (`oldPayload`).
   - Memperbarui kolom:
     `$coa->arus_kas_aktivitas = $ref->aktivitas;`
     `$coa->arus_kas_kelompok = $ref->nama;`
     `$coa->save();`
   - Mencatat audit log `'quick_map_langsung'` via `LogAktifitasService`.
5. Mengembalikan redirect ke halaman index tab Metode Langsung dengan flash success informatif:
   `"Akun '[kode] - [nama]' berhasil dipetakan ke kelompok '[nama kelompok]'."`.

#### Fungsi yang Dipanggil
- `QuickMapReferensiKelompokArusKasRequest::rules()`, `after()`
- `PengaturanArusKasController::quickMapLangsung(QuickMapReferensiKelompokArusKasRequest $request)`
- `ReferensiKelompokArusKasService::quickMapCoa(int $coaId, int $referensiId)`
- `ReferensiKelompokArusKasService::getUnmappedCashCounterpartAccounts(?string $startDate, ?string $endDate)`
- `LogAktifitasService::log(...)`

---

## Bagian B: Alur-Alur Metode Tidak Langsung

### Alur TL1: Menampilkan Pos Arus Kas, Pengecualian & Deteksi Akun Belum Dipetakan

Menampilkan daftar konfigurasi pos arus kas tidak langsung, daftar akun pengecualian, dan banner peringatan akun Neraca bermutasi yang belum terpetakan.

#### Fitur Antarmuka & UX

1. **Dual-Mode View Switcher (Mode Tab vs Mode Struktur Laporan)**:
   - Pengguna dapat beralih antara **Mode Tab** (`#viewModeTab`) dan **Mode Struktur Laporan** (`#viewModeStruktur`).
   - Pilihan mode disimpan di `localStorage` peramban pengguna (`siakurat.pengaturan_arus_kas.view_mode`).
   - Jika pengguna memuat URL dengan query parameter `bagian` atau `search`, antarmuka secara otomatis memprioritaskan **Mode Tab**.
2. **Mode Tab**:
   - Filter dropdown bagian laporan mencakup: `Semua Pos`, `Penyesuaian Laba`, `Modal Kerja Operasi`, `Aktivitas Investasi`, `Aktivitas Pendanaan`, dan `Akun Dikecualikan` beserta badge jumlah data di masing-masing kategori.
   - Kolom pencarian fleksibel di `#filterToolbarTidakLangsung` mencocokkan kata kunci terhadap `nama_pos`, `kode_pos`, kode COA terpetakan, dan nama COA terpetakan (serta kode/nama COA dan alasan pada tab pengecualian).
3. **Tampilan Ringkas COA & Modal Detail**:
   - Kolom tabel pos menampilkan nama pos, kode pos berformat monospace, badge `Prefix Perubahan` (jika aktif), serta badge interaktif jumlah akun (`[X Akun COA]`). Pos tanpa akun ditandai dengan badge peringatan kuning `0 Akun`.
   - Mengklik badge `[X Akun COA]` membuka modal interaktif `#modalDetailCoaPos` yang menampilkan judul & kode pos (`#modalDetailPosSubtitle`), daftar akun (`#modalDetailCoaList`), serta status aktif/nonaktif akun.
4. **Banner Akun Neraca Belum Dipetakan**:
   - Jika ada akun Neraca non-Kasbank yang memiliki mutasi saldo pada buku besar periode tahun berjalan namun belum masuk pos aktif maupun pengecualian, sistem menampilkan banner peringatan kuning `#unmappedAccountsCollapse`.
   - Tabel menampilkan daftar akun, tipe COA, total debit, total kredit, dan dampak kas (`kredit - debit`).
   - Disediakan tombol **Tindakan Cepat**:
     - **Petakan** (`.btn-quick-map-unmapped`): Membuka modal Tambah Pos dengan inferensi otomatis sifat pos dan akun COA bersangkutan langsung terpilih.
     - **Kecualikan** (`.btn-quick-exclude-unmapped`): Membuka modal Tambah Pengecualian dengan akun COA bersangkutan langsung terpilih.
5. **Mode Struktur Laporan**:
   - Menampilkan susunan pos per kelompok bagian (`Penyesuaian Laba`, `Modal Kerja`, `Investasi`, `Pendanaan`) dan accordion `Akun Dikecualikan` dalam bentuk kartu collapsible bersarang dengan chevron berputar.
   - Dilengkapi tombol global **Buka Semua** (`#btnExpandAllSections`) dan **Tutup Semua** (`#btnCollapseAllSections`).
   - Setiap baris pos di Mode Struktur Laporan dilengkapi tombol kontrol urutan naik (↑) dan turun (↓) yang mengirim pembaruan urutan secara AJAX ke server.

#### Algoritma Deteksi Akun Neraca Belum Dipetakan
1. `PengaturanArusKasService::getUnmappedMutatedNeracaAccounts()` memuat mutasi buku besar tahun berjalan:
   - Mengambil daftar `coa_id` yang terpetakan ke pos **aktif** (`setting_arus_kas.is_active = true`) dan `coa_id` pada tabel `setting_arus_kas_pengecualian`.
   - Mengagregasi mutasi debit dan kredit pada tabel `bukubesar` untuk akun Neraca non-Kasbank (`tipe_coa != 'kasbank'` dan bukan `SettingArusKas::TIPE_LABA_RUGI`, termasuk akun Neraca berstatus nonaktif) yang tidak berada dalam daftar yang diabaikan.
   - Menyaring akun yang nilai mutasinya memenuhi `abs(debit) >= 0.005 || abs(kredit) >= 0.005` dan menghitung `dampak_kas = kredit - debit`.

---

### Alur TL2: Ekspansi Akun Induk (Parent COA) ke Leaf COA

Ketika pengguna memilih akun induk (*parent COA*) pada modal Tambah/Edit Pos (`#parentCoaSelect`) atau modal Tambah Pengecualian (`#pengecualianParentCoaSelect`) dan menekan tombol **Tambah Sub-Akun** (`#btnAddParentLeaves`) atau **Pilih Semua** (`#btnAddPengecualianParentLeaves`), skrip klien memanggil endpoint AJAX untuk mengekspansi seluruh akun daun turunan (*leaf descendants*) di bawah parent tersebut dan mengklasifikasikannya ke dalam kelompok `eligible` atau `skipped`.

#### Validasi Request
`PengaturanArusKasController::parentCoaLeaves()` memvalidasi query parameter secara langsung:
- `parent_id`: wajib (`required`), integer, minimal `1`.
- `sifat_pos`: opsional (`nullable`), string, salah satu dari `SettingArusKas::SIFAT_POS` atau `'pengecualian'`.
- `ignore_pos_id`: opsional (`nullable`), integer, minimal `1`.

#### Flowchart

```mermaid
flowchart TD
    A["User klik 'Tambah Sub-Akun' / 'Pilih Semua' di modal"] --> B["GET /pengaturan/arus-kas/tidak-langsung/parent-coa-leaves"]
    B --> C["PengaturanArusKasController::parentCoaLeaves()"]
    C --> D["PengaturanArusKasService::getLeafDescendantsByParent()"]
    D --> E["Bangun relasi parent_coa -> children"]
    E --> F["Telusuri leaf descendants secara rekursif (collectLeafDescendants)"]
    F --> G{"Loop tiap leaf COA"}
    G --> H["isCoaCompatibleWithSifat(leaf, sifatPos)"]
    H -- Tidak kompatibel --> I["Masukkan ke skipped + reason"]
    H -- Kompatibel --> J{"Ada di pengecualian / pos lain?"}
    J -- Ya --> I
    J -- Tidak --> K["Masukkan ke eligible"]
    I --> L["Return JSON { eligible, skipped }"]
    K --> L
    L --> M["Frontend: Tambah eligible ke daftar pilihan COA & render alert feedback"]
```

#### Algoritma
1. `PengaturanArusKasService::getLeafDescendantsByParent()` memuat seluruh akun COA dan membentuk peta `parent_coa -> children`.
2. Prosedur rekursif `collectLeafDescendants()` menelusuri seluruh turunan dengan guard `$visited` untuk mencegah loop hierarki hingga menemukan node daun (*leaf*).
3. Untuk setiap leaf COA, service melakukan evaluasi:
   - **Kompatibilitas Tipe & Status Akun (`isCoaCompatibleWithSifat`)**:
     - Menolak akun `Kasbank`.
     - Sifat Laba Rugi (`penambah_laba`, `pengurang_laba`) hanya menerima akun `TIPE_LABA_RUGI` yang berstatus **aktif** (`status_aktif = 1`).
     - Sifat Neraca (`aset`, `liabilitas`, `ekuitas`) dan mode `pengecualian` menolak akun Laba Rugi, namun mengizinkan akun Neraca non-Kasbank berstatus nonaktif.
   - **Daftar Pengecualian (`loadExcludedCoaIdSet`)**: Jika akun tercantum dalam tabel `setting_arus_kas_pengecualian`, akun dimasukkan ke `skipped` dengan alasan pengecualian.
   - **Pemetaan Existing (`loadMappedByPosMap`)**: Jika akun sudah dipetakan ke pos lain (di luar `ignoreSettingArusKasId`), akun dimasukkan ke `skipped`.
4. Mengembalikan respons JSON `{ eligible: [...], skipped: [...] }`.

---

### Alur TL3: Menambah & Mengubah Pos Arus Kas Tidak Langsung serta Sinkronisasi COA

Form modal `#modalPosArusKas` digunakan untuk menambah (*create*) maupun menyunting (*edit*) pos arus kas tidak langsung.

#### Validasi Request
`StoreSettingArusKasRequest` memvalidasi penambahan pos baru (dan diwarisi oleh `UpdateSettingArusKasRequest`):
- **Sanitasi Pra-Validasi (`prepareForValidation`)**:
  - `nama_pos`: Menghapus spasi di awal/akhir dan mereduksi spasi ganda menjadi spasi tunggal.
  - `kode_pos`: Melakukan trim; bila bernilai string kosong diubah menjadi `null` agar otomatis dibuatkan kode oleh service.
  - `pakai_prefix_perubahan` dan `is_active`: Dinormalisasi menjadi boolean.
- **Aturan Validasi (`rules`)**:
  - `kode_pos`: opsional (`nullable`), string, maksimal `80` karakter, unik pada tabel `setting_arus_kas,kode_pos`. Pada update, ID pos yang sedang diubah diabaikan.
  - `nama_pos`: wajib (`required`), string, maksimal `150` karakter.
  - `kelompok_bagian`: wajib (`required`), string, salah satu dari: `penyesuaian_laba`, `modal_kerja`, `investasi`, `pendanaan`.
  - `sifat_pos`: wajib (`required`), string, salah satu dari: `aset`, `liabilitas`, `ekuitas`, `penambah_laba`, `pengurang_laba`.
  - `pakai_prefix_perubahan`: opsional (`nullable`), boolean.
  - `urutan`: wajib (`required`), integer, minimal `1`.
  - `is_active`: opsional (`nullable`), boolean.
  - `coa_ids`: opsional (`nullable`), array.
  - `coa_ids.*`: integer, `distinct`, `exists:coa,id`.
- **Validasi Kustom (`after`)**: Memanggil `PengaturanArusKasService::validateCoaIdsForValidator()` untuk menjamin seluruh akun berstatus *leaf*, kompatibel dengan `sifat_pos`, tidak terdaftar di pengecualian, dan belum dipetakan ke pos lain.

#### Flowchart

```mermaid
flowchart TD
    A["User submit form Tambah / Edit Pos"] --> B["POST /pengaturan/arus-kas/tidak-langsung atau PUT .../tidak-langsung/{id}"]
    B --> C["StoreSettingArusKasRequest / UpdateSettingArusKasRequest"]
    C --> D["PengaturanArusKasController::store() / update()"]
    D --> E["PengaturanArusKasService::store() / update()"]
    E --> F["Mulai DB::transaction()"]
    F --> G["Simpan / Update record SettingArusKas"]
    G --> H{"Sinkronisasi coa_ids diperlukan?"}
    H -- Ya --> I["syncCoaMappings(): validasi leaf, replace setting_arus_kas_coa"]
    H -- Tidak --> J["Pertahankan pemetaan existing"]
    I --> K["LogAktifitasService::log('Pengaturan Arus Kas', 'create'/'update', ...)"]
    J --> K
    K --> L["Redirect ke index (?metode=tidak_langsung) dengan flash success"]
```

#### Fungsi yang Dipanggil
- `StoreSettingArusKasRequest`, `UpdateSettingArusKasRequest`
- `PengaturanArusKasController::store()`, `update()`
- `PengaturanArusKasService::store()`, `update()`, `syncCoaMappings()`, `validateCoaIds()`
- `LogAktifitasService::log(...)`

---

### Alur TL4: Mengubah Status Aktif & Menghapus Pos Arus Kas Tidak Langsung

1. **Toggle Status Pos (`toggleStatus`)**:
   - `PATCH /pengaturan/arus-kas/tidak-langsung/{settingArusKas}/toggle`
   - Memanggil `PengaturanArusKasService::toggleStatus($settingArusKas)`.
   - Di dalam `DB::transaction()`, membalik status boolean `$pos->is_active = !$pos->is_active`, menyimpan, dan mencatat audit log.
   - Redirect `back()` dengan pesan flash sukses.
2. **Hapus Pos Arus Kas (`destroy`)**:
   - `DELETE /pengaturan/arus-kas/tidak-langsung/{settingArusKas}`
   - Di dalam `DB::transaction()`, menghapus seluruh pemetaan anak di `setting_arus_kas_coa`, menghapus record `SettingArusKas`, dan mencatat log audit.
   - Redirect ke `pengaturan.arus-kas.index` (`metode=tidak_langsung`) dengan pesan sukses.

---

### Alur TL5: Mengubah Urutan Pos Arus Kas (Reorder) via Mode Struktur Laporan

Pengurutan pos di dalam satu kelompok bagian laporan dilakukan melalui antarmuka Mode Struktur Laporan menggunakan tombol panah naik (↑) dan turun (↓) yang mengirim AJAX ke server.

#### Validasi Request
`ReorderSettingArusKasRequest` memvalidasi:
- `kelompok_bagian`: wajib (`required`), string, salah satu dari `SettingArusKas::KELOMPOK_BAGIAN`.
- `ordered_ids`: wajib (`required`), array, minimal 1 item.
- `ordered_ids.*`: wajib (`required`), integer, `distinct`, `exists:setting_arus_kas,id`.
- **Validasi Kustom (`after`)**: Memastikan seluruh ID pos di dalam `ordered_ids` memiliki nilai `kelompok_bagian` yang konsisten.

#### Flowchart

```mermaid
flowchart TD
    A["User klik tombol panah naik/turun di Mode Struktur Laporan"] --> B["PATCH /pengaturan/arus-kas/tidak-langsung/reorder (AJAX)"]
    B --> C["ReorderSettingArusKasRequest validasi"]
    C --> D["PengaturanArusKasController::reorder()"]
    D --> E["PengaturanArusKasService::reorder(kelompokBagian, orderedIds)"]
    E --> F["Update urutan 1..N dalam DB::transaction() + audit log"]
    F --> G["Return JSON { success: true, ordered_ids: [...] }"]
```

---

### Alur TL6: Mengelola Daftar Pengecualian Akun COA

Akun pengecualian digunakan untuk menandai akun Neraca non-Kasbank (seperti akumulasi penyusutan aset tetap atau penyisihan piutang) agar tidak dimasukkan ke pos arus kas dan tidak muncul pada banner peringatan unmapped accounts.

#### Validasi Request
`StorePengecualianArusKasRequest` memvalidasi:
- `coa_ids`: wajib (`required`), array, minimal 1 akun.
- `coa_ids.*`: integer, `distinct`, `exists:coa,id`.
- `alasan`: opsional (`nullable`), string, maksimal `255` karakter.
- **Validasi Kustom (`after`)**: Memanggil `PengaturanArusKasService::validateCoaIdsForValidator($validator, $coaIds, 'pengecualian')` guna menjamin seluruh akun berstatus *leaf*, bertipe Neraca non-Kasbank, belum berada di daftar pengecualian, dan belum dipetakan ke pos arus kas mana pun.

#### Operasi Tambah & Hapus
- **Tambah (`storePengecualian`)**: Menyisipkan record ke tabel `setting_arus_kas_pengecualian` di dalam `DB::transaction()` dan mencatat audit log `'create_pengecualian'`.
- **Hapus (`destroyPengecualian`)**: Menghapus record `SettingArusKasPengecualian` di dalam `DB::transaction()` dan mencatat audit log `'delete_pengecualian'`.

---

## Catatan Penting Bisnis

1. **Pengendalian Akses Modul Tunggal (Unified RBAC)**:
   - Seluruh endpoint konfigurasi arus kas (baik Metode Langsung maupun Metode Tidak Langsung) dikendalikan secara terpusat oleh satu modul hak akses: `pengaturan.arus-kas` (urutan registry: 206).
   - Hak akses default diberikan penuh (`view`, `create`, `update`, `delete`) kepada peran sistem (`roles.is_system = 1`, mis. Administrator/Superadmin).
   - Migrasi `2026_11_24_000002_add_referensi_kelompok_arus_kas_access_module.php` (legacy) dan migrasi penggabungan `2026_11_26_000001_merge_referensi_kelompok_arus_kas_into_pengaturan_arus_kas_module.php` menyatukan izin ke `pengaturan.arus-kas` serta membersihkan modul referensi kelompok lama dari tabel `role_permissions` dan `access_modules` secara aman.
2. **Karakteristik Metode Langsung vs Metode Tidak Langsung**:
   - **Metode Langsung** (*Direct Cash Flow*): Menggunakan penandaan kolom `arus_kas_aktivitas` dan `arus_kas_kelompok` pada master akun `coa`. Setiap jurnal kas/bank akan diklasifikasikan berdasarkan akun lawannya. Menghasilkan laporan di `/laporan/keuangan/arus-kas`.
   - **Metode Tidak Langsung** (*Indirect Cash Flow*): Menggunakan pemetaan tabel dinamis `setting_arus_kas`, `setting_arus_kas_coa`, dan `setting_arus_kas_pengecualian`. Menghasilkan laporan di `/laporan/keuangan/arus-kas-tidak-langsung`.
3. **Aturan Khusus Akun Kasbank vs Non-Kasbank**:
   - Akun COA bertipe `Kasbank` bertindak sebagai kas/setara kas itu sendiri, sehingga **tidak boleh** memiliki kelompok arus kas pada master COA (`arus_kas_kelompok = null`), dan **tidak boleh** dipetakan ke pos arus kas tidak langsung maupun dimasukkan ke daftar pengecualian.
4. **Immutabilitas Aktivitas & Cascade Update pada Metode Langsung**:
   - Kategori pilar aktivitas (`operasi`, `investasi`, `pendanaan`) pada pos referensi metode langsung bersifat *immutable* (tidak dapat diubah setelah dibuat) demi mencegah pergeseran klasifikasi fundamental pada akun COA terkait.
   - Pengubahan nama pos referensi dijalankan dalam `DB::transaction()` dan otomatis memicu *cascade update* ke seluruh record `coa` yang menggunakan nama lama.
5. **Proteksi Penghapusan (*Restricted Delete*) pada Pos Metode Langsung**:
   - Pos referensi metode langsung yang sedang terikat pada akun daun COA (`leaf_count > 0`) maupun akun induk template Master COA (`parent_count > 0`) diblokir dari penghapusan via `canDelete()`.
   - Pesan penolakan membedakan secara transparan apakah pos digunakan oleh akun detail (`leaf_count`) atau akun induk template pewarisan Master COA (`parent_count`). Pengguna dapat menonaktifkan status pos (`status_aktif = false`) agar tidak muncul di pilihan dropdown COA baru tanpa mengganggu riwayat akun yang sudah ada.
6. **Batasan Eksklusivitas & Keunikan pada Metode Tidak Langsung**:
   - Satu akun COA hanya dapat dipetakan ke **maksimal satu** pos arus kas aktif/nonaktif (`UNIQUE(coa_id)` pada `setting_arus_kas_coa`) dan tidak boleh berada di daftar pengecualian sekaligus (`UNIQUE(coa_id)` pada `setting_arus_kas_pengecualian`).
   - Sifat Laba Rugi (`penambah_laba`, `pengurang_laba`) hanya menerima akun Laba Rugi yang aktif (`status_aktif = 1`).
   - Sifat Neraca (`aset`, `liabilitas`, `ekuitas`) menerima akun Neraca non-Kasbank, termasuk akun Neraca nonaktif yang memiliki mutasi historis.
7. **Perilaku Pos Nonaktif pada Rekonsiliasi Kas**:
   - Ketika pos arus kas tidak langsung dinonaktifkan (`is_active = false`), data pemetaannya tetap dipertahankan di database.
   - Namun, scanner `getUnmappedMutatedNeracaAccounts()` dan Laporan Keuangan dinamis hanya memperhitungkan pos **aktif**. Akun-akun pada pos nonaktif yang memiliki mutasi tahun berjalan akan otomatis muncul di banner peringatan **belum dipetakan** agar pengguna menyadari adanya aliran kas yang belum terefleksi pada laporan aktif.
8. **Konsumsi pada Laporan Keuangan & Tautan Lintas Modul**:
   - Pada halaman Laporan Arus Kas Tidak Langsung (`/laporan/keuangan/arus-kas-tidak-langsung`), banner peringatan akun belum dipetakan menyediakan tombol pintasan yang mengarahkan pengguna langsung ke tab Metode Tidak Langsung:
     `route('pengaturan.arus-kas.index', ['metode' => 'tidak_langsung'])` (hanya tampil jika pengguna memiliki izin `pengaturan.arus-kas,view`).
9. **Jejak Audit & Idempotensi Seeder**:
   - Seluruh mutasi konfigurasi pos arus kas dicatat ke dalam audit log `LogAktifitasService` dengan nama modul `'Pengaturan Arus Kas'`.
   - Baik `ReferensiKelompokArusKasSeeder` maupun `SettingArusKasSeeder` bersifat idempoten: keduanya menyemai data standar awal tanpa menimpa perubahan kustom yang telah dilakukan oleh pengguna.
10. **Single Source of Truth pada Metode Langsung**:
    - Kolom `coa.arus_kas_aktivitas` dan `coa.arus_kas_kelompok` adalah sumber kebenaran tunggal (*single source of truth*) untuk pelaporan arus kas langsung di SiAkurat.
    - Sinkronisasi pemetaan pada antarmuka Pengaturan Arus Kas secara langsung memutasi nilai kolom ini secara atomik melalui `syncCoaMappings()` atau `quickMapCoa()`.
11. **Perlindungan Tag Akun Induk (*Parent COA Tag Protection*)**:
    - Akun induk (*parent COA*) yang memiliki tag kelompok di Master COA (berfungsi sebagai template pewarisan untuk sub-akun baru) terlindungi dari penghapusan pos referensi via `canDelete()` (`parent_count`).
    - Akun induk tidak pernah dicabut atau diubah oleh sinkronisasi akun daun (`syncCoaMappings()`).
    - Satu-satunya saat akun induk dimutasi di modul Pengaturan Arus Kas adalah ketika terjadi *cascade rename* nama pos kelompok referensi.
12. **Perbedaan Kriteria Pemindai Arus Kas (Metode Langsung vs Tidak Langsung)**:
    - **Pemindai Metode Langsung** (`getUnmappedCashCounterpartAccounts`): Melacak transaksi buku besar tahun berjalan yang melibatkan mutasi akun `Kasbank` (dengan net kas `>= 0.005`), lalu mendeteksi baris akun lawan non-Kasbank yang belum memiliki tag `arus_kas_aktivitas` dan `arus_kas_kelompok`. Jurnal memorial non-kas secara otomatis diabaikan.
    - **Pemindai Metode Tidak Langsung** (`getUnmappedMutatedNeracaAccounts`): Melacak mutasi saldo seluruh akun Neraca non-Kasbank tahun berjalan yang belum terpetakan ke pos arus kas aktif maupun daftar pengecualian.
