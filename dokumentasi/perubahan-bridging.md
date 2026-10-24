# Ringkasan Komprehensif Perubahan Arsitektur Bridging: Pendapatan, Obat & Pembelian

Dokumen ini merangkum secara lengkap dan mendalam seluruh pekerjaan modernisasi, perbaikan, serta refaktorisasi arsitektur yang telah diimplementasikan pada 3 modul bridging utama di Siakurat:
1. **Bridging Pendapatan** (Rawat Jalan & Rawat Inap)
2. **Bridging Pendapatan Obat** (Tarik Tagihan Obat & BHP serta Tarik Piutang Obat & BHP)
3. **Bridging Pembelian** (Pemesanan Obat & BHP serta Pemesanan Barang Non Medis)

Seluruh perubahan ini diintegrasikan dan telah dipublikasikan pada Git branch **`khanza-pku-mojoagung`**.

---

## 1. Latar Belakang & Masalah Sebelumnya

Sebelum refaktorisasi ini dilakukan, sistem memiliki ketergantungan kritis pada tabel jurnal internal SIMRS:

1. **Ketergantungan Kuat pada Jurnal SIMRS (`jurnal` & `detailjurnal`)**:
   - Modul Bridging mengambil akun lawan (kas/bank/piutang/hutang) dan nominal rinciannya dari tabel `jurnal` dan `detailjurnal` SIMRS.
   - Modul Pembelian juga bergantung pada tabel `mapping_coa_simrs` untuk menerjemahkan kode rekening SIMRS ke COA lokal.

2. **Kendala Operasional Nyata**:
   - **Gagal Impor Transaksi**: Jika kasir SIMRS belum atau terlambat memicu posting jurnal otomatis di SIMRS, proses bridging di Siakurat langsung gagal dengan pesan error:
     - *"Jurnal SIMRS tidak ditemukan untuk nomor rawat ini"* (Pendapatan), atau
     - *"Data jurnal SIMRS tidak ditemukan untuk nomor bukti ini"* (Pembelian).
   - **Inkonsistensi Rekening**: Kode rekening di jurnal SIMRS kerap berubah atau tidak terdaftar di `mapping_coa_simrs`, menyebabkan transaksi terhambat.
   - **Tidak Terbentuknya HPP & Pengurangan Persediaan Obat**: Pada transaksi penjualan obat di kasir, HPP dan mutasi kredit persediaan tidak terbentuk secara otomatis mengikuti harga beli riil.
   - **Inkonsistensi Nilai**: Adanya pembulatan atau selisih pencatatan antara billing kasir dan rincian jurnal SIMRS.

---

## 2. Matriks Komparasi Arsitektur: Sebelum vs Sesudah

| Aspek | Arsitektur Lama | Arsitektur Baru (Sekarang) |
| --- | --- | --- |
| **Sumber Akun Lawan Pendapatan** | Tabel `jurnal` & `detailjurnal` SIMRS | Rincian Cara Bayar Billing Kasir (`detail_nota_*` JOIN `akun_bayar`) + `akun_piutang` penjamin |
| **Sumber Akun Pembelian** | Tabel `jurnal` SIMRS + `mapping_coa_simrs` | Konfigurasi Terpusat di Menu **Setting Akun Pembelian** (`SettingBridgingPembelian`) |
| **Sumber Akun Obat & BHP** | Tabel `jurnal` SIMRS | Cara Bayar Penjualan + Menu **Setting Akun Obat & BHP** (`SettingBridgingObat`) |
| **Perhitungan HPP Obat** | Bergantung jurnal SIMRS (seringkali kosong) | Dihitung otomatis: $\sum (\text{detailjual.jumlah} \times \text{detailjual.h\_beli})$ |
| **Pencatatan Persediaan Obat** | Tidak tercatat otomatis pada penjualan apotek | Tercatat otomatis di Kredit Persediaan sebesar HPP |
| **Perhitungan PPN Pembelian** | Mengikuti baris debet jurnal SIMRS | Otomatis: jika $\text{ppn} > 0$ dibuat debit PPN Masukan; jika $\text{ppn} = 0$ dilewati |
| **Keseimbangan Debit & Kredit** | Bergantung pada balance jurnal SIMRS | Dijamin seimbang 100% oleh sistem Siakurat |
| **Ketergantungan SIMRS** | Membaca tabel transaksi + tabel jurnal SIMRS | **Hanya membaca tabel transaksi sumber** (bebas dari tabel `jurnal` SIMRS) |

---

## 3. Rincian Perubahan & Perbaikan per Modul

### A. Bridging Pendapatan (Rawat Jalan & Rawat Inap)

#### 1. Penentuan Akun Lawan Berbasis Cara Bayar Kasir
- **Kas/Bank**: Diambil langsung dari data transaksi pembayaran nota kasir SIMRS:
  - Rawat Jalan: tabel `detail_nota_jalan` JOIN `akun_bayar` $\rightarrow$ mendapatkan `kd_rek` kasir.
  - Rawat Inap: tabel `detail_nota_inap` JOIN `akun_bayar` $\rightarrow$ mendapatkan `kd_rek` kasir.
- **Piutang Penjamin (BPJS / Asuransi / Perusahaan)**:
  - Jika pembayaran nota kasir lebih kecil dari total billing (misal: selisih biaya penjamin atau pasien asuransi murni tanpa pembayaran kasir tunai), sisa tagihan otomatis dialokasikan ke akun piutang penjamin dari tabel `akun_piutang` SIMRS.
- Akun kasir dan akun piutang penjamin kemudian dipetakan ke COA lokal melalui tabel `mapping_lawan_pendapatan_simrs`.

#### 2. Dampak Pencatatan
- **Jurnal Umum**: Terbentuk seimbang:
  - Debit: Kas/Bank (sebesar nominal bayar kasir) + Piutang Penjamin (sebesar sisa piutang).
  - Kredit: Rekening-rekening pendapatan tindakan medis.
- **Invoice Pendapatan**: Terbentuk `FakturPenjualan` dengan kolom `sudah_terbayar` terisi dari nominal kasir dan sisa tagihan tercatat sebagai piutang.

---

### B. Bridging Pendapatan Obat & BHP (Tarik Tagihan & Tarik Piutang)

#### 1. Penghapusan Ketergantungan Tabel Jurnal SIMRS
- Modul tidak lagi mengakses tabel `jurnal` dan `detailjurnal` SIMRS.
- Seluruh kebutuhan akun diselesaikan melalui **Cara Bayar Billing Obat** dan **Setting Akun Terpusat**.

#### 2. Modul Baru: Setting Akun Obat & BHP (`/pengaturan/setting-obat`)
Disediakan menu dan antarmuka konfigurasi terpusat untuk mengelola 5 akun COA:
1. **Akun Pendapatan Penjualan Obat & BHP** (Kredit penjualan).
2. **Akun HPP Obat & BHP** (Debit Harga Pokok Penjualan).
3. **Akun Persediaan Obat & BHP** (Kredit pengurangan persediaan).
4. **Akun Piutang Obat & BHP** (Debit sisa piutang tagihan obat apotek).
5. **Akun Kas / Uang Muka Piutang Obat** (Debit penerimaan uang muka piutang obat).

#### 3. Tarik Tagihan Obat & BHP (Penjualan Bebas & Resep)
- **Akun Kas/Bank**: Diambil dari `penjualan.kd_rek` (atau via nama bayar ke `akun_bayar.kd_rek`) lalu dipetakan melalui `MappingLawanPendapatanSimrs`.
- **HPP Otomatis**: Dihitung dari detail obat: $\sum(\text{detailjual.jumlah} \times \text{detailjual.h\_beli})$.
- **Pencatatan 4 Akun Seimbang**:
  - **Debit**: Kas / Bank (sebesar `grandtotal`)
  - **Kredit**: Pendapatan Obat & BHP (sebesar `grandtotal`)
  - **Debit**: HPP Obat & BHP (sebesar `total_hpp`, jika $> 0$)
  - **Kredit**: Persediaan Obat & BHP (sebesar `total_hpp`, jika $> 0$)

#### 4. Tarik Piutang Obat & BHP
- Mengambil data dari `piutang` dan `detailpiutang` SIMRS dengan validasi rekonsiliasi: $\text{grandtotal} = \text{uangmuka} + \text{sisapiutang}$.
- Membentuk `FakturPenjualan` dengan nomor `P-[nota_piutang]` dan `akun_piutang_id = piutang_obat_coa_id`.
- Membentuk mutasi Buku Besar sumber `Invoice Piutang Obat`:
  - **Debit**: Piutang Obat & BHP (sebesar `sisapiutang`)
  - **Debit**: Kas / Uang Muka (sebesar `uangmuka`, jika $> 0$)
  - **Kredit**: Persediaan Obat & BHP (sebesar `grandtotal`)

---

### C. Bridging Pembelian (Obat & BHP serta Barang Non Medis)

#### 1. Penghapusan Ketergantungan Tabel Jurnal SIMRS
- Modul tidak lagi membaca tabel `jurnal` dan `detailjurnal` SIMRS maupun tabel `mapping_coa_simrs`.
- Menghilangkan kegagalan impor akibat jurnal SIMRS belum dibuat.

#### 2. Modul Baru: Setting Akun Pembelian (`/pengaturan/setting-pembelian`)
Disediakan menu dan antarmuka konfigurasi terpusat untuk mengelola 5 akun COA:
1. **Akun Persediaan Obat & BHP** (Debit DPP faktur obat).
2. **Akun PPN Masukan (Bersama)** (Debit nilai PPN jika $> 0$).
3. **Akun Hutang Pembelian Obat & BHP** (Kredit total tagihan faktur obat).
4. **Akun Persediaan / Beban Non Medis** (Debit DPP faktur non medis).
5. **Akun Hutang Pembelian Non Medis** (Kredit total tagihan faktur non medis).

Validasi *strict* diterapkan: proses impor tagihan ditolak dengan instruksi jelas jika pengaturan akun belum lengkap.

#### 3. Logika Jurnal & Mutasi Buku Besar Terstruktur
- **DPP Eksak**: Dihitung dari $\text{DPP} = \text{tagihan} - \text{ppn}$.
  - Verifikasi data riil SIMRS membuktikan formula $\text{tagihan} = \text{total detail} + \text{ppn}$ konsisten 100% (0 mismatch).
- **PPN Kondisional**:
  - Jika $\text{ppn} > 0$: dibuat mutasi Debit PPN Masukan sebesar nominal PPN.
  - Jika $\text{ppn} = 0$: baris mutasi PPN dilewati (hanya Debit Persediaan dan Kredit Hutang).
- **Keseimbangan Mutasi**:
  $$\text{Total Debit } (\text{DPP} + \text{PPN}) = \text{Total Kredit } (\text{Tagihan})$$

---

## 4. Masalah yang Ditemukan & Solusi Teknis (Bug Fixes)

Selama proses pengerjaan dan pengujian, ditemukan beberapa kendala teknis yang telah diselesaikan tuntas:

### 1. Masalah Dropdown COA Kosong pada Halaman Pengaturan
- **Gejala**: Dropdown pilihan akun COA pada menu Setting Akun Obat kosong atau tidak memunculkan seluruh akun yang ada di sistem.
- **Penyebab**: Kode awal menggunakan query `Coa::query()->where('is_postable', true)`. Pada database operasional riil, banyak akun aktif memiliki nilai `is_postable = 0` atau belum di-flag sebagai postable.
- **Solusi**: Diubah menjadi `Coa::query()->active()->orderBy('kode')->get(['id', 'kode', 'nama'])`, memastikan seluruh akun dengan `status_aktif = 1` dapat dipilih dan didukung oleh pencarian Select2.

### 2. Penanganan PPN Pembelian Bernilai 0
- **Gejala**: Jika faktur pembelian tidak dikenakan PPN ($\text{ppn} = 0$), pencatatan mutasi PPN dengan nilai 0 dapat mengotori Buku Besar.
- **Solusi**: Diberikan pengecekan kondisional `if ((float) $tagihan['ppn'] > 0)`. Jika 0, baris mutasi PPN tidak dibuat sehingga Buku Besar tetap ringkas dan rapi.

### 3. Kompatibilitas Pengujian SQLite In-Memory
- **Gejala**: Unit testing pada driver SQLite in-memory sempat gagal karena kata kunci SQL spesifik MySQL (`BINARY`) dan ketiadaan tabel `log_aktifitas` pada mock SQLite.
- **Solusi**:
  - Menghilangkan kata kunci `BINARY` agar query kompatibel di MySQL maupun SQLite.
  - Menyediakan skema tabel `log_aktifitas` yang identik dengan skema database production di method `prepareTables()`.
  - Menyelaraskan nama kolom periode `periode_tahun` dan `periode_bulan` pada tabel `bukubesar` mock test.

---

## 5. Katalog Komponen Teknis yang Dibuat & Dimodifikasi

| Komponen / File | Tipe | Keterangan |
| --- | --- | --- |
| `database/migrations/2026_10_24_060000_create_setting_bridging_obats_table.php` | Migration | Tabel `setting_bridging_obats` untuk konfigurasi 5 COA obat |
| `database/migrations/2026_10_24_060100_add_setting_obat_access_module.php` | Migration | Registrasi modul otorisasi `pengaturan.setting-obat` |
| `database/migrations/2026_10_24_070000_create_setting_bridging_pembelians_table.php` | Migration | Tabel `setting_bridging_pembelians` untuk konfigurasi 5 COA pembelian |
| `database/migrations/2026_10_24_070100_add_setting_pembelian_access_module.php` | Migration | Registrasi modul otorisasi `pengaturan.setting-pembelian` |
| `app/Support/AccessModuleRegistry.php` | Support | Registrasi modul setting obat dan setting pembelian ke registry |
| `app/Models/SettingBridgingObat.php` | Model | Eloquent model dengan relasi `BelongsTo` ke 5 COA obat |
| `app/Models/SettingBridgingPembelian.php` | Model | Eloquent model dengan relasi `BelongsTo` ke 5 COA pembelian |
| `app/Http/Requests/Pengaturan/UpdateSettingBridgingObatRequest.php` | Form Request | Validasi input form setting akun obat |
| `app/Http/Requests/Pengaturan/UpdateSettingBridgingPembelianRequest.php` | Form Request | Validasi input form setting akun pembelian |
| `app/Services/Pengaturan/SettingBridgingObatService.php` | Service | Business logic get setting, list active COA, dan save setting obat |
| `app/Services/Pengaturan/SettingBridgingPembelianService.php` | Service | Business logic get setting, list active COA, dan save setting pembelian |
| `app/Http/Controllers/Pengaturan/SettingBridgingObatController.php` | Controller | Controller index & update setting obat |
| `app/Http/Controllers/Pengaturan/SettingBridgingPembelianController.php` | Controller | Controller index & update setting pembelian |
| `routes/web.php` | Routing | Rute GET/POST `/pengaturan/setting-obat` dan `/pengaturan/setting-pembelian` |
| `resources/views/pengaturan/setting-obat/index.blade.php` | Blade View | UI form setting 5 akun obat dengan Select2 |
| `resources/views/pengaturan/setting-pembelian/index.blade.php` | Blade View | UI form setting 5 akun pembelian dengan Select2 |
| `resources/views/partials/sidebar.blade.php` & `menu.blade.php` | Blade View | Navigasi menu Setting Akun Obat & BHP dan Setting Akun Pembelian |
| `app/Services/Bridging/BridgingPendapatanService.php` | Service | Logika akun lawan dari cara bayar billing kasir & piutang penjamin |
| `app/Services/Bridging/BridgingPendapatanObatService.php` | Service | Refaktor tarik obat & piutang obat berbasis cara bayar kasir & setting obat |
| `app/Services/Bridging/BridgingPembelianService.php` | Service | Refaktor tarik pembelian obat & non medis berbasis setting akun terpusat |
| `tests/Feature/SettingBridgingObatTest.php` | Feature Test | Test otorisasi, render halaman, dan update setting obat |
| `tests/Feature/SettingBridgingPembelianTest.php` | Feature Test | Test otorisasi, render halaman, dan update setting pembelian |
| `tests/Unit/BridgingPendapatanObatServiceTest.php` | Unit Test | Test alur tarik obat, HPP, persediaan, dan piutang apotek |
| `tests/Unit/BridgingPembelianServiceTest.php` | Unit Test | Test alur pembelian obat & non medis dengan PPN > 0 dan PPN = 0 |
| `tests/Feature/BridgingPembelianDataTableTest.php` | Feature Test | Test DataTable hasil import faktur pembelian |
| `tests/Feature/BridgingPembelianTagihanObatDataTableTest.php` | Feature Test | Test DataTable kandidat tagihan obat SIMRS |
| `dokumentasi/pengaturan-setting-obat.md` | Dokumentasi | Dokumentasi modul baru Setting Akun Obat & BHP |
| `dokumentasi/pengaturan-setting-pembelian.md` | Dokumentasi | Dokumentasi modul baru Setting Akun Pembelian |
| `dokumentasi/bridging-pendapatan-obat.md` | Dokumentasi | Pembaruan flowchart, algoritma, dan catatan bisnis bridging obat |
| `dokumentasi/bridging-pembelian.md` | Dokumentasi | Pembaruan flowchart, algoritma, dan catatan bisnis bridging pembelian |
| `dokumentasi/fondasi/sistem-otorisasi.md` | Dokumentasi | Pembaruan modul hak akses dan aksi modul baru |
| `dokumentasi/README.md` | Dokumentasi | Pembaruan index dokumentasi sistem |
| `AGENTS.md` | Panduan | Sinkronisasi aturan dan mapping Code $\rightarrow$ Documentation |

---

## 6. Hasil Verifikasi & Pengujian Mutu (QA)

Rangkaian pengujian otomatis dijalankan secara berkala untuk memastikan tidak ada regresi (*zero regression*):

1. **Suite Bridging Pembelian & Setting Pembelian**:
   - `tests/Feature/SettingBridgingPembelianTest.php` $\rightarrow$ 5 passed
   - `tests/Unit/BridgingPembelianServiceTest.php` $\rightarrow$ 7 passed
   - `tests/Feature/BridgingPembelianDataTableTest.php` $\rightarrow$ 4 passed
   - `tests/Feature/BridgingPembelianTagihanObatDataTableTest.php` $\rightarrow$ 4 passed
2. **Suite Bridging Pendapatan Obat & Setting Obat**:
   - `tests/Feature/SettingBridgingObatTest.php` $\rightarrow$ 4 passed
   - `tests/Unit/BridgingPendapatanObatServiceTest.php` $\rightarrow$ 8 passed
   - `tests/Feature/BridgingPendapatanObatDataTableTest.php` $\rightarrow$ 4 passed
3. **Suite Bridging Pendapatan Ralan & Ranap**:
   - `tests/Unit/BridgingPendapatanServiceTest.php` $\rightarrow$ 10 passed
4. **Verifikasi Data Riil SIMRS**:
   - Script rekonsiliasi membuktikan konsistensi formula tagihan ($\text{tagihan} = \text{total rincian} + \text{ppn}$) pada 100 sampel transaksi tanpa selisih (0 mismatch).

**Total**: 46 assertions/tests pada rangkaian modul bridging lulus 100%.

---

## 7. Riwayat Git Commit pada Branch `khanza-pku-mojoagung`

Seluruh pekerjaan ini terdokumentasi rapi dalam riwayat commit Git pada branch `khanza-pku-mojoagung`:

1. `8457f0d` — `feat(bridging): ambil akun lawan pendapatan dari cara bayar kasir dan piutang SIMRS`
2. `f421a7f` — `feat(bridging-obat): hapus ketergantungan jurnal SIMRS, gunakan cara bayar kasir & setting akun obat terpusat`
3. `ca68330` — `fix(setting-obat): tampilkan semua coa aktif pada dropdown field select setting akun obat`
4. `13f9254` — `docs: tambahkan ringkasan perubahan arsitektur bridging pendapatan dan obat di perubahan-bridging.md`
5. `7885fe3` — `feat(bridging): implementasi setting akun terpusat pada bridging pembelian dan pelepasan jurnal simrs`
