<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penerimaan_penjualan', function (Blueprint $table) {
            $table->dropForeign(['akun_potongan_admin_id']);
            $table->renameColumn('potongan_admin', 'selisih_tarif');
            $table->renameColumn('akun_potongan_admin_id', 'akun_selisih_tarif_id');
            $table->foreign('akun_selisih_tarif_id')
                ->references('id')
                ->on('coa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penerimaan_penjualan', function (Blueprint $table) {
            $table->dropForeign(['akun_selisih_tarif_id']);
            $table->renameColumn('selisih_tarif', 'potongan_admin');
            $table->renameColumn('akun_selisih_tarif_id', 'akun_potongan_admin_id');
            $table->foreign('akun_potongan_admin_id')
                ->references('id')
                ->on('coa')
                ->nullOnDelete();
        });
    }
};
