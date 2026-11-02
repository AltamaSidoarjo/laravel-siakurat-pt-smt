<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\PembayaranPembelian;
use App\Models\PembayaranPembelianRinci;
use App\Models\Supplier;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\Bukubesar\JurnalUmumService;
use App\Services\LogAktifitasService;
use App\Services\Pembelian\InvoicePembelianService;
use App\Services\Pembelian\PembayaranPembelianService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class TransactionAtomicityTest extends TestCase
{
    private BukuBesarService&MockInterface $bukuBesarMock;
    private LogAktifitasService $logAktifitasService;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('pembayaran_pembelian');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('coa');
        Schema::enableForeignKeyConstraints();

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode');
            $table->string('nama');
            $table->boolean('status_aktif')->default(true);
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode_supplier');
            $table->string('nama_supplier');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('akun_hutang_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('status_proses')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('akun_bank_id');
            $table->unsignedInteger('akun_hutang_id');
            $table->unsignedInteger('akun_potongan_admin_id')->nullable();
            $table->string('nomer_pembayaran');
            $table->date('tanggal');
            $table->decimal('total_bayar', 15, 2)->default(0);
            $table->decimal('potongan_admin', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pembayaran_pembelian_id');
            $table->unsignedInteger('faktur_pembelian_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id');
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
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

        $this->bukuBesarMock = Mockery::mock(BukuBesarService::class);
        $this->logAktifitasService = new LogAktifitasService();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('pembayaran_pembelian');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('coa');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_jurnal_umum_create_rollback_ketika_sync_buku_besar_gagal(): void
    {
        $coa1 = Coa::query()->create(['kode' => '111.01', 'nama' => 'Kas']);
        $coa2 = Coa::query()->create(['kode' => '411.01', 'nama' => 'Pendapatan']);

        $this->bukuBesarMock
            ->shouldReceive('syncFromJurnalUmum')
            ->once()
            ->andThrow(new RuntimeException('Simulated database ledger failure'));

        $service = new JurnalUmumService($this->bukuBesarMock, $this->logAktifitasService);

        $payload = [
            'nomer' => 'JU-2026-0001',
            'tanggal' => '2026-11-02',
            'keterangan' => 'Test Transaksi Jurnal',
            'debit' => 100000,
            'kredit' => 100000,
            'rincian' => [
                ['coa_id' => $coa1->id, 'debit' => 100000, 'kredit' => 0],
                ['coa_id' => $coa2->id, 'debit' => 0, 'kredit' => 100000],
            ],
        ];

        try {
            $service->create($payload);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated database ledger failure', $exception->getMessage());
        }

        // Pastikan rollback transaksi berhasil: tidak ada jurnal_umum atau rincian yang tertinggal
        $this->assertDatabaseMissing('jurnal_umum', ['nomer' => 'JU-2026-0001']);
        $this->assertSame(0, JurnalUmum::query()->count());
        $this->assertSame(0, JurnalUmumRinci::query()->count());
    }

    public function test_jurnal_umum_update_rollback_ketika_sync_buku_besar_gagal(): void
    {
        $coa1 = Coa::query()->create(['kode' => '111.01', 'nama' => 'Kas']);
        $coa2 = Coa::query()->create(['kode' => '411.01', 'nama' => 'Pendapatan']);

        $realBukuBesarService = new BukuBesarService();
        $normalService = new JurnalUmumService($realBukuBesarService, $this->logAktifitasService);

        $initialJournal = $normalService->create([
            'nomer' => 'JU-ORIGINAL',
            'tanggal' => '2026-11-01',
            'keterangan' => 'Keterangan Awal',
            'debit' => 50000,
            'kredit' => 50000,
            'rincian' => [
                ['coa_id' => $coa1->id, 'debit' => 50000, 'kredit' => 0],
                ['coa_id' => $coa2->id, 'debit' => 0, 'kredit' => 50000],
            ],
        ]);

        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'JU-ORIGINAL', 'keterangan' => 'Keterangan Awal']);
        $this->assertSame(2, JurnalUmumRinci::query()->where('jurnal_umum_id', $initialJournal->id)->count());

        $this->bukuBesarMock
            ->shouldReceive('syncFromJurnalUmum')
            ->once()
            ->andThrow(new RuntimeException('Sync update failed'));

        $serviceWithMock = new JurnalUmumService($this->bukuBesarMock, $this->logAktifitasService);

        try {
            $serviceWithMock->update($initialJournal, [
                'nomer' => 'JU-MODIFIED',
                'tanggal' => '2026-11-02',
                'keterangan' => 'Keterangan Baru Yang Seharusnya Batal',
                'debit' => 75000,
                'kredit' => 75000,
                'rincian' => [
                    ['coa_id' => $coa1->id, 'debit' => 75000, 'kredit' => 0],
                    ['coa_id' => $coa2->id, 'debit' => 0, 'kredit' => 75000],
                ],
            ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sync update failed', $exception->getMessage());
        }

        // Verifikasi rollback: record jurnal kembali ke data awal
        $this->assertDatabaseHas('jurnal_umum', [
            'id' => $initialJournal->id,
            'nomer' => 'JU-ORIGINAL',
            'keterangan' => 'Keterangan Awal',
            'debit' => 50000,
        ]);
        $this->assertDatabaseMissing('jurnal_umum', ['nomer' => 'JU-MODIFIED']);
        $this->assertSame(2, JurnalUmumRinci::query()->where('jurnal_umum_id', $initialJournal->id)->count());
    }

    public function test_jurnal_umum_delete_rollback_ketika_buku_besar_delete_gagal(): void
    {
        $coa1 = Coa::query()->create(['kode' => '111.01', 'nama' => 'Kas']);
        $coa2 = Coa::query()->create(['kode' => '411.01', 'nama' => 'Pendapatan']);

        $realBukuBesarService = new BukuBesarService();
        $normalService = new JurnalUmumService($realBukuBesarService, $this->logAktifitasService);

        $journal = $normalService->create([
            'nomer' => 'JU-DELETE-TEST',
            'tanggal' => '2026-11-01',
            'keterangan' => 'Untuk Dihapus',
            'debit' => 25000,
            'kredit' => 25000,
            'rincian' => [
                ['coa_id' => $coa1->id, 'debit' => 25000, 'kredit' => 0],
                ['coa_id' => $coa2->id, 'debit' => 0, 'kredit' => 25000],
            ],
        ]);

        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'JU-DELETE-TEST']);

        $this->bukuBesarMock
            ->shouldReceive('deleteBySource')
            ->once()
            ->with('Jurnal Umum', (int) $journal->id)
            ->andThrow(new RuntimeException('Delete ledger failed'));

        $serviceWithMock = new JurnalUmumService($this->bukuBesarMock, $this->logAktifitasService);

        try {
            $serviceWithMock->delete($journal);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Delete ledger failed', $exception->getMessage());
        }

        // Verifikasi rollback: record jurnal dan rinciannya masih tetap ada
        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'JU-DELETE-TEST']);
        $this->assertSame(2, JurnalUmumRinci::query()->where('jurnal_umum_id', $journal->id)->count());
    }

    public function test_pembayaran_pembelian_create_rollback_ketika_sync_buku_besar_gagal(): void
    {
        $supplier = Supplier::query()->create(['kode_supplier' => 'SUP-01', 'nama_supplier' => 'PT Sumber Farma']);
        $bank = Coa::query()->create(['kode' => '111.02', 'nama' => 'Bank Mandiri']);
        $hutang = Coa::query()->create(['kode' => '211.01', 'nama' => 'Hutang Dagang']);

        $faktur = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-SUP-001',
            'tanggal_faktur' => '2026-11-01',
            'grandtotal' => 1000000,
            'sudah_terbayar' => 0,
        ]);

        $this->bukuBesarMock
            ->shouldReceive('syncFromPembayaranPembelian')
            ->once()
            ->andThrow(new RuntimeException('Sync payment ledger error'));

        $invoiceService = new InvoicePembelianService($this->bukuBesarMock, $this->logAktifitasService);
        $service = new PembayaranPembelianService($this->bukuBesarMock, $invoiceService, $this->logAktifitasService);

        $payload = [
            'supplier_id' => $supplier->id,
            'akun_bank_id' => $bank->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_pembayaran' => 'PAY-2026-0001',
            'tanggal' => '2026-11-02',
            'total_bayar' => 600000,
            'potongan_admin' => 0,
            'keterangan' => 'Bayar termin 1',
            'rincian' => [
                [
                    'check' => true,
                    'faktur_pembelian_id' => $faktur->id,
                    'nominal_bayar' => 600000,
                ],
            ],
        ];

        try {
            $service->create($payload, 'tester');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sync payment ledger error', $exception->getMessage());
        }

        // Verifikasi atomisitas: pembayaran tidak tersimpan, rincian pembayaran tidak tersimpan,
        // dan yang paling penting: sudah_terbayar pada FakturPembelian tetap 0 (tidak bertambah).
        $this->assertDatabaseMissing('pembayaran_pembelian', ['nomer_pembayaran' => 'PAY-2026-0001']);
        $this->assertSame(0, PembayaranPembelian::query()->count());
        $this->assertSame(0, PembayaranPembelianRinci::query()->count());

        $fakturFresh = $faktur->fresh();
        $this->assertEquals(0, (float) $fakturFresh->sudah_terbayar);
    }

    public function test_pembayaran_pembelian_delete_rollback_ketika_buku_besar_delete_gagal(): void
    {
        $supplier = Supplier::query()->create(['kode_supplier' => 'SUP-02', 'nama_supplier' => 'PT Kimia']);
        $bank = Coa::query()->create(['kode' => '111.02', 'nama' => 'Bank Mandiri']);
        $hutang = Coa::query()->create(['kode' => '211.01', 'nama' => 'Hutang Dagang']);

        $faktur = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-SUP-002',
            'tanggal_faktur' => '2026-11-01',
            'grandtotal' => 500000,
            'sudah_terbayar' => 0,
        ]);

        $realBukuBesarService = new BukuBesarService();
        $realInvoiceService = new InvoicePembelianService($realBukuBesarService, $this->logAktifitasService);
        $realPaymentService = new PembayaranPembelianService($realBukuBesarService, $realInvoiceService, $this->logAktifitasService);

        $payment = $realPaymentService->create([
            'supplier_id' => $supplier->id,
            'akun_bank_id' => $bank->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_pembayaran' => 'PAY-DELETE-TEST',
            'tanggal' => '2026-11-02',
            'total_bayar' => 300000,
            'rincian' => [
                [
                    'check' => true,
                    'faktur_pembelian_id' => $faktur->id,
                    'nominal_bayar' => 300000,
                ],
            ],
        ], 'tester');

        $this->assertEquals(300000, (float) $faktur->fresh()->sudah_terbayar);

        $this->bukuBesarMock
            ->shouldReceive('deleteBySource')
            ->once()
            ->with('Pembayaran Pembelian', (int) $payment->id)
            ->andThrow(new RuntimeException('Failed to delete ledger entries'));

        $serviceWithMock = new PembayaranPembelianService($this->bukuBesarMock, $realInvoiceService, $this->logAktifitasService);

        try {
            $serviceWithMock->delete($payment);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failed to delete ledger entries', $exception->getMessage());
        }

        // Verifikasi rollback: record pembayaran masih ada dan invoice sudah_terbayar tidak berkurang (tetap 300,000)
        $this->assertDatabaseHas('pembayaran_pembelian', ['nomer_pembayaran' => 'PAY-DELETE-TEST']);
        $this->assertEquals(300000, (float) $faktur->fresh()->sudah_terbayar);
    }
}
