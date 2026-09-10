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
use App\Http\Controllers\Laporan\LaporanPendapatanController;
use App\Http\Controllers\Pembelian\InvoicePembelianController;
use App\Http\Controllers\Pembelian\PembayaranPembelianController;
use App\Http\Controllers\Pendapatan\InvoicePendapatanController;
use App\Http\Controllers\Pendapatan\PenerimaanPendapatanController;
use App\Http\Controllers\Pengaturan\KonversiFileController;
use App\Http\Controllers\Pengaturan\MappingGeneralController;
use App\Http\Controllers\Pengaturan\MappingPendapatanController;
use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Controllers\Pengaturan\PreferensiController;
use App\Http\Controllers\Pengaturan\RoleAksesController;
use App\Http\Controllers\Pengaturan\SettingRbaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::middleware('module.access:home,view')->group(function () {
        Route::get('/home', [HomeController::class, 'index'])->name('home');
        Route::get('/home/kunjungan-harian', [HomeController::class, 'kunjunganHarian'])->name('home.kunjungan-harian');
        Route::get('/home/poli', [HomeController::class, 'distribusiPoli'])->name('home.poli');
        Route::get('/home/dokter', [HomeController::class, 'topDokter'])->name('home.dokter');
        Route::get('/home/pendapatan-harian', [HomeController::class, 'pendapatanHarian'])->name('home.pendapatan-harian');
        Route::get('/home/penjamin', [HomeController::class, 'komposisiPenjamin'])->name('home.penjamin');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('bukubesar')->name('bukubesar.')->group(function () {
        Route::middleware('module.access:bukubesar.jurnal-umum,view')->group(function () {
            Route::get('/jurnal-umum', [JurnalUmumController::class, 'index'])->name('jurnal-umum.index');
            Route::get('/jurnal-umum/load-data', [JurnalUmumController::class, 'loadData'])->name('jurnal-umum.load-data');
            Route::get('/jurnal-umum/export-csv', [JurnalUmumController::class, 'exportCsv'])->name('jurnal-umum.export-csv');
            Route::get('/jurnal-umum/{jurnalUmum}/print', [JurnalUmumController::class, 'print'])->name('jurnal-umum.print');
        });
        Route::middleware('module.access:bukubesar.jurnal-umum,create')->group(function () {
            Route::get('/jurnal-umum/create', [JurnalUmumController::class, 'create'])->name('jurnal-umum.create');
            Route::post('/jurnal-umum', [JurnalUmumController::class, 'store'])->name('jurnal-umum.store');
        });
        Route::middleware('module.access:bukubesar.jurnal-umum,update')->group(function () {
            Route::get('/jurnal-umum/{jurnalUmum}/edit', [JurnalUmumController::class, 'edit'])->name('jurnal-umum.edit');
            Route::put('/jurnal-umum/{jurnalUmum}', [JurnalUmumController::class, 'update'])->name('jurnal-umum.update');
        });
        Route::middleware('module.access:bukubesar.jurnal-umum,delete')->group(function () {
            Route::delete('/jurnal-umum/{jurnalUmum}', [JurnalUmumController::class, 'destroy'])->name('jurnal-umum.destroy');
        });

        Route::middleware('module.access:bukubesar.coa,view')->group(function () {
            Route::get('/coa', [CoaController::class, 'index'])->name('coa.index');
        });
        Route::middleware('module.access:bukubesar.coa,create')->group(function () {
            Route::get('/coa/create', [CoaController::class, 'create'])->name('coa.create');
            Route::post('/coa', [CoaController::class, 'store'])->name('coa.store');
        });
        Route::middleware('module.access:bukubesar.coa,update')->group(function () {
            Route::get('/coa/{coa}/edit', [CoaController::class, 'edit'])->name('coa.edit');
            Route::put('/coa/{coa}', [CoaController::class, 'update'])->name('coa.update');
        });
        Route::middleware('module.access:bukubesar.coa,delete')->group(function () {
            Route::delete('/coa/{coa}', [CoaController::class, 'destroy'])->name('coa.destroy');
        });
    });

    Route::prefix('kasbank')->name('kasbank.')->group(function () {
        Route::middleware('module.access:kasbank.penerimaan,view')->group(function () {
            Route::get('/penerimaan', [KasbankPenerimaanController::class, 'index'])->name('penerimaan.index');
            Route::get('/penerimaan/load-data', [KasbankPenerimaanController::class, 'loadData'])->name('penerimaan.load-data');
            Route::get('/penerimaan/export-csv', [KasbankPenerimaanController::class, 'exportCsv'])->name('penerimaan.export-csv');
            Route::get('/penerimaan/{kasbankPenerimaan}/print', [KasbankPenerimaanController::class, 'print'])->name('penerimaan.print');
        });
        Route::middleware('module.access:kasbank.penerimaan,create')->group(function () {
            Route::get('/penerimaan/create', [KasbankPenerimaanController::class, 'create'])->name('penerimaan.create');
            Route::post('/penerimaan', [KasbankPenerimaanController::class, 'store'])->name('penerimaan.store');
        });
        Route::middleware('module.access:kasbank.penerimaan,update')->group(function () {
            Route::get('/penerimaan/{kasbankPenerimaan}/edit', [KasbankPenerimaanController::class, 'edit'])->name('penerimaan.edit');
            Route::put('/penerimaan/{kasbankPenerimaan}', [KasbankPenerimaanController::class, 'update'])->name('penerimaan.update');
        });
        Route::middleware('module.access:kasbank.penerimaan,delete')->group(function () {
            Route::delete('/penerimaan/{kasbankPenerimaan}', [KasbankPenerimaanController::class, 'destroy'])->name('penerimaan.destroy');
        });

        Route::middleware('module.access:kasbank.pembayaran,view')->group(function () {
            Route::get('/pembayaran', [KasbankPembayaranController::class, 'index'])->name('pembayaran.index');
            Route::get('/pembayaran/load-data', [KasbankPembayaranController::class, 'loadData'])->name('pembayaran.load-data');
            Route::get('/pembayaran/export-csv', [KasbankPembayaranController::class, 'exportCsv'])->name('pembayaran.export-csv');
            Route::get('/pembayaran/{kasbankPembayaran}/print', [KasbankPembayaranController::class, 'print'])->name('pembayaran.print');
        });
        Route::middleware('module.access:kasbank.pembayaran,create')->group(function () {
            Route::get('/pembayaran/create', [KasbankPembayaranController::class, 'create'])->name('pembayaran.create');
            Route::post('/pembayaran', [KasbankPembayaranController::class, 'store'])->name('pembayaran.store');
        });
        Route::middleware('module.access:kasbank.pembayaran,update')->group(function () {
            Route::get('/pembayaran/{kasbankPembayaran}/edit', [KasbankPembayaranController::class, 'edit'])->name('pembayaran.edit');
            Route::put('/pembayaran/{kasbankPembayaran}', [KasbankPembayaranController::class, 'update'])->name('pembayaran.update');
        });
        Route::middleware('module.access:kasbank.pembayaran,delete')->group(function () {
            Route::delete('/pembayaran/{kasbankPembayaran}', [KasbankPembayaranController::class, 'destroy'])->name('pembayaran.destroy');
        });
    });

    Route::prefix('bridging')->name('bridging.')->group(function () {
        Route::middleware('module.access:bridging.pendapatan,view')->group(function () {
            Route::get('/pendapatan', [BridgingPendapatanController::class, 'index'])->name('pendapatan.index');
            Route::get('/pendapatan/load-imported-data', [BridgingPendapatanController::class, 'loadImportedData'])->name('pendapatan.load-imported-data');
            Route::get('/pendapatan/export-csv', [BridgingPendapatanController::class, 'exportCsv'])->name('pendapatan.export-csv');
            Route::get('/pendapatan/tarik-simrs', [BridgingPendapatanController::class, 'tarikBillingSimrs'])->name('pendapatan.tarik-billing-simrs');
            Route::get('/pendapatan/load-billing-simrs', [BridgingPendapatanController::class, 'loadBillingSimrs'])->name('pendapatan.load-billing-simrs');
            Route::get('/pendapatan/data-tidak-balance', [BridgingPendapatanController::class, 'dataTidakBalance'])->name('pendapatan.data-tidak-balance');
            Route::get('/pendapatan/detect-tidak-balance', [BridgingPendapatanController::class, 'detectTidakBalance'])->name('pendapatan.detect-tidak-balance');
        });
        Route::middleware('module.access:bridging.pendapatan,update')->group(function () {
            Route::post('/pendapatan/process-import', [BridgingPendapatanController::class, 'processImport'])->name('pendapatan.process-import');
        });
        Route::middleware('module.access:bridging.pendapatan,delete')->group(function () {
            Route::post('/pendapatan/destroy-bulk', [BridgingPendapatanController::class, 'destroyBulk'])->name('pendapatan.destroy-bulk');
        });

        Route::middleware('module.access:bridging.pendapatan-obat,view')->group(function () {
            Route::get('/pendapatan-obat', [BridgingPendapatanObatController::class, 'index'])->name('pendapatan-obat.index');
            Route::get('/pendapatan-obat/load-imported-data', [BridgingPendapatanObatController::class, 'loadImportedData'])->name('pendapatan-obat.load-imported-data');
            Route::get('/pendapatan-obat/tarik-tagihan', [BridgingPendapatanObatController::class, 'tarikTagihan'])->name('pendapatan-obat.tarik-tagihan');
            Route::get('/pendapatan-obat/load-tagihan-simrs', [BridgingPendapatanObatController::class, 'loadTagihanSimrs'])->name('pendapatan-obat.load-tagihan-simrs');
        });
        Route::middleware('module.access:bridging.pendapatan-obat,update')->group(function () {
            Route::post('/pendapatan-obat/process-import', [BridgingPendapatanObatController::class, 'processImport'])->name('pendapatan-obat.process-import');
        });
        Route::middleware('module.access:bridging.pendapatan-obat,delete')->group(function () {
            Route::post('/pendapatan-obat/destroy-bulk', [BridgingPendapatanObatController::class, 'destroyBulk'])->name('pendapatan-obat.destroy-bulk');
        });

        Route::middleware('module.access:bridging.pembelian,view')->group(function () {
            Route::get('/pembelian', [BridgingPembelianController::class, 'index'])->name('pembelian.index');
            Route::get('/pembelian/load-imported-data', [BridgingPembelianController::class, 'loadImportedData'])->name('pembelian.load-imported-data');
            Route::get('/pembelian/tarik-obat', [BridgingPembelianController::class, 'tarikPembelianObat'])->name('pembelian.tarik-obat');
            Route::get('/pembelian/load-tagihan-obat', [BridgingPembelianController::class, 'loadTagihanObat'])->name('pembelian.load-tagihan-obat');
            Route::get('/pembelian/tarik-nonmedis', [BridgingPembelianController::class, 'tarikPembelianNonMedis'])->name('pembelian.tarik-nonmedis');
            Route::get('/pembelian/load-tagihan-nonmedis', [BridgingPembelianController::class, 'loadTagihanNonMedis'])->name('pembelian.load-tagihan-nonmedis');
        });
        Route::middleware('module.access:bridging.pembelian,update')->group(function () {
            Route::post('/pembelian/process-import-obat', [BridgingPembelianController::class, 'processImportObat'])->name('pembelian.process-import-obat');
            Route::post('/pembelian/process-import-nonmedis', [BridgingPembelianController::class, 'processImportNonMedis'])->name('pembelian.process-import-nonmedis');
        });
        Route::middleware('module.access:bridging.pembelian,delete')->group(function () {
            Route::post('/pembelian/destroy-bulk', [BridgingPembelianController::class, 'destroyBulk'])->name('pembelian.destroy-bulk');
        });
    });

    Route::prefix('pendapatan')->name('pendapatan.')->group(function () {
        Route::middleware('module.access:pendapatan.invoice,view')->group(function () {
            Route::get('/invoice', [InvoicePendapatanController::class, 'index'])->name('invoice.index');
            Route::get('/invoice/load-data', [InvoicePendapatanController::class, 'loadData'])->name('invoice.load-data');
            Route::get('/invoice/export-csv', [InvoicePendapatanController::class, 'exportCsv'])->name('invoice.export-csv');
            Route::get('/invoice/{fakturPenjualan}', [InvoicePendapatanController::class, 'read'])->name('invoice.read');
        });

        Route::middleware('module.access:pendapatan.penerimaan,view')->group(function () {
            Route::get('/penerimaan', [PenerimaanPendapatanController::class, 'index'])->name('penerimaan.index');
            Route::get('/penerimaan/load-data', [PenerimaanPendapatanController::class, 'loadData'])->name('penerimaan.load-data');
            Route::get('/penerimaan/export-csv', [PenerimaanPendapatanController::class, 'exportCsv'])->name('penerimaan.export-csv');
            Route::get('/penerimaan/{penerimaanPenjualan}/print', [PenerimaanPendapatanController::class, 'print'])->name('penerimaan.print');
            Route::get('/penerimaan/api/invoice-by-pelanggan', [PenerimaanPendapatanController::class, 'apiGetInvByPelanggan'])->name('penerimaan.api.invoice-by-pelanggan');
        });
        Route::middleware('module.access:pendapatan.penerimaan,create')->group(function () {
            Route::get('/penerimaan/create', [PenerimaanPendapatanController::class, 'create'])->name('penerimaan.create');
            Route::post('/penerimaan', [PenerimaanPendapatanController::class, 'store'])->name('penerimaan.store');
        });
        Route::middleware('module.access:pendapatan.penerimaan,update')->group(function () {
            Route::get('/penerimaan/{penerimaanPenjualan}/edit', [PenerimaanPendapatanController::class, 'edit'])->name('penerimaan.edit');
            Route::put('/penerimaan/{penerimaanPenjualan}', [PenerimaanPendapatanController::class, 'update'])->name('penerimaan.update');
        });
        Route::middleware('module.access:pendapatan.penerimaan,delete')->group(function () {
            Route::delete('/penerimaan/{penerimaanPenjualan}', [PenerimaanPendapatanController::class, 'destroy'])->name('penerimaan.destroy');
        });
    });

    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::middleware('module.access:pembelian.invoice,view')->group(function () {
            Route::get('/invoice', [InvoicePembelianController::class, 'index'])->name('invoice.index');
            Route::get('/invoice/load-data', [InvoicePembelianController::class, 'loadData'])->name('invoice.load-data');
            Route::get('/invoice/export-csv', [InvoicePembelianController::class, 'exportCsv'])->name('invoice.export-csv');
            Route::get('/invoice/{fakturPembelian}/print', [InvoicePembelianController::class, 'print'])->name('invoice.print');
            Route::get('/invoice/{fakturPembelian}', [InvoicePembelianController::class, 'read'])->name('invoice.read');
        });

        Route::middleware('module.access:pembelian.pembayaran,view')->group(function () {
            Route::get('/pembayaran', [PembayaranPembelianController::class, 'index'])->name('pembayaran.index');
            Route::get('/pembayaran/load-data', [PembayaranPembelianController::class, 'loadData'])->name('pembayaran.load-data');
            Route::get('/pembayaran/export-csv', [PembayaranPembelianController::class, 'exportCsv'])->name('pembayaran.export-csv');
            Route::get('/pembayaran/{pembayaranPembelian}/print', [PembayaranPembelianController::class, 'print'])->name('pembayaran.print');
            Route::get('/pembayaran/api/invoice-by-supplier', [PembayaranPembelianController::class, 'apiGetInvBySupplier'])->name('pembayaran.api.invoice-by-supplier');
        });
        Route::middleware('module.access:pembelian.pembayaran,create')->group(function () {
            Route::get('/pembayaran/create', [PembayaranPembelianController::class, 'create'])->name('pembayaran.create');
            Route::post('/pembayaran', [PembayaranPembelianController::class, 'store'])->name('pembayaran.store');
        });
        Route::middleware('module.access:pembelian.pembayaran,update')->group(function () {
            Route::get('/pembayaran/{pembayaranPembelian}/edit', [PembayaranPembelianController::class, 'edit'])->name('pembayaran.edit');
            Route::put('/pembayaran/{pembayaranPembelian}', [PembayaranPembelianController::class, 'update'])->name('pembayaran.update');
        });
        Route::middleware('module.access:pembelian.pembayaran,delete')->group(function () {
            Route::delete('/pembayaran/{pembayaranPembelian}', [PembayaranPembelianController::class, 'destroy'])->name('pembayaran.destroy');
        });
    });

    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::middleware('module.access:laporan.keuangan,view')->group(function () {
            Route::get('/keuangan', [LaporanKeuanganController::class, 'index'])->name('keuangan.index');
            Route::get('/keuangan/rincian-transaksi-bukubesar', [LaporanKeuanganController::class, 'rincianTransaksiBukubesar'])->name('keuangan.rincian-transaksi-bukubesar');
            Route::get('/keuangan/rincian-transaksi-bukubesar/load-data', [LaporanKeuanganController::class, 'loadRincianTransaksiBukubesar'])->name('keuangan.rincian-transaksi-bukubesar.load-data');
            Route::get('/keuangan/deteksi-jurnal-tidak-balance', [LaporanKeuanganController::class, 'deteksiJurnalTidakBalance'])->name('keuangan.deteksi-jurnal-tidak-balance');
            Route::get('/keuangan/deteksi-jurnal-tidak-balance/load-data', [LaporanKeuanganController::class, 'loadDeteksiJurnalTidakBalance'])->name('keuangan.deteksi-jurnal-tidak-balance.load-data');
            Route::get('/keuangan/laba-rugi-detil', [LaporanKeuanganController::class, 'labaRugiDetil'])->name('keuangan.laba-rugi-detil');
            Route::get('/keuangan/laba-rugi-standard', [LaporanKeuanganController::class, 'labaRugiStandard'])->name('keuangan.laba-rugi-standard');
            Route::get('/keuangan/laba-rugi-per-parent-coa', [LaporanKeuanganController::class, 'labaRugiPerParentCoa'])->name('keuangan.laba-rugi-per-parent-coa');
            Route::get('/keuangan/neraca-standard', [LaporanKeuanganController::class, 'neracaStandard'])->name('keuangan.neraca-standard');
            Route::get('/keuangan/neraca-per-parent-coa', [LaporanKeuanganController::class, 'neracaPerParentCoa'])->name('keuangan.neraca-per-parent-coa');
            Route::get('/keuangan/bukubesar', [LaporanKeuanganController::class, 'bukubesar'])->name('keuangan.bukubesar');
            Route::get('/keuangan/bukubesar/search-coa', [LaporanKeuanganController::class, 'searchBukubesarCoa'])->name('keuangan.bukubesar.search-coa');
            Route::get('/keuangan/neraca-saldo', [LaporanKeuanganController::class, 'neracaSaldo'])->name('keuangan.neraca-saldo');
            Route::get('/keuangan/neraca-detil', [LaporanKeuanganController::class, 'neracaDetil'])->name('keuangan.neraca-detil');
            Route::get('/keuangan/neraca-rinci', [LaporanKeuanganController::class, 'neracaRinci'])->name('keuangan.neraca-rinci');
            Route::get('/keuangan/arus-kas', [LaporanKeuanganController::class, 'arusKas'])->name('keuangan.arus-kas');
        });

        Route::middleware('module.access:laporan.pendapatan,view')->group(function () {
            Route::get('/pendapatan', [LaporanPendapatanController::class, 'index'])->name('pendapatan.index');
            Route::get('/pendapatan/kunjungan', [LaporanPendapatanController::class, 'kunjungan'])->name('pendapatan.kunjungan');
            Route::get('/pendapatan/kunjungan/load-data', [LaporanPendapatanController::class, 'loadKunjungan'])->name('pendapatan.kunjungan.load-data');
            Route::get('/pendapatan/kunjungan/export-csv', [LaporanPendapatanController::class, 'exportKunjunganCsv'])->name('pendapatan.kunjungan.export-csv');
            Route::get('/pendapatan/penjualan-obat', [LaporanPendapatanController::class, 'penjualanObat'])->name('pendapatan.penjualan-obat');
            Route::get('/pendapatan/penjualan-obat/load-data', [LaporanPendapatanController::class, 'loadPenjualanObat'])->name('pendapatan.penjualan-obat.load-data');
            Route::get('/pendapatan/penjualan-obat/export-csv', [LaporanPendapatanController::class, 'exportPenjualanObatCsv'])->name('pendapatan.penjualan-obat.export-csv');
        });
    });

    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::middleware('module.access:pengaturan.mapping-pendapatan,view')->group(function () {
            Route::get('/mapping-pendapatan', [MappingPendapatanController::class, 'index'])->name('mapping-pendapatan.index');
            Route::get('/mapping-pendapatan/umum', [MappingPendapatanController::class, 'indexUmum'])->name('mapping-pendapatan.umum.index');
            Route::get('/mapping-pendapatan/lawan-pendapatan', [MappingPendapatanController::class, 'indexLawanPendapatan'])->name('mapping-pendapatan.lawan.index');
        });
        Route::middleware('module.access:pengaturan.mapping-pendapatan,create')->group(function () {
            Route::get('/mapping-pendapatan/create', [MappingPendapatanController::class, 'create'])->name('mapping-pendapatan.create');
            Route::post('/mapping-pendapatan', [MappingPendapatanController::class, 'store'])->name('mapping-pendapatan.store');
            Route::get('/mapping-pendapatan/umum/create', [MappingPendapatanController::class, 'createUmum'])->name('mapping-pendapatan.umum.create');
            Route::post('/mapping-pendapatan/umum', [MappingPendapatanController::class, 'storeUmum'])->name('mapping-pendapatan.umum.store');
            Route::get('/mapping-pendapatan/lawan-pendapatan/create', [MappingPendapatanController::class, 'createLawanPendapatan'])->name('mapping-pendapatan.lawan.create');
            Route::post('/mapping-pendapatan/lawan-pendapatan', [MappingPendapatanController::class, 'storeLawanPendapatan'])->name('mapping-pendapatan.lawan.store');
        });
        Route::middleware('module.access:pengaturan.mapping-pendapatan,delete')->group(function () {
            Route::delete('/mapping-pendapatan/{mappingPendapatan}', [MappingPendapatanController::class, 'destroy'])->name('mapping-pendapatan.destroy');
            Route::delete('/mapping-pendapatan/kamar/{mappingPendapatanKamar}', [MappingPendapatanController::class, 'destroyKamar'])->name('mapping-pendapatan.kamar.destroy');
            Route::delete('/mapping-pendapatan/umum/{mappingPendapatanUmum}', [MappingPendapatanController::class, 'destroyUmum'])->name('mapping-pendapatan.umum.destroy');
            Route::delete('/mapping-pendapatan/lawan-pendapatan/{mappingLawanPendapatanSimrs}', [MappingPendapatanController::class, 'destroyLawanPendapatan'])->name('mapping-pendapatan.lawan.destroy');
        });

        Route::middleware('module.access:pengaturan.mapping-general,view')->group(function () {
            Route::get('/mapping-general', [MappingGeneralController::class, 'index'])->name('mapping-general.index');
        });
        Route::middleware('module.access:pengaturan.mapping-general,create')->group(function () {
            Route::get('/mapping-general/create', [MappingGeneralController::class, 'create'])->name('mapping-general.create');
            Route::post('/mapping-general', [MappingGeneralController::class, 'store'])->name('mapping-general.store');
        });
        Route::middleware('module.access:pengaturan.mapping-general,delete')->group(function () {
            Route::delete('/mapping-general/{mappingCoaSimrs}', [MappingGeneralController::class, 'destroy'])->name('mapping-general.destroy');
        });

        Route::middleware('module.access:pengaturan.setting-rba,view')->group(function () {
            Route::get('/setting-rba', [SettingRbaController::class, 'index'])->name('setting-rba.index');
            Route::get('/setting-rba/load-data', [SettingRbaController::class, 'loadData'])->name('setting-rba.load-data');
        });
        Route::middleware('module.access:pengaturan.setting-rba,create')->group(function () {
            Route::get('/setting-rba/create', [SettingRbaController::class, 'create'])->name('setting-rba.create');
            Route::post('/setting-rba', [SettingRbaController::class, 'store'])->name('setting-rba.store');
        });
        Route::middleware('module.access:pengaturan.setting-rba,delete')->group(function () {
            Route::delete('/setting-rba/{settingRba}', [SettingRbaController::class, 'destroy'])->name('setting-rba.destroy');
        });

        Route::middleware('module.access:pengaturan.preferensi,view')->group(function () {
            Route::get('/preferensi', [PreferensiController::class, 'index'])->name('preferensi.index');
        });
        Route::middleware('module.access:pengaturan.preferensi,update')->group(function () {
            Route::post('/preferensi', [PreferensiController::class, 'update'])->name('preferensi.update');
        });

        Route::middleware('module.access:pengaturan.pengguna,view')->group(function () {
            Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        });
        Route::middleware('module.access:pengaturan.pengguna,create')->group(function () {
            Route::get('/pengguna/create', [PenggunaController::class, 'create'])->name('pengguna.create');
            Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        });
        Route::middleware('module.access:pengaturan.pengguna,update')->group(function () {
            Route::get('/pengguna/{user}/edit', [PenggunaController::class, 'edit'])->name('pengguna.edit');
            Route::put('/pengguna/{user}', [PenggunaController::class, 'update'])->name('pengguna.update');
        });
        Route::middleware('module.access:pengaturan.pengguna,delete')->group(function () {
            Route::delete('/pengguna/{user}', [PenggunaController::class, 'destroy'])->name('pengguna.destroy');
        });

        Route::middleware('module.access:pengaturan.role-akses,view')->group(function () {
            Route::get('/role-akses', [RoleAksesController::class, 'index'])->name('role-akses.index');
        });
        Route::middleware('module.access:pengaturan.role-akses,create')->group(function () {
            Route::get('/role-akses/create', [RoleAksesController::class, 'create'])->name('role-akses.create');
            Route::post('/role-akses', [RoleAksesController::class, 'store'])->name('role-akses.store');
        });
        Route::middleware('module.access:pengaturan.role-akses,update')->group(function () {
            Route::get('/role-akses/{role}/edit', [RoleAksesController::class, 'edit'])->name('role-akses.edit');
            Route::put('/role-akses/{role}', [RoleAksesController::class, 'update'])->name('role-akses.update');
        });

        Route::middleware('module.access:pengaturan.konversi-file,view')->group(function () {
            Route::get('/konversi-file', [KonversiFileController::class, 'index'])->name('konversi-file.index');
        });
        Route::middleware('module.access:pengaturan.konversi-file,create')->group(function () {
            Route::post('/konversi-file/csv-ke-xlsx', [KonversiFileController::class, 'convertCsvToXlsx'])->name('konversi-file.csv-ke-xlsx');
        });
    });
});
