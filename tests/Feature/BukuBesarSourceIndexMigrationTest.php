<?php

namespace Tests\Feature;

use App\Models\BukuBesar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BukuBesarSourceIndexMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function test_migration_up_and_down_creates_and_drops_source_and_number_indexes(): void
    {
        $migration = require database_path('migrations/2026_11_24_083000_add_source_and_number_indexes_to_bukubesar_table.php');

        // Pastikan sebelum migration dijalankan, index belum ada
        $this->assertFalse($this->indexExists('bukubesar', 'bukubesar_sumber_transaksi_sumber_id_index'));
        $this->assertFalse($this->indexExists('bukubesar', 'bukubesar_nomer_index'));

        // Jalankan up()
        $migration->up();

        // Pastikan kedua index sekarang sudah terbentuk
        $this->assertTrue($this->indexExists('bukubesar', 'bukubesar_sumber_transaksi_sumber_id_index'));
        $this->assertTrue($this->indexExists('bukubesar', 'bukubesar_nomer_index'));

        // Cek query forSource dan nomer bekerja normal dengan index
        BukuBesar::query()->create([
            'sumber_transaksi' => 'Jurnal Umum',
            'sumber_id' => 99,
            'nomer' => 'JU-TEST-001',
            'nominal' => 500000,
        ]);

        $row = BukuBesar::query()->forSource('Jurnal Umum', 99)->first();
        $this->assertNotNull($row);
        $this->assertSame('JU-TEST-001', $row->nomer);

        $rowByNomer = BukuBesar::query()->where('nomer', 'JU-TEST-001')->first();
        $this->assertNotNull($rowByNomer);
        $this->assertSame(99, $rowByNomer->sumber_id);

        // Uji idempotency: menjalankan up() kembali tidak melempar duplicate index error
        $migration->up();
        $this->assertTrue($this->indexExists('bukubesar', 'bukubesar_sumber_transaksi_sumber_id_index'));
        $this->assertTrue($this->indexExists('bukubesar', 'bukubesar_nomer_index'));

        // Jalankan down()
        $migration->down();

        // Pastikan kedua index sudah terhapus
        $this->assertFalse($this->indexExists('bukubesar', 'bukubesar_sumber_transaksi_sumber_id_index'));
        $this->assertFalse($this->indexExists('bukubesar', 'bukubesar_nomer_index'));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'mysql' => collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn (object $index) => ($index->Key_name ?? null) === $indexName),
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $index) => ($index->name ?? null) === $indexName),
            default => false,
        };
    }
}
