@php
    $path = '/'.trim(request()->path(), '/');
    $path = $path === '/' ? '/' : rtrim($path, '/');
    $isHome = request()->routeIs('home');
    $isKeuanganActive = str_starts_with($path, '/bukubesar')
        || str_starts_with($path, '/kasbank')
        || str_starts_with($path, '/pendapatan')
        || str_starts_with($path, '/pembelian')
        || str_starts_with($path, '/laporan')
        || str_starts_with($path, '/bridging');
    $isPengaturanActive = str_starts_with($path, '/pengaturan');
@endphp

<li class="nav-item mb-2 mb-lg-0 me-0 me-lg-2 rounded">
    <a class="nav-link fw-bold px-4 {{ $isHome ? 'active bg-success-subtle text-success' : 'text-white' }}" href="{{ route('home') }}">
        <i class="bi bi-house-door-fill me-1"></i> Home
    </a>
</li>

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
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/bukubesar') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Bukubesar</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/bukubesar/jurnal-umum') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('bukubesar.jurnal-umum.index') }}">
                        Jurnal Umum
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/bukubesar/coa') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('bukubesar.coa.index') }}">
                        Coa
                    </a>
                </li>
            </ul>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/kasbank') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Kasbank</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/kasbank/penerimaan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('kasbank.penerimaan.index') }}">
                        Penerimaan
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/kasbank/pembayaran') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('kasbank.pembayaran.index') }}">
                        Pembayaran
                    </a>
                </li>
            </ul>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/bridging') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Bridging</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/bridging/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('bridging.pendapatan.index') }}">
                        Pendapatan
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/bridging/pendapatan-obat') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('bridging.pendapatan-obat.index') }}">
                        Pendapatan Obat
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/bridging/pembelian') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('bridging.pembelian.index') }}">
                        Pembelian
                    </a>
                </li>
            </ul>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Pendapatan</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pendapatan/invoice') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('pendapatan.invoice.index') }}">
                        Invoice Pendapatan
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pendapatan/penerimaan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('pendapatan.penerimaan.index') }}">
                        Penerimaan Pendapatan
                    </a>
                </li>
            </ul>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/pembelian') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Pembelian</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pembelian/invoice') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('pembelian.invoice.index') }}">
                        Invoice Pembelian
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/pembelian/pembayaran') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('pembelian.pembayaran.index') }}">
                        Pembayaran Pembelian
                    </a>
                </li>
            </ul>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu">
            <a class="dropdown-item dropdown-toggle {{ str_starts_with($path, '/laporan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}" href="#">Laporan</a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/laporan/keuangan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('laporan.keuangan.index') }}">
                        Laporan Keuangan
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item {{ str_starts_with($path, '/laporan/pendapatan') ? 'active fw-bold bg-success-subtle text-success' : '' }}"
                        href="{{ route('laporan.pendapatan.index') }}">
                        Laporan Pendapatan
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</li>

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
        <li>
            <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/mapping-pendapatan') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                href="{{ route('pengaturan.mapping-pendapatan.index') }}">
                Mapping Pendapatan
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/mapping-general') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                href="{{ route('pengaturan.mapping-general.index') }}">
                Mapping General
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/setting-rba') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                href="{{ route('pengaturan.setting-rba.index') }}">
                Setting RBA
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/preferensi') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                href="{{ route('pengaturan.preferensi.index') }}">
                Preferensi
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item {{ str_starts_with($path, '/pengaturan/pengguna') ? 'active fw-bold bg-success-subtle text-success' : 'fw-semibold' }}"
                href="{{ route('pengaturan.pengguna.index') }}">
                Pengguna
            </a>
        </li>
    </ul>
</li>
