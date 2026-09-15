<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePembelianPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::enableForeignKeyConstraints();

        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->nullable();
            $table->string('nama_supplier');
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
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
            $table->id();
            $table->unsignedBigInteger('faktur_pembelian_id');
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

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('shortname')->nullable();
            $table->string('npwp_perusahaan')->nullable();
            $table->string('no_telp_perusahaan')->nullable();
            $table->string('email_perusahaan')->nullable();
            $table->string('nama_penandatangan')->nullable();
            $table->text('alamat_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->string('ttd_kabag')->nullable();
            $table->string('ttd_direktur')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_invoice_pembelian_print_page_renders_header_and_detail(): void
    {
        $supplierId = \DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Supplier Utama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = \DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'nomer_faktur' => 'INV-PB-0001',
            'tanggal_faktur' => '2026-05-19',
            'tanggal_jatuh_tempo' => '2026-05-30',
            'keterangan' => 'Pembelian obat dan BHP',
            'nilai_ppn' => 5000,
            'biaya_kirim' => 2500,
            'sudah_terbayar' => 0,
            'grandtotal' => 57500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('faktur_pembelian_rinci')->insert([
            'faktur_pembelian_id' => $invoiceId,
            'kode_barang' => 'OBT-001',
            'nama_barang' => 'Paracetamol',
            'satuan_barang' => 'Box',
            'harga_barang' => 25000,
            'kuantitas' => 2,
            'total' => 50000,
            'subtotal' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RS Contoh Sehat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('pembelian.invoice.print', $invoiceId));

        $response
            ->assertOk()
            ->assertSee('Invoice Pembelian')
            ->assertSee('INV-PB-0001')
            ->assertSee('PT Supplier Utama')
            ->assertSee('Paracetamol')
            ->assertSee('57.500');
    }

    public function test_invoice_index_status_ignores_decimal_fraction_when_checking_paid_status(): void
    {
        $supplierId = \DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Supplier Utama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('faktur_pembelian')->insert([
            'supplier_id' => $supplierId,
            'nomer_faktur' => 'BSP1872',
            'tanggal_faktur' => '2026-02-04',
            'tanggal_jatuh_tempo' => '2026-02-05',
            'sudah_terbayar' => 2_431_578,
            'grandtotal' => 2_431_578.21,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->getJson(route('pembelian.invoice.load-data', [
                'startDate' => '2026-02-01',
                'endDate' => '2026-02-28',
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.nomer_faktur', 'BSP1872')
            ->assertJsonPath('data.0.status_text', 'Sudah Lunas');
    }

    public function test_invoice_index_displays_and_preserves_multiple_supplier_filters(): void
    {
        $firstSupplierId = $this->createSupplier('SUP-001', 'PT Supplier Pertama');
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Supplier Kedua', false);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.invoice.index', [
                'startDate' => '2026-02-01',
                'endDate' => '2026-02-28',
                'supplierIds' => [$firstSupplierId, $secondSupplierId],
            ]));

        $response
            ->assertOk()
            ->assertViewHas('supplierIds', [$firstSupplierId, $secondSupplierId])
            ->assertSee('PT Supplier Pertama')
            ->assertSee('PT Supplier Kedua')
            ->assertSee('2 supplier dipilih.');
    }

    public function test_invoice_load_data_filters_multiple_suppliers(): void
    {
        $firstSupplierId = $this->createSupplier('SUP-001', 'PT Supplier Pertama');
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Supplier Kedua');
        $excludedSupplierId = $this->createSupplier('SUP-003', 'PT Supplier Lain');

        $this->createInvoice($firstSupplierId, 'INV-001');
        $this->createInvoice($secondSupplierId, 'INV-002');
        $this->createInvoice($excludedSupplierId, 'INV-003');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->getJson(route('pembelian.invoice.load-data', [
                'startDate' => '2026-02-01',
                'endDate' => '2026-02-28',
                'supplierIds' => [$firstSupplierId, $secondSupplierId],
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['nomer_faktur' => 'INV-001'])
            ->assertJsonFragment(['nomer_faktur' => 'INV-002'])
            ->assertJsonMissing(['nomer_faktur' => 'INV-003']);
    }

    public function test_invoice_load_data_without_supplier_filter_returns_all_suppliers(): void
    {
        $firstSupplierId = $this->createSupplier('SUP-001', 'PT Supplier Pertama');
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Supplier Kedua');

        $this->createInvoice($firstSupplierId, 'INV-001');
        $this->createInvoice($secondSupplierId, 'INV-002');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->getJson(route('pembelian.invoice.load-data', [
                'startDate' => '2026-02-01',
                'endDate' => '2026-02-28',
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['nomer_faktur' => 'INV-001'])
            ->assertJsonFragment(['nomer_faktur' => 'INV-002']);
    }

    public function test_invoice_supplier_filter_rejects_invalid_values(): void
    {
        $supplierId = $this->createSupplier('SUP-001', 'PT Supplier Pertama');

        foreach ([
            [['invalid'], 'supplierIds.0'],
            [[999999], 'supplierIds.0'],
            [[$supplierId, $supplierId], 'supplierIds.1'],
        ] as [$supplierIds, $errorKey]) {
            $response = $this
                ->withoutMiddleware(EnsureModuleAccess::class)
                ->actingAs($this->makeUser())
                ->getJson(route('pembelian.invoice.load-data', [
                    'startDate' => '2026-02-01',
                    'endDate' => '2026-02-28',
                    'supplierIds' => $supplierIds,
                ]));

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors($errorKey);
        }
    }

    public function test_invoice_csv_export_follows_supplier_filter(): void
    {
        $selectedSupplierId = $this->createSupplier('SUP-001', 'PT Supplier Terpilih');
        $excludedSupplierId = $this->createSupplier('SUP-002', 'PT Supplier Dikecualikan');

        $this->createInvoice($selectedSupplierId, 'INV-TERPILIH');
        $this->createInvoice($excludedSupplierId, 'INV-DIKECUALIKAN');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.invoice.export-csv', [
                'startDate' => '2026-02-01',
                'endDate' => '2026-02-28',
                'supplierIds' => [$selectedSupplierId],
            ]));

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());

        $response->assertOk();
        $this->assertStringContainsString('INV-TERPILIH', $content);
        $this->assertStringContainsString('PT Supplier Terpilih', $content);
        $this->assertStringNotContainsString('INV-DIKECUALIKAN', $content);
        $this->assertStringNotContainsString('PT Supplier Dikecualikan', $content);
    }

    private function createSupplier(string $code, string $name, bool $active = true): int
    {
        return (int) \DB::table('supplier')->insertGetId([
            'status_aktif' => $active,
            'kode_supplier' => $code,
            'nama_supplier' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInvoice(int $supplierId, string $number): int
    {
        return (int) \DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'nomer_faktur' => $number,
            'tanggal_faktur' => '2026-02-15',
            'tanggal_jatuh_tempo' => '2026-03-15',
            'sudah_terbayar' => 0,
            'grandtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
