<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Bukubesar\CoaController;
use App\Http\Controllers\Bukubesar\JurnalUmumController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kasbank\KasbankPenerimaanController;
use App\Http\Controllers\ModulePageController;
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
        Route::get('/pembayaran', fn (ModulePageController $controller) => $controller->show('Kasbank', 'Pembayaran'))
            ->name('pembayaran.index');
    });

    Route::prefix('bridging')->name('bridging.')->group(function () {
        Route::get('/pendapatan', fn (ModulePageController $controller) => $controller->show('Bridging', 'Pendapatan'))
            ->name('pendapatan.index');
        Route::get('/pendapatan-obat', fn (ModulePageController $controller) => $controller->show('Bridging', 'Pendapatan Obat'))
            ->name('pendapatan-obat.index');
        Route::get('/pembelian', fn (ModulePageController $controller) => $controller->show('Bridging', 'Pembelian'))
            ->name('pembelian.index');
    });

    Route::prefix('pendapatan')->name('pendapatan.')->group(function () {
        Route::get('/invoice', fn (ModulePageController $controller) => $controller->show('Pendapatan', 'Invoice Pendapatan'))
            ->name('invoice.index');
        Route::get('/penerimaan', fn (ModulePageController $controller) => $controller->show('Pendapatan', 'Penerimaan Pendapatan'))
            ->name('penerimaan.index');
    });

    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/invoice', fn (ModulePageController $controller) => $controller->show('Pembelian', 'Invoice Pembelian'))
            ->name('invoice.index');
        Route::get('/pembayaran', fn (ModulePageController $controller) => $controller->show('Pembelian', 'Pembayaran Pembelian'))
            ->name('pembayaran.index');
    });

    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/keuangan', fn (ModulePageController $controller) => $controller->show('Laporan', 'Laporan Keuangan'))
            ->name('keuangan.index');
        Route::get('/pendapatan', fn (ModulePageController $controller) => $controller->show('Laporan', 'Laporan Pendapatan'))
            ->name('pendapatan.index');
    });

    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/mapping-pendapatan', fn (ModulePageController $controller) => $controller->show('Pengaturan', 'Mapping Pendapatan'))
            ->name('mapping-pendapatan.index');
        Route::get('/mapping-general', fn (ModulePageController $controller) => $controller->show('Pengaturan', 'Mapping General'))
            ->name('mapping-general.index');
        Route::get('/setting-rba', fn (ModulePageController $controller) => $controller->show('Pengaturan', 'Setting RBA'))
            ->name('setting-rba.index');
        Route::get('/preferensi', fn (ModulePageController $controller) => $controller->show('Pengaturan', 'Preferensi'))
            ->name('preferensi.index');
        Route::get('/pengguna', fn (ModulePageController $controller) => $controller->show('Pengaturan', 'Pengguna'))
            ->name('pengguna.index');
    });
});
