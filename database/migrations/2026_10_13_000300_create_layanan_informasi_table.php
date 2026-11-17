<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('layanan_informasi')) {
            return;
        }

        Schema::create('layanan_informasi', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('judul', 255);
            $table->text('isi');
            $table->string('tipe', 30)->default('info');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('tampilkan_popup')->default(false);
            $table->boolean('status_aktif')->default(true);
            $table->integer('urutan')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status_aktif', 'tanggal_mulai', 'tanggal_selesai'], 'idx_info_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan_informasi');
    }
};
