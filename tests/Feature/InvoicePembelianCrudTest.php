<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePembelianCrudTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::factory()->make([
            'name' => 'Tester Pembelian',
            'email' => 'tester@example.com',
        ]);
    }

    public function test_user_can_view_create_page(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-01',
            'nama_supplier' => 'PT Kimia Sehat',
            'status_aktif' => true,
        ]);

        $hutang = $this->createCoa('201001', 'Hutang Usaha Obat', 'Utang Usaha');
        $persediaan = $this->createCoa('106001', 'Persediaan Obat Gudang', 'Persediaan');

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pembelian.invoice.create'));

        $response->assertOk()
            ->assertSee('Tambah Invoice Pembelian')
            ->assertSee('PT Kimia Sehat')
            ->assertSee('Hutang Usaha Obat')
            ->assertSee('Persediaan Obat Gudang');
    }

    public function test_user_can_create_invoice_pembelian_and_sync_ledger(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-02',
            'nama_supplier' => 'PT Farmasi Nusantara',
            'status_aktif' => true,
        ]);

        $hutang = $this->createCoa('201002', 'Hutang Dagang', 'Akun Hutang');
        $beban = $this->createCoa('501001', 'Beban Pengadaan Medis', 'Beban');
        $persediaan = $this->createCoa('106002', 'Persediaan BHP', 'Persediaan');

        $payload = [
            'nomer_faktur' => 'INV-PB-2026-001',
            'tanggal_faktur' => '2026-10-15',
            'tanggal_jatuh_tempo' => '2026-11-15',
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'keterangan' => 'Pembelian obat rutin',
            'rincian' => [
                [
                    'coa_id' => $beban->id,
                    'kuantitas' => 10,
                    'harga' => 15_000,
                    'catatan' => 'Beban item 1',
                ],
                [
                    'coa_id' => $persediaan->id,
                    'kuantitas' => 5,
                    'harga' => 20_000,
                    'catatan' => 'Persediaan item 2',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pembelian.invoice.store'), $payload);

        $invoice = FakturPembelian::query()->where('nomer_faktur', 'INV-PB-2026-001')->first();
        $this->assertNotNull($invoice);

        $response->assertRedirect(route('pembelian.invoice.read', $invoice));

        $this->assertEquals(250_000, (float) $invoice->grandtotal);
        $this->assertSame($supplier->id, $invoice->supplier_id);
        $this->assertSame($hutang->id, $invoice->akun_hutang_id);
        $this->assertCount(2, $invoice->rincian);

        // Verifikasi mutasi buku besar
        $ledgerEntries = DB::table('bukubesar')
            ->where('sumber_transaksi', 'Invoice Pembelian')
            ->where('sumber_id', $invoice->id)
            ->get();

        $this->assertCount(3, $ledgerEntries);

        $kredit = $ledgerEntries->firstWhere('tipe_mutasi', 'K');
        $this->assertNotNull($kredit);
        $this->assertEquals($hutang->id, $kredit->coa_id);
        $this->assertEquals(250_000, (float) $kredit->nominal);

        $debitTotal = $ledgerEntries->where('tipe_mutasi', 'D')->sum('nominal');
        $this->assertEquals(250_000, (float) $debitTotal);
    }

    public function test_user_can_view_read_page(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-03',
            'nama_supplier' => 'PT Mitra Medika',
            'status_aktif' => true,
        ]);
        $hutang = $this->createCoa('201003', 'Hutang Usaha', 'Utang Usaha');
        $persediaan = $this->createCoa('106003', 'Persediaan Obat', 'Persediaan');

        $invoice = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-PB-003',
            'tanggal_faktur' => '2026-10-13',
            'keterangan' => 'Test Read',
            'grandtotal' => 100_000,
            'sudah_terbayar' => 0,
        ]);

        $invoice->rincian()->create([
            'coa_id' => $persediaan->id,
            'kuantitas' => 2,
            'harga_barang' => 50_000,
            'subtotal' => 100_000,
            'total' => 100_000,
            'catatan' => 'Barang 1',
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pembelian.invoice.read', $invoice));

        $response->assertOk()
            ->assertSee('INV-PB-003')
            ->assertSee('PT Mitra Medika')
            ->assertSee('Hutang Usaha')
            ->assertSee('Persediaan Obat')
            ->assertSee('Edit')
            ->assertSee('Hapus');
    }

    public function test_user_can_update_invoice_pembelian_and_resync_ledger(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-04',
            'nama_supplier' => 'PT Sumber Sehat',
            'status_aktif' => true,
        ]);
        $hutang = $this->createCoa('201004', 'Hutang Usaha', 'Utang Usaha');
        $beban = $this->createCoa('501002', 'Beban Logistik', 'Beban');

        $invoice = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-PB-INITIAL',
            'tanggal_faktur' => '2026-10-13',
            'keterangan' => 'Awal',
            'grandtotal' => 50_000,
            'sudah_terbayar' => 0,
        ]);

        $invoice->rincian()->create([
            'coa_id' => $beban->id,
            'kuantitas' => 1,
            'harga_barang' => 50_000,
            'subtotal' => 50_000,
            'total' => 50_000,
            'catatan' => 'Awal',
        ]);

        $payload = [
            'nomer_faktur' => 'INV-PB-UPDATED',
            'tanggal_faktur' => '2026-10-14',
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'keterangan' => 'Setelah diubah',
            'rincian' => [
                [
                    'coa_id' => $beban->id,
                    'kuantitas' => 4,
                    'harga' => 25_000,
                    'catatan' => 'Diubah',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('pembelian.invoice.update', $invoice), $payload);

        $response->assertRedirect(route('pembelian.invoice.read', $invoice));

        $invoice->refresh();
        $this->assertSame('INV-PB-UPDATED', $invoice->nomer_faktur);
        $this->assertEquals(100_000, (float) $invoice->grandtotal);
        $this->assertSame('Setelah diubah', $invoice->keterangan);

        $ledgerEntries = DB::table('bukubesar')
            ->where('sumber_transaksi', 'Invoice Pembelian')
            ->where('sumber_id', $invoice->id)
            ->get();

        $this->assertCount(2, $ledgerEntries);
        $kredit = $ledgerEntries->firstWhere('tipe_mutasi', 'K');
        $this->assertEquals(100_000, (float) $kredit->nominal);
        $this->assertSame('INV-PB-UPDATED', $kredit->nomer);
    }

    public function test_user_can_delete_unpaid_invoice_and_clean_ledger(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-05',
            'nama_supplier' => 'PT Hapus Faktur',
            'status_aktif' => true,
        ]);
        $hutang = $this->createCoa('201005', 'Hutang Usaha', 'Utang Usaha');
        $persediaan = $this->createCoa('106005', 'Persediaan', 'Persediaan');

        $invoice = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-PB-DELETE',
            'tanggal_faktur' => '2026-10-13',
            'grandtotal' => 80_000,
            'sudah_terbayar' => 0,
        ]);

        $invoice->rincian()->create([
            'coa_id' => $persediaan->id,
            'kuantitas' => 1,
            'harga_barang' => 80_000,
            'subtotal' => 80_000,
            'total' => 80_000,
        ]);

        DB::table('bukubesar')->insert([
            'coa_id' => $hutang->id,
            'sumber_id' => $invoice->id,
            'tanggal' => '2026-10-13',
            'periode_tahun' => 2026,
            'periode_bulan' => 10,
            'nomer' => $invoice->nomer_faktur,
            'sumber_transaksi' => 'Invoice Pembelian',
            'nominal' => 80_000,
            'tipe_mutasi' => 'K',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pembelian.invoice.destroy', $invoice));

        $response->assertRedirect(route('pembelian.invoice.index'));

        $this->assertDatabaseMissing('faktur_pembelian', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('faktur_pembelian_rinci', ['faktur_pembelian_id' => $invoice->id]);
        $this->assertDatabaseMissing('bukubesar', [
            'sumber_transaksi' => 'Invoice Pembelian',
            'sumber_id' => $invoice->id,
        ]);
    }

    public function test_invoice_with_payment_cannot_be_edited_or_deleted(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-06',
            'nama_supplier' => 'PT Sudah Lunas',
            'status_aktif' => true,
        ]);
        $hutang = $this->createCoa('201006', 'Hutang Usaha', 'Utang Usaha');

        $invoice = FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-PB-PAID',
            'tanggal_faktur' => '2026-10-13',
            'grandtotal' => 100_000,
            'sudah_terbayar' => 100_000, // Sudah ada pembayaran
        ]);

        // Attempt Edit
        $editResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pembelian.invoice.edit', $invoice));

        $editResponse->assertRedirect(route('pembelian.invoice.read', $invoice))
            ->assertSessionHas('error');

        // Attempt Delete
        $deleteResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->delete(route('pembelian.invoice.destroy', $invoice));

        $deleteResponse->assertRedirect(route('pembelian.invoice.read', $invoice))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('faktur_pembelian', ['id' => $invoice->id]);
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pembelian.invoice.store'), []);

        $response->assertSessionHasErrors([
            'nomer_faktur',
            'tanggal_faktur',
            'supplier_id',
            'akun_hutang_id',
            'rincian',
        ]);
    }

    public function test_validation_fails_for_non_payable_account_or_duplicate_number(): void
    {
        $supplier = Supplier::query()->create([
            'kode_supplier' => 'SUP-07',
            'nama_supplier' => 'PT Supplier Unik',
            'status_aktif' => true,
        ]);

        $pendapatan = $this->createCoa('401001', 'Pendapatan Rawat', 'Pendapatan');
        $beban = $this->createCoa('501003', 'Beban Obat', 'Beban');

        // Non-payable account for akun_hutang_id
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pembelian.invoice.store'), [
                'nomer_faktur' => 'INV-DUP-01',
                'tanggal_faktur' => '2026-10-13',
                'supplier_id' => $supplier->id,
                'akun_hutang_id' => $pendapatan->id, // invalid account type
                'rincian' => [
                    ['coa_id' => $beban->id, 'kuantitas' => 1, 'harga' => 10_000],
                ],
            ]);

        $response->assertSessionHasErrors(['akun_hutang_id']);

        // Duplicate nomer_faktur
        $hutang = $this->createCoa('201007', 'Hutang Dagang', 'Utang Usaha');
        FakturPembelian::query()->create([
            'supplier_id' => $supplier->id,
            'akun_hutang_id' => $hutang->id,
            'nomer_faktur' => 'INV-EXISTING',
            'tanggal_faktur' => '2026-10-13',
            'grandtotal' => 10_000,
        ]);

        $dupResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('pembelian.invoice.store'), [
                'nomer_faktur' => 'INV-EXISTING',
                'tanggal_faktur' => '2026-10-13',
                'supplier_id' => $supplier->id,
                'akun_hutang_id' => $hutang->id,
                'rincian' => [
                    ['coa_id' => $beban->id, 'kuantitas' => 1, 'harga' => 10_000],
                ],
            ]);

        $dupResponse->assertSessionHasErrors(['nomer_faktur']);
    }

    public function test_index_page_does_not_contain_bangsal_and_kategori_faktur_columns(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pembelian.invoice.index'));

        $response->assertOk()
            ->assertSee('Nomer faktur')
            ->assertSee('Tanggal faktur')
            ->assertSee('Tgl jth tempo')
            ->assertSee('Supplier')
            ->assertSee('Grandtotal')
            ->assertSee('Sudah terbayar')
            ->assertSee('Status')
            ->assertDontSee('<th>Kode bangsal</th>', false)
            ->assertDontSee('<th>Kategori faktur</th>', false)
            ->assertDontSee("'kode_bangsal'", false)
            ->assertDontSee("'kategori_faktur'", false);
    }

    private function createCoa(string $kode, string $nama, string $tipe): Coa
    {
        return Coa::query()->create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipe,
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->nullable();
            $table->string('nama_supplier');
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('akun_hutang_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('nilai_ppn', 15, 2)->default(0);
            $table->decimal('biaya_kirim', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('status_proses')->nullable();
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
            $table->increments('id');
            $table->unsignedInteger('faktur_pembelian_id');
            $table->unsignedInteger('coa_id')->nullable();
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('diskon_rupiah', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->decimal('harga_barang', 15, 2)->default(0);
            $table->string('kode_barang')->nullable();
            $table->string('nama_barang')->nullable();
            $table->string('satuan_barang')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pembayaran_pembelian_id')->nullable();
            $table->unsignedInteger('faktur_pembelian_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('tipe_mutasi', 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }
}
