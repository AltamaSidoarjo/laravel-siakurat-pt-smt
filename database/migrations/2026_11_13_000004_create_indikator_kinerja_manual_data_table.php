<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator_kinerja_manual_data')) {
            return;
        }

        Schema::create('indikator_kinerja_manual_data', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('variabel_id');
            $table->integer('tahun');
            $table->tinyInteger('bulan');
            $table->decimal('nilai', 15, 2)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->foreign('variabel_id')
                ->references('id')
                ->on('indikator_kinerja_variabel_manual')
                ->cascadeOnDelete();

            $table->unique(['variabel_id', 'tahun', 'bulan'], 'uk_variabel_tahun_bulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_kinerja_manual_data');
    }
};
