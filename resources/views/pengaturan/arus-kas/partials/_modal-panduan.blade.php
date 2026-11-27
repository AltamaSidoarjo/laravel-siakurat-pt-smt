{{-- Modal Pusat Bantuan & Glosarium Pengaturan Arus Kas --}}
<div class="modal fade" id="modalPanduanArusKas" tabindex="-1" aria-labelledby="modalPanduanArusKasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            {{-- Modal Header --}}
            <div class="modal-header border-bottom py-3 px-4" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2.5 rounded-3 bg-white text-success shadow-xs d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-book-half fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalPanduanArusKasLabel">
                            Panduan Konfigurasi Arus Kas &amp; Glosarium COA
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">
                            Perbandingan metode langsung vs tidak langsung, aturan pemetaan COA, rumus dampak kas, dan studi kasus rumah sakit.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            {{-- Nav Tabs --}}
            <div class="px-4 pt-3 pb-2 bg-light border-bottom">
                <ul class="nav nav-pills gap-2" id="pillsTabPanduanArusKas" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill"
                                id="pills-perbandingan-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-perbandingan"
                                type="button"
                                role="tab"
                                aria-controls="pills-perbandingan"
                                aria-selected="true">
                            <i class="bi bi-columns-gap"></i>
                            <span>Langsung vs Tidak Langsung</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill"
                                id="pills-aturan-coa-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-aturan-coa"
                                type="button"
                                role="tab"
                                aria-controls="pills-aturan-coa"
                                aria-selected="false">
                            <i class="bi bi-shield-check"></i>
                            <span>Aturan COA, Sifat Pos &amp; Eliminasi</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill"
                                id="pills-glosarium-kasus-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-glosarium-kasus"
                                type="button"
                                role="tab"
                                aria-controls="pills-glosarium-kasus"
                                aria-selected="false">
                            <i class="bi bi-journal-medical"></i>
                            <span>Glosarium &amp; Studi Kasus RS</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4">
                <div class="tab-content" id="pillsTabPanduanArusKasContent">

                    {{-- TAB 1: LANGSUNG VS TIDAK LANGSUNG --}}
                    <div class="tab-pane fade show active" id="pills-perbandingan" role="tabpanel" aria-labelledby="pills-perbandingan-tab" tabindex="0">
                        {{-- Seksi 1: Perbandingan Dua Metode Pelaporan --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                Perbandingan Dua Metode Pelaporan
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100 border border-primary-subtle bg-primary-subtle bg-opacity-10 shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge bg-primary px-2.5 py-1 fw-bold">METODE LANGSUNG</span>
                                                <i class="bi bi-cash-coin fs-5 text-primary"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Direct Cash Flow (Basis Tag Lawan Kasbank)</h6>
                                            <p class="small text-muted mb-2">
                                                Mengelompokkan arus kas riil berdasarkan tag <code>coa.arus_kas_aktivitas</code> dan <code>coa.arus_kas_kelompok</code> pada akun non-<code>Kasbank</code> yang menjadi lawan transaksi jurnal <code>Kasbank</code> (terdaftar di master <code>referensi_kelompok_arus_kas</code>).
                                            </p>
                                            <div class="small bg-white p-2.5 rounded border border-light text-secondary d-flex flex-column gap-1">
                                                <div><strong>Struktur Pilar:</strong> 3 aktivitas (<code>Operasi</code>, <code>Investasi</code>, <code>Pendanaan</code>).</div>
                                                <div><strong>Output Laporan:</strong> <code>/laporan/keuangan/arus-kas</code></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 border border-success-subtle bg-success-subtle bg-opacity-10 shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge bg-success px-2.5 py-1 fw-bold">METODE TIDAK LANGSUNG</span>
                                                <i class="bi bi-diagram-3 fs-5 text-success"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Indirect Cash Flow (Basis Rekonsiliasi Laba &amp; Neraca)</h6>
                                            <p class="small text-muted mb-2">
                                                Dimulai dari <strong>Laba Bersih</strong> periode berjalan, lalu disesuaikan melalui pos dinamis (<code>setting_arus_kas</code>) berdasarkan mutasi akun Laba Rugi non-kas dan perubahan saldo akun Neraca non-Kas.
                                            </p>
                                            <div class="small bg-white p-2.5 rounded border border-light text-secondary d-flex flex-column gap-1">
                                                <div><strong>Struktur 4 Bagian:</strong> <code>Penyesuaian Laba</code>, <code>Modal Kerja Operasi</code>, <code>Aktivitas Investasi</code>, <code>Aktivitas Pendanaan</code>.</div>
                                                <div><strong>Output Laporan:</strong> <code>/laporan/keuangan/arus-kas-tidak-langsung</code></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seksi 2: Alur Kerja Konfigurasi (4 Langkah per Metode) --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                Alur Kerja Konfigurasi (4 Langkah per Metode)
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100 border border-light-subtle shadow-xs">
                                        <div class="card-header bg-light py-2 px-3 fw-bold text-dark small">
                                            <i class="bi bi-list-check text-primary me-1"></i> Konfigurasi Metode Langsung
                                        </div>
                                        <div class="card-body p-3 small">
                                            <ol class="mb-0 ps-3 d-flex flex-column gap-2 text-secondary">
                                                <li><strong>Kelola Master Kelompok:</strong> Buat atau periksa pos kelompok pada tab <em>Metode Langsung</em> melalui Tombol Tambah Pos Referensi sesuai aktivitas (Operasi, Investasi, Pendanaan).</li>
                                                <li><strong>Petakan COA &amp; Ekspansi Induk:</strong> Gunakan Aksi Edit Pos untuk memilih akun daun satu per satu atau fitur <em>Pilih Cepat Akun Induk</em> agar seluruh sub-akun masuk sekaligus.</li>
                                                <li><strong>Pantau Banner Lawan Kasbank:</strong> Periksa peringatan kuning akun lawan <code>Kasbank</code> yang bermutasi namun belum memiliki tag kelompok, lalu gunakan tombol <strong>Petakan</strong> cepat.</li>
                                                <li><strong>Verifikasi Laporan:</strong> Buka tombol <em>Laporan Arus Kas</em> (<code>/laporan/keuangan/arus-kas</code>) untuk memastikan mutasi kas riil tampil pada kelompok yang tepat.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 border border-light-subtle shadow-xs">
                                        <div class="card-header bg-light py-2 px-3 fw-bold text-dark small">
                                            <i class="bi bi-sliders text-success me-1"></i> Konfigurasi Metode Tidak Langsung
                                        </div>
                                        <div class="card-body p-3 small">
                                            <ol class="mb-0 ps-3 d-flex flex-column gap-2 text-secondary">
                                                <li><strong>Pilih Mode Tampilan:</strong> Gunakan <em>Mode Tab</em> untuk memfilter per bagian/pencarian, atau <em>Mode Struktur Laporan</em> untuk melihat urutan lengkap dan mengatur ulang posisi (tombol panah &uarr; / &darr;).</li>
                                                <li><strong>Atur Pos &amp; Pemetaan COA:</strong> Gunakan Tombol Tambah Pos Arus Kas, tentukan bagian, sifat pos, serta opsi prefix perubahan, lalu pilih akun daun yang kompatibel.</li>
                                                <li><strong>Kelola Daftar Pengecualian:</strong> Masukkan akun kontra (seperti akumulasi penyusutan) ke <em>Daftar Pengecualian</em> agar tidak memicu perhitungan ganda maupun peringatan palsu.</li>
                                                <li><strong>Verifikasi Rekonsiliasi:</strong> Pastikan banner peringatan akun Neraca bermutasi kosong, lalu periksa <code>/laporan/keuangan/arus-kas-tidak-langsung</code>.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seksi 3: Cara Kerja Pemindai Akun Belum Dipetakan & Quick-Map --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                Cara Kerja Pemindai Akun Belum Dipetakan &amp; Quick-Map
                            </h6>
                            <div class="alert alert-info border-info-subtle bg-info-subtle bg-opacity-25 d-flex align-items-start gap-3 p-3 rounded-3 mb-0">
                                <i class="bi bi-radar text-info fs-5 flex-shrink-0 mt-0.5"></i>
                                <div class="small text-dark d-flex flex-column gap-2">
                                    <div>
                                        <strong>Pemicu Banner Kuning:</strong>
                                        <ul class="mb-0 ps-3 mt-1">
                                            <li><strong>Metode Langsung:</strong> Mendeteksi akun daun non-<code>Kasbank</code> yang belum memiliki tag aktivitas/kelompok valid namun tercatat sebagai lawan transaksi <code>Kasbank</code> pada Buku Besar tahun berjalan.</li>
                                            <li><strong>Metode Tidak Langsung:</strong> Mendeteksi akun daun Neraca non-<code>Kasbank</code> (termasuk yang berstatus nonaktif) yang memiliki mutasi tahun berjalan tetapi belum terpetakan ke pos aktif maupun <em>Daftar Pengecualian</em>.</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong>Arti Kolom pada Tabel Pemindai:</strong>
                                        Kolom <strong>Debit</strong> dan <strong>Kredit</strong> menampilkan akumulasi mutasi periode berjalan pada akun terkait, sedangkan kolom <strong>Dampak Kas (<code>Kredit - Debit</code>)</strong> menunjukkan indikasi aliran kas masuk (bernilai positif) atau keluar (bernilai negatif).
                                    </div>
                                    <div>
                                        <strong>Aturan Eliminasi Khusus Pemindai Metode Langsung:</strong>
                                        Pemindai Metode Langsung otomatis mengabaikan <strong>transfer antar-<code>Kasbank</code></strong> karena total net kas transaksi bernilai nol (<code>|cashNet| &lt; 0,005</code>), serta mengabaikan <strong>jurnal memorial non-kas</strong> yang tidak menyentuh akun <code>Kasbank</code> sama sekali.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: ATURAN COA, SIFAT POS & ELIMINASI --}}
                    <div class="tab-pane fade" id="pills-aturan-coa" role="tabpanel" aria-labelledby="pills-aturan-coa-tab" tabindex="0">
                        {{-- Seksi 1: Matriks Aturan Pemetaan COA Lintas Metode --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                Matriks Aturan Pemetaan COA Lintas Metode
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-center small">
                                        <tr>
                                            <th style="width: 22%;">Aspek Aturan</th>
                                            <th style="width: 39%;">Metode Langsung</th>
                                            <th style="width: 39%;">Metode Tidak Langsung</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td class="fw-semibold text-dark">Level Akun</td>
                                            <td>
                                                Hanya <strong>akun daun (leaf)</strong> yang dipetakan untuk pelaporan. Akun induk yang bertag di Master COA tampil terpisah sebagai <strong>Akun Induk Template</strong> (<code>+Y Induk</code>) untuk pewarisan sub-akun baru dan dilindungi <em>Restricted Delete</em>.
                                            </td>
                                            <td>
                                                Hanya <strong>akun daun (leaf)</strong> yang dapat dipetakan ke pos ataupun dimasukkan ke Daftar Pengecualian; akun induk ditolak otomatis saat validasi.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Akun Tipe <code>Kasbank</code></td>
                                            <td colspan="2" class="text-danger-emphasis bg-danger-subtle bg-opacity-10">
                                                <strong>Dilarang mutlak</strong> pada kedua metode dan di Daftar Pengecualian karena akun <code>Kasbank</code> berperan sebagai kas/setara kas itu sendiri.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Eksklusivitas &amp; Pindah Pos</td>
                                            <td>
                                                Mengizinkan pemindahan akun leaf antar-pos referensi dengan dialog konfirmasi melalui <code>modalKonfirmasiPindahCoaLangsung</code> sebelum menimpa tag lama.
                                            </td>
                                            <td>
                                                Menerapkan eksklusivitas ketat berbasis <code>UNIQUE(coa_id)</code>: <strong>1 akun = maksimal 1 pos atau 1 entri pengecualian</strong>. Pemetaan ganda ditolak server.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Pilih Cepat Akun Induk (Parent Expansion)</td>
                                            <td>
                                                Mengelompokkan turunan leaf menjadi 3 daftar: <code>eligible</code> (siap dipilih), <code>reassignable</code> (sedang dipakai pos lain), dan <code>skipped</code> (akun <code>Kasbank</code>).
                                            </td>
                                            <td>
                                                Mengelompokkan turunan leaf menjadi 2 daftar: <code>eligible</code> (siap dipilih) dan <code>skipped</code> (tidak kompatibel, dikecualikan, atau sudah dipakai pos lain).
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Filter Status Aktif COA</td>
                                            <td>
                                                Menerima semua akun leaf non-<code>Kasbank</code>, termasuk akun berstatus nonaktif (ditandai label <code>[Nonaktif]</code>), agar histori transaksi tetap terbaca.
                                            </td>
                                            <td>
                                                Sifat <code>penambah_laba</code> / <code>pengurang_laba</code> hanya menerima akun Laba Rugi <strong>Aktif</strong>. Sifat Neraca (<code>aset</code>, <code>liabilitas</code>, <code>ekuitas</code>) &amp; Pengecualian menerima akun Neraca non-<code>Kasbank</code> termasuk yang nonaktif.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Seksi 2: 5 Sifat Pos, Rumus Dampak Kas & Prefix Perubahan --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                5 Sifat Pos, Rumus Dampak Kas &amp; Prefix Perubahan (Metode Tidak Langsung)
                            </h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-center small">
                                        <tr>
                                            <th style="width: 18%;">Sifat Pos</th>
                                            <th style="width: 24%;">Akun COA Kompatibel</th>
                                            <th style="width: 22%;">Rumus <code>perubahan</code></th>
                                            <th style="width: 22%;">Rumus <code>dampak_kas</code></th>
                                            <th style="width: 14%;">Prefix Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td><code>penambah_laba</code></td>
                                            <td>Laba Rugi <strong>Aktif</strong> (Beban non-kas)</td>
                                            <td><code>Debit - Kredit</code></td>
                                            <td><code>Debit - Kredit</code></td>
                                            <td class="text-center text-muted">Tidak dipakai</td>
                                        </tr>
                                        <tr>
                                            <td><code>pengurang_laba</code></td>
                                            <td>Laba Rugi <strong>Aktif</strong> (Pendapatan non-kas)</td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td><code>Debit - Kredit</code></td>
                                            <td class="text-center text-muted">Tidak dipakai</td>
                                        </tr>
                                        <tr>
                                            <td><code>aset</code></td>
                                            <td>Neraca non-<code>Kasbank</code></td>
                                            <td><code>Debit - Kredit</code></td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                        <tr>
                                            <td><code>liabilitas</code></td>
                                            <td>Neraca non-<code>Kasbank</code></td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                        <tr>
                                            <td><code>ekuitas</code></td>
                                            <td>Neraca non-<code>Kasbank</code></td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td><code>Kredit - Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-center small">
                                        <tr>
                                            <th style="width: 20%;">Sifat Pos (Jika Prefix Aktif)</th>
                                            <th style="width: 26%;">Saat <code>perubahan &gt; 0.005</code></th>
                                            <th style="width: 27%;">Saat <code>perubahan &lt; -0.005</code></th>
                                            <th style="width: 27%;">Saat Netral (<code>|perubahan| &le; 0.005</code>)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td><code>aset</code></td>
                                            <td><code>(Kenaikan) [Nama Pos]</code></td>
                                            <td><code>Penurunan [Nama Pos]</code></td>
                                            <td><code>Penurunan (kenaikan) [Nama Pos]</code></td>
                                        </tr>
                                        <tr>
                                            <td><code>liabilitas</code> &amp; <code>ekuitas</code></td>
                                            <td><code>Kenaikan [Nama Pos]</code></td>
                                            <td><code>(Penurunan) [Nama Pos]</code></td>
                                            <td><code>Kenaikan (penurunan) [Nama Pos]</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Seksi 3: Daftar Pengecualian vs Status Pos Nonaktif --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                Daftar Pengecualian vs Status Pos Nonaktif
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border border-secondary-subtle rounded p-3 bg-white h-100 small">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-secondary">Daftar Pengecualian</span>
                                            <span class="fw-bold text-dark">Akun Dikecualikan (Metode Tidak Langsung)</span>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Menandai akun Neraca non-<code>Kasbank</code> (misalnya akun kontra akumulasi penyusutan atau cadangan kerugian piutang) beserta alasan pencatatannya. Akun di daftar ini <strong>sengaja dikeluarkan dari kalkulasi laporan</strong> dan <strong>tidak muncul di banner peringatan</strong> akun belum dipetakan, sekaligus tetap dikunci eksklusif agar tidak terpetakan ganda ke pos lain.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border border-warning-subtle rounded p-3 bg-white h-100 small">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-warning text-dark">Pos Nonaktif</span>
                                            <span class="fw-bold text-dark">Status Nonaktif di Langsung &amp; Tidak Langsung</span>
                                        </div>
                                        <p class="text-muted mb-0">
                                            Pada Metode Langsung, pos nonaktif disembunyikan dari opsi baru tanpa menghapus tag COA historis. Pada Metode Tidak Langsung, <strong>Pos Nonaktif</strong> tidak ditampilkan pada laporan arus kas, namun <strong>tetap mengunci akun COA</strong> pada pemetaan; apabila akun di dalamnya memiliki mutasi berjalan, akun tersebut akan ikut muncul di banner peringatan sebagai belum aktif terpetakan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 3: GLOSARIUM & STUDI KASUS RS --}}
                    <div class="tab-pane fade" id="pills-glosarium-kasus" role="tabpanel" aria-labelledby="pills-glosarium-kasus-tab" tabindex="0">
                        {{-- Seksi 1: Kamus Istilah Inti (6 Kartu) --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                Kamus Istilah Inti Arus Kas
                            </h6>
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <div class="border border-primary-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-primary mb-1">Akun Lawan Kasbank</div>
                                        <p class="text-muted mb-0">Akun non-<code>Kasbank</code> yang berpasangan dengan akun <code>Kasbank</code> dalam satu jurnal transaksi; menjadi penentu kelompok arus kas pada Metode Langsung.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-success-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-success mb-1">Akun Daun (Leaf) vs Induk Template</div>
                                        <p class="text-muted mb-0">Akun daun adalah akun terbawah yang menampung jurnal dan dipetakan untuk laporan; Akun Induk Template adalah induk bertag COA untuk pewarisan sub-akun baru.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-info-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-info-emphasis mb-1">Dampak Kas (Kredit - Debit)</div>
                                        <p class="text-muted mb-0">Selisih mutasi kredit dikurangi debit pada akun lawan atau Neraca. Nilai positif menandakan aliran kas masuk, sedangkan nilai negatif menandakan kas keluar.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-warning-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-warning-emphasis mb-1">Prefix Perubahan</div>
                                        <p class="text-muted mb-0">Awalan dinamis <code>(Kenaikan)</code>, <code>Penurunan</code>, <code>Kenaikan</code>, atau <code>(Penurunan)</code> yang otomatis disisipkan di depan nama pos Neraca berdasarkan arah perubahan saldo.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-secondary-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-secondary-emphasis mb-1">Akun Dikecualikan (Kontra)</div>
                                        <p class="text-muted mb-0">Akun Neraca non-<code>Kasbank</code> seperti akumulasi penyusutan atau cadangan piutang yang sengaja dikecualikan agar tidak dihitung ganda atau memicu peringatan.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-danger-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-danger mb-1">Selisih Rekonsiliasi Kas Riil</div>
                                        <p class="text-muted mb-0">Selisih antara saldo kas akhir riil dan kas akhir rekonsiliasi laporan tidak langsung (<code>kas_akhir_riil - kas_akhir</code>). Bernilai 0 bila pemetaan sudah tuntas.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seksi 2: 5 Studi Kasus Nyata Rumah Sakit --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                5 Studi Kasus Nyata Rumah Sakit
                            </h6>
                            <div class="row g-3">
                                {{-- Kasus 1 --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 1: Klaim BPJS Kesehatan &amp; Piutang Pasien</span>
                                            <span class="badge bg-primary">Operasi / Modal Kerja</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary d-flex flex-column gap-1">
                                            <div><strong>Metode Langsung:</strong> Penerimaan kas/bank atas tagihan pasien BPJS memetakan akun lawan piutang ke kelompok operasi <strong>Penerimaan klaim BPJS Kesehatan</strong>.</div>
                                            <div><strong>Metode Tidak Langsung:</strong> Akun piutang <code>112.01.*</code> (serta <code>112.02.*</code>, <code>112.05.*</code>) dipetakan ke bagian <code>modal_kerja</code> (pos <em>Piutang usaha</em>, sifat <code>aset</code>, prefix perubahan aktif).</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 2 --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 2: Penyusutan Aset Tetap &amp; Akun Kontra</span>
                                            <span class="badge bg-success">Anti Double-Counting</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary d-flex flex-column gap-1">
                                            <div><strong>Metode Langsung:</strong> Jurnal penyusutan tidak menyentuh <code>Kasbank</code> sehingga otomatis diabaikan oleh pemindai dan laporan langsung.</div>
                                            <div><strong>Metode Tidak Langsung:</strong> Beban penyusutan <code>706.*</code> dipetakan ke bagian <code>penyesuaian_laba</code> sifat <code>penambah_laba</code> (tanpa prefix), sedangkan akumulasi penyusutan <code>142..147</code> masuk <strong>Daftar Pengecualian</strong>.</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 3 --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 3: Pembelian &amp; Hutang Aset Tetap</span>
                                            <span class="badge bg-info text-dark">Aktivitas Investasi</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary d-flex flex-column gap-1">
                                            <div><strong>Metode Langsung:</strong> Pengeluaran kas untuk perolehan maupun pelunasan cicilan alat medis/gedung dipetakan ke <strong>Pembelian &amp; pembayaran hutang aset tetap</strong>.</div>
                                            <div><strong>Metode Tidak Langsung:</strong> Bagian <code>investasi</code> memetakan akun perolehan aset <code>131..137</code> (pos <em>Pembelian aset</em>, sifat <code>aset</code>, tanpa prefix) dan hutang vendor aset <code>221.*</code> (sifat <code>liabilitas</code>, dengan prefix).</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 4 --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 4: Persediaan Farmasi, Gaji &amp; Jasa Medis</span>
                                            <span class="badge bg-warning text-dark">Operasional Rutin RS</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary d-flex flex-column gap-1">
                                            <div><strong>Metode Langsung:</strong> Pembayaran ke PBF dipetakan ke <strong>Pembayaran pemasok obat, alat kesehatan &amp; BHP</strong>, dan pembayaran honorarium ke <strong>Pembayaran gaji karyawan &amp; jasa medis dokter</strong>.</div>
                                            <div><strong>Metode Tidak Langsung:</strong> Persediaan farmasi <code>113.*</code> (<code>aset</code>) dan akrual gaji/jasa medis <code>213.*</code> (<em>Beban YMH dibayar</em>, <code>liabilitas</code>) dipetakan ke bagian <code>modal_kerja</code> dengan prefix perubahan aktif.</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 5 --}}
                                <div class="col-12">
                                    <div class="card border border-light-subtle shadow-xs">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 5: Transfer Antar-Kas/Bank &amp; Jurnal Memorial Non-Kas</span>
                                            <span class="badge bg-secondary">Eliminasi Otomatis</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <strong>Metode Langsung:</strong> Transfer antar-rekening <code>Kasbank</code> memiliki net kas transaksi nol (<code>|cashNet| &lt; 0,005</code>) sehingga diabaikan pemindai dan tidak muncul sebagai arus kas operasi/investasi/pendanaan; jurnal memorial non-kas juga diabaikan karena tidak memiliki mutasi <code>Kasbank</code>.
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Metode Tidak Langsung:</strong> Transfer antar-<code>Kasbank</code> tidak mengubah total kas akhir riil, sedangkan jurnal memorial yang memutasi akun Neraca non-Kas tetap dipantau pemindai Metode Tidak Langsung agar tidak menimbulkan selisih rekonsiliasi.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-light py-2 px-4 border-top">
                <button type="button" class="btn btn-secondary btn-sm px-3 fw-semibold" data-bs-dismiss="modal">
                    Tutup Panduan
                </button>
            </div>
        </div>
    </div>
</div>
