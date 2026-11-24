# Design Document: Standarisasi Input Kelompok Arus Kas dengan Autocomplete Preset & Menu Referensi Master

**Tanggal:** 2026-11-24  
**Status:** FINAL (Ready for Implementation Plan)  
**Tipe:** Project  

---

## 1. Intent & The Why

### Latar Belakang
Pada modul Buku Besar & COA aplikasi SIAKURAT, pengisian field **Kelompok Arus Kas** (`coa.arus_kas_kelompok`) pada form Tambah/Edit COA saat ini menggunakan input teks bebas (*free-text*). Di sisi pelaporan (`LaporanKeuanganService`), penyusunan Laporan Arus Kas Metode Langsung mengelompokkan transaksi per kelompok arus kas menggunakan `groupBy('kelompok')`.

### Masalah
Input teks bebas rentan menimbulkan inkonsistensi penamaan (salah ketik, perbedaan spasi, atau perbedaan huruf besar/kecil). Akibatnya, baris pos di Laporan Arus Kas terpecah-pecah menjadi beberapa pos yang sebenarnya sama (misalnya: *"Penerimaan dari pasien dan pelanggan"* dan *"Penerimaan dari pasien, pelanggan, dan pendapatan operasional"*). Staf keuangan telah menyusun 14 nama pos standar dalam draf laporan arus kas (`DRAFT CASHFLOW SIAKURAT.xlsx`).

### Tujuan (The Why)
Menerapkan standarisasi input kelompok arus kas dengan antarmuka yang terarah (*chained autocomplete* berdasarkan Aktivitas Arus Kas), didukung oleh tabel master referensi yang dapat dikelola secara mandiri oleh staf keuangan/admin melalui menu Pengaturan, tanpa mengorbankan fleksibilitas pembuatan pos baru jika sewaktu-waktu dibutuhkan.

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
1. Mengeliminasi inkonsistensi penulisan nama kelompok arus kas pada master COA.
2. Menyediakan dropdown rekomendasi otomatis di form COA yang tersaring secara kontekstual (*chained*) sesuai Aktivitas Arus Kas yang dipilih (*Operasi*, *Investasi*, atau *Pendanaan*).
3. Memberikan fleksibilitas terkontrol bagi user untuk menambahkan label pos baru langsung dari form COA (`tags: true`) dengan konfirmasi dan sanitasi.
4. Menyediakan modul CRUD Referensi Kelompok Arus Kas di bawah menu Pengaturan, lengkap dengan toggle status aktif dan fitur *Cascade Update* ke akun COA terkait jika nama pos diubah.
5. Melakukan seeding awal untuk 14 pos standar draf arus kas serta memigrasikan pos eksisting yang sudah ada di database saat ini.
6. Memenuhi seluruh *Mandatory Authorization Checklist* sesuai konvensi arsitektur proyek.

### Non-Goals
1. Tidak mengubah skema relasi database inti `coa.arus_kas_kelompok` menjadi foreign key integer; tetap mempertahankan tipe string untuk backward-compatibility penuh dengan mesin laporan yang ada.
2. Tidak mengubah logika kalkulasi matematika arus kas di `LaporanKeuanganService`.

### Anti-Goals
1. Mencegah master referensi menjadi kotor akibat salah ketik (typo) bebas tanpa validasi/konfirmasi.
2. Tidak memaksakan kolom `urutan` buatan yang membebani staf keuangan; pengurutan dilakukan secara alfabetis alami (`nama ASC`).
3. Tidak mengizinkan pembuatan duplikat nama pos yang hanya berbeda huruf besar/kecil dalam satu aktivitas.

---

## 3. Constraints

1. **Arsitektur SIAKURAT (AGENTS.md):**
   - Controller tipis (Thin Controllers), logika bisnis di service class (`ReferensiKelompokArusKasService`).
   - Validasi menggunakan Form Request (`StoreReferensiKelompokArusKasRequest`, `UpdateReferensiKelompokArusKasRequest`).
   - Kepatuhan PSR-12 dan konvensi Laravel.
2. **Mandatory Authorization Checklist (5 Langkah):**
   - Registrasi di `App\Support\AccessModuleRegistry::all()` dengan kode `pengaturan.referensi-kelompok-arus-kas`.
   - Migrasi modul akses dan pemberian default permission ke role sistem (`is_system = 1`).
   - Proteksi route dengan middleware `module.access`.
   - Penambahan menu sidebar di `resources/views/partials/sidebar.blade.php` dengan pengecekan `$can(...)`.
   - Dokumentasi modul di `dokumentasi/` dan feature test hak akses (403 vs 200).
3. **Database:**
   - MySQL-compatible, semua perubahan melalui migrasi yang dapat di-rollback (`down()`).

---

## 4. Approaches Considered & Trade-offs

### Pendekatan 1: Tabel Master Referensi String + Chained Select2 Tags + Cascade Update *(DIPILIH)*
- **Deskripsi:** Kolom `coa.arus_kas_kelompok` tetap bertipe `string` (`VARCHAR 150`). Dibuat tabel baru `referensi_kelompok_arus_kas` (`id`, `aktivitas`, `nama`, `status_aktif`, `timestamps`). Form COA menggunakan Select2 dengan opsi yang terfilter dinamis mengikuti `arus_kas_aktivitas`. Jika user menambahkan tag baru, sistem melakukan sanitasi, menyimpan ke COA, dan otomatis mendaftarkannya ke tabel master referensi via `firstOrCreate`. Jika nama pos di master referensi diedit/dikoreksi, sistem menyediakan opsi *Cascade Update* untuk memperbarui seluruh akun COA yang menggunakan nama lama.
- **Trade-off:** Memerlukan sedikit penanganan sinkronisasi string di backend saat nama diubah, namun 100% aman dan tidak menimbulkan breaking changes pada query laporan keuangan yang telah berjalan.
- **Alasan Dipilih:** Memberikan titik temu ideal antara fleksibilitas staf keuangan, stabilitas kode laporan eksisting, dan tata kelola data yang rapi.

### Pendekatan 2: Relasi Relasional Ketat (Foreign Key ID pada COA)
- **Deskripsi:** Mengubah skema `coa` dengan mengganti string `arus_kas_kelompok` menjadi foreign key `referensi_kelompok_arus_kas_id`.
- **Trade-off:** Memerlukan migrasi data destruktif pada tabel utama `coa`, refaktor query laporan keuangan di `LaporanKeuanganService` (harus join/eager load), dan penambahan pos baru via Select2 memerlukan pemanggilan AJAX async terpisah sebelum submit form COA.
- **Alasan Ditolak:** Risiko regresi tinggi dan over-engineering untuk kebutuhan standardisasi pos pelaporan.

### Pendekatan 3: Hardcoded Enum / Config File
- **Deskripsi:** 14 pos standar disimpan dalam file konfigurasi PHP tanpa tabel database.
- **Trade-off:** Sangat murah diimplementasikan, namun kaku. Staf keuangan tidak dapat menambah, membetulkan nama, atau menonaktifkan pos secara mandiri tanpa campur tangan programmer.
- **Alasan Ditolak:** Bertentangan langsung dengan kebutuhan pengguna yang tertuang dalam dokumen starter context.

---

## 5. Keputusan Desain yang Telah Disepakati

### 5.1 Skema Database `referensi_kelompok_arus_kas`
- `id` (bigint, unsigned, auto-increment, primary key)
- `aktivitas` (varchar(50)): Nilai valid: `'operasi'`, `'investasi'`, `'pendanaan'`
- `nama` (varchar(150))
- `status_aktif` (tinyint(1), default 1)
- `created_at`, `updated_at` (timestamps)
- **Indeks:** Index gabungan pada `[aktivitas, nama]`.
- **Catatan Kolom Urutan:** Berdasarkan keputusan pengguna, kolom `urutan` **ditiadakan** agar form master tetap ringkas dan pengurutan dilakukan secara alfabetis (`nama ASC`).

### 5.2 3 Lapis Perlindungan Data (Guardrails)
1. **Lapis 1 - UI Form COA:**
   - Select2 dikonfigurasi dengan pencarian case-insensitive.
   - Pilihan kelompok arus kas di-chain sesuai nilai `arus_kas_aktivitas`.
   - Saat user memasukkan nama pos baru yang belum ada di daftar, Select2/JavaScript memunculkan konfirmasi dialog ringan sebelum mendaftarkan tag baru.
2. **Lapis 2 - Sanitasi & Pengecekan Backend:**
   - String dibersihkan dari spasi ganda dan di-trim (`trim(preg_replace('/\s+/', ' ', $nama))`).
   - Pengecekan keberadaan dilakukan case-insensitive (`where('aktivitas', $aktivitas)->whereRaw('LOWER(nama) = ?', [strtolower($nama)])`). Jika sudah ada (hanya beda kapitalisasi), sistem menggunakan nama baku yang sudah tersimpan.
   - Jika belum ada, sistem membuat record baru di `referensi_kelompok_arus_kas` dengan `status_aktif = 1`.
3. **Lapis 3 - Tata Kelola Menu Master (Governance, Restricted Delete, & Cascade Update):**
   - **Restricted Delete:** Pos master hanya dapat dihapus (*destroy*) jika **tidak sedang digunakan** oleh akun COA mana pun. Jika sedang digunakan, penghapusan ditolak dengan pesan peringatan ramah (*"Pos ini sedang digunakan oleh X akun COA. Silakan nonaktifkan statusnya jika tidak ingin digunakan lagi"*).
   - **Cascade Update:** Pengeditan nama pos di menu master referensi otomatis memperbarui (*cascade update*) seluruh record di `coa` yang menggunakan `(arus_kas_aktivitas, arus_kas_kelompok)` lama ke nama baru dalam satu transaksi database.
   - **Immutabilitas Aktivitas pada Edit:** Field `aktivitas` dikunci (*read-only*) saat edit nama pos master untuk mencegah reklasifikasi sepihak yang membahayakan laporan keuangan.
4. **Otorisasi Pembuatan Tag di Form COA:**
   - User dengan hak akses tulis pada modul COA (`bukubesar.coa`) berhak membuat tag baru (dengan dialog konfirmasi); tidak dibatasi oleh hak akses modul menu Pengaturan agar alur kerja staf akuntansi tidak terblokir.

### 5.3 Seeder Awal (`ReferensiKelompokArusKasSeeder`)
Menyediakan 14 pos standar dari draf Excel, ditambah pos eksisting di database:
1. **Operasi:**
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
2. **Investasi:**
   - Pembelian & pembayaran hutang aset tetap
   - Penerimaan penjualan aset tetap bekas
3. **Pendanaan:**
   - Penerimaan pinjaman/pembiayaan bank
   - Pembayaran angsuran pokok & bunga pinjaman
4. **Data Eksisting:**
   - Menarik seluruh `distinct(arus_kas_kelompok)` yang saat ini ada di tabel `coa` (yang belum tercakup di 14 pos) untuk dimasukkan ke master referensi dengan `status_aktif = 0` (Nonaktif). Hal ini memastikan catatan historis tetap tersimpan tanpa mengotori dropdown rekomendasi form COA baru.
   - Tidak dilakukan remapping otomatis pada record `coa` lama demi menjaga integritas data historis.

---

## 6. What's Left to the Builder

Hal-hal detail implementasi teknis berikut diserahkan sepenuhnya kepada builder/developer:
1. Penyusunan layout tampilan blade untuk menu master (`index`, modal create/edit, tombol toggle status aktif).
2. Penyusunan struktur Service & Form Request (`ReferensiKelompokArusKasService`, `StoreReferensiKelompokArusKasRequest`, `UpdateReferensiKelompokArusKasRequest`).
3. Mekanisme passing data referensi ke form COA (bisa dioper langsung dari `CoaController@create` dan `@edit` via View, atau endpoint JSON internal jika diperlukan).
4. Penulisan Feature Test mencakup:
   - Akses modul (403 jika tidak berhak, 200 untuk admin).
   - CRUD master referensi kelompok arus kas.
   - Verifikasi auto-insert master saat COA disimpan dengan tag baru.
   - Verifikasi cascade update saat nama master diedit.
5. Pembaruan dokumentasi teknis pada `dokumentasi/fondasi/sistem-otorisasi.md`, `dokumentasi/pengaturan-referensi-kelompok-arus-kas.md`, dan `dokumentasi/README.md`.
