# Dokumentasi Modul Home / Dashboard

## Ringkasan Modul

Modul **Home / Dashboard** menampilkan ringkasan operasional rumah sakit dalam bentuk widget/grafik:
kunjungan harian, distribusi poli, top dokter, pendapatan harian, dan komposisi penjamin. Widget
memuat datanya melalui endpoint JSON terpisah (dipanggil dari front-end dashboard).

Distribusi Poli dan Top Dokter ditampilkan sebagai diagram ranking horizontal. Distribusi Poli
memuat 10 poli dengan kunjungan terbanyak dan, jika ada kategori tambahan, satu baris `Lainnya`
yang menjumlahkan seluruh kategori tersisa. Top Dokter memuat maksimal 10 dokter.

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/HomeController.php`
- `app/Services/HomeDashboardService.php`

## Entry Point / API

Semua endpoint memakai izin `home,view`.

| Method | Path | Route Name | Controller Method |
| --- | --- | --- | --- |
| `GET` | `/home` | `home` | `index()` |
| `GET` | `/home/kunjungan-harian` | `home.kunjungan-harian` | `kunjunganHarian()` |
| `GET` | `/home/poli` | `home.poli` | `distribusiPoli()` |
| `GET` | `/home/dokter` | `home.dokter` | `topDokter()` |
| `GET` | `/home/pendapatan-harian` | `home.pendapatan-harian` | `pendapatanHarian()` |
| `GET` | `/home/penjamin` | `home.penjamin` | `komposisiPenjamin()` |

## Parameter

- Endpoint data menerima `dariTanggal` dan `sampaiTanggal`; `resolveDateRange()` memberi default
  awal bulan s.d. hari ini bila kosong.
- `index()` mengirim `defaultStartDate`/`defaultEndDate` ke view untuk inisialisasi filter, serta
  `activeInformasiList` dari `LayananInformasiService::getActiveInformasi()` untuk banner pengumuman.
- Respons `/home/poli` tetap berbentuk `{ poli, total }` dan berisi maksimal 11 baris: 10 poli
  terbesar ditambah `Lainnya` bila jumlah kategori lebih dari 10.

## Alur

```mermaid
flowchart TD
    A["GET /home"] --> B["index() panggil LayananInformasiService::getActiveInformasi()"]
    B --> C["Render view home (default tanggal & activeInformasiList)"]
    C --> D{"Ada informasi aktif?"}
    D -- Ya --> E["Render banner notifikasi + modal popup (auto popup 1x via sessionStorage)"]
    D -- Tidak --> F["Render banner fallback: Saat ini tidak ada informasi..."]
    C --> G["Front-end memuat tiap widget grafik"]
    G --> H["GET /home/kunjungan-harian -> getKunjunganHarian()"]
    G --> I["GET /home/poli -> getDistribusiPoli() -> Top 10 + Lainnya"]
    G --> J["GET /home/dokter -> getTopDokter()"]
    G --> K["GET /home/pendapatan-harian -> getPendapatanHarian()"]
    G --> L["GET /home/penjamin -> getKomposisiPenjamin()"]
    H --> M["response()->json(...)"]
    I --> M
    J --> M
    K --> M
    L --> M
```

### Algoritma

1. `index()` mengambil daftar pengumuman yang aktif dari `LayananInformasiService::getActiveInformasi()` dan
   merender halaman dashboard dengan rentang tanggal default.
2. Banner notifikasi ditampilkan sesuai level warna (`info`, `success`, `warning`, `danger`). Jika pengumuman
   memiliki flag `tampilkan_popup`, front-end secara otomatis menampilkan modal pop-up (sekali per sesi tab
   melalui `sessionStorage`).
3. Tiap endpoint widget menentukan rentang tanggal via `resolveDateRange()` lalu memanggil method
   `HomeDashboardService` yang sesuai dan mengembalikan JSON.
4. `getDistribusiPoli()` mengurutkan hasil dari total terbesar, mengambil 10 poli pertama, lalu
   menjumlahkan kategori sisanya sebagai `Lainnya`.
5. Front-end merender Distribusi Poli dan Top Dokter sebagai diagram batang horizontal dengan
   tinggi adaptif berdasarkan jumlah baris.

## Fungsi yang Dipanggil

- `HomeController::index()/kunjunganHarian()/distribusiPoli()/topDokter()/pendapatanHarian()/komposisiPenjamin()`
- `HomeController::resolveDateRange()` (private)
- `HomeDashboardService::getKunjunganHarian()/getDistribusiPoli()/getTopDokter()/getPendapatanHarian()/getKomposisiPenjamin()`
- `LayananInformasiService::getActiveInformasi()`

## Catatan Penting Bisnis

- Dashboard bersifat **read-only**; hanya menampilkan agregasi data dan banner informasi pengumuman.
- Setiap widget memuat datanya secara asinkron melalui endpoint JSON masing-masing.
- Rentang tanggal default adalah awal bulan berjalan s.d. hari ini.
- Diagram ranking memakai satu warna aksen hijau; label panjang dibungkus ke beberapa baris dan
  nilai lengkap tetap tersedia melalui tooltip.
- Pengumuman aktif otomatis disaring berdasarkan `status_aktif = true` dan rentang tanggal penayangan hari ini.
- Popup modal hanya muncul otomatis sekali per sesi browser agar tidak mengganggu operasional pengguna.
