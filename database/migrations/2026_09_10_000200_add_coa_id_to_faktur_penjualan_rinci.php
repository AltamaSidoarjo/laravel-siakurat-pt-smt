<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->foreignId('coa_id')
                ->nullable()
                ->after('kode_proyek')
                ->constrained('coa')
                ->restrictOnDelete();
        });

        DB::table('faktur_penjualan_rinci')
            ->whereNull('coa_id')
            ->orderBy('id')
            ->get(['id', 'faktur_penjualan_id', 'subtotal', 'catatan'])
            ->each(function (object $detail): void {
                $subtotal = (float) $detail->subtotal;
                $tipeMutasi = $subtotal < 0 ? 'D' : 'K';

                $candidates = DB::table('bukubesar')
                    ->where('sumber_transaksi', 'Invoice Pendapatan')
                    ->where('sumber_id', $detail->faktur_penjualan_id)
                    ->where('tipe_mutasi', $tipeMutasi)
                    ->where('nominal', abs($subtotal))
                    ->when(
                        $detail->catatan === null,
                        fn ($query) => $query->whereNull('keterangan'),
                        fn ($query) => $query->where('keterangan', $detail->catatan),
                    )
                    ->distinct()
                    ->pluck('coa_id');

                if ($candidates->count() === 1) {
                    DB::table('faktur_penjualan_rinci')
                        ->where('id', $detail->id)
                        ->update(['coa_id' => $candidates->first()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coa_id');
        });
    }
};
