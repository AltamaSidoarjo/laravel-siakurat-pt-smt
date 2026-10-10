# Dokumentasi Modul Laporan — Keuangan

## Ringkasan Modul

Modul **Laporan Keuangan** menyajikan berbagai laporan akuntansi berbasis data Buku Besar dan COA
(read-only, hanya `view`):

1. Rincian transaksi buku besar,
2. Deteksi jurnal tidak balance,
3. Laba rugi (detil, standard, per parent COA, komparasi bulanan),
4. Neraca (standard, per parent COA, saldo, detil, rinci),
5. Buku besar per COA,
6. Arus kas.

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Laporan/LaporanKeuanganController.php`
- `app/Services/Laporan/LaporanKeuanganService.php`
- `app/Models/BukuBesar.php`, `app/Models/Coa.php`, `app/Models/TipeCoa.php`,
  `app/Models/JurnalUmum.php`, `app/Models/SettingRba.php`

## Entry Point / API

Semua endpoint memakai izin `laporan.keuangan,view`.

| Method | Path | Route Name | Controller Method |
| --- | --- | --- | --- |
| `GET` | `/laporan/keuangan` | `laporan.keuangan.index` | `index()` |
| `GET` | `/laporan/keuangan/rincian-transaksi-bukubesar` | `laporan.keuangan.rincian-transaksi-bukubesar` | `rincianTransaksiBukubesar()` |
| `GET` | `/laporan/keuangan/rincian-transaksi-bukubesar/load-data` | `laporan.keuangan.rincian-transaksi-bukubesar.load-data` | `loadRincianTransaksiBukubesar()` |
| `GET` | `/laporan/keuangan/deteksi-jurnal-tidak-balance` | `laporan.keuangan.deteksi-jurnal-tidak-balance` | `deteksiJurnalTidakBalance()` |
| `GET` | `/laporan/keuangan/deteksi-jurnal-tidak-balance/load-data` | `laporan.keuangan.deteksi-jurnal-tidak-balance.load-data` | `loadDeteksiJurnalTidakBalance()` |
| `GET` | `/laporan/keuangan/laba-rugi-detil` | `laporan.keuangan.laba-rugi-detil` | `labaRugiDetil()` |
| `GET` | `/laporan/keuangan/laba-rugi-standard` | `laporan.keuangan.laba-rugi-standard` | `labaRugiStandard()` |
| `GET` | `/laporan/keuangan/laba-rugi-per-parent-coa` | `laporan.keuangan.laba-rugi-per-parent-coa` | `labaRugiPerParentCoa()` |
| `GET` | `/laporan/keuangan/laba-rugi-komparasi-bulanan` | `laporan.keuangan.laba-rugi-komparasi-bulanan` | `labaRugiKomparasiBulanan()` |
| `GET` | `/laporan/keuangan/neraca-standard` | `laporan.keuangan.neraca-standard` | `neracaStandard()` |
| `GET` | `/laporan/keuangan/neraca-per-parent-coa` | `laporan.keuangan.neraca-per-parent-coa` | `neracaPerParentCoa()` |
| `GET` | `/laporan/keuangan/bukubesar` | `laporan.keuangan.bukubesar` | `bukubesar()` |
| `GET` | `/laporan/keuangan/bukubesar/search-coa` | `laporan.keuangan.bukubesar.search-coa` | `searchBukubesarCoa()` |
| `GET` | `/laporan/keuangan/neraca-saldo` | `laporan.keuangan.neraca-saldo` | `neracaSaldo()` |
| `GET` | `/laporan/keuangan/neraca-detil` | `laporan.keuangan.neraca-detil` | `neracaDetil()` |
| `GET` | `/laporan/keuangan/neraca-rinci` | `laporan.keuangan.neraca-rinci` | `neracaRinci()` |
| `GET` | `/laporan/keuangan/arus-kas` | `laporan.keuangan.arus-kas` | `arusKas()` |

## Parameter Umum

- `resolveDateRange()` menentukan `startDate`/`endDate` (default awal bulan s.d. hari ini).
- Laporan berbasis titik waktu memakai `perDate` (default hari ini): neraca standard, per parent COA,
  saldo, detil.
- `getIdentitasLaporan()` menyediakan identitas laporan (nama RS, dll) untuk header laporan.

## Alur Sub-Laporan

### Rincian Transaksi Buku Besar

```mermaid
flowchart TD
    A["GET rincian-transaksi-bukubesar"] --> B["Render view"]
    B --> C["DataTable /load-data -> getQueryRincianTransaksiBukubesar(start,end)"]
    C --> D["Kolom: tanggal, coa (kode-nama), debit (jika D), kredit (jika K)"]
    D --> E["DataTables::eloquent()->toJson()"]
```

### Deteksi Jurnal Tidak Balance

```mermaid
flowchart TD
    A["GET deteksi-jurnal-tidak-balance"] --> B["Render view"]
    B --> C["DataTable /load-data -> getJurnalTidakBalance(start,end)"]
    C --> D["Hitung total_debit, total_kredit, selisih per data"]
    D --> E["DataTables::collection() + grandTotalSelisih"]
```

### Laba Rugi (Detil / Standard / Per Parent COA / Komparasi Bulanan)

- `labaRugiDetil()` → `getLabaRugiDetil()`: baris laba rugi rinci per akun.
- `labaRugiStandard()` → `getLabaRugiStandard()`: bentuk agregat standar (parent-child).
- `labaRugiPerParentCoa()` (param `coaId`) → `getLabaRugiPerParentCoa()`: drill-down per parent COA.
- `labaRugiKomparasiBulanan()` (param `startDate`, `endDate`) → `getLabaRugiKomparasiBulanan()`: komparasi bulanan dengan persentase pertumbuhan horizontal antar-bulan (format Jurnal.id) untuk 5 pos laba rugi (Pendapatan, BPP, Beban Ops, Pendapatan Lain, Beban Lain) dan baris ringkasan (Laba Kotor, Laba Operasional, Laba Bersih).

Service melibatkan klasifikasi tipe (pendapatan vs biaya), agregasi subtree per akun daun, dan
integrasi anggaran **RBA** (`ambilRbaPerCoa`, `hitungAlokasiBulanPerTahun`) untuk pembanding.

### Neraca (Standard / Per Parent COA / Saldo / Detil / Rinci)

- `neracaStandard()` (param `perDate`) → `getNeracaStandard()`: aktiva/pasiva/ekuitas + status balance.
- `neracaPerParentCoa()` (param `perDate`, `coaId`) → `getNeracaPerParentCoa()`: drill-down hierarki subtree akun turunan dengan filter periode cut-off otomatis, tree toggle accordion, ringkasan saldo konsolidasi, serta ekspor Excel (.xlsx) dan Cetak/PDF.
- `neracaSaldo()` (param `perDate`) → `getNeracaSaldo()`.
- `neracaDetil()` (param `perDate`) → `getNeracaDetil()`.
- `neracaRinci()` (param `startDate`, `endDate`, `tipeCoa[]`) → `getNeracaRinci()`, dengan opsi tipe
  COA dari `getDaftarTipeCoaAktif()`.

```mermaid
flowchart TD
    A["neracaStandard(perDate)"] --> B["getNeracaStandard()"]
    B --> C["bangunNeracaTree(perDate)"]
    C --> D["ambilSaldoSampaiTanggal + hitungLabaTahunBerjalan"]
    D --> E["Klasifikasi & normalisasi saldo neraca"]
    E --> F["subtotal Aktiva/Pasiva/Ekuitas + ringkasStatusNeraca (isBalance)"]
```

### Buku Besar per COA

```mermaid
flowchart TD
    A["GET bukubesar (coaIds[])"] --> B["getBukubesar(start,end,coaIds)"]
    B --> C["rowsByCoa per akun terpilih"]
    C --> D["Render view dengan coaOptions terpilih"]
    E["GET bukubesar/search-coa?q="] --> F["searchBukubesarCoaOptions(keyword)"]
    F --> G["response json results (Select2)"]
```

### Arus Kas

```mermaid
flowchart TD
    A["GET arus-kas"] --> B["Validasi startDate/endDate"]
    B --> C["getArusKas(start,end)"]
    C --> D["susunArusKasPeriode() berjalan + pembanding"]
    D --> E["Klasifikasi arus kas via arus_kas_aktivitas/kelompok COA"]
    E --> F["Render view (berjalan, pembanding, detail)"]
```

## Fungsi yang Dipanggil

- Controller: `index()`, `rincianTransaksiBukubesar()/loadRincianTransaksiBukubesar()`,
  `deteksiJurnalTidakBalance()/loadDeteksiJurnalTidakBalance()`, `labaRugiDetil()/labaRugiStandard()/labaRugiPerParentCoa()/labaRugiKomparasiBulanan()`,
  `neracaStandard()/neracaPerParentCoa()/neracaSaldo()/neracaDetil()/neracaRinci()`,
  `bukubesar()/searchBukubesarCoa()`, `arusKas()`, `resolveDateRange()` (private).
- Service: `getIdentitasLaporan()`, `getQueryRincianTransaksiBukubesar()`, `getJurnalTidakBalance()`,
  `getLabaRugiDetil()/getLabaRugiStandard()/getLabaRugiPerParentCoa()/getLabaRugiKomparasiBulanan()`,
  `getNeracaStandard()/getNeracaPerParentCoa()/getNeracaSaldo()/getNeracaDetil()/getNeracaRinci()`,
  `getBukubesar()/getBukubesarSelectedCoaOptions()/searchBukubesarCoaOptions()`,
  `getArusKas()`, `getDaftarTipeCoaAktif()` (plus banyak helper privat untuk agregasi & klasifikasi).

## Catatan Penting Bisnis

- Modul ini **read-only**; tidak mengubah data buku besar/jurnal.
- Laporan laba rugi mengintegrasikan anggaran **RBA** sebagai pembanding.
- Klasifikasi neraca dan arus kas bergantung pada `tipe_coa` dan `arus_kas_aktivitas`/`arus_kas_kelompok`
  pada COA (lihat [Konvensi Buku Besar & COA](fondasi/konvensi-bukubesar-coa.md)).
- Deteksi jurnal tidak balance membantu menemukan hasil bridging/jurnal yang tidak seimbang
  (lihat juga [Bridging Pendapatan](bridging-pendapatan.md)).
- Neraca standard menyertakan indikator `isBalance` (aktiva = pasiva + ekuitas).
