<?php

namespace Tests\Feature;

use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\PenerimaanPenjualanRinci;
use App\Models\SimrsImportPendapatan;
use App\Models\SimrsImportPendapatanJualObat;
use App\Services\Bridging\BridgingPendapatanObatService;
use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPendapatanHapusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('penerimaan_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('simrs_import_pendapatan');
        Schema::dropIfExists('simrs_import_pendapatan_jual_obat');
        Schema::dropIfExists('log_hapus_import_pendapatan');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::enableForeignKeyConstraints();

        Schema::create('simrs_import_pendapatan', function (Blueprint $table) {
            $table->id();
            $table->string('nomer_billing');
            $table->date('tanggal_reg')->nullable();
            $table->string('user_importer')->nullable();
            $table->dateTime('import_time')->nullable();
            $table->string('import_ke')->nullable();
        });

        Schema::create('simrs_import_pendapatan_jual_obat', function (Blueprint $table) {
            $table->id();
            $table->string('nomer_transaksi');
            $table->date('tanggal')->nullable();
            $table->string('nama_pelanggan')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('import_ke')->nullable();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->unsignedBigInteger('ppn_id')->nullable();
            $table->unsignedBigInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->integer('status_proses')->default(0);
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('kuantitas', 15, 2)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penerimaan_penjualan_id')->nullable();
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

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

        Schema::create('log_hapus_import_pendapatan', function (Blueprint $table) {
            $table->id('log_hapus_import_pendapatan_id');
            $table->string('nomer');
            $table->string('dihapus_oleh')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('sumber_transaksi')->nullable();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_user')->nullable();
            $table->string('modul')->nullable();
            $table->string('tipe')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nomer')->nullable();
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });

        FakturPenjualan::unguard();
        FakturPenjualanRinci::unguard();
        PenerimaanPenjualanRinci::unguard();
        SimrsImportPendapatan::unguard();
        SimrsImportPendapatanJualObat::unguard();
    }

    public function test_hapus_banyak_bridging_pendapatan_menolak_faktur_yang_sudah_ada_penerimaan(): void
    {
        $noRawat = '2026/05/10/000001';

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $noRawat,
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $invoice = FakturPenjualan::query()->create([
            'nomor_faktur' => $noRawat,
            'grandtotal' => 1000000,
            'sudah_terbayar' => 500000,
        ]);

        FakturPenjualanRinci::query()->create([
            'faktur_penjualan_id' => (int) $invoice->id,
            'harga' => 1000000,
            'kuantitas' => 1,
            'subtotal' => 1000000,
        ]);

        PenerimaanPenjualanRinci::query()->create([
            'faktur_penjualan_id' => (int) $invoice->id,
            'nominal_bayar' => 500000,
        ]);

        DB::table('bukubesar')->insert([
            'sumber_id' => (int) $invoice->id,
            'nomer' => $noRawat,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'nominal' => 1000000,
        ]);

        $service = app(BridgingPendapatanService::class);
        $results = $service->hapusBanyak([$noRawat], 'tester');

        $this->assertCount(1, $results);
        $this->assertSame($noRawat, $results[0]['no_rawat']);
        $this->assertFalse($results[0]['berhasil']);
        $this->assertSame('Sudah ada penerimaan pendapatan untuk faktur penjualan nomor: '.$noRawat, $results[0]['alasan_gagal']);

        // Pastikan faktur dan buku besar tidak terhapus (rollback)
        $this->assertDatabaseHas('faktur_penjualan', ['id' => $invoice->id]);
        $this->assertDatabaseHas('bukubesar', ['sumber_id' => $invoice->id]);
        $this->assertDatabaseHas('simrs_import_pendapatan', ['nomer_billing' => $noRawat]);
    }

    public function test_hapus_banyak_bridging_pendapatan_berhasil_jika_belum_ada_penerimaan_meski_ada_kasir_simrs(): void
    {
        $noRawat = '2026/05/10/000002';

        SimrsImportPendapatan::query()->create([
            'nomer_billing' => $noRawat,
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $invoice = FakturPenjualan::query()->create([
            'nomor_faktur' => $noRawat,
            'grandtotal' => 200000,
            'sudah_terbayar' => 200000, // Bayar langsung di kasir SIMRS, tanpa baris di penerimaan_penjualan_rinci
        ]);

        FakturPenjualanRinci::query()->create([
            'faktur_penjualan_id' => (int) $invoice->id,
            'harga' => 200000,
            'kuantitas' => 1,
            'subtotal' => 200000,
        ]);

        DB::table('bukubesar')->insert([
            'sumber_id' => (int) $invoice->id,
            'nomer' => $noRawat,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'nominal' => 200000,
        ]);

        $service = app(BridgingPendapatanService::class);
        $results = $service->hapusBanyak([$noRawat], 'tester');

        $this->assertCount(1, $results);
        $this->assertSame($noRawat, $results[0]['no_rawat']);
        $this->assertTrue($results[0]['berhasil']);
        $this->assertNull($results[0]['alasan_gagal']);

        // Pastikan faktur, buku besar, dan log import berhasil dihapus
        $this->assertDatabaseMissing('faktur_penjualan', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('bukubesar', ['sumber_id' => $invoice->id]);
        $this->assertDatabaseMissing('simrs_import_pendapatan', ['nomer_billing' => $noRawat]);
        $this->assertDatabaseHas('log_hapus_import_pendapatan', ['nomer' => $noRawat]);
    }

    public function test_hapus_banyak_bridging_pendapatan_obat_menolak_faktur_yang_sudah_ada_penerimaan(): void
    {
        $noTransaksi = 'PJ-2026-0001';

        SimrsImportPendapatanJualObat::query()->create([
            'nomer_transaksi' => $noTransaksi,
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $invoice = FakturPenjualan::query()->create([
            'nomor_faktur' => $noTransaksi,
            'keterangan' => 'Bridging Pendapatan Obat no transaksi '.$noTransaksi,
            'grandtotal' => 150000,
            'sudah_terbayar' => 150000,
        ]);

        PenerimaanPenjualanRinci::query()->create([
            'faktur_penjualan_id' => (int) $invoice->id,
            'nominal_bayar' => 150000,
        ]);

        $service = app(BridgingPendapatanObatService::class);
        $results = $service->hapusBanyak([$noTransaksi], 'tester');

        $this->assertCount(1, $results);
        $this->assertSame($noTransaksi, $results[0]['nomer_transaksi']);
        $this->assertFalse($results[0]['berhasil']);
        $this->assertSame('Sudah ada penerimaan pendapatan untuk faktur penjualan nomor: '.$noTransaksi, $results[0]['alasan_gagal']);

        $this->assertDatabaseHas('faktur_penjualan', ['id' => $invoice->id]);
    }

    public function test_hapus_banyak_bridging_piutang_obat_menolak_faktur_yang_sudah_ada_penerimaan(): void
    {
        $noTransaksi = 'PIU-2026-0001';
        $nomorFakturPiutang = 'P-'.$noTransaksi;

        SimrsImportPendapatanJualObat::query()->create([
            'nomer_transaksi' => $noTransaksi,
            'import_ke' => 'Invoice Piutang Obat',
        ]);

        $invoice = FakturPenjualan::query()->create([
            'nomor_faktur' => $nomorFakturPiutang,
            'keterangan' => 'Bridging Piutang Obat & BHP '.$noTransaksi,
            'grandtotal' => 300000,
            'sudah_terbayar' => 300000,
        ]);

        PenerimaanPenjualanRinci::query()->create([
            'faktur_penjualan_id' => (int) $invoice->id,
            'nominal_bayar' => 300000,
        ]);

        $service = app(BridgingPendapatanObatService::class);
        $results = $service->hapusBanyak([$noTransaksi], 'tester');

        $this->assertCount(1, $results);
        $this->assertSame($noTransaksi, $results[0]['nomer_transaksi']);
        $this->assertFalse($results[0]['berhasil']);
        $this->assertSame('Sudah ada penerimaan pendapatan untuk faktur penjualan nomor: '.$nomorFakturPiutang, $results[0]['alasan_gagal']);

        $this->assertDatabaseHas('faktur_penjualan', ['id' => $invoice->id]);
    }
}
