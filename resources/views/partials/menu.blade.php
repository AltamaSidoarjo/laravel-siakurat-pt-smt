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
    $canPengaturanKonversiFile = $can('pengaturan.konversi-file');

    $canSeeKeuangan = $canBukubesarJurnalUmum
        || $canBukubesarCoa
        || $canKasbankPenerimaan
        || $canKasbankPembayaran
        || $canBridgingPendapatan
        || $canBridgingPendapatanObat
        || $canBridgingPembelian
        || $canPendapatanInvoice
        || $canPendapatanPenerimaan
        || $canPembelianInvoice
        || $canPembelianPembayaran
        || $canLaporanKeuangan
        || $canLaporanPendapatan;

    $canSeePengaturan = $canPengaturanMappingPendapatan
        || $canPengaturanMappingGeneral
        || $canPengaturanSettingRba
        || $canPengaturanPreferensi
        || $canPengaturanPengguna
        || $canPengaturanRoleAkses
        || $canPengaturanKonversiFile;

    $isHome = request()->routeIs('home');
    $isKeuanganActive = $canSeeKeuangan && (
        str_starts_with($path, '/bukubesar')
        || str_starts_with($path, '/kasbank')
        || str_starts_with($path, '/pendapatan')
        || str_starts_with($path, '/pembelian')
        || str_starts_with($path, '/laporan')
        || str_starts_with($path, '/bridging')
    );
    $isPengaturanActive = $canSeePengaturan && str_starts_with($path, '/pengaturan');
@endphp

@if ($canHome)
    <li class="nav-item mb-2 mb-lg-0 me-0 me-lg-2 rounded">
        <a class="nav-link fw-bold px-4 {{ $isHome ? 'active bg-success-subtle text-success' : 'text-white' }}" href="{{ route('home') }}">
            <i class="bi bi-house-door-fill me-1"></i> Home
        </a>
    </li>
@endif

@if ($canSeeKeuangan)
    <li class="nav-item mb-2 mb-lg-0 me-0 me-lg-2 dropdown rounded">
        <a class="nav-link fw-bold dropdown-toggle px-4 {{ $isKeuanganActive ? 'active bg-success-subtle text-success' : 'text-white' }}"
            href="#"
            id="keuanganDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-cash me-1"></i> Keuangan
        </a>
        <ul class="dropdown-menu" aria-labelledby="keuanganDropdown">
            @if ($canBukubesarJurnalUmum || $canBukubesarCoa)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/bukubesar') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Bukubesar</a>
                    <ul class="dropdown-menu">
                        @if ($canBukubesarJurnalUmum)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/bukubesar/jurnal-umum') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('bukubesar.jurnal-umum.index') }}">
                                    Jurnal Umum
                                </a>
                            </li>
                        @endif
                        @if ($canBukubesarJurnalUmum && $canBukubesarCoa)
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canBukubesarCoa)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/bukubesar/coa') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('bukubesar.coa.index') }}">
                                    Coa
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif
            @if (($canBukubesarJurnalUmum || $canBukubesarCoa) && ($canKasbankPenerimaan || $canKasbankPembayaran || $canBridgingPendapatan || $canBridgingPendapatanObat || $canBridgingPembelian || $canPendapatanInvoice || $canPendapatanPenerimaan || $canPembelianInvoice || $canPembelianPembayaran || $canLaporanKeuangan || $canLaporanPendapatan))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canKasbankPenerimaan || $canKasbankPembayaran)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/kasbank') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Kasbank</a>
                    <ul class="dropdown-menu">
                        @if ($canKasbankPenerimaan)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/kasbank/penerimaan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('kasbank.penerimaan.index') }}">
                                    Penerimaan
                                </a>
                            </li>
                        @endif
                        @if ($canKasbankPenerimaan && $canKasbankPembayaran)
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canKasbankPembayaran)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/kasbank/pembayaran') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('kasbank.pembayaran.index') }}">
                                    Pembayaran
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @if ($canBridgingPendapatan || $canBridgingPendapatanObat || $canBridgingPembelian || $canPendapatanInvoice || $canPendapatanPenerimaan || $canPembelianInvoice || $canPembelianPembayaran || $canLaporanKeuangan || $canLaporanPendapatan)
                    <li><hr class="dropdown-divider"></li>
                @endif
            @endif
            @if ($canBridgingPendapatan || $canBridgingPendapatanObat || $canBridgingPembelian)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/bridging') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Bridging</a>
                    <ul class="dropdown-menu">
                        @if ($canBridgingPendapatan)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/bridging/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('bridging.pendapatan.index') }}">
                                    Pendapatan
                                </a>
                            </li>
                        @endif
                        @if (($canBridgingPendapatan && $canBridgingPendapatanObat) || ($canBridgingPendapatan && $canBridgingPembelian) || ($canBridgingPendapatanObat && $canBridgingPembelian))
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canBridgingPendapatanObat)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/bridging/pendapatan-obat') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('bridging.pendapatan-obat.index') }}">
                                    Pendapatan Obat
                                </a>
                            </li>
                        @endif
                        @if ($canBridgingPembelian)
                            @if ($canBridgingPendapatan || $canBridgingPendapatanObat)
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/bridging/pembelian') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('bridging.pembelian.index') }}">
                                    Pembelian
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @if ($canPendapatanInvoice || $canPendapatanPenerimaan || $canPembelianInvoice || $canPembelianPembayaran || $canLaporanKeuangan || $canLaporanPendapatan)
                    <li><hr class="dropdown-divider"></li>
                @endif
            @endif
            @if ($canPendapatanInvoice || $canPendapatanPenerimaan)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Pendapatan</a>
                    <ul class="dropdown-menu">
                        @if ($canPendapatanInvoice)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/pendapatan/invoice') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('pendapatan.invoice.index') }}">
                                    Invoice Pendapatan
                                </a>
                            </li>
                        @endif
                        @if ($canPendapatanInvoice && $canPendapatanPenerimaan)
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canPendapatanPenerimaan)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/pendapatan/penerimaan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('pendapatan.penerimaan.index') }}">
                                    Penerimaan Pendapatan
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @if ($canPembelianInvoice || $canPembelianPembayaran || $canLaporanKeuangan || $canLaporanPendapatan)
                    <li><hr class="dropdown-divider"></li>
                @endif
            @endif
            @if ($canPembelianInvoice || $canPembelianPembayaran)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/pembelian') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Pembelian</a>
                    <ul class="dropdown-menu">
                        @if ($canPembelianInvoice)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/pembelian/invoice') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('pembelian.invoice.index') }}">
                                    Invoice Pembelian
                                </a>
                            </li>
                        @endif
                        @if ($canPembelianInvoice && $canPembelianPembayaran)
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canPembelianPembayaran)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/pembelian/pembayaran') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('pembelian.pembayaran.index') }}">
                                    Pembayaran Pembelian
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                @if ($canLaporanKeuangan || $canLaporanPendapatan)
                    <li><hr class="dropdown-divider"></li>
                @endif
            @endif
            @if ($canLaporanKeuangan || $canLaporanPendapatan)
                <li class="dropdown-submenu">
                    <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/laporan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Laporan</a>
                    <ul class="dropdown-menu">
                        @if ($canLaporanKeuangan)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/laporan/keuangan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('laporan.keuangan.index') }}">
                                    Laporan Keuangan
                                </a>
                            </li>
                        @endif
                        @if ($canLaporanKeuangan && $canLaporanPendapatan)
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if ($canLaporanPendapatan)
                            <li>
                                <a class="dropdown-item {{ str_starts_with($path, '/laporan/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                                    href="{{ route('laporan.pendapatan.index') }}">
                                    Laporan Pendapatan
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif
        </ul>
    </li>
@endif

@if ($canSeePengaturan)
    <li class="nav-item mb-2 mb-lg-0 me-0 me-lg-2 dropdown rounded">
        <a class="nav-link fw-bold dropdown-toggle px-4 {{ $isPengaturanActive ? 'active bg-success-subtle text-success' : 'text-white' }}"
            href="#"
            id="pengaturanDropdown"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="bi bi-gear-fill me-1"></i> Pengaturan
        </a>
        <ul class="dropdown-menu" aria-labelledby="pengaturanDropdown">
            @if ($canPengaturanMappingPendapatan)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/mapping-pendapatan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.mapping-pendapatan.index') }}">
                        Mapping Pendapatan
                    </a>
                </li>
            @endif
            @if ($canPengaturanMappingPendapatan && ($canPengaturanMappingGeneral || $canPengaturanSettingRba || $canPengaturanPreferensi || $canPengaturanPengguna || $canPengaturanRoleAkses || $canPengaturanKonversiFile))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanMappingGeneral)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/mapping-general') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.mapping-general.index') }}">
                        Mapping General
                    </a>
                </li>
            @endif
            @if ($canPengaturanMappingGeneral && ($canPengaturanSettingRba || $canPengaturanPreferensi || $canPengaturanPengguna || $canPengaturanRoleAkses || $canPengaturanKonversiFile))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanSettingRba)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/setting-rba') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.setting-rba.index') }}">
                        Setting RBA
                    </a>
                </li>
            @endif
            @if ($canPengaturanSettingRba && ($canPengaturanPreferensi || $canPengaturanPengguna || $canPengaturanRoleAkses || $canPengaturanKonversiFile))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanPreferensi)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/preferensi') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.preferensi.index') }}">
                        Preferensi
                    </a>
                </li>
            @endif
            @if ($canPengaturanPreferensi && ($canPengaturanPengguna || $canPengaturanRoleAkses || $canPengaturanKonversiFile))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanPengguna)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/pengguna') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.pengguna.index') }}">
                        Pengguna
                    </a>
                </li>
            @endif
            @if ($canPengaturanPengguna && ($canPengaturanRoleAkses || $canPengaturanKonversiFile))
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanRoleAkses)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/role-akses') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.role-akses.index') }}">
                        Role Akses
                    </a>
                </li>
            @endif
            @if ($canPengaturanRoleAkses && $canPengaturanKonversiFile)
                <li><hr class="dropdown-divider"></li>
            @endif
            @if ($canPengaturanKonversiFile)
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/konversi-file') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                        href="{{ route('pengaturan.konversi-file.index') }}">
                        Konversi File
                    </a>
                </li>
            @endif
        </ul>
    </li>
@endif
