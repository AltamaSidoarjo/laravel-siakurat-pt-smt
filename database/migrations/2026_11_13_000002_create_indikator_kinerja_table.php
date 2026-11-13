<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator_kinerja')) {
            return;
        }

        Schema::create('indikator_kinerja', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->string('satuan', 20)->default('%');
            $table->string('tipe_kalkulasi', 30)->default('RASIO');
            $table->string('basis_pembilang', 30)->nullable()->default('COA');
            $table->string('basis_penyebut', 30)->nullable()->default('COA');
            $table->unsignedInteger('variabel_manual_id')->nullable();
            $table->text('definisi_operasional')->nullable();
            $table->integer('urutan')->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->string('pola_evaluasi', 30)->default('HIGHER_IS_BETTER');
            $table->decimal('target_hijau_min', 15, 2)->nullable();
            $table->decimal('target_hijau_max', 15, 2)->nullable();
            $table->decimal('target_kuning_min', 15, 2)->nullable();
            $table->decimal('target_kuning_max', 15, 2)->nullable();
            $table->decimal('target_merah_min', 15, 2)->nullable();
            $table->decimal('target_merah_max', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('variabel_manual_id')
                ->references('id')
                ->on('indikator_kinerja_variabel_manual')
                ->nullOnDelete();

            $table->index(['status_aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_kinerja');
    }
};
