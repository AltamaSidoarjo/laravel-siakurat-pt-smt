<?php

namespace Tests\Unit;

use App\Models\BukuBesar;
use App\Services\Bukubesar\BukuBesarService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BukuBesarServicePenerimaanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bukubesar');
        Schema::enableForeignKeyConstraints();

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id');
            $table->date('tanggal');
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bukubesar');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_sync_without_selisih_creates_two_rows(): void
    {
        $service = new BukuBesarService();

        $service->syncFromPenerimaanPendapatan(
            penerimaanPenjualanId: 1,
            akunBankId: 10,
            akunPiutangId: 20,
            akunSelisihTarifId: null,
            nomer: 'PPD-001',
            tanggal: '2026-05-21',
            keterangan: null,
            jumlahPembayaran: 5_000_000,
            selisihTarif: 0,
        );

        $this->assertDatabaseCount('bukubesar', 2);
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 10, 'nominal' => 5_000_000, 'tipe_mutasi' => 'D']);
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 20, 'nominal' => 5_000_000, 'tipe_mutasi' => 'K']);
    }

    public function test_sync_with_selisih_creates_three_rows_with_correct_logic(): void
    {
        $service = new BukuBesarService();

        $service->syncFromPenerimaanPendapatan(
            penerimaanPenjualanId: 1,
            akunBankId: 10,
            akunPiutangId: 20,
            akunSelisihTarifId: 30,
            nomer: 'PPD-001',
            tanggal: '2026-05-21',
            keterangan: null,
            jumlahPembayaran: 5_000_000,
            selisihTarif: 500_000,
        );

        $this->assertDatabaseCount('bukubesar', 3);
        // Kasbank D = jumlah_pembayaran (full)
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 10, 'nominal' => 5_000_000, 'tipe_mutasi' => 'D']);
        // Piutang K = jumlah_pembayaran - selisih_tarif
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 20, 'nominal' => 4_500_000, 'tipe_mutasi' => 'K']);
        // Selisih Tarif K (bukan D)
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 30, 'nominal' => 500_000, 'tipe_mutasi' => 'K']);
    }

    public function test_sync_replaces_existing_rows_on_resync(): void
    {
        $service = new BukuBesarService();

        $service->syncFromPenerimaanPendapatan(1, 10, 20, null, 'PPD-001', '2026-05-21', null, 5_000_000, 0);
        $this->assertDatabaseCount('bukubesar', 2);

        $service->syncFromPenerimaanPendapatan(1, 10, 20, 30, 'PPD-001', '2026-05-21', null, 5_500_000, 500_000);
        $this->assertDatabaseCount('bukubesar', 3);
        $this->assertDatabaseHas('bukubesar', ['coa_id' => 10, 'nominal' => 5_500_000, 'tipe_mutasi' => 'D']);
    }
}
