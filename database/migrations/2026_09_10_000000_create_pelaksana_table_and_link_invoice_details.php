<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->id();
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->foreignId('pelaksana_id')
                ->nullable()
                ->after('faktur_penjualan_id')
                ->constrained('pelaksana')
                ->nullOnDelete();
            $table->string('kode_proyek')->nullable()->after('pelaksana_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pelaksana_id');
            $table->dropColumn('kode_proyek');
        });

        Schema::dropIfExists('pelaksana');
    }
};
