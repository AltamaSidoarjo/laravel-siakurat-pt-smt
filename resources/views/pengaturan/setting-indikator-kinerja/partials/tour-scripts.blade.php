@php
    $scenario = $scenario ?? 'tab1';
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
<style>
    /* Custom Styling Driver.js Tema Hijau SiAkurat */
    .driver-popover {
        border-radius: 0.75rem !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.16), 0 8px 12px -6px rgba(15, 23, 42, 0.08) !important;
        font-family: inherit !important;
        padding: 1.15rem 1.25rem !important;
        max-width: 380px !important;
    }
    .driver-popover-title {
        font-size: 1rem !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        margin-bottom: 0.5rem !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }
    .driver-popover-description {
        font-size: 0.845rem !important;
        line-height: 1.5 !important;
        color: #334155 !important;
        margin-bottom: 0.85rem !important;
    }
    .driver-popover-description strong {
        color: #0f172a !important;
    }
    .driver-popover-footer {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin-top: 0.75rem !important;
        padding-top: 0.75rem !important;
        border-top: 1px solid #f1f5f9 !important;
        gap: 0.5rem !important;
    }
    .driver-popover-progress-text {
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        color: #64748b !important;
    }
    .driver-popover-navigation-btns {
        display: flex !important;
        align-items: center !important;
        gap: 0.375rem !important;
    }
    .driver-popover-prev-btn {
        background-color: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.375rem !important;
        font-size: 0.785rem !important;
        font-weight: 600 !important;
        padding: 0.35rem 0.65rem !important;
        transition: all 0.15s ease !important;
        text-shadow: none !important;
    }
    .driver-popover-prev-btn:hover {
        background-color: #f1f5f9 !important;
        color: #1e293b !important;
    }
    .driver-popover-next-btn {
        background-color: #027e3f !important;
        color: #ffffff !important;
        border: 1px solid #027e3f !important;
        border-radius: 0.375rem !important;
        font-size: 0.785rem !important;
        font-weight: 600 !important;
        padding: 0.35rem 0.75rem !important;
        transition: all 0.15s ease !important;
        text-shadow: none !important;
    }
    .driver-popover-next-btn:hover {
        background-color: #166534 !important;
        border-color: #166534 !important;
    }
    .driver-popover-close-btn {
        color: #94a3b8 !important;
        transition: color 0.15s ease !important;
    }
    .driver-popover-close-btn:hover {
        color: #475569 !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>
<script>
    (function () {
        const currentScenario = @json($scenario);
        const storageKey = 'siakurat_tour_setting_{{ $scenario }}_seen';

        const scenariosConfig = {
            tab1: [
                {
                    element: '#executiveStrip',
                    popover: {
                        title: '📊 Ringkasan Master Indikator',
                        description: 'Pantau total metrik KPI yang terdaftar, jumlah indikator aktif di dashboard eksekutif, indikator nonaktif/diarsipkan, serta variabel non-akuntansi.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#filterToolbarTab1',
                    popover: {
                        title: '🔍 Pencarian & Filter Indikator',
                        description: 'Cari indikator secara cepat berdasarkan kode unik, nama, atau formula perhitungan akuntansi.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#btnTambahIndikator',
                    popover: {
                        title: '➕ Tambah Indikator Baru',
                        description: 'Klik tombol ini untuk mengonfigurasi indikator kinerja baru beserta formula kalkulasi dan ambang batas targetnya.',
                        side: 'bottom',
                        align: 'end'
                    }
                },
                {
                    element: '#tableMasterIndikatorContainer',
                    popover: {
                        title: '📋 Matriks Indikator & Target RAG',
                        description: 'Tabel ini menampilkan daftar master KPI, visualisasi ambang batas target berwarna (RAG), status publikasi, dan popover detail pemetaan akun COA.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#tabLinkManual',
                    popover: {
                        title: '📅 Entri Data Manual Bulanan',
                        description: 'Beralih ke Tab 2 kapan saja untuk menginput atau memperbarui angka bulanan non-akuntansi seperti BOR, BAHV BPJS, atau jumlah SDM.',
                        side: 'bottom',
                        align: 'start'
                    }
                }
            ],
            tab2: [
                {
                    element: '#filterTahunManual',
                    popover: {
                        title: '📆 Tahun Anggaran & Master Variabel',
                        description: 'Pilih tahun anggaran untuk memuat data bulanan, atau klik <strong>+ Tambah Variabel Baru</strong> untuk membuat metrik operasional baru.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#matrixScrollContainer',
                    popover: {
                        title: '🪟 Viewport Matriks Resizable',
                        description: 'Tabel spreadsheet ini membekukan kolom No, Kode, dan Nama Variabel serta baris header bulan agar entri data tetap nyaman. Tarik handle bar di bawah untuk memperbesar area lihat.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '.matrix-cell-input',
                    popover: {
                        title: '⌨️ Navigasi Sel Cepat Ala Excel',
                        description: 'Ketik angka langsung pada sel bulan. Gunakan tombol panah keyboard (⬆️ ⬇️ ⬅️ ➡️) atau Enter untuk berpindah sel secara cepat tanpa menyentuh mouse.',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: '.col-avg-header, .matrix-table td:nth-last-child(2)',
                    popover: {
                        title: '⚡ Rata-Rata Otomatis Tahunan',
                        description: 'Sistem menghitung rata-rata tahunan secara live saat Anda mengetikkan nilai tanpa perlu memuat ulang halaman.',
                        side: 'left',
                        align: 'center'
                    }
                },
                {
                    element: '#btnSimpanManualData',
                    popover: {
                        title: '💾 Simpan Data Bulanan',
                        description: 'Setelah menyelesaikan pengisian angka bulanan, klik tombol ini untuk menyimpan seluruh entri ke database.',
                        side: 'top',
                        align: 'end'
                    }
                }
            ],
            form: [
                {
                    element: '#stepperNavigation',
                    popover: {
                        title: '🪜 Navigasi Langkah Konfigurasi',
                        description: 'Gunakan tahapan ini untuk melompat antar bagian: Identitas & Formula, Ambang Batas Target RAG, dan Pemetaan Akun COA.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#sectionIdentitas',
                    popover: {
                        title: '🏷️ Identitas Dasar & Tipe Kalkulasi',
                        description: 'Tentukan nama, kode unik, satuan nilai (%/Hari/Kali/dll.), serta tipe kalkulasi (Rasio, Turnover, atau Manual). Sistem akan otomatis menyesuaikan opsi basis pembilang & penyebut.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#sectionTargetRag',
                    popover: {
                        title: '🎯 Ambang Batas Target (RAG)',
                        description: 'Pilih pola evaluasi (Higher is Better, Lower is Better, Target Band), lalu isi nilai batas Hijau (Baik), Kuning (Waspada), dan Merah (Kritis).',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#sectionMappingCoa',
                    popover: {
                        title: '📑 Pemetaan Akun COA',
                        description: 'Petakan akun pembukuan ke peran Pembilang, Penyebut, Penambah (+), atau Pengurang (-). Aktifkan switch Pengecualian bila akun anak ingin dikecualikan dari saldo.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#stickyLivePreview',
                    popover: {
                        title: '👁️ Live Preview Kartu KPI',
                        description: 'Panel samping ini bereaksi secara langsung memperlihatkan kartu metrik yang nantinya ditampilkan kepada direksi di dashboard eksekutif.',
                        side: 'left',
                        align: 'start'
                    }
                },
                {
                    element: '.btn-trigger-submit',
                    popover: {
                        title: '✅ Simpan Konfigurasi',
                        description: 'Klik tombol Simpan untuk memvalidasi dan menyimpan konfigurasi master indikator kinerja.',
                        side: 'bottom',
                        align: 'end'
                    }
                }
            ]
        };

        function startSettingIndikatorTour(force = false) {
            if (typeof window.driver === 'undefined' || !window.driver.js || !window.driver.js.driver) {
                console.warn('Driver.js library not loaded yet.');
                return;
            }

            const driver = window.driver.js.driver;
            const rawSteps = scenariosConfig[currentScenario] || [];
            
            // Filter out steps whose element doesn't exist in DOM
            const validSteps = rawSteps.filter(function (step) {
                return document.querySelector(step.element) !== null;
            });

            if (!validSteps.length) {
                return;
            }

            const tourDriver = driver({
                showProgress: true,
                animate: true,
                allowClose: true,
                doneBtnText: 'Selesai ✓',
                nextBtnText: 'Lanjut &rarr;',
                prevBtnText: '&larr; Kembali',
                progressText: 'Langkah @{{current}} dari @{{total}}',
                steps: validSteps,
                onDestroyStarted: function () {
                    localStorage.setItem(storageKey, '1');
                    tourDriver.destroy();
                }
            });

            tourDriver.drive();
        }

        window.initSettingIndikatorTour = startSettingIndikatorTour;

        document.addEventListener('DOMContentLoaded', function () {
            // Bind header button trigger
            const btnStartTour = document.getElementById('btnStartTour');
            if (btnStartTour) {
                btnStartTour.addEventListener('click', function (e) {
                    e.preventDefault();
                    startSettingIndikatorTour(true);
                });
            }

            // Auto-trigger on first visit (after smooth render)
            if (!localStorage.getItem(storageKey)) {
                setTimeout(function () {
                    startSettingIndikatorTour(false);
                }, 750);
            }
        });
    })();
</script>
@endpush
