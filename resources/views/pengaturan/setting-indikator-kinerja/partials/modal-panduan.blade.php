{{-- Modal Pusat Bantuan & Glosarium Setting Indikator Kinerja --}}
<div class="modal fade" id="modalPanduanSetting" tabindex="-1" aria-labelledby="modalPanduanSettingLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            {{-- Modal Header --}}
            <div class="modal-header border-bottom py-3 px-4" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2.5 rounded-3 bg-white text-success shadow-xs d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-book-half fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalPanduanSettingLabel">
                            Panduan Konfigurasi Indikator Kinerja &amp; Glosarium COA
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">
                            Panduan interaktif logika kalkulasi, matriks input form, pemetaan akun COA, dan ambang batas RAG.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            {{-- Nav Tabs --}}
            <div class="px-4 pt-3 pb-2 bg-light border-bottom">
                <ul class="nav nav-pills gap-2" id="pillsTabPanduan" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill" 
                                id="pills-formula-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#pills-formula" 
                                type="button" 
                                role="tab" 
                                aria-controls="pills-formula" 
                                aria-selected="true">
                            <i class="bi bi-calculator"></i>
                            <span>Logika Formula &amp; Input</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill" 
                                id="pills-glosarium-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#pills-glosarium" 
                                type="button" 
                                role="tab" 
                                aria-controls="pills-glosarium" 
                                aria-selected="false">
                            <i class="bi bi-diagram-3-fill"></i>
                            <span>Glosarium COA &amp; 4 Kasus RS</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3 rounded-pill" 
                                id="pills-rag-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#pills-rag" 
                                type="button" 
                                role="tab" 
                                aria-controls="pills-rag" 
                                aria-selected="false">
                            <i class="bi bi-speedometer2"></i>
                            <span>Target Ambang Batas (RAG)</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4">
                <div class="tab-content" id="pillsTabPanduanContent">

                    {{-- TAB 1: LOGIKA FORMULA & MATRIKS INPUT --}}
                    <div class="tab-pane fade show active" id="pills-formula" role="tabpanel" aria-labelledby="pills-formula-tab" tabindex="0">
                        {{-- 1. Diagram Alur Keputusan Tipe Kalkulasi --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                Diagram Alur Pemilihan Tipe Kalkulasi
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card h-100 border border-primary-subtle bg-primary-subtle bg-opacity-10 shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge bg-primary px-2.5 py-1 fw-bold">RASIO</span>
                                                <i class="bi bi-percent fs-5 text-primary"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Perbandingan Nilai</h6>
                                            <p class="small text-muted mb-2">
                                                Membandingkan dua besaran angka: <code>(Pembilang ÷ Penyebut) × 100</code> (jika satuan <code>%</code>).
                                            </p>
                                            <div class="small bg-white p-2 rounded border border-light text-secondary">
                                                <strong>Kapan digunakan:</strong> Rasio Likuiditas, Margin Surplus, Rasio Beban terhadap Pendapatan, dsb.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100 border border-warning-subtle bg-warning-subtle bg-opacity-10 shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge bg-warning text-dark px-2.5 py-1 fw-bold">TURNOVER</span>
                                                <i class="bi bi-arrow-repeat fs-5 text-warning"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Kecepatan Putaran (Hari)</h6>
                                            <p class="small text-muted mb-2">
                                                Menghitung efisiensi aset: <code>(Saldo Neraca ÷ Beban/Pendapatan) × 365</code>.
                                            </p>
                                            <div class="small bg-white p-2 rounded border border-light text-secondary">
                                                <strong>Kapan digunakan:</strong> Perputaran Persediaan Obat (ITO), Collection Period Piutang, dsb.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card h-100 border border-secondary-subtle bg-secondary-subtle bg-opacity-10 shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge bg-secondary px-2.5 py-1 fw-bold">MANUAL</span>
                                                <i class="bi bi-pencil-square fs-5 text-secondary"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Input Data Operasional</h6>
                                            <p class="small text-muted mb-2">
                                                Indikator non-akuntansi tanpa perhitungan COA. Data diisi langsung setiap bulan di Tab 2.
                                            </p>
                                            <div class="small bg-white p-2 rounded border border-light text-secondary">
                                                <strong>Kapan digunakan:</strong> Verifikasi BAHV BPJS Pertama, BOR, BTO, Kepuasan Pasien, dsb.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Matriks Perilaku Input Form --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                Matriks Perilaku Input Formulir
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="width: 15%;">Tipe Kalkulasi</th>
                                            <th style="width: 20%;">Basis Pembilang</th>
                                            <th style="width: 25%;">Basis Penyebut</th>
                                            <th style="width: 20%;">Variabel Manual RS</th>
                                            <th style="width: 20%;">Tabel Pemetaan COA</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr>
                                            <td class="text-center fw-bold text-primary">RASIO</td>
                                            <td><code>COA</code> atau <code>Surplus Defisit</code></td>
                                            <td>
                                                <code>COA</code>, <code>RBA</code>, atau <code>Variabel Manual</code>
                                            </td>
                                            <td>
                                                Wajib dipilih <strong>hanya jika</strong> Basis Penyebut memilih <em>Variabel Manual</em>
                                            </td>
                                            <td class="text-success fw-semibold">
                                                <i class="bi bi-check-circle-fill text-success me-1"></i> Aktif (Numerator / Denominator)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center fw-bold text-warning">TURNOVER</td>
                                            <td>
                                                Terkunci <code>COA</code> (Saldo Kumulatif Neraca)
                                            </td>
                                            <td>
                                                Terkunci <code>COA</code> (Mutasi Laba Rugi)
                                            </td>
                                            <td class="text-muted">
                                                <i class="bi bi-dash-circle text-muted me-1"></i> Tidak Digunakan
                                            </td>
                                            <td class="text-success fw-semibold">
                                                <i class="bi bi-check-circle-fill text-success me-1"></i> Aktif (Numerator / Denominator)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center fw-bold text-secondary">MANUAL</td>
                                            <td class="text-muted" colspan="2">
                                                <i class="bi bi-eye-slash text-muted me-1"></i> Otomatis Disembunyikan oleh Sistem
                                            </td>
                                            <td class="text-danger fw-semibold">
                                                <i class="bi bi-exclamation-circle-fill text-danger me-1"></i> Wajib Dipilih (Sumber Data)
                                            </td>
                                            <td class="text-muted">
                                                <i class="bi bi-eye-slash text-muted me-1"></i> Otomatis Disembunyikan
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- 3. Dampak Satuan Nilai --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">3</span>
                                Penjelasan Dampak Satuan Nilai
                            </h6>
                            <div class="row g-2 small">
                                <div class="col-md-3">
                                    <div class="border rounded p-2.5 bg-light h-100">
                                        <div class="fw-bold text-primary mb-1">Satuan Persentase (<code>%</code>)</div>
                                        <p class="text-muted mb-0">Sistem otomatis mengalikan hasil pembagian dengan 100.</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2.5 bg-light h-100">
                                        <div class="fw-bold text-warning mb-1">Satuan Waktu (<code>Hari</code> / <code>Jam</code>)</div>
                                        <p class="text-muted mb-0">Menyatakan lama siklus (misal turnover persediaan atau waktu tunggu).</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2.5 bg-light h-100">
                                        <div class="fw-bold text-success mb-1">Satuan Frekuensi (<code>Kali</code>)</div>
                                        <p class="text-muted mb-0">Menyatakan intensitas atau rasio perputaran modal/aktiva.</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2.5 bg-light h-100">
                                        <div class="fw-bold text-info mb-1">Satuan Khusus (<code>Juta/org/bln</code>)</div>
                                        <p class="text-muted mb-0">Digunakan untuk produktivitas per kapita staf/tenaga medis.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: GLOSARIUM COA & 4 KASUS RS --}}
                    <div class="tab-pane fade" id="pills-glosarium" role="tabpanel" aria-labelledby="pills-glosarium-tab" tabindex="0">
                        {{-- 1. Kamus Peran Akun --}}
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                Kamus Peran Akun Pemetaan COA
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="border border-primary-subtle rounded p-3 bg-white h-100">
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="badge bg-primary">Numerator</span>
                                            <span class="fw-bold text-dark small">Pembilang</span>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Akun utama di posisi atas pecahan ($A$ dalam $\frac{A}{B}$). Nilai saldo akun ini menjadi dasar utama perhitungan.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-warning-subtle rounded p-3 bg-white h-100">
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="badge bg-warning text-dark">Denominator</span>
                                            <span class="fw-bold text-dark small">Penyebut</span>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Akun pembanding di posisi bawah pecahan ($B$ dalam $\frac{A}{B}$). Menjadi angka pembagi nilai pembilang.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border border-success-subtle rounded p-3 bg-white h-100">
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="badge bg-success">Addition</span>
                                            <span class="fw-bold text-dark small">Penambah (+)</span>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Akun yang nilainya ditambahkan ke pembilang ($+$). Sangat lazim untuk <em>add-back</em> beban non-kas (seperti depresiasi).
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <div class="border border-danger-subtle rounded p-3 bg-white h-100">
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="badge bg-danger">Deduction</span>
                                            <span class="fw-bold text-dark small">Pengurang (-)</span>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Akun yang nilainya dikurangkan dari pembilang ($-$). Digunakan untuk mengeliminasi pos non-operasional dari hasil kalkulasi.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <div class="border border-secondary-subtle rounded p-3 bg-white h-100">
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="badge bg-secondary">Exclusion</span>
                                            <span class="fw-bold text-dark small">Pengecualian Sub-Akun</span>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Kotak centang untuk mengecualikan sub-akun spesifik agar saldonya tidak ikut dijumlahkan dari akun induk yang dipetakan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Callout Neraca vs Laba Rugi --}}
                        <div class="alert alert-info border-info-subtle bg-info-subtle bg-opacity-25 d-flex align-items-start gap-3 p-3 rounded-3 mb-4">
                            <i class="bi bi-info-circle-fill text-info fs-5 flex-shrink-0 mt-0.5"></i>
                            <div class="small text-dark">
                                <strong>Kaidah Pemrosesan Saldo Akun di SiAkurat:</strong><br>
                                • <strong>Akun Neraca (Aset & Kewajiban)</strong>: Sistem otomatis mengambil <em>Saldo Kumulatif Akhir Periode</em>.<br>
                                • <strong>Akun Laba Rugi (Pendapatan & Beban)</strong>: Sistem otomatis mengambil <em>Mutasi Berjalan Periode Terkait</em>.
                            </div>
                        </div>

                        {{-- 3. 4 Studi Kasus Nyata Rumah Sakit --}}
                        <div>
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">2</span>
                                4 Studi Kasus Nyata Rumah Sakit
                            </h6>
                            <div class="row g-3">
                                {{-- Kasus 1: Rasio Standar --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 1: Rasio Beban Pegawai</span>
                                            <span class="badge bg-primary">Rasio Standar</span>
                                        </div>
                                        <div class="card-body p-3 small">
                                            <div class="mb-1"><strong>Formula:</strong> <code>(Beban Pegawai ÷ Pendapatan Operasional) × 100%</code></div>
                                            <div class="mb-1"><strong>Tipe Kalkulasi:</strong> <code>RASIO</code> | <strong>Satuan:</strong> <code>%</code></div>
                                            <div class="mb-1"><strong>Pembilang (Numerator):</strong> Akun Beban Pegawai (5.1.01.xx)</div>
                                            <div class="mb-1"><strong>Penyebut (Denominator):</strong> Akun Pendapatan Pelayanan (4.1.01.xx)</div>
                                            <div><strong>Pola Target:</strong> <code>Target Band</code> (Optimal 20% - 25%)</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 2: EBITDA Add-Back --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 2: Margin EBITDA RS</span>
                                            <span class="badge bg-success">Add-Back (+)</span>
                                        </div>
                                        <div class="card-body p-3 small">
                                            <div class="mb-1"><strong>Formula:</strong> <code>(Surplus + Beban Bunga + Penyusutan) ÷ Pendapatan</code></div>
                                            <div class="mb-1"><strong>Tipe Kalkulasi:</strong> <code>RASIO</code> | <strong>Satuan:</strong> <code>%</code></div>
                                            <div class="mb-1"><strong>Basis Pembilang:</strong> <code>Surplus Defisit</code></div>
                                            <div class="mb-1"><strong>Akun Addition (+):</strong> Beban Bunga & Beban Penyusutan</div>
                                            <div><strong>Pola Target:</strong> <code>Higher is Better</code> (Target Min: 15%)</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 3: Turnover + Exclusion --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 3: Perputaran Persediaan Farmasi</span>
                                            <span class="badge bg-warning text-dark">Turnover + Exclusion</span>
                                        </div>
                                        <div class="card-body p-3 small">
                                            <div class="mb-1"><strong>Formula:</strong> <code>(Persediaan Farmasi ÷ Beban Obat) × 365 Hari</code></div>
                                            <div class="mb-1"><strong>Tipe Kalkulasi:</strong> <code>TURNOVER</code> | <strong>Satuan:</strong> <code>Hari</code></div>
                                            <div class="mb-1"><strong>Pembilang (Neraca):</strong> Akun Persediaan Obat (1.1.05.xx)</div>
                                            <div class="mb-1"><strong>Pengecualian (Exclusion):</strong> Centang sub-akun <em>Obat Program Hibah</em></div>
                                            <div><strong>Pola Target:</strong> <code>Lower is Better</code> (Target Maks: 45 Hari)</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kasus 4: Produktivitas SDM Hibrida --}}
                                <div class="col-md-6">
                                    <div class="card border border-light-subtle shadow-xs h-100">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small">Kasus 4: Produktivitas SDM Medis</span>
                                            <span class="badge bg-secondary">Hibrida (Akuntansi ÷ Non-Keuangan)</span>
                                        </div>
                                        <div class="card-body p-3 small">
                                            <div class="mb-1"><strong>Formula:</strong> <code>Pendapatan Pelayanan ÷ Total Tenaga Medis</code></div>
                                            <div class="mb-1"><strong>Tipe Kalkulasi:</strong> <code>RASIO</code> | <strong>Satuan:</strong> <code>Juta/org/bln</code></div>
                                            <div class="mb-1"><strong>Basis Pembilang:</strong> <code>COA</code> (Pendapatan RS)</div>
                                            <div class="mb-1"><strong>Basis Penyebut:</strong> <code>Variabel Manual</code> (Jumlah Staf Medis)</div>
                                            <div><strong>Pola Target:</strong> <code>Higher is Better</code> (Target Min: 18 Juta/org/bln)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 3: TARGET AMBANG BATAS (RAG) --}}
                    <div class="tab-pane fade" id="pills-rag" role="tabpanel" aria-labelledby="pills-rag-tab" tabindex="0">
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-success rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">1</span>
                                3 Pola Evaluasi Ambang Batas Target (RAG)
                            </h6>
                            <p class="small text-muted mb-4">
                                Warna pada kartu KPI dan RAG Bar di dashboard eksekutif otomatis ditentukan berdasarkan capaian aktual terhadap ambang batas berikut:
                            </p>

                            <div class="row g-3">
                                {{-- Pola 1: Higher is Better --}}
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-xs h-100" style="background-color: #f8fafc; border-left: 4px solid #10b981 !important;">
                                        <div class="card-body p-3">
                                            <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-1.5">
                                                <i class="bi bi-graph-up-arrow text-success"></i>
                                                Higher is Better
                                            </h6>
                                            <small class="text-muted d-block mb-3">Semakin tinggi nilai capaian, semakin baik kinerja rumah sakit.</small>
                                            <div class="d-flex flex-column gap-2 small">
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-success-subtle border border-success-subtle">
                                                    <span class="fw-bold text-success">🟢 Hijau (Optimal)</span>
                                                    <span>Nilai $\ge$ Target Min</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-warning-subtle border border-warning-subtle">
                                                    <span class="fw-bold text-warning-emphasis">🟡 Kuning (Waspada)</span>
                                                    <span>Ambang Kuning s/d Target Min</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-danger-subtle border border-danger-subtle">
                                                    <span class="fw-bold text-danger">🔴 Merah (Kritis)</span>
                                                    <span>Nilai $<$ Ambang Kuning</span>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-3">
                                                <em>Contoh:</em> Capaian Pendapatan, Likuiditas, Margin Surplus RS.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Pola 2: Lower is Better --}}
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-xs h-100" style="background-color: #f8fafc; border-left: 4px solid #f59e0b !important;">
                                        <div class="card-body p-3">
                                            <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-1.5">
                                                <i class="bi bi-graph-down-arrow text-warning"></i>
                                                Lower is Better
                                            </h6>
                                            <small class="text-muted d-block mb-3">Semakin rendah nilai aktual, semakin efisien kinerja operasional.</small>
                                            <div class="d-flex flex-column gap-2 small">
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-success-subtle border border-success-subtle">
                                                    <span class="fw-bold text-success">🟢 Hijau (Optimal)</span>
                                                    <span>Nilai $\le$ Target Maks</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-warning-subtle border border-warning-subtle">
                                                    <span class="fw-bold text-warning-emphasis">🟡 Kuning (Waspada)</span>
                                                    <span>Target Maks s/d Ambang Merah</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-danger-subtle border border-danger-subtle">
                                                    <span class="fw-bold text-danger">🔴 Merah (Kritis)</span>
                                                    <span>Nilai $>$ Ambang Merah</span>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-3">
                                                <em>Contoh:</em> Rasio Beban Operasional, Collection Period Piutang, ITO Hari.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Pola 3: Target Band --}}
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-xs h-100" style="background-color: #f8fafc; border-left: 4px solid #3b82f6 !important;">
                                        <div class="card-body p-3">
                                            <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-1.5">
                                                <i class="bi bi-arrows-collapse text-primary"></i>
                                                Target Band
                                            </h6>
                                            <small class="text-muted d-block mb-3">Nilai optimal harus berada di antara batas minimum dan batas maksimum.</small>
                                            <div class="d-flex flex-column gap-2 small">
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-success-subtle border border-success-subtle">
                                                    <span class="fw-bold text-success">🟢 Hijau (Optimal)</span>
                                                    <span>Target Min $\le$ Nilai $\le$ Target Maks</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-warning-subtle border border-warning-subtle">
                                                    <span class="fw-bold text-warning-emphasis">🟡 Kuning (Waspada)</span>
                                                    <span>Sedikit di Luar Rentang Target</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-danger-subtle border border-danger-subtle">
                                                    <span class="fw-bold text-danger">🔴 Merah (Kritis)</span>
                                                    <span>Terlalu Jauh di Luar Toleransi</span>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-3">
                                                <em>Contoh:</em> Rasio Beban Pegawai (proporsi seimbang antara efisiensi & mutu).
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
