<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->dropUnique('mapping_penjamin_piutang_penjamin_id_unique');
            $table->string('jenis_layanan', 20)->default('rawat_jalan')->after('nama_penjamin');
            $table->unique(['penjamin_id', 'jenis_layanan'], 'mapping_penjamin_layanan_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->dropUnique('mapping_penjamin_layanan_unique');
            $table->unique('penjamin_id');
        });

        Schema::table('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->dropColumn('jenis_layanan');
        });
    }
};
