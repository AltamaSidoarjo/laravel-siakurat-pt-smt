@php
    $activeMetode = ($activeMetode ?? 'langsung') === 'tidak_langsung' ? 'tidak_langsung' : 'langsung';
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
        const activeMetode = @json($activeMetode);
        const storageKeys = {
            langsung: 'siakurat_tour_arus_kas_langsung_seen',
            tidak_langsung_tab: 'siakurat_tour_arus_kas_tidak_langsung_tab_seen',
            tidak_langsung_struktur: 'siakurat_tour_arus_kas_tidak_langsung_struktur_seen',
        };

        function readSeen(key) {
            if (!key) {
                return false;
            }
            try {
                return window.localStorage.getItem(key) === '1';
            } catch (err) {
                return false;
            }
        }

        function markSeen(key) {
            if (!key) {
                return;
            }
            try {
                window.localStorage.setItem(key, '1');
            } catch (err) {
                // Ignore storage failures in private browsing
            }
        }

        function resolveCurrentScenario() {
            if (activeMetode === 'langsung') {
                return 'langsung';
            }

            const viewModeStrukturEl = document.getElementById('viewModeStruktur');
            if (viewModeStrukturEl && !viewModeStrukturEl.classList.contains('d-none')) {
                return 'tidak_langsung_struktur';
            }

            return 'tidak_langsung_tab';
        }

        function isElementVisible(selector) {
            const el = document.querySelector(selector);
            if (!el) {
                return false;
            }
            if (el.closest('.d-none')) {
                return false;
            }
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            return el.getClientRects().length > 0;
        }

        const scenariosConfig = {
            langsung: [
                {
                    element: '#tabLinkLangsung',
                    popover: {
                        title: 'Tab Metode Langsung',
                        description: 'Kelola master pos referensi 3 pilar aktivitas (Operasi, Investasi, Pendanaan) yang tersinkron langsung dengan kolom klasifikasi arus kas pada Master COA.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#bannerUnmappedLangsung',
                    popover: {
                        title: 'Deteksi Akun Lawan Kas/Bank',
                        description: 'Akun non-Kasbank yang bertransaksi dengan Kas/Bank pada tahun berjalan namun belum dipetakan muncul di sini. Klik <strong>Lihat Daftar Akun</strong> lalu gunakan tombol <strong>Petakan</strong> untuk aksi cepat.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#filterToolbarLangsung',
                    popover: {
                        title: 'Filter Aktivitas &amp; Pencarian COA',
                        description: 'Saring daftar berdasarkan aktivitas atau ketik nama pos maupun kode/nama akun COA daun (leaf) yang terpetakan.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#btnOpenCreateReferensi',
                    popover: {
                        title: 'Tambah Pos &amp; Ekspansi Induk',
                        description: 'Tambahkan pos baru sekaligus memetakan banyak akun leaf atau mengekspansi seluruh sub-akun dari akun induk COA secara cepat.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#tableReferensiContainer',
                    popover: {
                        title: 'Tabel Referensi &amp; Detail COA',
                        description: 'Klik pill jumlah akun untuk melihat rincian akun daun terpetakan serta catatan akun induk template Master COA.',
                        side: 'top',
                        align: 'center',
                    },
                },
                {
                    element: '#tabLinkTidakLangsung',
                    popover: {
                        title: 'Tab Metode Tidak Langsung',
                        description: 'Beralih ke tab sebelah untuk mengatur struktur pos laporan arus kas tidak langsung dan daftar akun pengecualian.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#btnLaporanArusKas',
                    popover: {
                        title: 'Buka Laporan Arus Kas',
                        description: 'Setelah pemetaan selesai, klik tombol ini untuk langsung memeriksa hasil Laporan Arus Kas Metode Langsung.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
            ],
            tidak_langsung_tab: [
                {
                    element: '#viewModeSwitcher',
                    popover: {
                        title: 'Mode Tab vs Mode Struktur',
                        description: 'Beralih antara tampilan tabel filter (<strong>Mode Tab</strong>) dan tampilan struktur laporan berurutan (<strong>Mode Struktur Laporan</strong>) kapan saja.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#bannerUnmappedTidakLangsung',
                    popover: {
                        title: 'Deteksi Akun Neraca Bermutasi',
                        description: 'Akun Neraca non-Kasbank yang memiliki mutasi tahun berjalan tetapi belum masuk pos aktif maupun pengecualian muncul di sini dengan pintasan <strong>Petakan</strong> atau <strong>Kecualikan</strong>.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#filterToolbarTidakLangsung',
                    popover: {
                        title: 'Filter Bagian &amp; Pencarian',
                        description: 'Saring pos per bagian laporan (Penyesuaian Laba, Modal Kerja, Investasi, Pendanaan), buka daftar <strong>Akun Dikecualikan</strong>, atau cari nama/kode pos dan COA.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '#btnOpenCreatePosTidakLangsung',
                    popover: {
                        title: 'Tambah Pos Arus Kas',
                        description: 'Buat pos baru, tentukan sifat pos (Aset, Liabilitas, Ekuitas, Penambah/Pengurang Laba), aktifkan Prefix Perubahan, dan petakan akun leaf COA.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#btnOpenCreatePengecualian',
                    popover: {
                        title: 'Tambah Akun Pengecualian',
                        description: 'Daftarkan akun Neraca kontra (seperti akumulasi penyusutan) agar tidak ikut dihitung dan tidak memicu peringatan belum dipetakan.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#tableTidakLangsungContainer',
                    popover: {
                        title: 'Tabel Pos &amp; Detail COA',
                        description: 'Pantau urutan, sifat pos, status aktif, serta klik pill jumlah akun COA untuk melihat rincian akun terpetakan atau kelola daftar pengecualian.',
                        side: 'top',
                        align: 'center',
                    },
                },
                {
                    element: '#btnLaporanArusKas',
                    popover: {
                        title: 'Laporan Tidak Langsung',
                        description: 'Buka Laporan Arus Kas Metode Tidak Langsung untuk mengecek nilai per bagian dan rekonsiliasi saldo kas akhir riil.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
            ],
            tidak_langsung_struktur: [
                {
                    element: '#viewModeSwitcher',
                    popover: {
                        title: 'Tampilan Struktur Laporan',
                        description: 'Mode ini menampilkan pos-pos sesuai susunan asli laporan arus kas tidak langsung dari Penyesuaian Laba hingga Pendanaan.',
                        side: 'bottom',
                        align: 'end',
                    },
                },
                {
                    element: '#subBarModeStruktur',
                    popover: {
                        title: 'Buka / Tutup Semua Bagian',
                        description: 'Gunakan kontrol ini untuk membuka atau meringkas seluruh kartu bagian laporan secara serentak.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '[data-section-bagian="penyesuaian_laba"]',
                    popover: {
                        title: 'Kartu Bagian &amp; Tambah Kontekstual',
                        description: 'Klik header kartu untuk buka/tutup daftar pos, atau tekan <strong>+ Tambah Pos</strong> untuk membuat pos yang otomatis terarah ke bagian tersebut.',
                        side: 'top',
                        align: 'center',
                    },
                },
                {
                    element: '#viewModeStruktur .btn-reorder-pos:not(:disabled)',
                    popover: {
                        title: 'Urutkan Posisi Pos (&uarr; / &darr;)',
                        description: 'Geser urutan pos naik atau turun di dalam bagiannya; perubahan langsung disimpan otomatis ke server tanpa muat ulang halaman.',
                        side: 'right',
                        align: 'center',
                    },
                },
                {
                    element: '[data-section-bagian="pengecualian"]',
                    popover: {
                        title: 'Daftar Akun Dikecualikan',
                        description: 'Bagian terbawah menampung akun Neraca kontra yang dikecualikan dari laporan dan dari banner peringatan belum dipetakan.',
                        side: 'top',
                        align: 'center',
                    },
                },
            ],
        };

        let activeTourDriver = null;

        function startArusKasTour(force = false) {
            if (typeof window.driver === 'undefined' || !window.driver.js || !window.driver.js.driver) {
                console.warn('Driver.js library not loaded yet.');
                return;
            }

            if (!force && document.querySelector('.modal.show') !== null) {
                return;
            }

            const scenario = resolveCurrentScenario();
            const rawSteps = scenariosConfig[scenario] || [];
            const validSteps = rawSteps.filter(function (step) {
                return isElementVisible(step.element);
            });

            if (!validSteps.length) {
                return;
            }

            if (activeTourDriver) {
                activeTourDriver.destroy();
                activeTourDriver = null;
            }

            const tourDriver = window.driver.js.driver({
                showProgress: true,
                animate: true,
                allowClose: true,
                doneBtnText: 'Selesai ✓',
                nextBtnText: 'Lanjut &rarr;',
                prevBtnText: '&larr; Kembali',
                progressText: 'Langkah @{{current}} dari @{{total}}',
                steps: validSteps,
                onDestroyStarted: function () {
                    markSeen(storageKeys[scenario]);
                    activeTourDriver = null;
                    tourDriver.destroy();
                },
            });

            activeTourDriver = tourDriver;
            tourDriver.drive();
        }

        window.initArusKasTour = startArusKasTour;

        document.addEventListener('DOMContentLoaded', function () {
            const btnStartTour = document.getElementById('btnStartTour');
            if (btnStartTour) {
                btnStartTour.addEventListener('click', function (e) {
                    e.preventDefault();
                    setTimeout(function () {
                        startArusKasTour(true);
                    }, 80);
                });
            }

            setTimeout(function () {
                if (document.querySelector('.modal.show') !== null) {
                    return;
                }
                const scenario = resolveCurrentScenario();
                if (!readSeen(storageKeys[scenario])) {
                    startArusKasTour(false);
                }
            }, 750);

            const btnModeStruktur = document.getElementById('btnModeStruktur');
            if (btnModeStruktur) {
                btnModeStruktur.addEventListener('click', function () {
                    setTimeout(function () {
                        if (
                            document.querySelector('.modal.show') === null
                            && resolveCurrentScenario() === 'tidak_langsung_struktur'
                            && !readSeen(storageKeys.tidak_langsung_struktur)
                        ) {
                            startArusKasTour(false);
                        }
                    }, 250);
                });
            }
        });
    })();
</script>
@endpush
