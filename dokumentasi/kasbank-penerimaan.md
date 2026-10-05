# Dokumentasi Modul Kasbank — Penerimaan

## Ringkasan Modul

Modul **Kasbank Penerimaan** digunakan untuk:

1. mencatat penerimaan kas/bank (satu akun kas + rincian akun lawan),
2. menampilkan daftar penerimaan per periode (DataTables) dan mengekspornya ke CSV,
3. mengubah dan menghapus penerimaan,
4. mencetak bukti penerimaan,
5. mengimpor banyak penerimaan sekaligus dari file XLSX (template + validasi all-or-nothing),
6. menyinkronkan setiap perubahan ke **Buku Besar**.

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Kasbank/KasbankPenerimaanController.php`
- `app/Http/Requests/Kasbank/StoreKasbankPenerimaanRequest.php`
- `app/Http/Requests/Kasbank/UpdateKasbankPenerimaanRequest.php`
- `app/Http/Requests/Kasbank/ImportKasbankPenerimaanRequest.php`
- `app/Services/Kasbank/KasbankPenerimaanService.php`
- `app/Services/Kasbank/KasbankPenerimaanImportService.php`
- `app/Services/Kasbank/KasbankPenerimaanImportException.php`
- `app/Services/Bukubesar/BukuBesarService.php`
- `app/Models/KasbankPenerimaan.php`, `app/Models/KasbankPenerimaanRinci.php`, `app/Models/Coa.php`

## Entry Point / API

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/kasbank/penerimaan` | `kasbank.penerimaan.index` | `index()` | view |
| `GET` | `/kasbank/penerimaan/load-data` | `kasbank.penerimaan.load-data` | `loadData()` | view |
| `GET` | `/kasbank/penerimaan/export-csv` | `kasbank.penerimaan.export-csv` | `exportCsv()` | view |
| `GET` | `/kasbank/penerimaan/{kasbankPenerimaan}/print` | `kasbank.penerimaan.print` | `print()` | view |
| `GET` | `/kasbank/penerimaan/import` | `kasbank.penerimaan.import.form` | `importForm()` | create |
| `GET` | `/kasbank/penerimaan/import/template` | `kasbank.penerimaan.import.template` | `importTemplate()` | create |
| `POST` | `/kasbank/penerimaan/import` | `kasbank.penerimaan.import.store` | `import()` | create |
| `GET` | `/kasbank/penerimaan/create` | `kasbank.penerimaan.create` | `create()` | create |
| `POST` | `/kasbank/penerimaan` | `kasbank.penerimaan.store` | `store()` | create |
| `GET` | `/kasbank/penerimaan/{kasbankPenerimaan}/edit` | `kasbank.penerimaan.edit` | `edit()` | update |
| `PUT` | `/kasbank/penerimaan/{kasbankPenerimaan}` | `kasbank.penerimaan.update` | `update()` | update |
| `DELETE` | `/kasbank/penerimaan/{kasbankPenerimaan}` | `kasbank.penerimaan.destroy` | `destroy()` | delete |

## Validasi Request

`StoreKasbankPenerimaanRequest` / `UpdateKasbankPenerimaanRequest` memvalidasi:

- `coa_id` wajib integer yang ada di `coa` (akun kas/bank),
- `nomer` wajib, unik pada `kasbank_penerimaan` (ignore diri sendiri saat update),
- `tanggal` wajib berupa tanggal,
- `keterangan` opsional,
- `total` wajib numerik `>= 0`,
- `rincian` wajib array minimal 1 baris,
- `rincian.*.coa_id` wajib integer yang ada di `coa`,
- `rincian.*.nominal` wajib numerik `>= 0`,
- `rincian.*.catatan` opsional.

Validasi tambahan (`after`):

- `total` harus lebih besar dari nol,
- `total` harus sama dengan jumlah `nominal` seluruh rincian (toleransi `0.00001`).

`ImportKasbankPenerimaanRequest` memvalidasi file unggahan: `file` wajib, bertipe `file`,
berekstensi `xlsx`/`xls` (`mimes:xlsx,xls`), dan maksimal 10 MB (`max:10240`). Validasi isi baris
(nomer, tanggal, kode COA, nominal, konsistensi, total, duplikasi) dilakukan di
`KasbankPenerimaanImportService`.

## Alur 1: Daftar & Ekspor

### Flowchart

```mermaid
flowchart TD
    A["GET /kasbank/penerimaan"] --> B["KasbankPenerimaanController::index()"]
    B --> C["Tentukan rentang tanggal (default awal bulan s.d hari ini)"]
    C --> D["Render view kasbank.penerimaan.index"]
    D --> E["DataTable GET /load-data"]
    E --> F["loadData() -> KasbankPenerimaanService::getIndexQuery()"]
    F --> G["DataTables::eloquent()->toJson()"]
    D --> H["Export CSV -> streamCsvExport()"]
```

### Algoritma

1. `index()` menentukan `startDate`/`endDate` (default awal bulan s.d. hari ini).
2. `loadData()` mengambil query dari `getIndexQuery()` (eager load `coa`, urut `tanggal`/`id` desc).
3. Kolom `tanggal`, `total` diformat; `coa_display` menampilkan nama akun; `nomer_link` menuju edit.
4. Ekspor CSV memvalidasi rentang tanggal lalu men-stream kolom Nomor, Tanggal, Bank, Nominal, Keterangan.

## Alur 2: Buat / Ubah + Sinkronisasi Buku Besar

### Flowchart

```mermaid
flowchart TD
    A["Submit form create/edit"] --> B["Store/Update Request (validasi + cek total=rincian)"]
    B --> C["Controller store()/update() -> DB::transaction"]
    C --> D["KasbankPenerimaanService::create()/update()"]
    D --> E["Simpan/Update header kasbank_penerimaan"]
    E --> F["mapRincianPayload() (buang baris tanpa coa_id)"]
    F --> G["rincian()->createMany() (update: delete lalu createMany)"]
    G --> H["BukuBesarService::syncFromKasbankPenerimaan()"]
    H --> I["LogAktifitasService::log('Kasbank Penerimaan', ...)"]
    I --> J["Redirect index (atau print bila action=save_print)"]
```

### Algoritma

1. Request tervalidasi (termasuk kecocokan total = jumlah rincian).
2. Controller membungkus dalam `DB::transaction`.
3. Service menyimpan/memperbarui header lalu menulis ulang rincian.
4. `syncFromKasbankPenerimaan()` mengisi buku besar: **akun kas Debit** sebesar total, tiap
   **rincian Kredit** sebesar nominalnya (idempoten: hapus dulu lalu isi ulang).
5. Aktivitas dicatat via `LogAktifitasService::log()`.
6. Pada `store`, `action = save_print` mengarah ke halaman cetak.

## Alur 3: Import XLSX

```mermaid
flowchart TD
    A["GET /kasbank/penerimaan/import"] --> B["importForm() -> view import"]
    B --> C["GET /import/template -> importTemplate()"]
    C --> D["KasbankPenerimaanImportService::buildTemplate() (sheet Transaksi, Petunjuk, Master COA)"]
    B --> E["POST /import (ImportKasbankPenerimaanRequest: file xlsx/xls, max 10MB)"]
    E --> F["import() -> DB::transaction"]
    F --> G["KasbankPenerimaanImportService::importFromXlsx()"]
    G --> H["readRows() baca sheet pertama (lewati header, buang baris kosong)"]
    H --> I["groupAndValidate() per nomer"]
    I -->|ada error| J["throw KasbankPenerimaanImportException -> back()->withErrors('file')"]
    I -->|valid| K["loop: KasbankPenerimaanService::create() per nomer"]
    K --> L["Redirect index + ringkasan jumlah penerimaan dibuat"]
```

### Algoritma

1. Pengguna mengunduh template XLSX (`buildTemplate()`): sheet **Transaksi** dengan kolom
   `nomer, tanggal, keterangan, kode_coa_kas, kode_coa_rincian, nominal, catatan` + baris contoh,
   sheet **Petunjuk**, dan sheet **Master COA** (seluruh akun leaf aktif via `selectableTransaction()`).
2. File diunggah, `ImportKasbankPenerimaanRequest` memvalidasi tipe & ukuran file.
3. `importFromXlsx()` membaca baris, lalu `groupAndValidate()` mengelompokkan per `nomer`:
   - `nomer`, `kode_coa_kas`, `kode_coa_rincian` wajib; kode COA harus akun transaksi (leaf aktif).
   - `tanggal` format `YYYY-MM-DD`; `nominal` numerik ≥ 0.
   - `tanggal`, `keterangan`, dan `kode_coa_kas` diambil dari baris pertama tiap `nomer` dan
     **harus konsisten** untuk seluruh baris dengan `nomer` sama (bila beda → error).
   - setiap baris menjadi satu rincian (akun lawan + nominal); `total` = jumlah nominal grup, harus > 0.
   - `nomer` harus unik dan belum ada di database.
4. Bila ada **satu** error, seluruh import dibatalkan (dibungkus `DB::transaction`, exception memicu rollback).
5. Bila valid, tiap grup dibuat lewat `KasbankPenerimaanService::create()` sehingga rincian, buku besar,
   dan log aktivitas tersinkron sama seperti pembuatan manual.

## Alur 4: Hapus

```mermaid
flowchart TD
    A["DELETE /kasbank/penerimaan/{id}"] --> B["destroy() -> DB::transaction"]
    B --> C["KasbankPenerimaanService::delete()"]
    C --> D["LogAktifitasService::log(delete)"]
    D --> E["BukuBesarService::deleteBySource('Kasbank Penerimaan', id)"]
    E --> F["rincian()->delete()"]
    F --> G["header->delete()"]
```

## Alur 5: Cetak

- `print()` mengambil identitas cetak (`PreferensiPerusahaanService::getPrintIdentity()`) dan merender
  view `kasbank.penerimaan.print` dengan data (beserta `coa`, `rincian.coa`), nama RS, petugas, TTD.

## Fungsi yang Dipanggil

- `KasbankPenerimaanController::index()/loadData()/exportCsv()/create()/store()/edit()/update()/destroy()/print()`
- `KasbankPenerimaanController::importForm()/importTemplate()/import()`
- `KasbankPenerimaanService::getCoaOptions()/getIndexQuery()/create()/update()/delete()/mapRincianPayload()`
- `KasbankPenerimaanImportService::buildTemplate()/importFromXlsx()`
- `KasbankPenerimaanImportException::errors()`
- `BukuBesarService::syncFromKasbankPenerimaan()/deleteBySource()`
- `LogAktifitasService::log()`
- `PreferensiPerusahaanService::getPrintIdentity()`
- `Coa::scopeSelectableTransaction()`, `KasbankPenerimaan::scopeBetweenDates()`, `KasbankPenerimaan::rincian()`
- Trait `StreamsCsvExport::streamCsvExport()/csvNumber()`

## Catatan Penting Bisnis

- `total` harus sama dengan jumlah nominal rincian dan lebih besar dari nol.
- Arah buku besar: **akun kas Debit**, **rincian akun lawan Kredit**.
- Sinkronisasi buku besar idempoten (hapus lalu isi ulang) pada setiap create/update.
- Hapus juga menghapus mutasi buku besar sumber `Kasbank Penerimaan`.
- Opsi COA memakai akun **daun aktif** (`selectableTransaction()`).
- Import XLSX bersifat **all-or-nothing**: satu baris tidak valid membatalkan seluruh import.
- Pada import, `tanggal`, `keterangan`, dan `kode_coa_kas` diambil dari baris pertama tiap `nomer`
  dan harus konsisten di seluruh baris dengan `nomer` yang sama; `total` = jumlah nominal rincian
  dan harus > 0; `nomer` harus unik (belum ada di database).
- Import memanggil `KasbankPenerimaanService::create()` sehingga efek buku besar & log identik dengan input manual.
- Rute import berada di grup izin **create** (`module.access:kasbank.penerimaan,create`).
- Lihat [Konvensi Buku Besar & COA](fondasi/konvensi-bukubesar-coa.md).
