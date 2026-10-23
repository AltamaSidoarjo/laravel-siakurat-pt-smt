<?php

namespace Tests\Unit;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\LogHapusImportPendapatan;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\Pelanggan;
use App\Models\SettingBridgingObat;
use App\Models\SimrsImportPendapatanJualObat;
use App\Services\Bridging\BridgingPendapatanObatService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPendapatanObatServiceTest extends TestCase
{
    private BridgingPendapatanObatService $service;
    private Coa $coaKas;
    private Coa $coaPendapatan;
    private Coa $coaHpp;
    private Coa $coaPersediaan;
    private Coa $coaPiutang;
    private Coa $coaUangMuka;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.simrs', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('simrs');

        $this->prepareTables();

        $this->service = new BridgingPendapatanObatService($this->createMock(LogAktifitasService::class));
    }

    private function prepareTables(): void
    {
        // 1. Setup local tables
        Schema::dropIfExists('simrs_import_pendapatan_jual_obat');
        Schema::dropIfExists('log_hapus_import_pendapatan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('pelanggan');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('mapping_lawan_pendapatan_simrs');
        Schema::dropIfExists('setting_bridging_obats');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->integer('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('setting_bridging_obats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pendapatan_obat_coa_id')->nullable();
            $table->unsignedInteger('hpp_obat_coa_id')->nullable();
            $table->unsignedInteger('persediaan_obat_coa_id')->nullable();
            $table->unsignedInteger('piutang_obat_coa_id')->nullable();
            $table->unsignedInteger('kas_uang_muka_coa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('mapping_lawan_pendapatan_simrs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_coa_simrs')->nullable();
            $table->string('nama_coa_simrs')->nullable();
            $table->unsignedInteger('coa_id');
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('kredit', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('kredit', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pelanggan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_pelanggan')->unique();
            $table->string('nama_pelanggan');
            $table->string('jenis_pelanggan')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pelanggan_id');
            $table->unsignedInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur')->unique();
            $table->date('tanggal_faktur');
            $table->date('tanggal_registrasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('grandtotal', 18, 2)->default(0);
            $table->decimal('sudah_terbayar', 18, 2)->default(0);
            $table->tinyInteger('status_proses')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->string('nomer_rekam_medis')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->decimal('harga', 18, 2)->default(0);
            $table->decimal('kuantitas', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->integer('periode_tahun');
            $table->integer('periode_bulan');
            $table->string('nomer');
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('tipe_mutasi', 1);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('simrs_import_pendapatan_jual_obat', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer_transaksi');
            $table->date('tanggal');
            $table->string('nama_pelanggan')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('jenis_jual')->nullable();
            $table->decimal('ongkir', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->string('kode_gudang')->nullable();
            $table->string('kode_rekening')->nullable();
            $table->string('nama_rekening')->nullable();
            $table->decimal('grandtotal', 18, 2)->default(0);
            $table->string('import_ke');
            $table->timestamps();
        });

        Schema::create('log_hapus_import_pendapatan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer');
            $table->string('dihapus_oleh');
            $table->string('sumber_transaksi');
            $table->timestamps();
        });

        // 2. Setup SIMRS tables
        Schema::connection('simrs')->create('penjualan', function (Blueprint $table) {
            $table->string('nota_jual')->primary();
            $table->date('tgl_jual');
            $table->string('no_rkm_medis')->nullable();
            $table->string('nm_pasien')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('jns_jual')->nullable();
            $table->decimal('ongkir', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->string('kd_bangsal')->nullable();
            $table->string('kd_rek')->nullable();
            $table->string('nama_bayar')->nullable();
            $table->string('status')->default('Sudah Dibayar');
        });

        Schema::connection('simrs')->create('detailjual', function (Blueprint $table) {
            $table->string('nota_jual');
            $table->string('kode_brng');
            $table->decimal('h_beli', 18, 2)->default(0);
            $table->decimal('h_jual', 18, 2)->default(0);
            $table->decimal('jumlah', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
        });

        Schema::connection('simrs')->create('databarang', function (Blueprint $table) {
            $table->string('kode_brng')->primary();
            $table->string('nama_brng');
        });

        Schema::connection('simrs')->create('akun_bayar', function (Blueprint $table) {
            $table->string('nama_bayar')->primary();
            $table->string('kd_rek');
        });

        Schema::connection('simrs')->create('piutang', function (Blueprint $table) {
            $table->string('nota_piutang')->primary();
            $table->date('tgl_piutang');
            $table->string('no_rkm_medis')->nullable();
            $table->string('nm_pasien')->nullable();
            $table->text('catatan')->nullable();
            $table->date('tgltempo')->nullable();
            $table->string('jns_jual')->nullable();
            $table->decimal('ongkir', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->string('kd_bangsal')->nullable();
            $table->string('status')->default('Belum Lunas');
            $table->decimal('uangmuka', 18, 2)->default(0);
            $table->decimal('sisapiutang', 18, 2)->default(0);
        });

        Schema::connection('simrs')->create('detailpiutang', function (Blueprint $table) {
            $table->string('nota_piutang');
            $table->string('kode_brng');
            $table->string('kode_sat')->nullable();
            $table->decimal('h_jual', 18, 2)->default(0);
            $table->decimal('jumlah', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('no_batch')->nullable();
            $table->string('aturan_pakai')->nullable();
        });
    }

    private function setupCoaDanSetting(): void
    {
        $this->coaKas = Coa::create(['kode' => '111010', 'nama' => 'Kas Kasir', 'tipe_coa' => 'KasBank', 'is_postable' => true]);
        $this->coaPendapatan = Coa::create(['kode' => '410000', 'nama' => 'Pendapatan Obat', 'tipe_coa' => 'Pendapatan', 'is_postable' => true]);
        $this->coaHpp = Coa::create(['kode' => '510000', 'nama' => 'HPP Obat', 'tipe_coa' => 'Beban', 'is_postable' => true]);
        $this->coaPersediaan = Coa::create(['kode' => '115010', 'nama' => 'Persediaan Obat', 'tipe_coa' => 'Aset Lancar', 'is_postable' => true]);
        $this->coaPiutang = Coa::create(['kode' => '117000', 'nama' => 'Piutang Obat', 'tipe_coa' => 'Piutang', 'is_postable' => true]);
        $this->coaUangMuka = Coa::create(['kode' => '111020', 'nama' => 'Kas Uang Muka', 'tipe_coa' => 'KasBank', 'is_postable' => true]);

        SettingBridgingObat::create([
            'pendapatan_obat_coa_id' => $this->coaPendapatan->id,
            'hpp_obat_coa_id' => $this->coaHpp->id,
            'persediaan_obat_coa_id' => $this->coaPersediaan->id,
            'piutang_obat_coa_id' => $this->coaPiutang->id,
            'kas_uang_muka_coa_id' => $this->coaUangMuka->id,
        ]);

        MappingLawanPendapatanSimrs::create([
            'kode_coa_simrs' => '111010',
            'nama_coa_simrs' => 'Bayar Cash',
            'coa_id' => $this->coaKas->id,
        ]);
    }

    public function test_impor_banyak_rejects_unsupported_process_type(): void
    {
        $result = $this->service->imporBanyak(['PJ-TEST-001'], 'UnsupportedType', 'tester');

        $this->assertSame([
            [
                'nomer_transaksi' => 'PJ-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Tujuan import tidak didukung.',
            ],
        ], $result);
    }

    public function test_get_setting_throws_exception_if_not_configured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pengaturan Akun Obat & BHP belum lengkap');

        $this->service->getSettingBridgingObat();
    }

    public function test_impor_satu_fails_if_setting_not_configured(): void
    {
        DB::connection('simrs')->table('penjualan')->insert([
            'nota_jual' => 'PJ001',
            'tgl_jual' => '2026-09-16',
            'kd_rek' => '111010',
            'nama_bayar' => 'Bayar Cash',
            'status' => 'Sudah Dibayar',
        ]);
        DB::connection('simrs')->table('detailjual')->insert([
            'nota_jual' => 'PJ001',
            'kode_brng' => 'OBT01',
            'h_beli' => 1000,
            'h_jual' => 1500,
            'jumlah' => 2,
            'subtotal' => 3000,
            'total' => 3000,
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'OBT01',
            'nama_brng' => 'Paracetamol',
        ]);

        $result = $this->service->imporBanyak(['PJ001'], 'JurnalUmum', 'tester');

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('Pengaturan Akun Obat & BHP belum lengkap', $result[0]['alasan_gagal']);
    }

    public function test_impor_satu_fails_if_cara_bayar_not_mapped(): void
    {
        $this->setupCoaDanSetting();

        // Ganti kd_rek menjadi cara bayar yang belum dimapping
        DB::connection('simrs')->table('penjualan')->insert([
            'nota_jual' => 'PJ002',
            'tgl_jual' => '2026-09-16',
            'kd_rek' => '999999',
            'nama_bayar' => 'Bayar Via Mandiri Belum Mapping',
            'status' => 'Sudah Dibayar',
        ]);
        DB::connection('simrs')->table('detailjual')->insert([
            'nota_jual' => 'PJ002',
            'kode_brng' => 'OBT01',
            'h_beli' => 1000,
            'h_jual' => 1500,
            'jumlah' => 2,
            'subtotal' => 3000,
            'total' => 3000,
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'OBT01',
            'nama_brng' => 'Paracetamol',
        ]);

        $result = $this->service->imporBanyak(['PJ002'], 'JurnalUmum', 'tester');

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('Mapping akun lawan pendapatan belum disetting', $result[0]['alasan_gagal']);
    }

    public function test_impor_jurnal_umum_sukses_dan_seimbang(): void
    {
        $this->setupCoaDanSetting();

        DB::connection('simrs')->table('penjualan')->insert([
            'nota_jual' => 'PJ003',
            'tgl_jual' => '2026-09-16',
            'kd_rek' => '111010',
            'nama_bayar' => 'Bayar Cash',
            'status' => 'Sudah Dibayar',
            'ongkir' => 0,
            'ppn' => 0,
        ]);
        DB::connection('simrs')->table('detailjual')->insert([
            'nota_jual' => 'PJ003',
            'kode_brng' => 'OBT01',
            'h_beli' => 10000, // HPP = 10.000 * 2 = 20.000
            'h_jual' => 15000,
            'jumlah' => 2,
            'subtotal' => 30000, // Grandtotal = 30.000
            'total' => 30000,
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'OBT01',
            'nama_brng' => 'Amoxicillin',
        ]);

        $result = $this->service->imporBanyak(['PJ003'], 'JurnalUmum', 'tester');

        $this->assertTrue($result[0]['berhasil'], (string) ($result[0]['alasan_gagal'] ?? ''));
        $this->assertNull($result[0]['alasan_gagal']);

        $jurnal = JurnalUmum::where('nomer', 'PJ003')->first();
        $this->assertNotNull($jurnal);
        // Total jurnal = 30.000 (penjualan) + 20.000 (HPP) = 50.000
        $this->assertEquals(50000, $jurnal->debit);
        $this->assertEquals(50000, $jurnal->kredit);

        $rincian = JurnalUmumRinci::where('jurnal_umum_id', $jurnal->id)->get();
        $this->assertCount(4, $rincian);

        // Kas Kasir Debit 30k
        $kasRow = $rincian->firstWhere('coa_id', $this->coaKas->id);
        $this->assertEquals(30000, $kasRow->debit);
        $this->assertEquals(0, $kasRow->kredit);

        // Pendapatan Kredit 30k
        $pendRow = $rincian->firstWhere('coa_id', $this->coaPendapatan->id);
        $this->assertEquals(0, $pendRow->debit);
        $this->assertEquals(30000, $pendRow->kredit);

        // HPP Debit 20k
        $hppRow = $rincian->firstWhere('coa_id', $this->coaHpp->id);
        $this->assertEquals(20000, $hppRow->debit);
        $this->assertEquals(0, $hppRow->kredit);

        // Persediaan Kredit 20k
        $persRow = $rincian->firstWhere('coa_id', $this->coaPersediaan->id);
        $this->assertEquals(0, $persRow->debit);
        $this->assertEquals(20000, $persRow->kredit);

        // Periksa mutasi buku besar
        $bukuBesar = BukuBesar::where('nomer', 'PJ003')->get();
        $this->assertCount(4, $bukuBesar);
        $totalDebitBb = $bukuBesar->where('tipe_mutasi', 'D')->sum('nominal');
        $totalKreditBb = $bukuBesar->where('tipe_mutasi', 'K')->sum('nominal');
        $this->assertEquals(50000, $totalDebitBb);
        $this->assertEquals(50000, $totalKreditBb);
    }

    public function test_impor_invoice_pendapatan_sukses_dan_seimbang(): void
    {
        $this->setupCoaDanSetting();

        DB::connection('simrs')->table('penjualan')->insert([
            'nota_jual' => 'PJ004',
            'tgl_jual' => '2026-09-16',
            'no_rkm_medis' => 'RM001',
            'nm_pasien' => 'Pasien Testing',
            'kd_rek' => '111010',
            'nama_bayar' => 'Bayar Cash',
            'status' => 'Sudah Dibayar',
            'ongkir' => 0,
            'ppn' => 0,
        ]);
        DB::connection('simrs')->table('detailjual')->insert([
            'nota_jual' => 'PJ004',
            'kode_brng' => 'OBT01',
            'h_beli' => 8000,
            'h_jual' => 12000,
            'jumlah' => 1,
            'subtotal' => 12000,
            'total' => 12000,
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'OBT01',
            'nama_brng' => 'Bodrex',
        ]);

        $result = $this->service->imporBanyak(['PJ004'], 'InvoicePendapatan', 'tester');

        $this->assertTrue($result[0]['berhasil']);

        $faktur = FakturPenjualan::where('nomor_faktur', 'PJ004')->first();
        $this->assertNotNull($faktur);
        $this->assertEquals(12000, $faktur->grandtotal);
        $this->assertEquals(12000, $faktur->sudah_terbayar);
        $this->assertEquals('Pasien Testing', $faktur->nama_pasien);

        $fakturRinci = FakturPenjualanRinci::where('faktur_penjualan_id', $faktur->id)->get();
        $this->assertCount(1, $fakturRinci);

        $bukuBesar = BukuBesar::where('nomer', 'PJ004')->get();
        $this->assertCount(4, $bukuBesar);
        // Kas D 12k, Pendapatan K 12k, HPP D 8k, Persediaan K 8k
        $totalDebit = $bukuBesar->where('tipe_mutasi', 'D')->sum('nominal');
        $totalKredit = $bukuBesar->where('tipe_mutasi', 'K')->sum('nominal');
        $this->assertEquals(20000, $totalDebit);
        $this->assertEquals(20000, $totalKredit);
    }

    public function test_impor_piutang_obat_sukses_dengan_uang_muka(): void
    {
        $this->setupCoaDanSetting();

        DB::connection('simrs')->table('piutang')->insert([
            'nota_piutang' => 'PT001',
            'tgl_piutang' => '2026-09-16',
            'no_rkm_medis' => 'RM002',
            'nm_pasien' => 'Pasien Piutang',
            'uangmuka' => 20000,
            'sisapiutang' => 80000,
            'ongkir' => 0,
            'ppn' => 0,
            'status' => 'Belum Lunas',
        ]);
        DB::connection('simrs')->table('detailpiutang')->insert([
            'nota_piutang' => 'PT001',
            'kode_brng' => 'OBT02',
            'h_jual' => 50000,
            'jumlah' => 2,
            'total' => 100000,
        ]);

        $result = $this->service->imporPiutangBanyak(['PT001'], 'tester');

        $this->assertTrue($result[0]['berhasil'], (string) ($result[0]['alasan_gagal'] ?? ''));

        $faktur = FakturPenjualan::where('nomor_faktur', 'P-PT001')->first();
        $this->assertNotNull($faktur);
        $this->assertEquals(100000, $faktur->grandtotal);
        $this->assertEquals(20000, $faktur->sudah_terbayar);
        $this->assertEquals($this->coaPiutang->id, $faktur->akun_piutang_id);

        $bukuBesar = BukuBesar::where('nomer', 'P-PT001')->get();
        $this->assertCount(3, $bukuBesar);

        // Piutang D 80k
        $piutangRow = $bukuBesar->firstWhere('coa_id', $this->coaPiutang->id);
        $this->assertEquals('D', $piutangRow->tipe_mutasi);
        $this->assertEquals(80000, $piutangRow->nominal);

        // Uang Muka D 20k
        $uangMukaRow = $bukuBesar->firstWhere('coa_id', $this->coaUangMuka->id);
        $this->assertEquals('D', $uangMukaRow->tipe_mutasi);
        $this->assertEquals(20000, $uangMukaRow->nominal);

        // Persediaan K 100k
        $persRow = $bukuBesar->firstWhere('coa_id', $this->coaPersediaan->id);
        $this->assertEquals('K', $persRow->tipe_mutasi);
        $this->assertEquals(100000, $persRow->nominal);

        $totalDebit = $bukuBesar->where('tipe_mutasi', 'D')->sum('nominal');
        $totalKredit = $bukuBesar->where('tipe_mutasi', 'K')->sum('nominal');
        $this->assertEquals(100000, $totalDebit);
        $this->assertEquals(100000, $totalKredit);
    }

    public function test_hapus_banyak_membersihkan_data(): void
    {
        $this->setupCoaDanSetting();

        DB::connection('simrs')->table('penjualan')->insert([
            'nota_jual' => 'PJ005',
            'tgl_jual' => '2026-09-16',
            'kd_rek' => '111010',
            'nama_bayar' => 'Bayar Cash',
            'status' => 'Sudah Dibayar',
        ]);
        DB::connection('simrs')->table('detailjual')->insert([
            'nota_jual' => 'PJ005',
            'kode_brng' => 'OBT01',
            'h_beli' => 5000,
            'h_jual' => 10000,
            'jumlah' => 1,
            'subtotal' => 10000,
            'total' => 10000,
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'OBT01',
            'nama_brng' => 'Obat Test',
        ]);

        $this->service->imporBanyak(['PJ005'], 'JurnalUmum', 'tester');
        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'PJ005']);

        // Hapus
        $hapusResult = $this->service->hapusBanyak(['PJ005'], 'tester');
        $this->assertTrue($hapusResult[0]['berhasil']);

        $this->assertDatabaseMissing('jurnal_umum', ['nomer' => 'PJ005']);
        $this->assertDatabaseMissing('bukubesar', ['nomer' => 'PJ005']);
        $this->assertDatabaseMissing('simrs_import_pendapatan_jual_obat', ['nomer_transaksi' => 'PJ005']);
        $this->assertDatabaseHas('log_hapus_import_pendapatan', ['nomer' => 'PJ005']);
    }
}
