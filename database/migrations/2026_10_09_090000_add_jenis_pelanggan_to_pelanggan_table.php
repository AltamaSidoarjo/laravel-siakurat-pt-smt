<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pelanggan')) {
            return;
        }

        if (! Schema::hasColumn('pelanggan', 'jenis_pelanggan')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->string('jenis_pelanggan', 50)->nullable()->after('nama_pelanggan');
            });
        }

        if (Schema::hasTable('faktur_penjualan') && Schema::hasTable('simrs_import_pendapatan')) {
            DB::table('pelanggan as p')
                ->whereNull('p.jenis_pelanggan')
                ->whereIn('p.id', function ($query) {
                    $query->select('fp.pelanggan_id')
                        ->from('faktur_penjualan as fp')
                        ->join('simrs_import_pendapatan as sip', function ($join) {
                            $join->whereRaw('BINARY sip.nomer_billing = BINARY fp.nomer_rawat');
                        })
                        ->where('sip.import_ke', 'Invoice Pendapatan');
                })
                ->update(['jenis_pelanggan' => 'Penjamin']);
        }

        if (Schema::hasTable('faktur_penjualan') && Schema::hasTable('simrs_import_pendapatan_jual_obat')) {
            DB::table('pelanggan as p')
                ->whereNull('p.jenis_pelanggan')
                ->whereIn('p.id', function ($query) {
                    $query->select('fp.pelanggan_id')
                        ->from('faktur_penjualan as fp')
                        ->join('simrs_import_pendapatan_jual_obat as sip', function ($join) {
                            $join->whereRaw('BINARY sip.nomer_transaksi = BINARY fp.nomor_faktur');
                        })
                        ->where('sip.import_ke', 'Invoice Pendapatan');
                })
                ->update(['jenis_pelanggan' => 'Obat & BHP']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pelanggan') && Schema::hasColumn('pelanggan', 'jenis_pelanggan')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->dropColumn('jenis_pelanggan');
            });
        }
    }
};
