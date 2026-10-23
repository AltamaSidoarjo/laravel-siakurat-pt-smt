<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_bridging_obats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pendapatan_obat_coa_id');
            $table->unsignedBigInteger('hpp_obat_coa_id');
            $table->unsignedBigInteger('persediaan_obat_coa_id');
            $table->unsignedBigInteger('piutang_obat_coa_id');
            $table->unsignedBigInteger('kas_uang_muka_coa_id')->nullable();
            $table->timestamps();

            $table->foreign('pendapatan_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('hpp_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('persediaan_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('piutang_obat_coa_id')->references('id')->on('coa')->restrictOnDelete();
            $table->foreign('kas_uang_muka_coa_id')->references('id')->on('coa')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_bridging_obats');
    }
};
