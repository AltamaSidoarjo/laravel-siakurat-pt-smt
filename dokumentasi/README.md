# Dokumentasi Siakurat

Siakurat adalah aplikasi akuntansi/keuangan rumah sakit berbasis **Laravel + MySQL** yang
terintegrasi dengan **SIMRS**. Dokumentasi ini memetakan seluruh modul aplikasi beserta
alur bisnisnya menggunakan flowchart, algoritma langkah demi langkah, dan daftar fungsi
yang dipanggil.

## Cara Memakai Dokumentasi Ini

- Mulai dari **Fondasi** untuk memahami arsitektur, otorisasi, integrasi SIMRS, dan konvensi akuntansi.
- Lanjut ke dokumen **per modul** untuk detail endpoint dan alur.
- Gunakan `_template-modul.md` saat membuat dokumen modul/fitur baru.

## Cara Memperbarui Dokumentasi

Dokumentasi ini **wajib diperbarui setiap ada perubahan kode, fitur, endpoint, atau aturan bisnis**
pada modul terkait. Aturan lengkap dan tabel pemetaan "file kode → dokumen" ada di `AGENTS.md`
bagian **Documentation Policy**.

## Fondasi (Lintas Modul)

| Dokumen | Isi |
| --- | --- |
| [Overview Arsitektur](fondasi/overview-arsitektur.md) | Lapisan Controller → Service → Model, struktur folder, alur request |
| [Sistem Otorisasi](fondasi/sistem-otorisasi.md) | Middleware `module.access`, Role/Permission, daftar modul & aksi |
| [Integrasi SIMRS](fondasi/integrasi-simrs.md) | Koneksi DB `simrs`, pola query lintas-koneksi |
| [Konvensi Buku Besar & COA](fondasi/konvensi-bukubesar-coa.md) | COA, TipeCoa, Buku Besar, Jurnal Umum, aturan debit-kredit & sinkronisasi |
| [Glosarium](fondasi/glosarium.md) | Istilah domain: billing, no_rawat, penjamin, ranap/ralan, dll |

## Modul Aplikasi

| Modul | Dokumen | Status |
| --- | --- | --- |
| Autentikasi | [auth.md](auth.md) | selesai |
| Home / Dashboard | [home-dashboard.md](home-dashboard.md) | selesai |
| Bukubesar — Jurnal Umum | [bukubesar-jurnal-umum.md](bukubesar-jurnal-umum.md) | selesai |
| Bukubesar — COA | [bukubesar-coa.md](bukubesar-coa.md) | selesai |
| Kasbank — Penerimaan | [kasbank-penerimaan.md](kasbank-penerimaan.md) | selesai |
| Kasbank — Pembayaran | [kasbank-pembayaran.md](kasbank-pembayaran.md) | selesai |
| Kasbank — Buku Bank | [kasbank-buku-bank.md](kasbank-buku-bank.md) | selesai |
| Bridging — Pendapatan | [bridging-pendapatan.md](bridging-pendapatan.md) | selesai |
| Bridging — Pendapatan Obat | [bridging-pendapatan-obat.md](bridging-pendapatan-obat.md) | selesai |
| Bridging — Pembelian | [bridging-pembelian.md](bridging-pembelian.md) | selesai |
| Pendapatan — Invoice | [pendapatan-invoice.md](pendapatan-invoice.md) | selesai |
| Pendapatan — Penerimaan | [pendapatan-penerimaan.md](pendapatan-penerimaan.md) | selesai |
| Pembelian — Invoice | [pembelian-invoice.md](pembelian-invoice.md) | selesai |
| Pembelian — Pembayaran | [pembelian-pembayaran.md](pembelian-pembayaran.md) | selesai |
| Laporan — Keuangan | [laporan-keuangan.md](laporan-keuangan.md) | selesai |
| Laporan — Pendapatan | [laporan-pendapatan.md](laporan-pendapatan.md) | selesai |
| Pengaturan — Mapping Pendapatan | [pengaturan-mapping-pendapatan.md](pengaturan-mapping-pendapatan.md) | selesai |
| Pengaturan — Mapping General | [pengaturan-mapping-general.md](pengaturan-mapping-general.md) | selesai |
| Pengaturan — Setting RBA | [pengaturan-setting-rba.md](pengaturan-setting-rba.md) | selesai |
| Pengaturan — Preferensi | [pengaturan-preferensi.md](pengaturan-preferensi.md) | selesai |
| Pengaturan — Pengguna | [pengaturan-pengguna.md](pengaturan-pengguna.md) | selesai |
| Pengaturan — Role Akses | [pengaturan-role-akses.md](pengaturan-role-akses.md) | selesai |
| Pengaturan — Konversi File | [pengaturan-konversi-file.md](pengaturan-konversi-file.md) | selesai |

## Template

- [`_template-modul.md`](_template-modul.md) — template standar untuk dokumen modul baru.
