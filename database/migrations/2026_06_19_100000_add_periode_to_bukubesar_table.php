<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bukubesar')) {
            return;
        }

        Schema::table('bukubesar', function (Blueprint $table) {
            if (! Schema::hasColumn('bukubesar', 'periode_tahun')) {
                $table->unsignedSmallInteger('periode_tahun')->nullable()->after('tanggal');
            }

            if (! Schema::hasColumn('bukubesar', 'periode_bulan')) {
                $table->unsignedTinyInteger('periode_bulan')->nullable()->after('periode_tahun');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bukubesar')) {
            return;
        }

        Schema::table('bukubesar', function (Blueprint $table) {
            if (Schema::hasColumn('bukubesar', 'periode_bulan')) {
                $table->dropColumn('periode_bulan');
            }

            if (Schema::hasColumn('bukubesar', 'periode_tahun')) {
                $table->dropColumn('periode_tahun');
            }
        });
    }
};
