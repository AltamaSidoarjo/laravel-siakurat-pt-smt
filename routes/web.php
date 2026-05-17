<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Bridging\BridgingPembelianController;
use App\Http\Controllers\Bridging\BridgingPendapatanController;
use App\Http\Controllers\Bridging\BridgingPendapatanObatController;
use App\Http\Controllers\Bukubesar\CoaController;
use App\Http\Controllers\Bukubesar\JurnalUmumController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kasbank\KasbankPembayaranController;
use App\Http\Controllers\Kasbank\KasbankPenerimaanController;
use App\Http\Controllers\Laporan\LaporanKeuanganController;
use App\Http\Controllers\ModulePageController;
use App\Http\Controllers\Pembelian\InvoicePembelianController;
use App\Http\Controllers\Pembelian\PembayaranPembelianController;
use App\Http\Controllers\Pendapatan\InvoicePendapatanController;
use App\Http\Controllers\Pendapatan\PenerimaanPendapatanController;
use App\Http\Controllers\Pengaturan\MappingGeneralController;
use App\Http\Controllers\Pengaturan\MappingPendapatanController;
use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Controllers\Pengaturan\PreferensiController;
use App\Http\Controllers\Pengaturan\SettingRbaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return session()->has('auth.preview_user')
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('preview.auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('bukubesar')->name('bukubesar.')->group(function () {
        Route::get('/jurnal-umum', [JurnalUmumController::class, 'index'])->name('jurnal-umum.index');
        Route::get('/jurnal-umum/load-data', [JurnalUmumController::class, 'loadData'])->name('jurnal-umum.load-data');
        Route::get('/jurnal-umum/create', [JurnalUmumController::class, 'create'])->name('jurnal-umum.create');
        Route::post('/jurnal-umum', [JurnalUmumController::class, 'store'])->name('jurnal-umum.store');
        Route::get('/jurnal-umum/{jurnalUmum}/edit', [JurnalUmumController::class, 'edit'])->name('jurnal-umum.edit');
        Route::put('/jurnal-umum/{jurnalUmum}', [JurnalUmumController::class, 'update'])->name('jurnal-umum.update');
        Route::delete('/jurnal-umum/{jurnalUmum}', [JurnalUmumController::class, 'destroy'])->name('jurnal-umum.destroy');
        Route::get('/jurnal-umum/{jurnalUmum}/print', [JurnalUmumController::class, 'print'])->name('jurnal-umum.print');
        Route::get('/coa', [CoaController::class, 'index'])->name('coa.index');
        Route::get('/coa/create', [CoaController::class, 'create'])->name('coa.create');
        Route::post('/coa', [CoaController::class, 'store'])->name('coa.store');
        Route::get('/coa/{coa}/edit', [CoaController::class, 'edit'])->name('coa.edit');
        Route::put('/coa/{coa}', [CoaController::class, 'update'])->name('coa.update');
        Route::delete('/coa/{coa}', [CoaController::class, 'destroy'])->name('coa.destroy');
    });

    Route::prefix('kasbank')->name('kasbank.')->group(function () {
        Route::get('/penerimaan', [KasbankPenerimaanController::class, 'index'])->name('penerimaan.index');
        Route::get('/penerimaan/load-data', [KasbankPenerimaanController::class, 'loadData'])->name('penerimaan.load-data');
        Route::get('/penerimaan/create', [KasbankPenerimaanController::class, 'create'])->name('penerimaan.create');
        Route::post('/penerimaan', [KasbankPenerimaanController::class, 'store'])->name('penerimaan.store');
        Route::get('/penerimaan/{kasbankPenerimaan}/edit', [KasbankPenerimaanController::class, 'edit'])->name('penerimaan.edit');
        Route::put('/penerimaan/{kasbankPenerimaan}', [KasbankPenerimaanController::class, 'update'])->name('penerimaan.update');
        Route::delete('/penerimaan/{kasbankPenerimaan}', [KasbankPenerimaanController::class, 'destroy'])->name('penerimaan.destroy');
        Route::get('/penerimaan/{kasbankPenerimaan}/print', [KasbankPenerimaanController::class, 'print'])->name('penerimaan.print');
        Route::get('/pembayaran', [KasbankPembayaranController::class, 'index'])->name('pembayaran.index');
        Route::get('/pembayaran/load-data', [KasbankPembayaranController::class, 'loadData'])->name('pembayaran.load-data');
        Route::get('/pembayaran/create', [KasbankPembayaranController::class, 'create'])->name('pembayaran.create');
        Route::post('/pembayaran', [KasbankPembayaranController::class, 'store'])->name('pembayaran.store');
        Route::get('/pembayaran/{kasbankPembayaran}/edit', [KasbankPembayaranController::class, 'edit'])->name('pembayaran.edit');
        Route::put('/pembayaran/{kasbankPembayaran}', [KasbankPembayaranController::class, 'update'])->name('pembayaran.update');
        Route::delete('/pembayaran/{kasbankPembayaran}', [KasbankPembayaranController::class, 'destroy'])->name('pembayaran.destroy');
        Route::get('/pembayaran/{kasbankPembayaran}/print', [KasbankPembayaranController::class, 'print'])->name('pembayaran.print');
    });

    Route::prefix('bridging')->name('bridging.')->group(function () {
        Route::get('/pendapatan', [BridgingPendapatanController::class, 'index'])
            ->name('pendapatan.index');
        Route::get('/pendapatan/load-imported-data', [BridgingPendapatanController::class, 'loadImportedData'])
            ->name('pendapatan.load-imported-data');
        Route::get('/pendapatan/tarik-simrs', [BridgingPendapatanController::class, 'tarikBillingSimrs'])
            ->name('pendapatan.tarik-billing-simrs');
        Route::get('/pendapatan/load-billing-simrs', [BridgingPendapatanController::class, 'loadBillingSimrs'])
            ->name('pendapatan.load-billing-simrs');
        Route::post('/pendapatan/process-import', [BridgingPendapatanController::class, 'processImport'])
            ->name('pendapatan.process-import');
        Route::post('/pendapatan/destroy-bulk', [BridgingPendapatanController::class, 'destroyBulk'])
            ->name('pendapatan.destroy-bulk');
        Route::get('/pendapatan/data-tidak-balance', [BridgingPendapatanController::class, 'dataTidakBalance'])
            ->name('pendapatan.data-tidak-balance');
        Route::get('/pendapatan/detect-tidak-balance', [BridgingPendapatanController::class, 'detectTidakBalance'])
            ->name('pendapatan.detect-tidak-balance');
        Route::get('/pendapatan-obat', [BridgingPendapatanObatController::class, 'index'])
            ->name('pendapatan-obat.index');
        Route::get('/pendapatan-obat/load-imported-data', [BridgingPendapatanObatController::class, 'loadImportedData'])
            ->name('pendapatan-obat.load-imported-data');
        Route::get('/pendapatan-obat/tarik-tagihan', [BridgingPendapatanObatController::class, 'tarikTagihan'])
            ->name('pendapatan-obat.tarik-tagihan');
        Route::get('/pendapatan-obat/load-tagihan-simrs', [BridgingPendapatanObatController::class, 'loadTagihanSimrs'])
            ->name('pendapatan-obat.load-tagihan-simrs');
        Route::post('/pendapatan-obat/process-import', [BridgingPendapatanObatController::class, 'processImport'])
            ->name('pendapatan-obat.process-import');
        Route::post('/pendapatan-obat/destroy-bulk', [BridgingPendapatanObatController::class, 'destroyBulk'])
            ->name('pendapatan-obat.destroy-bulk');
        Route::get('/pembelian', [BridgingPembelianController::class, 'index'])
            ->name('pembelian.index');
        Route::get('/pembelian/load-imported-data', [BridgingPembelianController::class, 'loadImportedData'])
            ->name('pembelian.load-imported-data');
        Route::get('/pembelian/tarik-obat', [BridgingPembelianController::class, 'tarikPembelianObat'])
            ->name('pembelian.tarik-obat');
        Route::get('/pembelian/load-tagihan-obat', [BridgingPembelianController::class, 'loadTagihanObat'])
            ->name('pembelian.load-tagihan-obat');
        Route::post('/pembelian/process-import-obat', [BridgingPembelianController::class, 'processImportObat'])
            ->name('pembelian.process-import-obat');
        Route::get('/pembelian/tarik-nonmedis', [BridgingPembelianController::class, 'tarikPembelianNonMedis'])
            ->name('pembelian.tarik-nonmedis');
        Route::get('/pembelian/load-tagihan-nonmedis', [BridgingPembelianController::class, 'loadTagihanNonMedis'])
            ->name('pembelian.load-tagihan-nonmedis');
        Route::post('/pembelian/process-import-nonmedis', [BridgingPembelianController::class, 'processImportNonMedis'])
            ->name('pembelian.process-import-nonmedis');
        Route::post('/pembelian/destroy-bulk', [BridgingPembelianController::class, 'destroyBulk'])
            ->name('pembelian.destroy-bulk');
    });

    Route::prefix('pendapatan')->name('pendapatan.')->group(function () {
        Route::get('/invoice', [InvoicePendapatanController::class, 'index'])->name('invoice.index');
        Route::get('/invoice/load-data', [InvoicePendapatanController::class, 'loadData'])->name('invoice.load-data');
        Route::get('/invoice/{fakturPenjualan}', [InvoicePendapatanController::class, 'read'])->name('invoice.read');
        Route::get('/penerimaan', [PenerimaanPendapatanController::class, 'index'])->name('penerimaan.index');
        Route::get('/penerimaan/load-data', [PenerimaanPendapatanController::class, 'loadData'])->name('penerimaan.load-data');
        Route::get('/penerimaan/create', [PenerimaanPendapatanController::class, 'create'])->name('penerimaan.create');
        Route::post('/penerimaan', [PenerimaanPendapatanController::class, 'store'])->name('penerimaan.store');
        Route::get('/penerimaan/{penerimaanPenjualan}/edit', [PenerimaanPendapatanController::class, 'edit'])->name('penerimaan.edit');
        Route::put('/penerimaan/{penerimaanPenjualan}', [PenerimaanPendapatanController::class, 'update'])->name('penerimaan.update');
        Route::delete('/penerimaan/{penerimaanPenjualan}', [PenerimaanPendapatanController::class, 'destroy'])->name('penerimaan.destroy');
        Route::get('/penerimaan/{penerimaanPenjualan}/print', [PenerimaanPendapatanController::class, 'print'])->name('penerimaan.print');
        Route::get('/penerimaan/api/invoice-by-pelanggan', [PenerimaanPendapatanController::class, 'apiGetInvByPelanggan'])
            ->name('penerimaan.api.invoice-by-pelanggan');
    });

    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/invoice', [InvoicePembelianController::class, 'index'])->name('invoice.index');
        Route::get('/invoice/load-data', [InvoicePembelianController::class, 'loadData'])->name('invoice.load-data');
        Route::get('/invoice/{fakturPembelian}', [InvoicePembelianController::class, 'read'])->name('invoice.read');
        Route::get('/pembayaran', [PembayaranPembelianController::class, 'index'])->name('pembayaran.index');
        Route::get('/pembayaran/load-data', [PembayaranPembelianController::class, 'loadData'])->name('pembayaran.load-data');
        Route::get('/pembayaran/create', [PembayaranPembelianController::class, 'create'])->name('pembayaran.create');
        Route::post('/pembayaran', [PembayaranPembelianController::class, 'store'])->name('pembayaran.store');
        Route::get('/pembayaran/{pembayaranPembelian}/edit', [PembayaranPembelianController::class, 'edit'])->name('pembayaran.edit');
        Route::put('/pembayaran/{pembayaranPembelian}', [PembayaranPembelianController::class, 'update'])->name('pembayaran.update');
        Route::delete('/pembayaran/{pembayaranPembelian}', [PembayaranPembelianController::class, 'destroy'])->name('pembayaran.destroy');
        Route::get('/pembayaran/{pembayaranPembelian}/print', [PembayaranPembelianController::class, 'print'])->name('pembayaran.print');
        Route::get('/pembayaran/api/invoice-by-supplier', [PembayaranPembelianController::class, 'apiGetInvBySupplier'])
            ->name('pembayaran.api.invoice-by-supplier');
    });

    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/keuangan', [LaporanKeuanganController::class, 'index'])
            ->name('keuangan.index');
        Route::get('/keuangan/rincian-transaksi-bukubesar', [LaporanKeuanganController::class, 'rincianTransaksiBukubesar'])
            ->name('keuangan.rincian-transaksi-bukubesar');
        Route::get('/keuangan/rincian-transaksi-bukubesar/load-data', [LaporanKeuanganController::class, 'loadRincianTransaksiBukubesar'])
            ->name('keuangan.rincian-transaksi-bukubesar.load-data');
        Route::get('/keuangan/deteksi-jurnal-tidak-balance', [LaporanKeuanganController::class, 'deteksiJurnalTidakBalance'])
            ->name('keuangan.deteksi-jurnal-tidak-balance');
        Route::get('/keuangan/deteksi-jurnal-tidak-balance/load-data', [LaporanKeuanganController::class, 'loadDeteksiJurnalTidakBalance'])
            ->name('keuangan.deteksi-jurnal-tidak-balance.load-data');
        Route::get('/keuangan/laba-rugi-detil', [LaporanKeuanganController::class, 'labaRugiDetil'])
            ->name('keuangan.laba-rugi-detil');
        Route::get('/keuangan/laba-rugi-standard', [LaporanKeuanganController::class, 'labaRugiStandard'])
            ->name('keuangan.laba-rugi-standard');
        Route::get('/keuangan/laba-rugi-per-parent-coa', [LaporanKeuanganController::class, 'labaRugiPerParentCoa'])
            ->name('keuangan.laba-rugi-per-parent-coa');
        Route::get('/keuangan/neraca-standard', [LaporanKeuanganController::class, 'neracaStandard'])
            ->name('keuangan.neraca-standard');
        Route::get('/keuangan/neraca-per-parent-coa', [LaporanKeuanganController::class, 'neracaPerParentCoa'])
            ->name('keuangan.neraca-per-parent-coa');
        Route::get('/keuangan/bukubesar', [LaporanKeuanganController::class, 'bukubesar'])
            ->name('keuangan.bukubesar');
        Route::get('/keuangan/neraca-saldo', [LaporanKeuanganController::class, 'neracaSaldo'])
            ->name('keuangan.neraca-saldo');
        Route::get('/keuangan/neraca-detil', [LaporanKeuanganController::class, 'neracaDetil'])
            ->name('keuangan.neraca-detil');
        Route::get('/keuangan/neraca-rinci', [LaporanKeuanganController::class, 'neracaRinci'])
            ->name('keuangan.neraca-rinci');
        Route::get('/keuangan/arus-kas', [LaporanKeuanganController::class, 'arusKas'])
            ->name('keuangan.arus-kas');
        Route::get('/pendapatan', fn (ModulePageController $controller) => $controller->show('Laporan', 'Laporan Pendapatan'))
            ->name('pendapatan.index');
    });

    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/mapping-pendapatan', [MappingPendapatanController::class, 'index'])
            ->name('mapping-pendapatan.index');
        Route::get('/mapping-pendapatan/create', [MappingPendapatanController::class, 'create'])
            ->name('mapping-pendapatan.create');
        Route::post('/mapping-pendapatan', [MappingPendapatanController::class, 'store'])
            ->name('mapping-pendapatan.store');
        Route::delete('/mapping-pendapatan/{mappingPendapatan}', [MappingPendapatanController::class, 'destroy'])
            ->name('mapping-pendapatan.destroy');
        Route::get('/mapping-pendapatan/umum', [MappingPendapatanController::class, 'indexUmum'])
            ->name('mapping-pendapatan.umum.index');
        Route::get('/mapping-pendapatan/umum/create', [MappingPendapatanController::class, 'createUmum'])
            ->name('mapping-pendapatan.umum.create');
        Route::post('/mapping-pendapatan/umum', [MappingPendapatanController::class, 'storeUmum'])
            ->name('mapping-pendapatan.umum.store');
        Route::delete('/mapping-pendapatan/umum/{mappingPendapatanUmum}', [MappingPendapatanController::class, 'destroyUmum'])
            ->name('mapping-pendapatan.umum.destroy');
        Route::get('/mapping-pendapatan/lawan-pendapatan', [MappingPendapatanController::class, 'indexLawanPendapatan'])
            ->name('mapping-pendapatan.lawan.index');
        Route::get('/mapping-pendapatan/lawan-pendapatan/create', [MappingPendapatanController::class, 'createLawanPendapatan'])
            ->name('mapping-pendapatan.lawan.create');
        Route::post('/mapping-pendapatan/lawan-pendapatan', [MappingPendapatanController::class, 'storeLawanPendapatan'])
            ->name('mapping-pendapatan.lawan.store');
        Route::delete('/mapping-pendapatan/lawan-pendapatan/{mappingLawanPendapatanSimrs}', [MappingPendapatanController::class, 'destroyLawanPendapatan'])
            ->name('mapping-pendapatan.lawan.destroy');
        Route::get('/mapping-general', [MappingGeneralController::class, 'index'])
            ->name('mapping-general.index');
        Route::get('/mapping-general/create', [MappingGeneralController::class, 'create'])
            ->name('mapping-general.create');
        Route::post('/mapping-general', [MappingGeneralController::class, 'store'])
            ->name('mapping-general.store');
        Route::delete('/mapping-general/{mappingCoaSimrs}', [MappingGeneralController::class, 'destroy'])
            ->name('mapping-general.destroy');
        Route::get('/setting-rba', [SettingRbaController::class, 'index'])
            ->name('setting-rba.index');
        Route::get('/setting-rba/load-data', [SettingRbaController::class, 'loadData'])
            ->name('setting-rba.load-data');
        Route::get('/setting-rba/create', [SettingRbaController::class, 'create'])
            ->name('setting-rba.create');
        Route::post('/setting-rba', [SettingRbaController::class, 'store'])
            ->name('setting-rba.store');
        Route::delete('/setting-rba/{settingRba}', [SettingRbaController::class, 'destroy'])
            ->name('setting-rba.destroy');
        Route::get('/preferensi', [PreferensiController::class, 'index'])
            ->name('preferensi.index');
        Route::post('/preferensi', [PreferensiController::class, 'update'])
            ->name('preferensi.update');
        Route::get('/pengguna', [PenggunaController::class, 'index'])
            ->name('pengguna.index');
        Route::get('/pengguna/create', [PenggunaController::class, 'create'])
            ->name('pengguna.create');
        Route::post('/pengguna', [PenggunaController::class, 'store'])
            ->name('pengguna.store');
        Route::get('/pengguna/{user}/edit', [PenggunaController::class, 'edit'])
            ->name('pengguna.edit');
        Route::put('/pengguna/{user}', [PenggunaController::class, 'update'])
            ->name('pengguna.update');
    });
});
