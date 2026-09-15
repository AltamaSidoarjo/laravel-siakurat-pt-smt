# Dokumentasi Modul Pembelian — Invoice

## Ringkasan Modul

Modul **Invoice Pembelian** (faktur pembelian) digunakan untuk:

1. menampilkan daftar invoice pembelian per periode dan multi-supplier (DataTables) dengan status lunas/belum lunas,
2. mengekspor daftar sesuai filter periode dan supplier ke CSV,
3. melihat detail satu invoice (read-only),
4. mencetak invoice.

Invoice pembelian **dibuat oleh proses Bridging Pembelian**, bukan diinput manual di modul ini
(modul ini bersifat baca-saja: hanya `view`).

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Pembelian/InvoicePembelianController.php`
- `app/Http/Requests/Pembelian/InvoicePembelianFilterRequest.php`
- `app/Services/Pembelian/InvoicePembelianService.php`
- `app/Models/FakturPembelian.php`, `app/Models/FakturPembelianRinci.php`, `app/Models/Supplier.php`

## Entry Point / API

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/pembelian/invoice` | `pembelian.invoice.index` | `index()` | view |
| `GET` | `/pembelian/invoice/load-data` | `pembelian.invoice.load-data` | `loadData()` | view |
| `GET` | `/pembelian/invoice/export-csv` | `pembelian.invoice.export-csv` | `exportCsv()` | view |
| `GET` | `/pembelian/invoice/{fakturPembelian}/print` | `pembelian.invoice.print` | `print()` | view |
| `GET` | `/pembelian/invoice/{fakturPembelian}` | `pembelian.invoice.read` | `read()` | view |

## Alur 1: Daftar & Ekspor

```mermaid
flowchart TD
    A["GET /pembelian/invoice"] --> B["Validasi periode dan supplierIds[]"]
    B --> C["index() (default awal bulan s.d hari ini; supplier kosong = semua)"]
    C --> D["Render view pembelian.invoice.index + opsi supplier"]
    D --> E["DataTable /load-data -> getIndexQuery() (periode + whereIn supplier opsional)"]
    E --> F["Format tanggal, nama_supplier, grandtotal, sudah_terbayar, status_text"]
    F --> G["DataTables::eloquent()->toJson()"]
    D --> H["Export CSV dengan filter supplier yang sama -> streamCsvExport()"]
```

### Algoritma

1. Filter menerima `startDate`, `endDate`, dan `supplierIds[]`. Setiap ID supplier harus berupa integer,
   unik, dan tersedia pada tabel `supplier`; pilihan kosong berarti semua supplier.
2. `getIndexQuery()` mengambil `FakturPembelian` per periode dan menerapkan `whereIn(supplier_id)`
   saat supplier dipilih (urut `tanggal_faktur`/`id` desc, eager `supplier`).
3. DataTable menambahkan kolom turunan: `nama_supplier`, `grandtotal_display`, `sudah_terbayar_display`,
   `status_text` (`Sudah Lunas` bila `sudah_terbayar >= grandtotal`), `nomer_link` (ke read).
4. Ekspor CSV memakai filter supplier aktif dari halaman dan memuat kolom Nomor faktur, Tanggal
   faktur, Tgl jatuh tempo, Supplier, Kode bangsal, Kategori faktur, Grandtotal, Sudah terbayar, Status.

## Alur 2: Lihat Detail & Cetak

```mermaid
flowchart TD
    A["GET /pembelian/invoice/{id}"] --> B["read() -> load(supplier, rincian)"]
    B --> C["Render view pembelian.invoice.read"]
    D["GET /pembelian/invoice/{id}/print"] --> E["print() -> load(supplier, rincian) + identitas cetak"]
    E --> F["Render view pembelian.invoice.print"]
```

## Fungsi yang Dipanggil

- `InvoicePembelianController::index()/loadData()/exportCsv()/read()/print()`
- `InvoicePembelianService::getIndexQuery()/findById()/increaseSudahTerbayar()/decreaseSudahTerbayar()`
- `FakturPembelian::scopeBetweenDates()`, `FakturPembelian::rincian()`, `FakturPembelian::supplier()`
- `PreferensiPerusahaanService::getPrintIdentity()`
- Trait `StreamsCsvExport::streamCsvExport()/csvNumber()`

> Catatan: `increaseSudahTerbayar()`/`decreaseSudahTerbayar()` dipakai oleh modul
> [Pembelian — Pembayaran](pembelian-pembayaran.md) untuk memperbarui `sudah_terbayar` faktur.

## Catatan Penting Bisnis

- Modul ini **read-only** (hanya `view`); tidak ada create/update/delete langsung.
- Sumber data invoice berasal dari [Bridging Pembelian](bridging-pembelian.md).
- Status lunas dihitung dari perbandingan `sudah_terbayar` dan `grandtotal`.
- Kolom `sudah_terbayar` diubah oleh modul Pembayaran Pembelian saat pembayaran dicatat/dihapus.
