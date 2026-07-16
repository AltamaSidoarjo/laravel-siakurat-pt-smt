<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mappings = [
            ['types' => ['Beban lain', 'Beban Pokok Penjualan'], 'aktivitas' => 'operasi', 'kelompok' => 'Pembayaran beban operasional'],
            ['types' => ['Akun Piutang', 'Piutang Usaha'], 'aktivitas' => 'operasi', 'kelompok' => 'Penerimaan dari pasien dan pelanggan'],
            ['types' => ['Aset Tetap'], 'aktivitas' => 'investasi', 'kelompok' => 'Perolehan dan pelepasan aset tetap'],
        ];

        foreach ($mappings as $mapping) {
            DB::table('coa')
                ->whereNull('arus_kas_aktivitas')
                ->whereIn(DB::raw('LOWER(tipe_coa)'), array_map('strtolower', $mapping['types']))
                ->update([
                    'arus_kas_aktivitas' => $mapping['aktivitas'],
                    'arus_kas_kelompok' => $mapping['kelompok'],
                ]);
        }
    }

    public function down(): void
    {
        DB::table('coa')
            ->whereIn('arus_kas_kelompok', [
                'Pembayaran beban operasional',
                'Penerimaan dari pasien dan pelanggan',
                'Perolehan dan pelepasan aset tetap',
            ])
            ->whereIn(DB::raw('LOWER(tipe_coa)'), [
                'beban lain',
                'beban pokok penjualan',
                'akun piutang',
                'piutang usaha',
                'aset tetap',
            ])
            ->update([
                'arus_kas_aktivitas' => null,
                'arus_kas_kelompok' => null,
            ]);
    }
};
