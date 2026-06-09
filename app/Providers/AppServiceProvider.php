<?php

namespace App\Providers;

use App\Models\PreferensiPerusahaan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $preferensi = Schema::hasTable('preferensi_perusahaan')
                ? PreferensiPerusahaan::query()->first()
                : null;

            $view->with('brandCompanyName', filled($preferensi?->nama_perusahaan)
                ? $preferensi->nama_perusahaan
                : config('siakurat.rs_name'));
        });
    }
}
