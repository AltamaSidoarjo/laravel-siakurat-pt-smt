<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator_kinerja_variabel_manual')) {
            return;
        }

        Schema::create('indikator_kinerja_variabel_manual', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->string('satuan', 20);
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_kinerja_variabel_manual');
    }
};
