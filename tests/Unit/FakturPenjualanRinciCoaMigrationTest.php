<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FakturPenjualanRinciCoaMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->string('kode_proyek')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->string('catatan')->nullable();
        });
        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id');
            $table->string('sumber_transaksi');
            $table->string('tipe_mutasi', 1);
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan')->nullable();
        });

        DB::table('coa')->insert([['id' => 1], ['id' => 2]]);
        DB::table('faktur_penjualan_rinci')->insert([
            ['id' => 1, 'faktur_penjualan_id' => 10, 'subtotal' => 100, 'catatan' => 'Unik kredit'],
            ['id' => 2, 'faktur_penjualan_id' => 10, 'subtotal' => 200, 'catatan' => 'Ambigu'],
            ['id' => 3, 'faktur_penjualan_id' => 10, 'subtotal' => -50, 'catatan' => 'Unik debit'],
            ['id' => 4, 'faktur_penjualan_id' => 10, 'subtotal' => 75, 'catatan' => null],
        ]);
        DB::table('bukubesar')->insert([
            $this->ledger(1, 'K', 100, 'Unik kredit'),
            $this->ledger(1, 'K', 200, 'Ambigu'),
            $this->ledger(2, 'K', 200, 'Ambigu'),
            $this->ledger(2, 'D', 50, 'Unik debit'),
        ]);
    }

    public function test_migration_backfills_only_unique_matches_and_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_09_10_000200_add_coa_id_to_faktur_penjualan_rinci.php');

        $migration->up();

        $this->assertSame(1, DB::table('faktur_penjualan_rinci')->where('id', 1)->value('coa_id'));
        $this->assertNull(DB::table('faktur_penjualan_rinci')->where('id', 2)->value('coa_id'));
        $this->assertSame(2, DB::table('faktur_penjualan_rinci')->where('id', 3)->value('coa_id'));
        $this->assertNull(DB::table('faktur_penjualan_rinci')->where('id', 4)->value('coa_id'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('faktur_penjualan_rinci', 'coa_id'));
    }

    private function ledger(int $coaId, string $mutation, float $nominal, ?string $note): array
    {
        return [
            'coa_id' => $coaId,
            'sumber_id' => 10,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'tipe_mutasi' => $mutation,
            'nominal' => $nominal,
            'keterangan' => $note,
        ];
    }
}
