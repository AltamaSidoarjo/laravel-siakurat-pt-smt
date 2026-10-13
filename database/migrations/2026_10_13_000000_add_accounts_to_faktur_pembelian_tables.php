<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faktur_pembelian', function (Blueprint $table): void {
            $table->foreignId('akun_hutang_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('coa')
                ->nullOnDelete();
        });

        Schema::table('faktur_pembelian_rinci', function (Blueprint $table): void {
            $table->foreignId('coa_id')
                ->nullable()
                ->after('faktur_pembelian_id')
                ->constrained('coa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faktur_pembelian_rinci', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coa_id');
        });

        Schema::table('faktur_pembelian', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('akun_hutang_id');
        });
    }
};
