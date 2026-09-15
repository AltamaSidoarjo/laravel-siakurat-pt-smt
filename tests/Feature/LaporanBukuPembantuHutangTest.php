<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Laporan\LaporanPembelianService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
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
        Carbon::setTestNow();

        Schema::disableForeignKeyConstraints();
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_initial_page_uses_today_and_loads_all_suppliers_with_open_debt(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'INV-OPEN', '2026-09-01', 1000, dueDate: '2026-09-10');

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertViewHas('supplierIds', [])
            ->assertSee('Rincian Buku Pembantu Hutang')
            ->assertSee('RINCIAN BUKU PEMBANTU HUTANG')
            ->assertDontSee('MATA UANG DASAR')
            ->assertSee('INV-OPEN')
            ->assertSee('0 - 30 Hari')
            ->assertDontSee('Mata Uang')
            ->assertSee('Print')
            ->assertSee('Export CSV');
    }

    public function test_supplier_filter_limits_the_report_but_options_still_include_all_suppliers(): void
    {
        [$firstSupplierId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Farma Dua');
        $this->createInvoice($firstSupplierId, 'INV-FIRST', '2026-09-01', 100);
        $this->createInvoice($secondSupplierId, 'INV-SECOND', '2026-09-01', 200);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang', [
                'reportDate' => '2026-09-15',
                'supplierIds' => [$secondSupplierId],
            ]))
            ->assertOk()
            ->assertSee('[SUP-001] PT Sehat Farma')
            ->assertSee('[SUP-002] PT Farma Dua')
            ->assertSee('INV-SECOND')
            ->assertDontSee('INV-FIRST');
    }

    public function test_report_assigns_exact_aging_boundaries_and_falls_back_to_invoice_date(): void
    {
        [$supplierId] = $this->seedMasterData();
        $reportDate = Carbon::parse('2026-09-15');

        $this->createInvoice($supplierId, 'INV-FUTURE', '2026-09-01', 100, dueDate: $reportDate->copy()->addDays(5)->format('Y-m-d'));
        foreach ([30, 31, 60, 61, 90, 91] as $age) {
            $this->createInvoice(
                $supplierId,
                'INV-'.$age,
                '2026-01-01',
                $age * 10,
                dueDate: $reportDate->copy()->subDays($age)->format('Y-m-d'),
            );
        }
        $fallbackDate = $reportDate->copy()->subDays(61)->format('Y-m-d');
        $this->createInvoice($supplierId, 'INV-FALLBACK', $fallbackDate, 610);

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutang('2026-09-15', [$supplierId]);
        $rows = collect($report['cards'][0]['rows'])->keyBy('nomor_referensi');

        $this->assertSame(100.0, $rows['INV-FUTURE']['days_0_30']);
        $this->assertSame(300.0, $rows['INV-30']['days_0_30']);
        $this->assertSame(310.0, $rows['INV-31']['days_31_60']);
        $this->assertSame(600.0, $rows['INV-60']['days_31_60']);
        $this->assertSame(610.0, $rows['INV-61']['days_61_90']);
        $this->assertSame(900.0, $rows['INV-90']['days_61_90']);
        $this->assertSame(910.0, $rows['INV-91']['days_over_90']);
        $this->assertSame($fallbackDate, $rows['INV-FALLBACK']['tanggal_jatuh_tempo']);
        $this->assertSame(610.0, $rows['INV-FALLBACK']['days_61_90']);
    }

    public function test_historical_balance_ignores_later_payments_and_includes_direct_payment(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice(
            $supplierId,
            'INV-HISTORY',
            '2026-08-01',
            1000,
            paid: 600,
            dueDate: '2026-08-15',
        );
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-BEFORE', '2026-09-10', 200);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-AFTER', '2026-09-20', 300);

        $this->createInvoice($supplierId, 'INV-PAID', '2026-08-01', 100, paid: 100);
        $this->createInvoice($supplierId, 'INV-OVERPAID', '2026-08-01', 100, paid: 150);

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutang('2026-09-15');
        $rows = collect($report['cards'][0]['rows'])->keyBy('nomor_referensi');

        $this->assertSame(700.0, $rows['INV-HISTORY']['sisa_hutang']);
        $this->assertSame(700.0, $rows['INV-HISTORY']['days_31_60']);
        $this->assertFalse($rows->has('INV-PAID'));
        $this->assertFalse($rows->has('INV-OVERPAID'));
    }

    public function test_supplier_subtotals_and_grand_totals_match_visible_rows(): void
    {
        [$firstSupplierId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Farma Dua');
        $this->createInvoice($firstSupplierId, 'INV-A', '2026-09-01', 100, dueDate: '2026-09-10');
        $this->createInvoice($firstSupplierId, 'INV-B', '2026-06-01', 400, dueDate: '2026-06-01');
        $this->createInvoice($secondSupplierId, 'INV-C', '2026-08-01', 250, dueDate: '2026-08-01');
        $this->createInvoice($secondSupplierId, 'INV-FUTURE-DATED', '2026-09-20', 999, dueDate: '2026-09-20');

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutang('2026-09-15');

        $this->assertSame(['SUP-001', 'SUP-002'], array_column($report['cards'], 'kode_supplier'));
        $this->assertSame(100.0, $report['cards'][0]['totals']['days_0_30']);
        $this->assertSame(400.0, $report['cards'][0]['totals']['days_over_90']);
        $this->assertSame(500.0, $report['cards'][0]['saldo_hutang']);
        $this->assertSame(250.0, $report['cards'][1]['totals']['days_31_60']);
        $this->assertSame(100.0, $report['summary']['days_0_30']);
        $this->assertSame(250.0, $report['summary']['days_31_60']);
        $this->assertSame(400.0, $report['summary']['days_over_90']);
        $this->assertSame(750.0, $report['summary']['saldo_hutang']);
    }

    public function test_csv_uses_aging_columns_rows_subtotals_and_report_date_filename(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'INV-CSV', '2026-09-01', 1000, dueDate: '2026-09-10');

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rincian-buku-pembantu-hutang-20260915.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Jatuh Tempo",Tipe,"No. Referensi","0 - 30 Hari","31 - 60 Hari","61 - 90 Hari","> 90 Hari"', $content);
        $this->assertStringNotContainsString('Mata Uang', $content);
        $this->assertStringContainsString('2026-09-01,2026-09-10,FP,INV-CSV,1000.00,,,', $content);
        $this->assertStringContainsString('"Saldo PT Sehat Farma",1000.00,0.00,0.00,0.00', $content);
        $this->assertStringContainsString('"GRAND TOTAL",1000.00,0.00,0.00,0.00', $content);
    }

    public function test_summary_page_uses_today_and_has_no_currency_labels(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'INV-SUMMARY', '2026-09-01', 1000, dueDate: '2026-09-10');

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.rangkuman-buku-pembantu-hutang'))
            ->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertViewHas('supplierIds', [])
            ->assertSee('RANGKUMAN BUKU PEMBANTU HUTANG')
            ->assertSee('PT SEHAT FARMA | SUP-001')
            ->assertSee('0 - 30 Hari')
            ->assertSee('1.000,00')
            ->assertDontSee('Mata Uang')
            ->assertDontSee('MATA UANG DASAR')
            ->assertDontSee('IDR');
    }

    public function test_summary_totals_match_detail_and_historical_payment_cutoff(): void
    {
        [$firstSupplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Farma Dua');
        $invoiceId = $this->createInvoice(
            $firstSupplierId,
            'INV-HISTORY-SUMMARY',
            '2026-08-01',
            1000,
            paid: 500,
            dueDate: '2026-08-15',
        );
        $this->createPayment($firstSupplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-BEFORE', '2026-09-10', 200);
        $this->createPayment($firstSupplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-AFTER', '2026-09-20', 300);
        $this->createInvoice($secondSupplierId, 'INV-SUMMARY-SECOND', '2026-06-01', 400, dueDate: '2026-06-01');

        $service = app(LaporanPembelianService::class);
        $detail = $service->getBukuPembantuHutang('2026-09-15');
        $summary = $service->getRangkumanBukuPembantuHutang('2026-09-15');

        $this->assertCount(2, $summary['rows']);
        $this->assertSame($detail['summary'], $summary['summary']);
        $this->assertSame($detail['cards'][0]['totals'], [
            'days_0_30' => $summary['rows'][0]['days_0_30'],
            'days_31_60' => $summary['rows'][0]['days_31_60'],
            'days_61_90' => $summary['rows'][0]['days_61_90'],
            'days_over_90' => $summary['rows'][0]['days_over_90'],
        ]);
        $this->assertSame(800.0, $summary['rows'][0]['total_hutang']);
        $this->assertSame(400.0, $summary['rows'][1]['days_over_90']);
        $this->assertSame(1200.0, $summary['summary']['saldo_hutang']);

        $filtered = $service->getRangkumanBukuPembantuHutang('2026-09-15', [$secondSupplierId]);
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('SUP-002', $filtered['rows'][0]['kode_supplier']);
        $this->assertSame(400.0, $filtered['summary']['saldo_hutang']);
    }

    public function test_summary_csv_contains_supplier_totals_and_grand_total(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'INV-SUMMARY-CSV', '2026-09-01', 1000, dueDate: '2026-09-10');

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.rangkuman-buku-pembantu-hutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rangkuman-buku-pembantu-hutang-20260915.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Nama Supplier","0 - 30 Hari","31 - 60 Hari","61 - 90 Hari","> 90 Hari","Total Hutang"', $content);
        $this->assertStringContainsString('SUP-001,"PT Sehat Farma",1000.00,0.00,0.00,0.00,1000.00', $content);
        $this->assertStringContainsString('"GRAND TOTAL",1000.00,0.00,0.00,0.00,1000.00', $content);
        $this->assertStringNotContainsString('Mata Uang', $content);
        $this->assertStringNotContainsString('IDR', $content);
    }

    public function test_request_validates_report_date_and_supplier_ids(): void
    {
        $this->actingAs($this->makeUser())
            ->from(route('laporan.pembelian.buku-pembantu-hutang'))
            ->get(route('laporan.pembelian.buku-pembantu-hutang', [
                'reportDate' => '15-09-2026',
                'supplierIds' => [999],
            ]))
            ->assertRedirect(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertSessionHasErrors(['reportDate', 'supplierIds.0']);
    }

    public function test_supplier_search_returns_suppliers_with_or_without_invoices(): void
    {
        [$supplierId] = $this->seedMasterData();
        $supplierTanpaFakturId = $this->createSupplier('SUP-EMPTY', 'PT Tanpa Faktur');
        $this->createInvoice($supplierId, 'INV-CARI', '2026-09-01', 100);

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-supplier', ['q' => 'Sehat']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $supplierId)
            ->assertJsonPath('results.0.text', '[SUP-001] PT Sehat Farma');

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-supplier', ['q' => 'Tanpa Faktur']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $supplierTanpaFakturId);
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
            $table->date('tanggal_jatuh_tempo')->nullable();
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

    private function createInvoice(
        int $supplierId,
        string $nomor,
        string $tanggal,
        float $grandtotal,
        float $paid = 0,
        ?string $dueDate = null,
    ): int {
        return DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'nomer_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'tanggal_jatuh_tempo' => $dueDate,
            'keterangan' => 'Pembelian persediaan',
            'kategori_faktur' => 'Obat & BHP',
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $paid,
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
