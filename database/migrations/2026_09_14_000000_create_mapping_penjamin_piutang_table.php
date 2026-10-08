<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can leave the table behind when adding a foreign key fails.
        // This migration creates a new, initially empty mapping table, so clean up that partial result on retry.
        Schema::dropIfExists('mapping_penjamin_piutang');

        Schema::create('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->id();
            $table->string('penjamin_id', 100);
            $table->string('nama_penjamin');
            $table->string('jenis_layanan', 20)->default('rawat_jalan');
            $table->unsignedBigInteger('coa_id');
            $table->timestamps();

            $table->unique(['penjamin_id', 'jenis_layanan'], 'mapping_penjamin_layanan_unique');
            $table->foreign('coa_id')->references('id')->on('coa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_penjamin_piutang');
    }
};
