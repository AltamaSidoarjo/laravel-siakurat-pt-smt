<?php

namespace Tests\Unit;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\FakturPembelianRinci;
use App\Models\SettingBridgingPembelian;
use App\Models\Supplier;
use App\Services\Bridging\BridgingPembelianService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPembelianServiceTest extends TestCase
{
    private BridgingPembelianService $service;
    private Coa $coaPersediaanObat;
    private Coa $coaPpnMasukan;
    private Coa $coaHutangObat;
    private Coa $coaPersediaanNonMedis;
    private Coa $coaHutangNonMedis;

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

        $this->coaPersediaanObat = Coa::create([
            'kode' => '115010',
            'nama' => 'Persediaan Obat & BHP',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPpnMasukan = Coa::create([
            'kode' => '116011',
            'nama' => 'PPN Masukan',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaHutangObat = Coa::create([
            'kode' => '211010',
            'nama' => 'Hutang Pembelian Obat & BHP',
            'tipe_coa' => 'Kewajiban',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaPersediaanNonMedis = Coa::create([
            'kode' => '115020',
            'nama' => 'Persediaan Non Medis',
            'tipe_coa' => 'Aset Lancar',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);
        $this->coaHutangNonMedis = Coa::create([
            'kode' => '211210',
            'nama' => 'Hutang Pembelian Non Medis',
            'tipe_coa' => 'Kewajiban',
            'is_postable' => true,
            'status_aktif' => 1,
        ]);

        $this->service = new BridgingPembelianService($this->createMock(LogAktifitasService::class));
    }

    private function prepareTables(): void
    {
        // Local database schema
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('setting_bridging_pembelians');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('log_aktifitas');

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('tipe_coa')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('setting_bridging_pembelians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persediaan_obat_coa_id');
            $table->unsignedBigInteger('ppn_masukan_coa_id');
            $table->unsignedBigInteger('hutang_obat_coa_id');
            $table->unsignedBigInteger('persediaan_non_medis_coa_id');
            $table->unsignedBigInteger('hutang_non_medis_coa_id');
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->index();
            $table->string('nama_supplier')->index();
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('nomer_faktur')->unique();
            $table->date('tanggal_faktur');
            $table->text('keterangan')->nullable();
            $table->decimal('nilai_ppn', 15, 2)->default(0);
            $table->decimal('biaya_kirim', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('status_proses')->default('0');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->date('tanggal_pesan')->nullable();
            $table->string('kategori_faktur')->nullable();
            $table->string('kode_bangsal')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faktur_pembelian_id')->index();
            $table->integer('kuantitas');
            $table->decimal('diskon_rupiah', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->decimal('harga_barang', 15, 2)->default(0);
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan_barang')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->index();
            $table->unsignedBigInteger('sumber_id')->nullable()->index();
            $table->date('tanggal');
            $table->integer('periode_tahun')->nullable();
            $table->integer('periode_bulan')->nullable();
            $table->string('nomer')->nullable()->index();
            $table->string('sumber_transaksi')->nullable()->index();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        // SIMRS database schema
        $simrsSchema = Schema::connection('simrs');

        $simrsSchema->dropIfExists('detailpesan');
        $simrsSchema->dropIfExists('pemesanan');
        $simrsSchema->dropIfExists('datasuplier');
        $simrsSchema->dropIfExists('databarang');
        $simrsSchema->dropIfExists('ipsrsdetailpesan');
        $simrsSchema->dropIfExists('ipsrspemesanan');
        $simrsSchema->dropIfExists('ipsrssuplier');
        $simrsSchema->dropIfExists('ipsrsbarang');

        $simrsSchema->create('datasuplier', function (Blueprint $table) {
            $table->string('kode_suplier')->primary();
            $table->string('nama_suplier');
        });

        $simrsSchema->create('databarang', function (Blueprint $table) {
            $table->string('kode_brng')->primary();
            $table->string('nama_brng');
        });

        $simrsSchema->create('pemesanan', function (Blueprint $table) {
            $table->string('no_faktur')->primary();
            $table->string('no_order')->nullable();
            $table->date('tgl_faktur');
            $table->date('tgl_pesan')->nullable();
            $table->date('tgl_tempo')->nullable();
            $table->string('kode_suplier');
            $table->string('kd_bangsal')->nullable();
            $table->string('status')->nullable();
            $table->double('ppn')->default(0);
            $table->double('tagihan')->default(0);
        });

        $simrsSchema->create('detailpesan', function (Blueprint $table) {
            $table->string('no_faktur');
            $table->string('kode_brng');
            $table->double('h_pesan')->default(0);
            $table->double('jumlah')->default(0);
            $table->double('besardis')->default(0);
            $table->double('subtotal')->default(0);
            $table->string('kode_sat')->nullable();
            $table->double('total')->default(0);
        });

        $simrsSchema->create('ipsrssuplier', function (Blueprint $table) {
            $table->string('kode_suplier')->primary();
            $table->string('nama_suplier');
        });

        $simrsSchema->create('ipsrsbarang', function (Blueprint $table) {
            $table->string('kode_brng')->primary();
            $table->string('nama_brng');
        });

        $simrsSchema->create('ipsrspemesanan', function (Blueprint $table) {
            $table->string('no_faktur')->primary();
            $table->string('no_order')->nullable();
            $table->date('tgl_faktur');
            $table->date('tgl_pesan')->nullable();
            $table->date('tgl_tempo')->nullable();
            $table->string('kode_suplier');
            $table->string('status')->nullable();
            $table->double('ppn')->default(0);
            $table->double('tagihan')->default(0);
        });

        $simrsSchema->create('ipsrsdetailpesan', function (Blueprint $table) {
            $table->string('no_faktur');
            $table->string('kode_brng');
            $table->double('harga')->default(0);
            $table->double('jumlah')->default(0);
            $table->double('besardis')->default(0);
            $table->double('subtotal')->default(0);
            $table->string('kode_sat')->nullable();
            $table->double('total')->default(0);
        });
    }

    private function createSettingBridgingPembelian(): SettingBridgingPembelian
    {
        return SettingBridgingPembelian::create([
            'persediaan_obat_coa_id' => $this->coaPersediaanObat->id,
            'ppn_masukan_coa_id' => $this->coaPpnMasukan->id,
            'hutang_obat_coa_id' => $this->coaHutangObat->id,
            'persediaan_non_medis_coa_id' => $this->coaPersediaanNonMedis->id,
            'hutang_non_medis_coa_id' => $this->coaHutangNonMedis->id,
        ]);
    }

    public function test_impor_banyak_pembelian_obat_rejects_unsupported_process_type(): void
    {
        $result = $this->service->imporBanyakPembelianObat(
            ['PB-TEST-001'],
            'JurnalUmum',
            'TanggalInvoice',
            'tester',
        );

        $this->assertSame([
            [
                'nomer_transaksi' => 'PB-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ],
        ], $result);
    }

    public function test_impor_banyak_pembelian_nonmedis_rejects_unsupported_process_type(): void
    {
        $result = $this->service->imporBanyakPembelianNonMedis(
            ['PNM-TEST-001'],
            'JurnalUmum',
            'TanggalInvoice',
            'tester',
        );

        $this->assertSame([
            [
                'nomer_transaksi' => 'PNM-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ],
        ], $result);
    }

    public function test_tanggal_pengakuan_uses_invoice_date(): void
    {
        $this->assertSame('2026-09-01', $this->resolveTanggalPengakuan([
            'tgl_faktur' => '2026-09-01',
            'tgl_pesan' => '2026-09-03',
        ], 'TanggalInvoice'));
    }

    public function test_tanggal_pengakuan_uses_barang_datang_date(): void
    {
        $this->assertSame('2026-09-03', $this->resolveTanggalPengakuan([
            'tgl_faktur' => '2026-09-01',
            'tgl_pesan' => '2026-09-03',
        ], 'TanggalBarangDatang'));
    }

    public function test_impor_gagal_jika_setting_belum_dikonfigurasi(): void
    {
        DB::connection('simrs')->table('datasuplier')->insert([
            'kode_suplier' => 'SUP01',
            'nama_suplier' => 'PT Kimia Farma',
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'BRG01',
            'nama_brng' => 'Paracetamol 500mg',
        ]);
        DB::connection('simrs')->table('pemesanan')->insert([
            'no_faktur' => 'FAK-001',
            'no_order' => 'ORD-001',
            'tgl_faktur' => '2026-10-24',
            'tgl_pesan' => '2026-10-22',
            'tgl_tempo' => '2026-11-24',
            'kode_suplier' => 'SUP01',
            'kd_bangsal' => 'AP',
            'status' => 'Sudah Datang',
            'ppn' => 11000,
            'tagihan' => 111000,
        ]);
        DB::connection('simrs')->table('detailpesan')->insert([
            'no_faktur' => 'FAK-001',
            'kode_brng' => 'BRG01',
            'h_pesan' => 1000,
            'jumlah' => 100,
            'besardis' => 0,
            'subtotal' => 100000,
            'kode_sat' => 'TAB',
            'total' => 100000,
        ]);

        $result = $this->service->imporBanyakPembelianObat(
            ['FAK-001'],
            'InvoicePembelian',
            'TanggalInvoice',
            'tester',
        );

        $this->assertFalse($result[0]['berhasil']);
        $this->assertStringContainsString('Pengaturan akun bridging pembelian belum lengkap', $result[0]['alasan_gagal']);
    }

    public function test_impor_pembelian_obat_berhasil_dengan_ppn_tanpa_jurnal_simrs(): void
    {
        $this->createSettingBridgingPembelian();

        DB::connection('simrs')->table('datasuplier')->insert([
            'kode_suplier' => 'SUP01',
            'nama_suplier' => 'PT Kimia Farma',
        ]);
        DB::connection('simrs')->table('databarang')->insert([
            'kode_brng' => 'BRG01',
            'nama_brng' => 'Amoxicillin 500mg',
        ]);
        DB::connection('simrs')->table('pemesanan')->insert([
            'no_faktur' => 'FAK-OBAT-99',
            'no_order' => 'ORD-99',
            'tgl_faktur' => '2026-10-24',
            'tgl_pesan' => '2026-10-20',
            'tgl_tempo' => '2026-11-20',
            'kode_suplier' => 'SUP01',
            'kd_bangsal' => 'AP',
            'status' => 'Sudah Datang',
            'ppn' => 11000,
            'tagihan' => 111000,
        ]);
        DB::connection('simrs')->table('detailpesan')->insert([
            'no_faktur' => 'FAK-OBAT-99',
            'kode_brng' => 'BRG01',
            'h_pesan' => 1000,
            'jumlah' => 100,
            'besardis' => 0,
            'subtotal' => 100000,
            'kode_sat' => 'STRIP',
            'total' => 100000,
        ]);

        $result = $this->service->imporBanyakPembelianObat(
            ['FAK-OBAT-99'],
            'InvoicePembelian',
            'TanggalInvoice',
            'tester',
        );

        $this->assertNull($result[0]['alasan_gagal'], (string) ($result[0]['alasan_gagal'] ?? ''));
        $this->assertTrue($result[0]['berhasil']);

        // Check Faktur Pembelian created
        $faktur = FakturPembelian::query()->where('nomer_faktur', 'FAK-OBAT-99')->first();
        $this->assertNotNull($faktur);
        $this->assertSame('111000.00', (string) $faktur->grandtotal);
        $this->assertSame('11000.00', (string) $faktur->nilai_ppn);
        $this->assertSame('Obat & BHP', $faktur->kategori_faktur);

        // Check Supplier created/associated
        $supplier = Supplier::query()->where('kode_supplier', 'SUP01')->first();
        $this->assertNotNull($supplier);
        $this->assertSame('PT Kimia Farma', $supplier->nama_supplier);
        $this->assertSame($supplier->id, $faktur->supplier_id);

        // Check Faktur Pembelian Rinci
        $this->assertSame(1, FakturPembelianRinci::query()->where('faktur_pembelian_id', $faktur->id)->count());

        // Check Buku Besar entries:
        // 1. Debit Persediaan Obat: DPP = 100,000 (tagihan - ppn)
        // 2. Debit PPN Masukan: 11,000
        // 3. Kredit Hutang Obat: 111,000 (tagihan)
        $mutasi = BukuBesar::query()->where('sumber_id', $faktur->id)->get();
        $this->assertCount(3, $mutasi);

        $mutasiPersediaan = $mutasi->firstWhere('coa_id', $this->coaPersediaanObat->id);
        $this->assertNotNull($mutasiPersediaan);
        $this->assertSame('D', $mutasiPersediaan->tipe_mutasi);
        $this->assertEquals(100000.00, (float) $mutasiPersediaan->nominal);

        $mutasiPpn = $mutasi->firstWhere('coa_id', $this->coaPpnMasukan->id);
        $this->assertNotNull($mutasiPpn);
        $this->assertSame('D', $mutasiPpn->tipe_mutasi);
        $this->assertEquals(11000.00, (float) $mutasiPpn->nominal);

        $mutasiHutang = $mutasi->firstWhere('coa_id', $this->coaHutangObat->id);
        $this->assertNotNull($mutasiHutang);
        $this->assertSame('K', $mutasiHutang->tipe_mutasi);
        $this->assertEquals(111000.00, (float) $mutasiHutang->nominal);

        // Check balance
        $totalDebit = $mutasi->where('tipe_mutasi', 'D')->sum('nominal');
        $totalKredit = $mutasi->where('tipe_mutasi', 'K')->sum('nominal');
        $this->assertEquals($totalDebit, $totalKredit);
        $this->assertEquals(111000.00, $totalDebit);
    }

    public function test_impor_pembelian_nonmedis_tanpa_ppn_melewati_baris_ppn(): void
    {
        $this->createSettingBridgingPembelian();

        DB::connection('simrs')->table('ipsrssuplier')->insert([
            'kode_suplier' => 'SUPIPS01',
            'nama_suplier' => 'CV Sumber Makmur',
        ]);
        DB::connection('simrs')->table('ipsrsbarang')->insert([
            'kode_brng' => 'BRGNM01',
            'nama_brng' => 'Kertas HVS A4',
        ]);
        DB::connection('simrs')->table('ipsrspemesanan')->insert([
            'no_faktur' => 'FAK-NM-001',
            'no_order' => 'ORD-NM-001',
            'tgl_faktur' => '2026-10-24',
            'tgl_pesan' => '2026-10-23',
            'tgl_tempo' => '2026-11-24',
            'kode_suplier' => 'SUPIPS01',
            'status' => 'Sudah Datang',
            'ppn' => 0,
            'tagihan' => 500000,
        ]);
        DB::connection('simrs')->table('ipsrsdetailpesan')->insert([
            'no_faktur' => 'FAK-NM-001',
            'kode_brng' => 'BRGNM01',
            'harga' => 50000,
            'jumlah' => 10,
            'besardis' => 0,
            'subtotal' => 500000,
            'kode_sat' => 'RIM',
            'total' => 500000,
        ]);

        $result = $this->service->imporBanyakPembelianNonMedis(
            ['FAK-NM-001'],
            'InvoicePembelian',
            'TanggalInvoice',
            'tester',
        );

        $this->assertNull($result[0]['alasan_gagal'], (string) ($result[0]['alasan_gagal'] ?? ''));
        $this->assertTrue($result[0]['berhasil']);

        $faktur = FakturPembelian::query()->where('nomer_faktur', 'FAK-NM-001')->first();
        $this->assertNotNull($faktur);
        $this->assertSame('500000.00', (string) $faktur->grandtotal);
        $this->assertSame('0.00', (string) $faktur->nilai_ppn);
        $this->assertSame('Barang Non Medis', $faktur->kategori_faktur);

        // Check Buku Besar: Only 2 rows (Debit Persediaan Non Medis & Kredit Hutang Non Medis)
        $mutasi = BukuBesar::query()->where('sumber_id', $faktur->id)->get();
        $this->assertCount(2, $mutasi);

        $mutasiPersediaan = $mutasi->firstWhere('coa_id', $this->coaPersediaanNonMedis->id);
        $this->assertNotNull($mutasiPersediaan);
        $this->assertSame('D', $mutasiPersediaan->tipe_mutasi);
        $this->assertEquals(500000.00, (float) $mutasiPersediaan->nominal);

        $mutasiHutang = $mutasi->firstWhere('coa_id', $this->coaHutangNonMedis->id);
        $this->assertNotNull($mutasiHutang);
        $this->assertSame('K', $mutasiHutang->tipe_mutasi);
        $this->assertEquals(500000.00, (float) $mutasiHutang->nominal);

        // No PPN row created
        $this->assertNull($mutasi->firstWhere('coa_id', $this->coaPpnMasukan->id));

        // Balance check
        $totalDebit = $mutasi->where('tipe_mutasi', 'D')->sum('nominal');
        $totalKredit = $mutasi->where('tipe_mutasi', 'K')->sum('nominal');
        $this->assertEquals($totalDebit, $totalKredit);
        $this->assertEquals(500000.00, $totalDebit);
    }

    private function resolveTanggalPengakuan(array $tagihan, string $metode): string
    {
        $method = new \ReflectionMethod($this->service, 'tentukanTanggalPengakuan');

        return $method->invoke($this->service, $tagihan, $metode);
    }
}
