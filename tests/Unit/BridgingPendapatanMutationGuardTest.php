<?php

namespace Tests\Unit;

use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\LogHapusImportPendapatan;
use App\Models\PenerimaanPenjualanRinci;
use App\Models\SimrsImportPendapatan;
use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPendapatanMutationGuardTest extends TestCase
{
    private BridgingPendapatanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('log_hapus_import_pendapatan');
        Schema::dropIfExists('simrs_import_pendapatan');
        Schema::dropIfExists('penerimaan_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::enableForeignKeyConstraints();

        Schema::create('faktur_penjualan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomor_faktur')->unique();
            $table->string('nomer_rawat')->nullable();
            $table->unsignedInteger('pelanggan_id')->nullable();
            $table->unsignedInteger('akun_piutang_id')->nullable();
            $table->date('tanggal_faktur')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->tinyInteger('status_proses')->default(0);
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->unsignedInteger('coa_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('penerimaan_penjualan_id')->nullable();
            $table->unsignedInteger('faktur_penjualan_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('simrs_import_pendapatan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer_billing');
            $table->string('import_ke')->nullable();
            $table->decimal('total_tagihan', 15, 2)->default(0);
        });

        Schema::create('log_hapus_import_pendapatan', function (Blueprint $table): void {
            $table->increments('log_hapus_import_pendapatan_id');
            $table->string('nomer');
            $table->string('dihapus_oleh');
            $table->dateTime('created_at');
            $table->string('sumber_transaksi')->nullable();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coa_id')->nullable();
            $table->unsignedInteger('sumber_id');
            $table->string('sumber_transaksi');
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul')->nullable();
            $table->string('tipe')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        FakturPenjualan::unguard();
        FakturPenjualanRinci::unguard();
        SimrsImportPendapatan::unguard();
        LogHapusImportPendapatan::unguard();

        $bukuBesarService = new BukuBesarService();
        $logAktifitasService = new LogAktifitasService();
        $this->service = new BridgingPendapatanService($bukuBesarService, $logAktifitasService);
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('log_hapus_import_pendapatan');
        Schema::dropIfExists('simrs_import_pendapatan');
        Schema::dropIfExists('penerimaan_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_hapus_banyak_berhasil_ketika_faktur_belum_memiliki_pembayaran(): void
    {
        $noRawat = 'BILL-UNPAID-001';

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $noRawat,
            'import_ke' => 'Invoice Pendapatan',
            'total_tagihan' => 350000,
        ]);

        $faktur = FakturPenjualan::query()->create([
            'nomor_faktur' => $noRawat,
            'nomer_rawat' => $noRawat,
            'grandtotal' => 350000,
            'sudah_terbayar' => 0,
        ]);

        FakturPenjualanRinci::query()->create([
            'faktur_penjualan_id' => $faktur->id,
            'subtotal' => 350000,
        ]);

        DB::table('bukubesar')->insert([
            'sumber_id' => $faktur->id,
            'sumber_transaksi' => 'Invoice Pendapatan',
        ]);

        $hasil = $this->service->hapusBanyak([$noRawat], 'tester');

        $this->assertCount(1, $hasil);
        $this->assertTrue($hasil[0]['berhasil']);
        $this->assertNull($hasil[0]['alasan_gagal']);

        $this->assertDatabaseMissing('faktur_penjualan', ['nomor_faktur' => $noRawat]);
        $this->assertDatabaseMissing('faktur_penjualan_rinci', ['faktur_penjualan_id' => $faktur->id]);
        $this->assertDatabaseMissing('simrs_import_pendapatan', ['nomer_billing' => $noRawat]);
        $this->assertDatabaseMissing('bukubesar', [
            'sumber_id' => $faktur->id,
            'sumber_transaksi' => 'Invoice Pendapatan',
        ]);
        $this->assertDatabaseHas('log_hapus_import_pendapatan', [
            'nomer' => $noRawat,
            'dihapus_oleh' => 'tester',
            'sumber_transaksi' => 'Invoice Pendapatan',
        ]);
    }

    public function test_hapus_banyak_ditolak_dan_rollback_ketika_faktur_memiliki_sudah_terbayar_positif(): void
    {
        $noRawat = 'BILL-PAID-001';

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $noRawat,
            'import_ke' => 'Invoice Pendapatan',
            'total_tagihan' => 500000,
        ]);

        $faktur = FakturPenjualan::query()->create([
            'nomor_faktur' => $noRawat,
            'nomer_rawat' => $noRawat,
            'grandtotal' => 500000,
            'sudah_terbayar' => 200000,
        ]);

        FakturPenjualanRinci::query()->create([
            'faktur_penjualan_id' => $faktur->id,
            'subtotal' => 500000,
        ]);

        DB::table('bukubesar')->insert([
            'sumber_id' => $faktur->id,
            'sumber_transaksi' => 'Invoice Pendapatan',
        ]);

        $hasil = $this->service->hapusBanyak([$noRawat], 'tester');

        $this->assertCount(1, $hasil);
        $this->assertFalse($hasil[0]['berhasil']);
        $this->assertStringContainsString('sudah memiliki transaksi penerimaan/pembayaran', (string) $hasil[0]['alasan_gagal']);

        // Data faktur, rincian, dan import HARUS tetap utuh (rollback)
        $this->assertDatabaseHas('faktur_penjualan', ['nomor_faktur' => $noRawat]);
        $this->assertDatabaseHas('faktur_penjualan_rinci', ['faktur_penjualan_id' => $faktur->id]);
        $this->assertDatabaseHas('simrs_import_pendapatan', ['nomer_billing' => $noRawat]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $faktur->id,
            'sumber_transaksi' => 'Invoice Pendapatan',
        ]);
        $this->assertDatabaseMissing('log_hapus_import_pendapatan', ['nomer' => $noRawat]);
    }

    public function test_hapus_banyak_ditolak_ketika_faktur_memiliki_relasi_penerimaan_meski_sudah_terbayar_nol(): void
    {
        $noRawat = 'BILL-REL-PAID-001';

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $noRawat,
            'import_ke' => 'Invoice Pendapatan',
            'total_tagihan' => 400000,
        ]);

        $faktur = FakturPenjualan::query()->create([
            'nomor_faktur' => $noRawat,
            'nomer_rawat' => $noRawat,
            'grandtotal' => 400000,
            'sudah_terbayar' => 0,
        ]);

        PenerimaanPenjualanRinci::query()->create([
            'faktur_penjualan_id' => $faktur->id,
            'nominal_bayar' => 400000,
        ]);

        $hasil = $this->service->hapusBanyak([$noRawat], 'tester');

        $this->assertCount(1, $hasil);
        $this->assertFalse($hasil[0]['berhasil']);
        $this->assertStringContainsString('sudah memiliki transaksi penerimaan/pembayaran', (string) $hasil[0]['alasan_gagal']);

        $this->assertDatabaseHas('faktur_penjualan', ['nomor_faktur' => $noRawat]);
        $this->assertDatabaseHas('simrs_import_pendapatan', ['nomer_billing' => $noRawat]);
    }
}
