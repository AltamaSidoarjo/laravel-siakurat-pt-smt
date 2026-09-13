<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Laporan\LaporanPembelianService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanBukuPembantuHutangTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_initial_page_does_not_load_supplier_cards(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertOk()
            ->assertSee('Pilih minimal satu supplier')
            ->assertViewHas('cards', []);
    }

    public function test_initial_page_renders_supplier_options_without_ajax(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createSupplier('SUP-EMPTY', 'PT Tanpa Faktur');
        $this->createInvoice($supplierId, 'INV-OPTION', '2026-09-01', 100, 0);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertOk()
            ->assertSee('[SUP-001] PT Sehat Farma')
            ->assertDontSee('PT Tanpa Faktur')
            ->assertDontSee('ajax: {', false);
    }

    public function test_report_calculates_opening_invoices_direct_payments_allocations_and_running_balance(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceLama = $this->createInvoice($supplierId, 'INV-LAMA', '2026-08-20', 1000, 400);
        $invoicePeriode = $this->createInvoice($supplierId, 'INV-PERIODE', '2026-09-05', 800, 500);

        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceLama, 'BYR-001', '2026-08-25', 400);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoicePeriode, 'BYR-002', '2026-09-10', 200);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceLama, 'BYR-003', '2026-09-12', 100);

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutang(
            '2026-09-01',
            '2026-09-30',
            [$supplierId],
        );

        $this->assertCount(1, $report['cards']);
        $card = $report['cards'][0];
        $this->assertSame(600.0, $card['saldo_awal']);
        $this->assertSame(600.0, $card['total_debit']);
        $this->assertSame(800.0, $card['total_kredit']);
        $this->assertSame(800.0, $card['saldo_akhir']);
        $this->assertSame(
            ['Faktur', 'Pembayaran langsung', 'Pembayaran', 'Pembayaran'],
            array_column($card['rows'], 'jenis'),
        );
        $this->assertSame([1400.0, 1100.0, 900.0, 800.0], array_column($card['rows'], 'saldo'));
        $this->assertSame('-', $card['rows'][0]['akun']);
        $this->assertSame('[210.01] Hutang Usaha', $card['rows'][2]['akun']);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
            ]))
            ->assertOk()
            ->assertSee('PT Sehat Farma')
            ->assertSee('INV-PERIODE')
            ->assertSee('BYR-003');
    }

    public function test_status_filter_and_summary_handle_multiple_suppliers_and_negative_balance(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $supplierLunas = $this->createSupplier('SUP-002', 'PT Lunas Selalu');
        $invoiceHutang = $this->createInvoice($supplierId, 'INV-HUTANG', '2026-09-01', 500, 0);
        $invoiceLunas = $this->createInvoice($supplierLunas, 'INV-LUNAS', '2026-09-01', 200, 200);
        $this->createPayment($supplierLunas, $akunBankId, $akunHutangId, $invoiceLunas, 'BYR-LUNAS', '2026-09-02', 250);
        DB::table('faktur_pembelian')->where('id', $invoiceLunas)->update(['sudah_terbayar' => 250]);

        $service = app(LaporanPembelianService::class);
        $all = $service->getBukuPembantuHutang('2026-09-01', '2026-09-30', [$supplierId, $supplierLunas]);
        $this->assertCount(2, $all['cards']);
        $this->assertSame(450.0, $all['summary']['saldo_akhir']);

        $outstanding = $service->getBukuPembantuHutang('2026-09-01', '2026-09-30', [$supplierId, $supplierLunas], 'masih-hutang');
        $this->assertCount(1, $outstanding['cards']);
        $this->assertSame($invoiceHutang, $outstanding['cards'][0]['rows'][0]['faktur_id']);

        $paid = $service->getBukuPembantuHutang('2026-09-01', '2026-09-30', [$supplierId, $supplierLunas], 'lunas');
        $this->assertSame([], $paid['cards']);
        $this->assertSame(-50.0, $all['cards'][1]['saldo_akhir']);
    }

    public function test_csv_uses_the_same_filters_and_running_balances(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($supplierId, 'INV-CSV', '2026-09-05', 1000, 250);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-CSV', '2026-09-10', 250);

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
                'statusSaldo' => 'semua',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=buku-pembantu-hutang-20260901-20260930.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('INV-CSV,Faktur,INV-CSV', $content);
        $this->assertStringContainsString('BYR-CSV,Pembayaran,INV-CSV', $content);
        $this->assertStringContainsString('0.00,1000.00,1000.00', $content);
        $this->assertStringContainsString('250.00,0.00,750.00', $content);
    }

    public function test_export_requires_dates_and_at_least_one_supplier(): void
    {
        $this->actingAs($this->makeUser())
            ->from(route('laporan.pembelian.buku-pembantu-hutang'))
            ->get(route('laporan.pembelian.buku-pembantu-hutang.export-csv', [
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
            ]))
            ->assertRedirect(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertSessionHasErrors(['endDate', 'supplierIds']);
    }

    public function test_supplier_search_only_returns_suppliers_with_invoices(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createSupplier('SUP-EMPTY', 'PT Tanpa Faktur');
        $this->createInvoice($supplierId, 'INV-CARI', '2026-09-01', 100, 0);

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-supplier', ['q' => 'Sehat']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $supplierId)
            ->assertJsonPath('results.0.text', '[SUP-001] PT Sehat Farma');
    }

    public function test_supplier_search_returns_initial_options_without_keyword(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createSupplier('SUP-EMPTY', 'PT Tanpa Faktur');
        $this->createInvoice($supplierId, 'INV-AWAL', '2026-09-01', 100, 0);

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-supplier'))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $supplierId);
    }

    private function tables(): array
    {
        return [
            'preferensi_perusahaan',
            'pembayaran_pembelian_rinci',
            'pembayaran_pembelian',
            'faktur_pembelian',
            'coa',
            'supplier',
        ];
    }

    private function createTables(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->nullable();
            $table->string('nama_supplier');
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('kategori_faktur')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('akun_bank_id');
            $table->unsignedBigInteger('akun_hutang_id')->nullable();
            $table->string('nomer_pembayaran');
            $table->date('tanggal');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_pembelian_id');
            $table->unsignedBigInteger('faktur_pembelian_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
    }

    private function seedMasterData(): array
    {
        $supplierId = $this->createSupplier('SUP-001', 'PT Sehat Farma');
        $akunBankId = DB::table('coa')->insertGetId([
            'kode' => '111.01', 'nama' => 'Bank', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $akunHutangId = DB::table('coa')->insertGetId([
            'kode' => '210.01', 'nama' => 'Hutang Usaha', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$supplierId, $akunBankId, $akunHutangId];
    }

    private function createSupplier(string $kode, string $nama): int
    {
        return DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => $kode,
            'nama_supplier' => $nama,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInvoice(int $supplierId, string $nomor, string $tanggal, float $grandtotal, float $sudahTerbayar): int
    {
        return DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'nomer_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'keterangan' => 'Pembelian persediaan',
            'kategori_faktur' => 'Obat & BHP',
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $sudahTerbayar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPayment(
        int $supplierId,
        int $akunBankId,
        int $akunHutangId,
        int $invoiceId,
        string $nomor,
        string $tanggal,
        float $nominal,
    ): int {
        $paymentId = DB::table('pembayaran_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'akun_bank_id' => $akunBankId,
            'akun_hutang_id' => $akunHutangId,
            'nomer_pembayaran' => $nomor,
            'tanggal' => $tanggal,
            'keterangan' => 'Pembayaran supplier',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayaran_pembelian_rinci')->insert([
            'pembayaran_pembelian_id' => $paymentId,
            'faktur_pembelian_id' => $invoiceId,
            'nominal_bayar' => $nominal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $paymentId;
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
