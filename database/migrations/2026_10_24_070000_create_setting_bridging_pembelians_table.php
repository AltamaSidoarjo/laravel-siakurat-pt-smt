<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_bridging_pembelians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persediaan_obat_coa_id');
            $table->unsignedBigInteger('ppn_masukan_coa_id');
            $table->unsignedBigInteger('hutang_obat_coa_id');
            $table->unsignedBigInteger('persediaan_non_medis_coa_id');
            $table->unsignedBigInteger('hutang_non_medis_coa_id');
            $table->timestamps();

            $table->foreign('persediaan_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('ppn_masukan_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('hutang_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('persediaan_non_medis_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('hutang_non_medis_coa_id')->references('id')->on('coa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_bridging_pembelians');
    }
};
