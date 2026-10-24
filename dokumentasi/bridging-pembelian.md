# Dokumentasi Modul Bridging — Pembelian

## Ringkasan Modul

Modul **Bridging Pembelian** digunakan untuk:

1. menarik kandidat tagihan pembelian **Obat & BHP** dan **Barang Non Medis** dari SIMRS,
2. mengimpor tagihan terpilih menjadi **Invoice Pembelian** (`FakturPembelian`),
3. menghapus data hasil import secara massal (dengan proteksi bila sudah ada pembayaran).

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Bridging/BridgingPembelianController.php`
- `app/Http/Requests/Bridging/ImportPembelianObatRequest.php`
- `app/Http/Requests/Bridging/ImportPembelianNonMedisRequest.php`
- `app/Http/Requests/Bridging/BulkDeleteBridgingPembelianRequest.php`
- `app/Services/Bridging/BridgingPembelianService.php`
- `app/Models/FakturPembelian.php`, `app/Models/FakturPembelianRinci.php`, `app/Models/Supplier.php`,
  `app/Models/SettingBridgingPembelian.php`, `app/Models/BukuBesar.php`

## Entry Point / API

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/bridging/pembelian` | `bridging.pembelian.index` | `index()` | view |
| `GET` | `/bridging/pembelian/load-imported-data` | `bridging.pembelian.load-imported-data` | `loadImportedData()` | view |
| `GET` | `/bridging/pembelian/tarik-obat` | `bridging.pembelian.tarik-obat` | `tarikPembelianObat()` | view |
| `GET` | `/bridging/pembelian/load-tagihan-obat` | `bridging.pembelian.load-tagihan-obat` | `loadTagihanObat()` | view |
| `GET` | `/bridging/pembelian/tarik-nonmedis` | `bridging.pembelian.tarik-nonmedis` | `tarikPembelianNonMedis()` | view |
| `GET` | `/bridging/pembelian/load-tagihan-nonmedis` | `bridging.pembelian.load-tagihan-nonmedis` | `loadTagihanNonMedis()` | view |
| `POST` | `/bridging/pembelian/process-import-obat` | `bridging.pembelian.process-import-obat` | `processImportObat()` | update |
| `POST` | `/bridging/pembelian/process-import-nonmedis` | `bridging.pembelian.process-import-nonmedis` | `processImportNonMedis()` | update |
| `POST` | `/bridging/pembelian/destroy-bulk` | `bridging.pembelian.destroy-bulk` | `destroyBulk()` | delete |

## Validasi Request

`ImportPembelianObatRequest` / `ImportPembelianNonMedisRequest`:

- `selectedNoTransaksi` wajib array minimal 1 (item string maks 100),
- `jenisProses` hanya boleh `InvoicePembelian`,
- `metodeTanggalPengakuan` hanya boleh `TanggalInvoice` atau `TanggalBarangDatang`.

`BulkDeleteBridgingPembelianRequest`: `selectedNoTransaksi` wajib array minimal 1 (item string).

## Alur 1: Tarik Kandidat Tagihan dari SIMRS

Dua sumber kandidat dengan pola sama:

- **Obat & BHP**: tabel SIMRS `pemesanan` + `datasuplier` (`loadTagihanObat()`, query builder,
  mendukung pencarian per kolom).
- **Barang Non Medis**: tabel SIMRS `ipsrspemesanan` + `ipsrssuplier` (`loadTagihanNonMedis()`, collection).

```mermaid
flowchart TD
    A["GET /tarik-obat atau /tarik-nonmedis"] --> B["Render view tarik"]
    B --> C["DataTable load-tagihan-obat / load-tagihan-nonmedis"]
    C --> D["Service getKandidatPembelianObatQuery() / getKandidatPembelianNonMedis()"]
    D --> E["ambilLookupFakturTerpakai(): faktur yang sudah diimpor"]
    E --> F["Query SIMRS per periode, kecualikan faktur terpakai"]
    F --> G["DataTables JSON"]
```

## Alur 2: Import ke Invoice Pembelian

Kedua jenis (obat / non medis) berujung pada method bersama `imporTagihan()`. Pencatatan mutasi Buku Besar menggunakan konfigurasi terpusat dari **Setting Akun Pembelian** (`SettingBridgingPembelian`) tanpa membaca tabel jurnal SIMRS.

```mermaid
flowchart TD
    A["POST /process-import-obat|nonmedis"] --> B["Import Request (validasi)"]
    B --> C["imporBanyakPembelian* -> loop nomer unik"]
    C --> D["imporSatuPembelian*"]
    D --> E{"jenisProses = InvoicePembelian?"}
    E -- Tidak --> F["Gagal: hanya Invoice Pembelian didukung"]
    E -- Ya --> G["Ambil tagihan header + rincian dari SIMRS"]
    G --> H["imporTagihan()"]
    H --> I{"Rincian kosong / kode_suplier kosong / faktur sudah ada?"}
    I -- Ya --> J["Return gagal dengan alasan"]
    I -- Tidak --> K["Validasi SettingBridgingPembelian lengkap"]
    K --> L["DB::transaction"]
    L --> M["cariAtauBuatSupplier() (berdasarkan kategori)"]
    M --> N["Buat FakturPembelian (tanggal via tentukanTanggalPengakuan)"]
    N --> O["Buat FakturPembelianRinci per baris"]
    O --> P["Debit: Persediaan Obat/Non Medis (DPP = tagihan - ppn)"]
    P --> Q{"Nilai PPN > 0?"}
    Q -- Ya --> R["Debit: PPN Masukan (sebesar nilai PPN)"]
    Q -- Tidak --> S["Lanjut"]
    R --> S
    S --> T["Kredit: Hutang Pembelian Obat/Non Medis (sebesar tagihan)"]
    T --> U["Return berhasil + LogAktifitasService::log(create)"]
```

### Algoritma

1. `imporBanyakPembelianObat()`/`imporBanyakPembelianNonMedis()` loop tiap nomor unik, menangkap error per item.
2. `imporSatuPembelian*()` menolak jenis proses selain `InvoicePembelian`, mengambil header tagihan,
   lalu memanggil `imporTagihan()` dengan kategori supplier/faktur yang sesuai.
3. `imporTagihan()` menolak bila rincian kosong, `kode_suplier` kosong, atau `nomer_faktur` sudah ada.
4. Memvalidasi `SettingBridgingPembelian` via `getSettingBridgingPembelian()`. Jika akun belum lengkap, import ditolak.
5. Dalam transaksi: cari/buat supplier via `cariAtauBuatSupplier()` (mengisi `kategori_supplier`
   bila belum ada), buat `FakturPembelian`, tentukan `tanggal_faktur` via `tentukanTanggalPengakuan()`.
6. Buat `FakturPembelianRinci` per baris rincian tagihan.
7. Buat mutasi `BukuBesar` (`sumber_transaksi = 'Invoice Pembelian'`):
   - Debit Akun Persediaan (Obat atau Non Medis) sebesar DPP (`tagihan - ppn`).
   - Debit Akun PPN Masukan jika nilai `ppn > 0`.
   - Kredit Akun Hutang Usaha (Obat atau Non Medis) sebesar total `tagihan`.
8. Aktivitas dicatat via `LogAktifitasService::log('Bridging Pembelian', 'create')`.

### Tanggal Pengakuan

- `TanggalBarangDatang`: pakai `tgl_pesan` bila tersedia,
- selain itu (atau `tgl_pesan` kosong): pakai `tgl_faktur`.

## Alur 3: Hapus Massal

```mermaid
flowchart TD
    A["POST /destroy-bulk"] --> B["BulkDeleteBridgingPembelianRequest"]
    B --> C["hapusBanyak() -> loop nomer unik"]
    C --> D["DB::transaction"]
    D --> E["Cari FakturPembelian by nomer_faktur (with rincian, pembayaranPembelianRincis)"]
    E --> F{"Faktur ditemukan?"}
    F -- Tidak --> G["throw: faktur tidak ditemukan"]
    F -- Ya --> H{"Sudah ada pembayaran?"}
    H -- Ya --> I["throw: sudah ada pembayaran"]
    H -- Tidak --> J["Hapus BukuBesar sumber 'Invoice Pembelian'"]
    J --> K["rincian()->delete()"]
    K --> L["faktur->delete()"]
    L --> M["LogAktifitasService::log(delete)"]
```

### Algoritma

1. Untuk tiap nomor, cari faktur beserta rincian dan rincian pembayaran.
2. Bila faktur tidak ada, gagal. Bila **sudah ada pembayaran**, hapus ditolak.
3. Hapus mutasi buku besar sumber `Invoice Pembelian`, hapus rincian faktur, lalu faktur.

## Fungsi yang Dipanggil

- `BridgingPembelianController::index()/loadImportedData()/tarikPembelianObat()/loadTagihanObat()`
- `BridgingPembelianController::tarikPembelianNonMedis()/loadTagihanNonMedis()/processImportObat()/processImportNonMedis()/destroyBulk()`
- `BridgingPembelianController::resolveDateRange()` + helper pencarian DataTable (private)
- `BridgingPembelianService::getQueryHasilImport()/getKandidatPembelianObat()/getKandidatPembelianObatQuery()/getKandidatPembelianNonMedis()`
- `BridgingPembelianService::imporBanyakPembelianObat()/imporBanyakPembelianNonMedis()/hapusBanyak()`
- `BridgingPembelianService::imporSatuPembelianObat()/imporSatuPembelianNonMedis()/imporTagihan()` (private)
- `BridgingPembelianService::getSettingBridgingPembelian()`
- `BridgingPembelianService::cariAtauBuatSupplier()/tentukanTanggalPengakuan()/buatKeteranganFaktur()` (private)
- `BridgingPembelianService::ambilTagihan*()/ambilRincianTagihan*()` (private)
- `BukuBesarService::resolvePeriode()` (static), `LogAktifitasService::log()`
- `FakturPembelian::scopeBetweenDates()`, `SettingBridgingPembelian::query()`

## Catatan Penting Bisnis

- Saat ini hanya import ke **Invoice Pembelian** yang didukung.
- Faktur dengan `nomer_faktur` yang sudah ada tidak diimpor ulang; kandidat yang sudah dipakai
  dikecualikan dari daftar tarik.
- Mutasi buku besar **menggunakan akun terpusat dari Setting Akun Pembelian** (`SettingBridgingPembelian`), tidak lagi membaca tabel `jurnal` atau `detailjurnal` SIMRS.
- Supplier dibuat otomatis bila belum ada (berdasarkan `kode_supplier` + `nama_supplier`), dengan
  kategori `Obat & BHP` atau `Barang Non Medis`.
- Hapus massal **ditolak** bila faktur sudah memiliki pembayaran (`pembayaranPembelianRincis`).
- Lihat [Integrasi SIMRS](fondasi/integrasi-simrs.md), [Setting Akun Pembelian](pengaturan-setting-pembelian.md), [Pembelian — Invoice](pembelian-invoice.md),
  dan [Pembelian — Pembayaran](pembelian-pembayaran.md).
