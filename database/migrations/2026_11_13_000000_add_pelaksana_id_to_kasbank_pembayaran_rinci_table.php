<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasbank_pembayaran_rinci', function (Blueprint $table): void {
            $table->foreignId('pelaksana_id')
                ->nullable()
                ->after('coa_id')
                ->constrained('pelaksana')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kasbank_pembayaran_rinci', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pelaksana_id');
        });
    }
};
