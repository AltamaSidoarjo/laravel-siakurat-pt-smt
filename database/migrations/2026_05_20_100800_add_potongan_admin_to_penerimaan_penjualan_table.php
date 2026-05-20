<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penerimaan_penjualan', function (Blueprint $table) {
            $table->decimal('potongan_admin', 15, 2)->default(0)->after('jumlah_pembayaran');
            $table->unsignedBigInteger('akun_potongan_admin_id')->nullable()->after('akun_piutang_id');
            $table->foreign('akun_potongan_admin_id')
                ->references('id')
                ->on('coa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penerimaan_penjualan', function (Blueprint $table) {
            $table->dropForeign(['akun_potongan_admin_id']);
            $table->dropColumn(['potongan_admin', 'akun_potongan_admin_id']);
        });
    }
};
