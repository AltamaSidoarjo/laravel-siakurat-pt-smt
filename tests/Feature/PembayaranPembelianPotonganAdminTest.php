<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PembayaranPembelianPotonganAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('pembayaran_pembelian');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('supplier');
        Schema::enableForeignKeyConstraints();

        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->nullable();
            $table->string('nama_supplier');
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('akun_hutang_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('status_proses')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('akun_bank_id');
            $table->unsignedBigInteger('akun_hutang_id');
            $table->unsignedBigInteger('akun_potongan_admin_id')->nullable();
            $table->string('nomer_pembayaran');
            $table->date('tanggal');
            $table->decimal('total_bayar', 15, 2)->default(0);
            $table->decimal('potongan_admin', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_pembelian_id');
            $table->unsignedBigInteger('faktur_pembelian_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id');
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('ttd_kabag')->nullable();
            $table->string('ttd_direktur')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('pembayaran_pembelian');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('supplier');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_store_without_potongan_admin_keeps_two_bukubesar_rows(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
            ));

        $response->assertRedirect(route('pembelian.pembayaran.index'));

        $this->assertDatabaseHas('pembayaran_pembelian', [
            'nomer_pembayaran' => 'BYR-001',
            'potongan_admin' => 0,
            'akun_potongan_admin_id' => null,
        ]);

        $pembayaranId = \DB::table('pembayaran_pembelian')->value('id');

        $this->assertDatabaseCount('bukubesar', 2);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'sumber_transaksi' => 'Pembayaran Pembelian',
            'coa_id' => $akunHutangId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'D',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'sumber_transaksi' => 'Pembayaran Pembelian',
            'coa_id' => $akunBankId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'K',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
        ]);
    }

    public function test_store_with_potongan_admin_creates_three_bukubesar_rows(): void
    {
        [$supplierId, $akunBankId, $akunHutangId, $akunPotonganId] = $this->seedMasterData(withPotongan: true);
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
                potonganAdmin: 500_000,
                akunPotonganAdminId: $akunPotonganId,
            ));

        $response->assertRedirect(route('pembelian.pembayaran.index'));

        $pembayaranId = \DB::table('pembayaran_pembelian')->value('id');

        $this->assertDatabaseHas('pembayaran_pembelian', [
            'id' => $pembayaranId,
            'potongan_admin' => 500_000,
            'akun_potongan_admin_id' => $akunPotonganId,
        ]);

        $this->assertDatabaseCount('bukubesar', 3);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunHutangId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'D',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunBankId,
            'nominal' => 4_500_000,
            'tipe_mutasi' => 'K',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunPotonganId,
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
        ]);
    }

    public function test_update_rebuilds_bukubesar_rows_based_on_latest_potongan_admin(): void
    {
        [$supplierId, $akunBankId, $akunHutangId, $akunPotonganId] = $this->seedMasterData(withPotongan: true);
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
            ));

        $pembayaranId = \DB::table('pembayaran_pembelian')->value('id');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->put(route('pembelian.pembayaran.update', $pembayaranId), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
                potonganAdmin: 500_000,
                akunPotonganAdminId: $akunPotonganId,
                nomorPembayaran: 'BYR-001-REV',
            ));

        $response->assertRedirect(route('pembelian.pembayaran.index'));

        $this->assertDatabaseMissing('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunBankId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunBankId,
            'nominal' => 4_500_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $akunPotonganId,
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseCount('bukubesar', 3);
    }

    public function test_delete_removes_related_bukubesar_rows(): void
    {
        [$supplierId, $akunBankId, $akunHutangId, $akunPotonganId] = $this->seedMasterData(withPotongan: true);
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
                potonganAdmin: 500_000,
                akunPotonganAdminId: $akunPotonganId,
            ));

        $pembayaranId = \DB::table('pembayaran_pembelian')->value('id');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->delete(route('pembelian.pembayaran.destroy', $pembayaranId));

        $response->assertRedirect(route('pembelian.pembayaran.index'));

        $this->assertDatabaseMissing('pembayaran_pembelian', ['id' => $pembayaranId]);
        $this->assertDatabaseCount('bukubesar', 0);
    }

    public function test_validation_requires_akun_potongan_and_limits_nominal(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.create'))
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
                potonganAdmin: 5_500_000,
            ));

        $response
            ->assertRedirect(route('pembelian.pembayaran.create'))
            ->assertSessionHasErrors(['akun_potongan_admin_id', 'potongan_admin']);
    }

    public function test_print_page_shows_potongan_admin_and_nominal_bank(): void
    {
        [$supplierId, $akunBankId, $akunHutangId, $akunPotonganId] = $this->seedMasterData(withPotongan: true);
        $invoiceId = $this->createInvoice($supplierId, 5_000_000);

        \DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RS Contoh Sehat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.store'), $this->buildPayload(
                supplierId: $supplierId,
                akunBankId: $akunBankId,
                akunHutangId: $akunHutangId,
                invoiceId: $invoiceId,
                totalBayar: 5_000_000,
                potonganAdmin: 500_000,
                akunPotonganAdminId: $akunPotonganId,
            ));

        $pembayaranId = \DB::table('pembayaran_pembelian')->value('id');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.pembayaran.print', $pembayaranId));

        $response
            ->assertOk()
            ->assertSee('Potongan Admin')
            ->assertSee('500.000')
            ->assertSee('Nominal Bank')
            ->assertSee('4.500.000');
    }

    public function test_api_invoice_by_supplier_only_returns_invoices_with_remaining_balance(): void
    {
        [$supplierId] = $this->seedMasterData();
        $unpaidInvoiceId = $this->createInvoice($supplierId, 5_000_000, 0, 'INV-PB-BELUM-BAYAR');
        $partialInvoiceId = $this->createInvoice($supplierId, 5_000_000, 2_000_000, 'INV-PB-SEBAGIAN');
        $oneRupiahRemainingInvoiceId = $this->createInvoice($supplierId, 2_431_579.21, 2_431_578.99, 'INV-PB-SISA-RUPIAH');
        $decimalOnlyRemainingInvoiceId = $this->createInvoice($supplierId, 2_431_578.21, 2_431_578, 'INV-PB-SISA-DESIMAL');
        $paidInvoiceId = $this->createInvoice($supplierId, 5_000_000, 5_000_000, 'INV-PB-LUNAS');
        $overpaidInvoiceId = $this->createInvoice($supplierId, 5_000_000, 6_000_000, 'INV-PB-LEBIH-BAYAR');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->getJson(route('pembelian.pembayaran.api.invoice-by-supplier', ['id' => $supplierId]));

        $response
            ->assertOk()
            ->assertJsonPath('data.supplier.faktur_pembelians.0.id', $unpaidInvoiceId)
            ->assertJsonPath('data.supplier.faktur_pembelians.1.id', $partialInvoiceId);

        $invoiceIds = collect($response->json('data.supplier.faktur_pembelians'))->pluck('id')->all();

        $this->assertContains($unpaidInvoiceId, $invoiceIds);
        $this->assertContains($partialInvoiceId, $invoiceIds);
        $this->assertContains($oneRupiahRemainingInvoiceId, $invoiceIds);
        $this->assertNotContains($decimalOnlyRemainingInvoiceId, $invoiceIds);
        $this->assertNotContains($paidInvoiceId, $invoiceIds);
        $this->assertNotContains($overpaidInvoiceId, $invoiceIds);
    }

    public function test_create_supplier_options_only_include_suppliers_with_remaining_invoice_balance(): void
    {
        [$openSupplierId] = $this->seedMasterData();
        $paidSupplierId = \DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-002',
            'nama_supplier' => 'PT Supplier Lunas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createInvoice($openSupplierId, 5_000_000, 1_000_000, 'INV-PB-OPEN');
        $this->createInvoice($paidSupplierId, 5_000_000, 5_000_000, 'INV-PB-PAID');

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.pembayaran.create'));

        $response
            ->assertOk()
            ->assertSee('SUP-001 - PT Supplier Utama')
            ->assertDontSee('SUP-002 - PT Supplier Lunas');
    }

    private function seedMasterData(bool $withPotongan = false): array
    {
        $supplierId = \DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Supplier Utama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunBankId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '111001',
            'nama' => 'Bank BCA',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunHutangId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '210001',
            'nama' => 'Hutang Usaha',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $withPotongan) {
            return [$supplierId, $akunBankId, $akunHutangId];
        }

        $akunPotonganId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '510001',
            'nama' => 'Potongan Admin',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supplierId, $akunBankId, $akunHutangId, $akunPotonganId];
    }

    private function createInvoice(
        int $supplierId,
        int|float $grandTotal,
        int|float $sudahTerbayar = 0,
        string $nomorFaktur = 'INV-PB-0001',
    ): int {
        return \DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'nomer_faktur' => $nomorFaktur,
            'tanggal_faktur' => '2026-05-20',
            'grandtotal' => $grandTotal,
            'sudah_terbayar' => $sudahTerbayar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildPayload(
        int $supplierId,
        int $akunBankId,
        int $akunHutangId,
        int $invoiceId,
        int $totalBayar,
        int $potonganAdmin = 0,
        ?int $akunPotonganAdminId = null,
        string $nomorPembayaran = 'BYR-001',
    ): array {
        return [
            'supplier_id' => $supplierId,
            'akun_bank_id' => $akunBankId,
            'akun_hutang_id' => $akunHutangId,
            'akun_potongan_admin_id' => $akunPotonganAdminId,
            'nomer_pembayaran' => $nomorPembayaran,
            'tanggal' => '2026-05-20',
            'total_bayar' => $totalBayar,
            'potongan_admin' => $potonganAdmin,
            'keterangan' => 'Pembayaran invoice supplier',
            'rincian' => [
                [
                    'faktur_pembelian_id' => $invoiceId,
                    'nominal_bayar' => $totalBayar,
                    'check' => '1',
                ],
            ],
        ];
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
