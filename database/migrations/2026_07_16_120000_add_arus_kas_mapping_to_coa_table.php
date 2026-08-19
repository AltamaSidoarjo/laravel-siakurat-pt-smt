<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coa', function (Blueprint $table) {
            $table->string('arus_kas_aktivitas', 20)->nullable()->after('tipe_coa');
            $table->string('arus_kas_kelompok', 150)->nullable()->after('arus_kas_aktivitas');
        });

        $mappings = [
            ['types' => ['Pendapatan', 'Pendapatan lain'], 'aktivitas' => 'operasi', 'kelompok' => 'Penerimaan dari pasien, pelanggan, dan pendapatan operasional'],
            ['types' => ['Beban'], 'aktivitas' => 'operasi', 'kelompok' => 'Pembayaran beban operasional'],
            ['types' => ['Piutang'], 'aktivitas' => 'operasi', 'kelompok' => 'Penerimaan dari pasien dan pelanggan'],
            ['types' => ['Persediaan'], 'aktivitas' => 'operasi', 'kelompok' => 'Pembayaran kepada pemasok'],
            ['types' => ['Aktiva tetap', 'Investasi'], 'aktivitas' => 'investasi', 'kelompok' => 'Perolehan dan pelepasan aset atau investasi'],
            ['types' => ['Ekuitas'], 'aktivitas' => 'pendanaan', 'kelompok' => 'Setoran atau distribusi modal'],
        ];

        foreach ($mappings as $mapping) {
            DB::table('coa')
                ->whereIn(DB::raw('LOWER(tipe_coa)'), array_map('strtolower', $mapping['types']))
                ->update([
                    'arus_kas_aktivitas' => $mapping['aktivitas'],
                    'arus_kas_kelompok' => $mapping['kelompok'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('coa', function (Blueprint $table) {
            $table->dropColumn(['arus_kas_aktivitas', 'arus_kas_kelompok']);
        });
    }
};
