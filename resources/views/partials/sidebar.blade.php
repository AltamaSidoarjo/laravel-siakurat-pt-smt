@php
    $path = '/'.trim(request()->path(), '/');
    $path = $path === '/' ? '/' : rtrim($path, '/');
    $user = auth()->user();
    $can = fn (string $module, string $action = 'view') => $user?->hasModuleAccess($module, $action) ?? false;

    $canHome = $can('home');
    $canBukubesarJurnalUmum = $can('bukubesar.jurnal-umum');
    $canBukubesarCoa = $can('bukubesar.coa');
    $canKasbankPenerimaan = $can('kasbank.penerimaan');
    $canKasbankPembayaran = $can('kasbank.pembayaran');
    $canBridgingPendapatan = $can('bridging.pendapatan');
    $canBridgingPendapatanObat = $can('bridging.pendapatan-obat');
    $canBridgingPembelian = $can('bridging.pembelian');
    $canPendapatanInvoice = $can('pendapatan.invoice');
    $canPendapatanPenerimaan = $can('pendapatan.penerimaan');
    $canPembelianInvoice = $can('pembelian.invoice');
    $canPembelianPembayaran = $can('pembelian.pembayaran');
    $canLaporanKeuangan = $can('laporan.keuangan');
    $canLaporanPendapatan = $can('laporan.pendapatan');
    $canPengaturanMappingPendapatan = $can('pengaturan.mapping-pendapatan');
    $canPengaturanMappingGeneral = $can('pengaturan.mapping-general');
    $canPengaturanSettingRba = $can('pengaturan.setting-rba');
    $canPengaturanPreferensi = $can('pengaturan.preferensi');
    $canPengaturanPengguna = $can('pengaturan.pengguna');
    $canPengaturanRoleAkses = $can('pengaturan.role-akses');

    $canSeePengaturan = $canPengaturanMappingPendapatan
        || $canPengaturanMappingGeneral
        || $canPengaturanSettingRba
        || $canPengaturanPreferensi
        || $canPengaturanPengguna
        || $canPengaturanRoleAkses;

    $isHome = request()->routeIs('home');
@endphp

<div class="sidebar-brand">
    <img src="{{ asset('assets/logo-jrsma.png') }}" alt="Logo">
    <div class="sidebar-brand-text">
        <span class="sidebar-brand-name">{{ config('siakurat.app_name') }}</span>
        <span class="sidebar-brand-rs">{{ config('siakurat.rs_name') }}</span>
    </div>
</div>

<nav class="sidebar-menu flex-grow-1">
    <ul class="sidebar-nav list-unstyled">

        @if ($canHome)
        <li class="sidebar-item">
            <a class="sidebar-link {{ $isHome ? 'active' : '' }}" href="{{ route('home') }}">
                <i class="bi bi-house-door-fill sidebar-icon"></i>
                <span class="sidebar-label">Home</span>
            </a>
        </li>
        @endif

        @if ($canBukubesarJurnalUmum || $canBukubesarCoa)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/bukubesar') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-bukubesar" role="button"
               aria-expanded="{{ str_starts_with($path, '/bukubesar') ? 'true' : 'false' }}">
                <i class="bi bi-journal-text sidebar-icon"></i>
                <span class="sidebar-label">Bukubesar</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/bukubesar') ? 'show' : '' }}" id="menu-bukubesar">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canBukubesarJurnalUmum)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/bukubesar/jurnal-umum') ? 'active' : '' }}"
                           href="{{ route('bukubesar.jurnal-umum.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Jurnal Umum
                        </a>
                    </li>
                    @endif
                    @if ($canBukubesarCoa)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/bukubesar/coa') ? 'active' : '' }}"
                           href="{{ route('bukubesar.coa.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Coa
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canKasbankPenerimaan || $canKasbankPembayaran)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/kasbank') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-kasbank" role="button"
               aria-expanded="{{ str_starts_with($path, '/kasbank') ? 'true' : 'false' }}">
                <i class="bi bi-bank sidebar-icon"></i>
                <span class="sidebar-label">Kasbank</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/kasbank') ? 'show' : '' }}" id="menu-kasbank">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canKasbankPenerimaan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/kasbank/penerimaan') ? 'active' : '' }}"
                           href="{{ route('kasbank.penerimaan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Penerimaan
                        </a>
                    </li>
                    @endif
                    @if ($canKasbankPembayaran)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/kasbank/pembayaran') ? 'active' : '' }}"
                           href="{{ route('kasbank.pembayaran.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pembayaran
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canBridgingPendapatan || $canBridgingPendapatanObat || $canBridgingPembelian)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/bridging') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-bridging" role="button"
               aria-expanded="{{ str_starts_with($path, '/bridging') ? 'true' : 'false' }}">
                <i class="bi bi-arrow-left-right sidebar-icon"></i>
                <span class="sidebar-label">Bridging</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/bridging') ? 'show' : '' }}" id="menu-bridging">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canBridgingPendapatan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/bridging/pendapatan') && !str_starts_with($path, '/bridging/pendapatan-obat') ? 'active' : '' }}"
                           href="{{ route('bridging.pendapatan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pendapatan
                        </a>
                    </li>
                    @endif
                    @if ($canBridgingPendapatanObat)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/bridging/pendapatan-obat') ? 'active' : '' }}"
                           href="{{ route('bridging.pendapatan-obat.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pendapatan Obat
                        </a>
                    </li>
                    @endif
                    @if ($canBridgingPembelian)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/bridging/pembelian') ? 'active' : '' }}"
                           href="{{ route('bridging.pembelian.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pembelian
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canPendapatanInvoice || $canPendapatanPenerimaan)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/pendapatan') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-pendapatan" role="button"
               aria-expanded="{{ str_starts_with($path, '/pendapatan') ? 'true' : 'false' }}">
                <i class="bi bi-cash-stack sidebar-icon"></i>
                <span class="sidebar-label">Pendapatan</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/pendapatan') ? 'show' : '' }}" id="menu-pendapatan">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canPendapatanInvoice)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pendapatan/invoice') ? 'active' : '' }}"
                           href="{{ route('pendapatan.invoice.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Invoice Pendapatan
                        </a>
                    </li>
                    @endif
                    @if ($canPendapatanPenerimaan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pendapatan/penerimaan') ? 'active' : '' }}"
                           href="{{ route('pendapatan.penerimaan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Penerimaan Pendapatan
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canPembelianInvoice || $canPembelianPembayaran)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/pembelian') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-pembelian" role="button"
               aria-expanded="{{ str_starts_with($path, '/pembelian') ? 'true' : 'false' }}">
                <i class="bi bi-cart sidebar-icon"></i>
                <span class="sidebar-label">Pembelian</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/pembelian') ? 'show' : '' }}" id="menu-pembelian">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canPembelianInvoice)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pembelian/invoice') ? 'active' : '' }}"
                           href="{{ route('pembelian.invoice.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Invoice Pembelian
                        </a>
                    </li>
                    @endif
                    @if ($canPembelianPembayaran)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pembelian/pembayaran') ? 'active' : '' }}"
                           href="{{ route('pembelian.pembayaran.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pembayaran Pembelian
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canLaporanKeuangan || $canLaporanPendapatan)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/laporan') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-laporan" role="button"
               aria-expanded="{{ str_starts_with($path, '/laporan') ? 'true' : 'false' }}">
                <i class="bi bi-bar-chart-line sidebar-icon"></i>
                <span class="sidebar-label">Laporan</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/laporan') ? 'show' : '' }}" id="menu-laporan">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canLaporanKeuangan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/laporan/keuangan') ? 'active' : '' }}"
                           href="{{ route('laporan.keuangan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Laporan Keuangan
                        </a>
                    </li>
                    @endif
                    @if ($canLaporanPendapatan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/laporan/pendapatan') ? 'active' : '' }}"
                           href="{{ route('laporan.pendapatan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Laporan Pendapatan
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        @if ($canSeePengaturan)
        <li class="sidebar-item">
            <a class="sidebar-link sidebar-toggle {{ str_starts_with($path, '/pengaturan') ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#menu-pengaturan" role="button"
               aria-expanded="{{ str_starts_with($path, '/pengaturan') ? 'true' : 'false' }}">
                <i class="bi bi-gear-fill sidebar-icon"></i>
                <span class="sidebar-label">Pengaturan</span>
                <i class="bi bi-chevron-down sidebar-arrow ms-auto"></i>
            </a>
            <div class="collapse {{ str_starts_with($path, '/pengaturan') ? 'show' : '' }}" id="menu-pengaturan">
                <ul class="sidebar-submenu list-unstyled">
                    @if ($canPengaturanMappingPendapatan)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/mapping-pendapatan') ? 'active' : '' }}"
                           href="{{ route('pengaturan.mapping-pendapatan.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Mapping Pendapatan
                        </a>
                    </li>
                    @endif
                    @if ($canPengaturanMappingGeneral)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/mapping-general') ? 'active' : '' }}"
                           href="{{ route('pengaturan.mapping-general.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Mapping General
                        </a>
                    </li>
                    @endif
                    @if ($canPengaturanSettingRba)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/setting-rba') ? 'active' : '' }}"
                           href="{{ route('pengaturan.setting-rba.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Setting RBA
                        </a>
                    </li>
                    @endif
                    @if ($canPengaturanPreferensi)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/preferensi') ? 'active' : '' }}"
                           href="{{ route('pengaturan.preferensi.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Preferensi
                        </a>
                    </li>
                    @endif
                    @if ($canPengaturanPengguna)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/pengguna') ? 'active' : '' }}"
                           href="{{ route('pengaturan.pengguna.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Pengguna
                        </a>
                    </li>
                    @endif
                    @if ($canPengaturanRoleAkses)
                    <li>
                        <a class="sidebar-sublink {{ str_starts_with($path, '/pengaturan/role-akses') ? 'active' : '' }}"
                           href="{{ route('pengaturan.role-akses.index') }}">
                            <i class="bi bi-circle-fill sidebar-bullet"></i> Role Akses
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

    </ul>
</nav>

<div class="sidebar-footer">
    <button id="sidebar-toggle-btn" class="sidebar-footer-btn" title="Toggle Sidebar">
        <i class="bi bi-layout-sidebar-reverse"></i>
        <span class="sidebar-label ms-2">Tutup Menu</span>
    </button>
    <form action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit" class="sidebar-footer-btn text-warning">
            <i class="bi bi-box-arrow-right"></i>
            <span class="sidebar-label ms-2">Logout</span>
        </button>
    </form>
</div>
