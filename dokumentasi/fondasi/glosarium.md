# Glosarium

Istilah domain yang sering muncul di kode dan dokumentasi Siakurat.

## Umum & SIMRS

| Istilah | Arti |
| --- | --- |
| **SIMRS** | Sistem Informasi Manajemen Rumah Sakit; sumber data operasional yang dibaca via koneksi `simrs` |
| **Bridging** | Proses menarik data dari SIMRS lalu mengubahnya menjadi jurnal/invoice di Siakurat |
| **Billing** | Tagihan pasien di SIMRS yang menjadi dasar pengakuan pendapatan |
| **no_rawat** | Nomor rawat pasien; identitas satu episode pelayanan di SIMRS |
| **nomer_billing** | Nomor billing yang menandai satu tagihan; dipakai untuk cegah import ganda |
| **Penjamin** | Pihak penanggung biaya (mis. Umum/Tunai, BPJS, asuransi) |
| **Poli** | Poliklinik/unit layanan tempat pasien dilayani |
| **Ralan** | Rawat jalan |
| **Ranap** | Rawat inap |
| **ranap_gabung** | Relasi SIMRS untuk episode rawat inap yang tergabung (no_rawat lanjutan `no_rawat2`) |
| **Laborat** | Pemeriksaan laboratorium |
| **Radiologi** | Pemeriksaan radiologi |
| **Kamar** | Komponen tarif kamar rawat inap |

## Akuntansi

| Istilah | Arti |
| --- | --- |
| **COA** | Chart of Accounts; daftar akun (tabel `coa`) |
| **TipeCoa** | Klasifikasi tipe akun (mis. `Kasbank`, tipe mengandung `piutang`) |
| **Akun daun (leaf)** | COA tanpa anak; hanya akun daun aktif yang dipakai untuk transaksi |
| **Jurnal Umum** | Catatan jurnal manual/otomatis dengan baris debit-kredit |
| **Buku Besar** | Mutasi akun (`bukubesar`) yang diturunkan dari transaksi sumber |
| **tipe_mutasi** | Arah mutasi buku besar: `D` (debit) atau `K` (kredit) |
| **sumber_transaksi** | Jenis transaksi asal mutasi buku besar (mis. `Jurnal Umum`, `Kasbank Penerimaan`) |
| **Balance** | Kondisi total debit sama dengan total kredit |
| **Akun lawan** | Akun penyeimbang (mis. kas/piutang) terhadap akun pendapatan/beban |
| **Selisih tarif** | Selisih antara tarif/piutang dengan jumlah pembayaran yang diterima |
| **Potongan admin** | Potongan biaya administrasi pada pembayaran pembelian |

## Transaksi & Dokumen

| Istilah | Arti |
| --- | --- |
| **Invoice / Faktur Pendapatan** | Faktur penjualan (`faktur_penjualan`) atas pendapatan |
| **Penerimaan Pendapatan** | Penerimaan pembayaran atas invoice pendapatan (`penerimaan_penjualan`) |
| **Invoice / Faktur Pembelian** | Faktur pembelian (`faktur_pembelian`) |
| **Pembayaran Pembelian** | Pembayaran atas invoice pembelian (`pembayaran_pembelian`) |
| **Kasbank Penerimaan** | Penerimaan kas/bank di luar siklus penjualan |
| **Kasbank Pembayaran** | Pengeluaran kas/bank di luar siklus pembelian |
| **Pelanggan** | Entitas pembeli/penjamin pada invoice pendapatan |
| **Supplier** | Pemasok pada invoice pembelian |

## Konfigurasi & Akses

| Istilah | Arti |
| --- | --- |
| **module.access** | Middleware otorisasi berbasis modul + aksi |
| **AccessModule** | Master modul yang dapat diberi izin |
| **Role / RolePermission** | Peran dan izin (`can_view/create/update/delete`) per modul |
| **RBA** | Rencana Bisnis Anggaran (modul Setting RBA) |
| **Mapping Pendapatan** | Pemetaan komponen billing SIMRS ke COA pendapatan |
| **Mapping General** | Pemetaan kode rekening SIMRS ke COA (`mapping_coa_simrs`) |
| **Preferensi** | Preferensi perusahaan (branding, dll) |

## Teknis

| Istilah | Arti |
| --- | --- |
| **DataTables** | Tabel server-side (yajra) untuk daftar data; endpoint `load-data`/`load-*` |
| **Form Request** | Kelas validasi input Laravel di `app/Http/Requests` |
| **Service** | Kelas berisi business logic di `app/Services` |
| **LogAktifitas** | Audit trail aktivitas pengguna |
