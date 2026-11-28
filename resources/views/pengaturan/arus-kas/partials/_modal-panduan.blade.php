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
                            Panduan Pengaturan Arus Kas &amp; Glosarium COA
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">
                            Panduan praktis metode langsung vs tidak langsung, aturan pemetaan akun COA, dampak kas, serta studi kasus keuangan rumah sakit.
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
                                            <h6 class="fw-bold text-dark mb-1">Berbasis Transaksi Kas &amp; Bank</h6>
                                            <p class="small text-muted mb-2">
                                                Mengelompokkan arus kas masuk dan keluar riil berdasarkan pilihan <strong>Aktivitas Arus Kas</strong> dan <strong>Kelompok Arus Kas</strong> pada akun selain <strong>Kasbank</strong> yang menjadi lawan transaksi jurnal kas/bank (dikelola melalui Master Pos Referensi).
                                            </p>
                                            <div class="small bg-white p-2.5 rounded border border-light text-secondary d-flex flex-column gap-1">
                                                <div><strong>Struktur Pilar:</strong> 3 aktivitas (<strong>Operasi</strong>, <strong>Investasi</strong>, <strong>Pendanaan</strong>).</div>
                                                <div><strong>Lokasi Laporan:</strong> Menu <strong>Laporan Keuangan &rarr; Arus Kas (Metode Langsung)</strong> atau tombol <strong>Laporan Arus Kas</strong>.</div>
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
                                            <h6 class="fw-bold text-dark mb-1">Berbasis Rekonsiliasi Laba &amp; Neraca</h6>
                                            <p class="small text-muted mb-2">
                                                Dimulai dari <strong>Laba (Rugi) Bersih Periode Berjalan</strong>, lalu disesuaikan melalui daftar <strong>Pos Arus Kas</strong> berdasarkan mutasi pos Laba Rugi non-kas dan perubahan saldo pos Neraca selain kas.
                                            </p>
                                            <div class="small bg-white p-2.5 rounded border border-light text-secondary d-flex flex-column gap-1">
                                                <div><strong>Struktur 4 Bagian:</strong> <strong>Penyesuaian Laba</strong>, <strong>Modal Kerja Operasi</strong>, <strong>Aktivitas Investasi</strong>, <strong>Aktivitas Pendanaan</strong>.</div>
                                                <div><strong>Lokasi Laporan:</strong> Menu <strong>Laporan Keuangan &rarr; Arus Kas (Metode Tidak Langsung)</strong> atau tombol <strong>Laporan Arus Kas</strong>.</div>
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
                                                <li><strong>Siapkan Daftar Kelompok:</strong> Pada tab <em>Pengaturan Arus Kas Metode Langsung</em>, buat atau periksa pos kelompok sesuai aktivitasnya (Operasi, Investasi, Pendanaan) lewat tombol <strong>Tambah Pos Referensi</strong>.</li>
                                                <li><strong>Petakan Akun COA &amp; Sub-Akun:</strong> Gunakan tombol edit pada pos terkait untuk memilih akun detail satu per satu, atau pilih akun induk lalu klik <strong>+ Sub-Akun</strong> untuk memasukkan seluruh akun turunannya sekaligus.</li>
                                                <li><strong>Tindak Lanjuti Banner Kuning:</strong> Periksa peringatan kuning akun lawan <strong>Kasbank</strong> yang bermutasi namun belum memiliki kelompok arus kas, lalu klik tombol <strong>Petakan</strong> untuk melengkapinya dengan cepat.</li>
                                                <li><strong>Verifikasi Laporan:</strong> Klik tombol <strong>Laporan Arus Kas</strong> untuk memastikan mutasi kas masuk dan keluar sudah tampil pada kelompok yang tepat.</li>
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
                                                <li><strong>Pilih Tampilan Kerja:</strong> Gunakan <em>Mode Tab</em> untuk menyaring per bagian atau mencari akun, atau <em>Mode Struktur Laporan</em> untuk melihat urutan laporan secara utuh dan mengatur posisi baris (tombol panah &uarr; / &darr;).</li>
                                                <li><strong>Susun Pos &amp; Petakan COA:</strong> Klik <strong>Tambah Pos</strong>, tentukan <strong>Kelompok Bagian</strong>, <strong>Sifat Pos</strong>, serta opsi <strong>Prefix Perubahan</strong>, lalu pilih akun detail yang sesuai.</li>
                                                <li><strong>Kelola Daftar Pengecualian:</strong> Masukkan akun pengurang nilai/kontra (seperti akumulasi penyusutan) ke <em>Akun Dikecualikan</em> agar tidak terhitung ganda maupun memicu peringatan palsu.</li>
                                                <li><strong>Cek Rekonsiliasi Saldo Kas:</strong> Pastikan banner peringatan akun Neraca bermutasi sudah kosong, lalu periksa <strong>Laporan Keuangan &rarr; Arus Kas (Metode Tidak Langsung)</strong> hingga selisih rekonsiliasi kas bernilai 0.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Seksi 3: Cara Kerja Pemindai Akun Belum Dipetakan & Pintasan Petakan --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                Cara Kerja Pemindai Akun Belum Dipetakan &amp; Pintasan Petakan
                            </h6>
                            <div class="alert alert-info border-info-subtle bg-info-subtle bg-opacity-25 d-flex align-items-start gap-3 p-3 rounded-3 mb-0">
                                <i class="bi bi-radar text-info fs-5 flex-shrink-0 mt-0.5"></i>
                                <div class="small text-dark d-flex flex-column gap-2">
                                    <div>
                                        <strong>Kapan Banner Peringatan Kuning Muncul?</strong>
                                        <ul class="mb-0 ps-3 mt-1">
                                            <li><strong>Metode Langsung:</strong> Mendeteksi akun detail selain <strong>Kasbank</strong> yang belum memiliki <strong>Aktivitas Arus Kas</strong> maupun <strong>Kelompok Arus Kas</strong> yang sesuai, namun tercatat berpasangan dengan transaksi <strong>Kasbank</strong> pada Buku Besar tahun berjalan.</li>
                                            <li><strong>Metode Tidak Langsung:</strong> Mendeteksi akun detail Neraca selain <strong>Kasbank</strong> (termasuk akun berstatus Nonaktif) yang memiliki mutasi tahun berjalan tetapi belum masuk ke pos aktif maupun <em>Daftar Pengecualian</em>.</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong>Arti Kolom pada Tabel Pemindai:</strong>
                                        Kolom <strong>Debit</strong> dan <strong>Kredit</strong> menampilkan akumulasi mutasi periode berjalan pada akun tersebut, sedangkan kolom <strong>Dampak Kas (Kredit &minus; Debit)</strong> menunjukkan arah kas: nilai positif menandakan kas masuk dan nilai negatif menandakan kas keluar.
                                    </div>
                                    <div>
                                        <strong>Transaksi yang Diabaikan Otomatis (Metode Langsung):</strong>
                                        Pemindai Metode Langsung otomatis mengabaikan <strong>transfer antar-rekening Kasbank</strong> (misalnya pindah buku kas ke bank) karena selisih kas bersihnya bernilai nol (di bawah pembulatan Rp 0,01), serta mengabaikan <strong>jurnal memorial non-kas</strong> yang tidak menyentuh akun <strong>Kasbank</strong> sama sekali.
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
                                Perbandingan Aturan Pemetaan Akun COA
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
                                            <td class="fw-semibold text-dark">Tingkat Akun</td>
                                            <td>
                                                Hanya <strong>akun detail (leaf)</strong> yang dipetakan untuk pelaporan. Akun induk yang bertag di Master COA tampil terpisah sebagai catatan <strong>Akun Induk Template</strong> (mis. lencana <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">+1 Induk</span>) untuk pewarisan sub-akun baru, dan pos yang masih dipakai <strong>dilindungi agar tidak terhapus</strong>.
                                            </td>
                                            <td>
                                                Hanya <strong>akun detail (leaf)</strong> yang dapat dipetakan ke pos ataupun dimasukkan ke Daftar Pengecualian; akun induk ditolak otomatis saat disimpan.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Akun Tipe <strong>Kasbank</strong></td>
                                            <td colspan="2" class="text-danger-emphasis bg-danger-subtle bg-opacity-10">
                                                <strong>Dilarang mutlak</strong> pada kedua metode maupun di Daftar Pengecualian, karena akun bertipe <strong>Kasbank</strong> berperan sebagai kas dan setara kas itu sendiri.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Kepemilikan Akun &amp; Pindah Pos</td>
                                            <td>
                                                Mengizinkan pemindahan akun detail antar-pos referensi. Jika akun sedang dipakai pos lain, sistem menampilkan dialog <strong>Konfirmasi Pemindahan Akun COA</strong> sebelum mengganti kelompok lamanya.
                                            </td>
                                            <td>
                                                Menerapkan pemetaan <strong>eksklusif (1 akun = maksimal 1 pos atau 1 pengecualian)</strong>. Pemetaan satu akun ke lebih dari satu pos ditolak sistem.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Pilih Cepat Akun Induk (+ Sub-Akun)</td>
                                            <td>
                                                Membagi sub-akun di bawah induk terpilih menjadi 3 hasil: <strong>siap dipilih</strong> (otomatis tercentang), <strong>sudah dipakai pos lain</strong> (dapat disertakan/dipindahkan), dan <strong>dilewati</strong> (akun tipe Kasbank).
                                            </td>
                                            <td>
                                                Membagi sub-akun di bawah induk terpilih menjadi 2 hasil: <strong>siap dipilih</strong> (otomatis tercentang) dan <strong>dilewati</strong> (tidak sesuai sifat pos, masuk daftar pengecualian, atau sudah dipakai pos lain).
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Akun Aktif vs Nonaktif</td>
                                            <td>
                                                Menerima semua akun detail selain Kasbank, termasuk akun berstatus nonaktif (ditandai label <strong>[Nonaktif]</strong>), agar transaksi historis tetap terbaca.
                                            </td>
                                            <td>
                                                Sifat <strong>Penambah / Pengurang Laba (Non-Kas)</strong> hanya menerima akun Laba Rugi berstatus <strong>Aktif</strong>. Sifat Neraca (<strong>Aset</strong>, <strong>Liabilitas</strong>, <strong>Ekuitas</strong>) &amp; Pengecualian menerima akun Neraca selain Kasbank termasuk yang nonaktif.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Seksi 2: 5 Pilihan Sifat Pos, Cara Hitung Dampak Kas & Awalan Perubahan --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                5 Pilihan Sifat Pos, Dampak Kas &amp; Prefix Perubahan (Metode Tidak Langsung)
                            </h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-center small">
                                        <tr>
                                            <th style="width: 21%;">Pilihan Sifat Pos</th>
                                            <th style="width: 24%;">Akun COA yang Cocok</th>
                                            <th style="width: 20%;">Perubahan Saldo</th>
                                            <th style="width: 20%;">Dampak ke Arus Kas</th>
                                            <th style="width: 15%;">Prefix Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td class="fw-semibold text-dark">Penambah Laba (Non-Kas)</td>
                                            <td>Laba Rugi <strong>Aktif</strong> (mis. beban penyusutan)</td>
                                            <td><code>Debit &minus; Kredit</code></td>
                                            <td><code>Debit &minus; Kredit</code></td>
                                            <td class="text-center text-muted">Tidak dipakai</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Pengurang Laba (Non-Kas)</td>
                                            <td>Laba Rugi <strong>Aktif</strong> (mis. pendapatan non-kas)</td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td><code>Debit &minus; Kredit</code></td>
                                            <td class="text-center text-muted">Tidak dipakai</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Aset (Neraca)</td>
                                            <td>Neraca selain <strong>Kasbank</strong></td>
                                            <td><code>Debit &minus; Kredit</code></td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Liabilitas (Neraca)</td>
                                            <td>Neraca selain <strong>Kasbank</strong></td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Ekuitas (Neraca)</td>
                                            <td>Neraca selain <strong>Kasbank</strong></td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td><code>Kredit &minus; Debit</code></td>
                                            <td class="text-center text-success fw-semibold">Mendukung</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle mb-1">
                                    <thead class="table-light text-center small">
                                        <tr>
                                            <th style="width: 22%;">Sifat Pos (Jika Prefix Aktif)</th>
                                            <th style="width: 26%;">Saat Saldo Naik</th>
                                            <th style="width: 26%;">Saat Saldo Turun</th>
                                            <th style="width: 26%;">Saat Saldo Tetap / Netral</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td class="fw-semibold text-dark">Aset (Neraca)</td>
                                            <td><strong>(Kenaikan) [Nama Pos]</strong></td>
                                            <td><strong>Penurunan [Nama Pos]</strong></td>
                                            <td><strong>Penurunan (kenaikan) [Nama Pos]</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold text-dark">Liabilitas &amp; Ekuitas (Neraca)</td>
                                            <td><strong>Kenaikan [Nama Pos]</strong></td>
                                            <td><strong>(Penurunan) [Nama Pos]</strong></td>
                                            <td><strong>Kenaikan (penurunan) [Nama Pos]</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0">
                                <i class="bi bi-info-circle me-1"></i> Tanda kurung <strong>(...)</strong> menandakan arus kas keluar (misalnya piutang bertambah atau utang berkurang). Perubahan sangat kecil akibat selisih pembulatan (di bawah Rp 0,01) dianggap tetap/netral.
                            </p>
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
                                            Menandai akun Neraca selain <strong>Kasbank</strong> (misalnya akun kontra akumulasi penyusutan atau cadangan kerugian piutang) beserta alasan pencatatannya. Akun di daftar ini <strong>sengaja dikeluarkan dari kalkulasi laporan</strong> dan <strong>tidak muncul di banner peringatan</strong>, sekaligus terkunci agar tidak terpetakan ke pos lain.
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
                                            Pada Metode Langsung, pos nonaktif disembunyikan dari pilihan pemetaan baru tanpa mencabut kelompok pada akun COA historis. Pada Metode Tidak Langsung, <strong>Pos Nonaktif</strong> tidak ditampilkan pada laporan arus kas, namun <strong>masih mengunci akun COA di dalamnya</strong>; bila akun tersebut bermutasi pada periode berjalan, akun akan muncul di banner peringatan karena tidak berada pada pos aktif.
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
                                Kamus Istilah Keuangan Arus Kas
                            </h6>
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <div class="border border-primary-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-primary mb-1">Akun Lawan Kasbank</div>
                                        <p class="text-muted mb-0">Akun selain <strong>Kasbank</strong> yang berpasangan dengan akun <strong>Kasbank</strong> dalam satu jurnal transaksi; menjadi penentu kelompok arus kas pada Metode Langsung.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-success-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-success mb-1">Akun Detail (Leaf) vs Induk Template</div>
                                        <p class="text-muted mb-0">Akun detail (leaf) adalah akun terbawah tempat jurnal transaksi dicatat dan yang dipetakan untuk laporan; Akun Induk Template adalah akun induk di Master COA untuk pewarisan sub-akun baru.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-info-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-info-emphasis mb-1">Dampak Kas (Kredit &minus; Debit)</div>
                                        <p class="text-muted mb-0">Selisih mutasi kredit dikurangi debit pada akun lawan atau akun Neraca. Nilai positif menandakan aliran kas masuk, sedangkan nilai negatif menandakan kas keluar.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-warning-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-warning-emphasis mb-1">Prefix Perubahan</div>
                                        <p class="text-muted mb-0">Awalan otomatis <strong>(Kenaikan)</strong>, <strong>Penurunan</strong>, <strong>Kenaikan</strong>, atau <strong>(Penurunan)</strong> yang disisipkan di depan nama pos Neraca mengikuti arah perubahan saldo.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-secondary-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-secondary-emphasis mb-1">Akun Dikecualikan (Akun Kontra)</div>
                                        <p class="text-muted mb-0">Akun Neraca selain <strong>Kasbank</strong> seperti akumulasi penyusutan atau cadangan piutang yang sengaja dikecualikan agar tidak terhitung ganda atau memicu peringatan.</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="border border-danger-subtle rounded p-3 bg-white h-100">
                                        <div class="fw-bold text-danger mb-1">Selisih Rekonsiliasi Kas Riil</div>
                                        <p class="text-muted mb-0">Selisih antara <strong>Saldo Kas dan Setara Kas Riil (Buku Besar)</strong> dikurangi <strong>Kas dan Setara Kas pada Akhir Periode</strong> di laporan tidak langsung. Nilainya 0 bila pemetaan sudah tuntas.</p>
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
                                            <div><strong>Metode Tidak Langsung:</strong> Akun piutang <code>112.01.*</code> (serta <code>112.02.*</code>, <code>112.05.*</code>) dipetakan ke bagian <strong>Modal Kerja Operasi</strong> pada pos <em>Piutang usaha</em> (sifat <strong>Aset (Neraca)</strong>, Prefix Perubahan aktif).</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 2 --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 2: Penyusutan Aset Tetap &amp; Akun Kontra</span>
                                            <span class="badge bg-success">Anti Hitung Ganda</span>
                                        </div>
                                        <div class="card-body p-3 small text-secondary d-flex flex-column gap-1">
                                            <div><strong>Metode Langsung:</strong> Jurnal penyusutan tidak menyentuh akun <strong>Kasbank</strong> sehingga otomatis diabaikan oleh pemindai dan laporan langsung.</div>
                                            <div><strong>Metode Tidak Langsung:</strong> Beban penyusutan <code>706.*</code> dipetakan ke bagian <strong>Penyesuaian Laba</strong> dengan sifat <strong>Penambah Laba (Non-Kas)</strong> (tanpa prefix), sedangkan akumulasi penyusutan <code>142..147</code> masuk <strong>Daftar Pengecualian</strong>.</div>
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
                                            <div><strong>Metode Tidak Langsung:</strong> Bagian <strong>Aktivitas Investasi</strong> memetakan akun perolehan aset <code>131..137</code> (pos <em>Pembelian aset</em>, sifat <strong>Aset (Neraca)</strong>, tanpa prefix) dan hutang vendor aset <code>221.*</code> (sifat <strong>Liabilitas (Neraca)</strong>, dengan prefix).</div>
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
                                            <div><strong>Metode Tidak Langsung:</strong> Persediaan farmasi <code>113.*</code> (sifat <strong>Aset (Neraca)</strong>) dan akrual gaji/jasa medis <code>213.*</code> (pos <em>Beban YMH dibayar</em>, sifat <strong>Liabilitas (Neraca)</strong>) dipetakan ke bagian <strong>Modal Kerja Operasi</strong> dengan Prefix Perubahan aktif.</div>
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
                                                    <strong>Metode Langsung:</strong> Transfer antar-rekening <strong>Kasbank</strong> memiliki dampak kas bersih bernilai nol (di bawah pembulatan Rp 0,01) sehingga diabaikan pemindai dan tidak muncul sebagai arus kas operasi/investasi/pendanaan; jurnal memorial non-kas juga diabaikan karena tidak melibatkan akun <strong>Kasbank</strong>.
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Metode Tidak Langsung:</strong> Transfer antar-<strong>Kasbank</strong> tidak mengubah total kas akhir riil, sedangkan jurnal memorial yang memutasi akun Neraca selain kas tetap dipantau pemindai Metode Tidak Langsung agar tidak menimbulkan selisih rekonsiliasi.
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
