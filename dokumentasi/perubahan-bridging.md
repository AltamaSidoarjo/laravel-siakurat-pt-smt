# Ringkasan Perubahan Arsitektur Bridging Pendapatan, Pendapatan Obat & Pembelian

Dokumen ini merangkum perubahan menyeluruh yang telah diimplementasikan pada modul **Bridging Pendapatan**, **Bridging Pendapatan Obat**, dan **Bridging Pembelian**, yaitu peralihan dari pembacaan tabel jurnal SIMRS ke penentuan akun lawan langsung dari **Cara Bayar billing kasir SIMRS** serta konfigurasi akun terpusat.

---

## 1. Latar Belakang & Masalah Sebelumnya

Sebelum perubahan ini dilakukan:
1. **Ketergantungan Kuat pada Jurnal SIMRS**:
   Modul Bridging Pendapatan dan Bridging Pendapatan Obat bergantung pada tabel `jurnal` dan `detailjurnal` SIMRS untuk mengambil akun lawan (kas/bank/piutang) dan rincian nominalnya.
2. **Kendala Operasional**:
   - Jika kasir SIMRS belum atau terlambat memicu jurnal otomatis di SIMRS, proses bridging di Siakurat akan gagal (*"Jurnal SIMRS tidak ditemukan"*).
   - Format dan kode akun di jurnal SIMRS kerap berubah atau tidak konsisten dengan kebutuhan Buku Besar akuntansi lokal.
   - Pada transaksi obat, HPP dan pengurangan persediaan tidak selalu terbentuk otomatis sesuai harga beli riil.

---

## 2. Rincian Perubahan Modul

### A. Bridging Pendapatan (Rawat Jalan & Rawat Inap)

#### 1. Pengambilan Akun Lawan Berbasis Cara Bayar Kasir
- **Kas/Bank**: Diambil langsung dari rincian pembayaran nota kasir SIMRS:
  - Rawat Jalan: tabel `detail_nota_jalan` JOIN `akun_bayar`.
  - Rawat Inap: tabel `detail_nota_inap` JOIN `akun_bayar`.
- **Piutang Penjamin (BPJS / Asuransi / Perusahaan)**: Jika terdapat sisa tagihan setelah pembayaran nota kasir (atau pasien penjamin murni tanpa kasir), sisa nominal dialokasikan otomatis ke rekening piutang penjamin dari tabel `akun_piutang` SIMRS.
- Akun lawan kasir dan piutang penjamin kemudian dipetakan ke COA lokal melalui tabel `mapping_lawan_pendapatan_simrs`.

#### 2. Dampak Pencatatan
- **Jurnal Umum**: Terbentuk seimbang antara total pendapatan (kredit) dan total cara bayar kas/bank + piutang (debit).
- **Invoice Pendapatan**: Terbentuk `FakturPenjualan` dengan `sudah_terbayar` dari total kas/bank dan sisa tagihan tercatat sebagai piutang penjamin. Mutasi Buku Besar mencatat akun kas/bank dan piutang penjamin secara akurat.

---

### B. Bridging Pendapatan Obat (Tarik Tagihan & Tarik Piutang)

#### 1. Penghapusan Ketergantungan Tabel Jurnal SIMRS
- Modul tidak lagi membaca tabel `jurnal` dan `detailjurnal` SIMRS.
- Seluruh kebutuhan akun diselesaikan melalui **Cara Bayar** dan **Setting Akun Terpusat**.

#### 2. Modul Baru: Setting Akun Obat & BHP (`/pengaturan/setting-obat`)
Dibuat menu dan modul baru di Pengaturan untuk mengelola 5 COA tetap:
1. **Akun Pendapatan Penjualan Obat & BHP** (Kredit penjualan).
2. **Akun HPP Obat & BHP** (Debit Harga Pokok Penjualan).
3. **Akun Persediaan Obat & BHP** (Kredit pengurangan persediaan).
4. **Akun Piutang Obat & BHP** (Debit sisa piutang tagihan obat apotek).
5. **Akun Kas / Uang Muka Piutang Obat** (Debit penerimaan uang muka piutang obat).

Seluruh COA aktif (`status_aktif = 1`) ditampilkan secara lengkap pada dropdown select dengan dukungan Select2 untuk memudahkan pencarian.

#### 3. Tarik Tagihan Obat & BHP (Penjualan Bebas / Resep)
- **Akun Kas/Bank**: Diambil dari `penjualan.kd_rek` (atau via `penjualan.nama_bayar` $\rightarrow$ `akun_bayar.kd_rek`) dan dipetakan via `MappingLawanPendapatanSimrs`.
- **Perhitungan HPP Otomatis**: Dihitung langsung dari rincian barang: $\sum(\text{detailjual.jumlah} \times \text{detailjual.h\_beli})$.
- **Pencatatan 4 Akun Seimbang**:
  - **Debit**: Kas / Bank (sebesar `grandtotal`)
  - **Kredit**: Pendapatan Obat & BHP (sebesar `grandtotal`)
  - **Debit**: HPP Obat & BHP (sebesar `total_hpp`, jika $> 0$)
  - **Kredit**: Persediaan Obat & BHP (sebesar `total_hpp`, jika $> 0$)
- Baik pada **Jurnal Umum** maupun **Invoice Pendapatan**, mutasi Buku Besar mencatat keempat akun tersebut sehingga laporan Laba Rugi dan Neraca selalu sinkron.

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
- Tidak ada lagi kegagalan impor akibat jurnal SIMRS belum terbentuk (*"Data jurnal SIMRS tidak ditemukan untuk nomor bukti ini"*).

#### 2. Modul Baru: Setting Akun Pembelian (`/pengaturan/setting-pembelian`)
Dibuat menu dan modul baru di Pengaturan untuk mengelola 5 COA tetap:
1. **Akun Persediaan Obat & BHP** (Debit DPP faktur obat).
2. **Akun PPN Masukan (Bersama)** (Debit nilai PPN jika $> 0$).
3. **Akun Hutang Pembelian Obat & BHP** (Kredit total tagihan faktur obat).
4. **Akun Persediaan / Beban Non Medis** (Debit DPP faktur non medis).
5. **Akun Hutang Pembelian Non Medis** (Kredit total tagihan faktur non medis).

Validasi strict diterapkan: proses impor tagihan ditolak dengan instruksi jelas bila pengaturan akun belum dikonfigurasi.

#### 3. Logika Jurnal & Mutasi Buku Besar Terstruktur
- **DPP Eksak**: Dihitung dari $\text{DPP} = \text{tagihan} - \text{ppn}$.
- **PPN Kondisional**: Jika $\text{ppn} > 0$, dibuat mutasi Debit PPN Masukan. Jika $\text{ppn} = 0$, baris PPN dilewati.
- **Keseimbangan Jurnal**: Total Debit $(\text{DPP} + \text{ppn} = \text{tagihan})$ selalu seimbang dengan Total Kredit (tagihan) pada mutasi Buku Besar sumber `Invoice Pembelian`.

---

## 3. Komponen Teknis yang Dibuat & Dimodifikasi

| Layer / File | Keterangan Perubahan |
| --- | --- |
| `database/migrations/2026_10_24_060000_create_setting_bridging_obats_table.php` | Tabel `setting_bridging_obats` untuk menyimpan konfigurasi 5 COA obat |
| `database/migrations/2026_10_24_060100_add_setting_obat_access_module.php` | Registrasi modul hak akses `pengaturan.setting-obat` |
| `database/migrations/2026_10_24_070000_create_setting_bridging_pembelians_table.php` | Tabel `setting_bridging_pembelians` untuk konfigurasi 5 COA pembelian |
| `database/migrations/2026_10_24_070100_add_setting_pembelian_access_module.php` | Registrasi modul hak akses `pengaturan.setting-pembelian` |
| `app/Support/AccessModuleRegistry.php` | Registrasi modul `pengaturan.setting-obat` dan `pengaturan.setting-pembelian` |
| `app/Models/SettingBridgingObat.php` | Model Eloquent dengan relasi ke 5 COA obat |
| `app/Models/SettingBridgingPembelian.php` | Model Eloquent dengan relasi ke 5 COA pembelian |
| `app/Http/Requests/Pengaturan/UpdateSettingBridgingObatRequest.php` | Validasi input form setting akun obat |
| `app/Http/Requests/Pengaturan/UpdateSettingBridgingPembelianRequest.php` | Validasi input form setting akun pembelian |
| `app/Services/Pengaturan/SettingBridgingObatService.php` | Service logika get, active COA options, dan save setting obat |
| `app/Services/Pengaturan/SettingBridgingPembelianService.php` | Service logika get, active COA options, dan save setting pembelian |
| `app/Http/Controllers/Pengaturan/SettingBridgingObatController.php` | Controller index dan update setting akun obat |
| `app/Http/Controllers/Pengaturan/SettingBridgingPembelianController.php` | Controller index dan update setting akun pembelian |
| `routes/web.php` | Definisi route GET dan POST `/pengaturan/setting-obat` & `/pengaturan/setting-pembelian` |
| `resources/views/pengaturan/setting-obat/index.blade.php` | Halaman UI formulir setting 5 akun obat dengan Select2 |
| `resources/views/pengaturan/setting-pembelian/index.blade.php` | Halaman UI formulir setting 5 akun pembelian dengan Select2 |
| `resources/views/partials/sidebar.blade.php` & `menu.blade.php` | Penambahan navigasi menu Setting Akun Obat & BHP serta Setting Akun Pembelian |
| `app/Services/Bridging/BridgingPendapatanService.php` | Logika akun lawan dari cara bayar kasir + piutang penjamin |
| `app/Services/Bridging/BridgingPendapatanObatService.php` | Refaktor total tarik obat & piutang obat berbasis cara bayar & setting obat |
| `app/Services/Bridging/BridgingPembelianService.php` | Refaktor tarik pembelian obat & non medis berbasis setting akun terpusat |
| `tests/Feature/SettingBridgingObatTest.php` | Feature test otorisasi, render halaman, dan update setting obat |
| `tests/Feature/SettingBridgingPembelianTest.php` | Feature test otorisasi, render halaman, dan update setting pembelian |
| `tests/Unit/BridgingPendapatanObatServiceTest.php` | Unit test komprehensif alur bridging obat & piutang obat |
| `tests/Unit/BridgingPembelianServiceTest.php` | Unit test komprehensif alur bridging pembelian obat & non medis |
| `tests/Feature/BridgingPembelianDataTableTest.php` & `BridgingPembelianTagihanObatDataTableTest.php` | Feature test DataTable bridging pembelian |
| `dokumentasi/pengaturan-setting-obat.md` | Dokumentasi modul baru Setting Akun Obat & BHP |
| `dokumentasi/pengaturan-setting-pembelian.md` | Dokumentasi modul baru Setting Akun Pembelian |
| `dokumentasi/bridging-pendapatan-obat.md` | Pembaruan flowchart, algoritma, dan catatan bisnis bridging obat |
| `dokumentasi/bridging-pembelian.md` | Pembaruan flowchart, algoritma, dan catatan bisnis bridging pembelian |
| `dokumentasi/fondasi/sistem-otorisasi.md` | Penambahan modul hak akses dan aksi modul baru |
| `dokumentasi/README.md` | Index daftar dokumentasi sistem |
| `AGENTS.md` | Sinkronisasi aturan dan mapping Code $\rightarrow$ Documentation |

---

## 4. Hasil Verifikasi & Pengujian

- **Automated Tests**:
  - `tests/Feature/SettingBridgingObatTest.php` $\rightarrow$ 5 tests passed.
  - `tests/Unit/BridgingPendapatanObatServiceTest.php` $\rightarrow$ 8 tests passed.
  - `tests/Feature/BridgingPendapatanObatDataTableTest.php` $\rightarrow$ 4 tests passed.
  - `tests/Feature/SettingBridgingPembelianTest.php` $\rightarrow$ 5 tests passed.
  - `tests/Unit/BridgingPembelianServiceTest.php` $\rightarrow$ 7 tests passed.
  - `tests/Feature/BridgingPembelianDataTableTest.php` $\rightarrow$ 4 tests passed.
  - `tests/Feature/BridgingPembelianTagihanObatDataTableTest.php` $\rightarrow$ 4 tests passed.
  - Seluruh rangkaian test bridging pendapatan, obat, dan pembelian (37 tests) lulus 100% tanpa regresi.
- **Git Branch**: Branch aktif `khanza-pku-mojoagung`.
