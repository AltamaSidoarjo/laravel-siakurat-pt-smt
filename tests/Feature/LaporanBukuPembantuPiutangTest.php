<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Laporan\LaporanPendapatanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanBukuPembantuPiutangTest extends TestCase
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

    public function test_initial_page_uses_today_and_loads_all_customers_with_open_receivables(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$pelangganId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, 'INV-OPEN', '2026-09-01', 1000);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang'))
            ->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertViewHas('pelangganIds', [])
            ->assertSee('Rincian Buku Pembantu Piutang')
            ->assertSee('RINCIAN BUKU PEMBANTU PIUTANG')
            ->assertSee('INV-OPEN')
            ->assertSee('0 - 30 Hari')
            ->assertSee('Print')
            ->assertSee('Export CSV')
            ->assertSee('(IDR)')
            ->assertDontSee('MATA UANG DASAR')
            ->assertDontSee('Mata Uang')
            ->assertDontSee('Jatuh Tempo');
    }

    public function test_customer_filter_limits_report_but_options_include_all_customers(): void
    {
        [$firstCustomerId] = $this->seedMasterData();
        $secondCustomerId = $this->createCustomer('PLG-002', 'Asuransi Sehat');
        $this->createInvoice($firstCustomerId, 'INV-FIRST', '2026-09-01', 100);
        $this->createInvoice($secondCustomerId, 'INV-SECOND', '2026-09-01', 200);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang', [
                'reportDate' => '2026-09-15',
                'pelangganIds' => [$secondCustomerId],
            ]))
            ->assertOk()
            ->assertSee('[PLG-001] BPJS Kesehatan')
            ->assertSee('[PLG-002] Asuransi Sehat')
            ->assertSee('INV-SECOND')
            ->assertDontSee('INV-FIRST');
    }

    public function test_customers_without_open_receivables_are_not_reported(): void
    {
        [$pelangganId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, 'INV-LUNAS', '2026-09-01', 100, paid: 100);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang', ['reportDate' => '2026-09-15']))
            ->assertOk()
            ->assertViewHas('cards', [])
            ->assertSee('Tidak ada piutang terbuka pada tanggal laporan.');

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rangkuman-buku-pembantu-piutang', ['reportDate' => '2026-09-15']))
            ->assertOk()
            ->assertViewHas('rows', [])
            ->assertSee('Tidak ada piutang terbuka pada tanggal laporan.');
    }

    public function test_report_assigns_exact_aging_boundaries_from_invoice_date(): void
    {
        [$pelangganId] = $this->seedMasterData();
        $reportDate = Carbon::parse('2026-09-15');

        foreach ([0, 30, 31, 60, 61, 90, 91] as $age) {
            $this->createInvoice(
                $pelangganId,
                'INV-'.$age,
                $reportDate->copy()->subDays($age)->format('Y-m-d'),
                100 + $age,
            );
        }

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutang('2026-09-15', [$pelangganId]);
        $rows = collect($report['cards'][0]['rows'])->keyBy('nomor_referensi');

        $this->assertSame(100.0, $rows['INV-0']['days_0_30']);
        $this->assertSame(130.0, $rows['INV-30']['days_0_30']);
        $this->assertSame(131.0, $rows['INV-31']['days_31_60']);
        $this->assertSame(160.0, $rows['INV-60']['days_31_60']);
        $this->assertSame(161.0, $rows['INV-61']['days_61_90']);
        $this->assertSame(190.0, $rows['INV-90']['days_61_90']);
        $this->assertSame(191.0, $rows['INV-91']['days_over_90']);
    }

    public function test_historical_balance_ignores_later_receipts_and_includes_direct_payment(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice(
            $pelangganId,
            'INV-HISTORY',
            '2026-08-01',
            1000,
            paid: 600,
            akunPiutangId: $akunPiutangId,
        );
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-BEFORE', '2026-09-10', 200);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-AFTER', '2026-09-20', 300);

        $this->createInvoice($pelangganId, 'INV-PAID', '2026-08-01', 100, paid: 100);
        $this->createInvoice($pelangganId, 'INV-OVERPAID', '2026-08-01', 100, paid: 150);

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutang('2026-09-15');
        $rows = collect($report['cards'][0]['rows'])->keyBy('nomor_referensi');

        $this->assertSame(700.0, $rows['INV-HISTORY']['sisa_piutang']);
        $this->assertSame(700.0, $rows['INV-HISTORY']['days_31_60']);
        $this->assertFalse($rows->has('INV-PAID'));
        $this->assertFalse($rows->has('INV-OVERPAID'));
    }

    public function test_customer_subtotals_and_grand_totals_match_visible_rows(): void
    {
        [$firstCustomerId] = $this->seedMasterData();
        $secondCustomerId = $this->createCustomer('PLG-002', 'Asuransi Sehat');
        $this->createInvoice($firstCustomerId, 'INV-A', '2026-09-15', 100);
        $this->createInvoice($firstCustomerId, 'INV-B', '2026-06-16', 400);
        $this->createInvoice($secondCustomerId, 'INV-C', '2026-08-15', 250);
        $this->createInvoice($secondCustomerId, 'INV-FUTURE', '2026-09-20', 999);

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutang('2026-09-15');

        $this->assertSame(['PLG-001', 'PLG-002'], array_column($report['cards'], 'kode_pelanggan'));
        $this->assertSame(100.0, $report['cards'][0]['totals']['days_0_30']);
        $this->assertSame(400.0, $report['cards'][0]['totals']['days_over_90']);
        $this->assertSame(500.0, $report['cards'][0]['saldo_piutang']);
        $this->assertSame(250.0, $report['cards'][1]['totals']['days_31_60']);
        $this->assertSame(100.0, $report['summary']['days_0_30']);
        $this->assertSame(250.0, $report['summary']['days_31_60']);
        $this->assertSame(400.0, $report['summary']['days_over_90']);
        $this->assertSame(750.0, $report['summary']['saldo_piutang']);
    }

    public function test_detail_csv_uses_aging_columns_and_report_date_filename(): void
    {
        [$pelangganId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, 'INV-CSV', '2026-09-01', 1000);

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rincian-buku-pembantu-piutang-20260915.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('Tanggal,Tipe,"No. Referensi","0 - 30 Hari","31 - 60 Hari","61 - 90 Hari","> 90 Hari"', $content);
        $this->assertStringContainsString('2026-09-01,FJ,INV-CSV,1000.00,,,', $content);
        $this->assertStringContainsString('"Saldo BPJS Kesehatan",1000.00,0.00,0.00,0.00', $content);
        $this->assertStringContainsString('"GRAND TOTAL",1000.00,0.00,0.00,0.00', $content);
        $this->assertStringNotContainsString('Mata Uang', $content);
    }

    public function test_summary_page_uses_today_and_has_no_currency_labels(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$pelangganId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, 'INV-SUMMARY', '2026-09-01', 1000);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rangkuman-buku-pembantu-piutang'))
            ->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertViewHas('pelangganIds', [])
            ->assertSee('RANGKUMAN BUKU PEMBANTU PIUTANG')
            ->assertSee('BPJS KESEHATAN | PLG-001')
            ->assertSee('1.000,00')
            ->assertDontSee('Mata Uang')
            ->assertDontSee('MATA UANG DASAR')
            ->assertDontSee('IDR');
    }

    public function test_summary_totals_match_detail_and_customer_filter(): void
    {
        [$firstCustomerId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $secondCustomerId = $this->createCustomer('PLG-002', 'Asuransi Sehat');
        $invoiceId = $this->createInvoice(
            $firstCustomerId,
            'INV-HISTORY-SUMMARY',
            '2026-08-01',
            1000,
            paid: 500,
            akunPiutangId: $akunPiutangId,
        );
        $this->createReceipt($firstCustomerId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-BEFORE', '2026-09-10', 200);
        $this->createReceipt($firstCustomerId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-AFTER', '2026-09-20', 300);
        $this->createInvoice($secondCustomerId, 'INV-SUMMARY-SECOND', '2026-06-01', 400);

        $service = app(LaporanPendapatanService::class);
        $detail = $service->getBukuPembantuPiutang('2026-09-15');
        $summary = $service->getRangkumanBukuPembantuPiutang('2026-09-15');

        $this->assertCount(2, $summary['rows']);
        $this->assertSame($detail['summary'], $summary['summary']);
        $this->assertSame($detail['cards'][0]['totals'], [
            'days_0_30' => $summary['rows'][0]['days_0_30'],
            'days_31_60' => $summary['rows'][0]['days_31_60'],
            'days_61_90' => $summary['rows'][0]['days_61_90'],
            'days_over_90' => $summary['rows'][0]['days_over_90'],
        ]);
        $this->assertSame(800.0, $summary['rows'][0]['total_piutang']);
        $this->assertSame(400.0, $summary['rows'][1]['days_over_90']);
        $this->assertSame(1200.0, $summary['summary']['saldo_piutang']);

        $filtered = $service->getRangkumanBukuPembantuPiutang('2026-09-15', [$secondCustomerId]);
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('PLG-002', $filtered['rows'][0]['kode_pelanggan']);
        $this->assertSame(400.0, $filtered['summary']['saldo_piutang']);
    }

    public function test_summary_csv_contains_customer_totals_and_grand_total(): void
    {
        [$pelangganId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, 'INV-SUMMARY-CSV', '2026-09-01', 1000);

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rangkuman-buku-pembantu-piutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rangkuman-buku-pembantu-piutang-20260915.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Nama Pelanggan","0 - 30 Hari","31 - 60 Hari","61 - 90 Hari","> 90 Hari","Total Piutang"', $content);
        $this->assertStringContainsString('PLG-001,"BPJS Kesehatan",1000.00,0.00,0.00,0.00,1000.00', $content);
        $this->assertStringContainsString('"GRAND TOTAL",1000.00,0.00,0.00,0.00,1000.00', $content);
        $this->assertStringNotContainsString('Mata Uang', $content);
        $this->assertStringNotContainsString('IDR', $content);
    }

    public function test_request_validates_report_date_and_customer_ids(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang', [
                'reportDate' => '15-09-2026',
                'pelangganIds' => [999999, 999999],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors(['reportDate', 'pelangganIds.0', 'pelangganIds.1']);
    }

    public function test_customer_and_receivable_account_search_endpoints_remain_available(): void
    {
        [$pelangganId, $akunPiutangId] = $this->seedMasterData();
        $pelangganTanpaFakturId = $this->createCustomer('PLG-002', 'Pelanggan Tanpa Faktur');

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pendapatan.buku-pembantu-piutang.search-pelanggan', ['q' => 'BPJS']))
            ->assertOk()
            ->assertJsonPath('results.0.id', (string) $pelangganId)
            ->assertJsonPath('results.0.text', '[PLG-001] BPJS Kesehatan');

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pendapatan.buku-pembantu-piutang.search-pelanggan', ['q' => 'Tanpa Faktur']))
            ->assertOk()
            ->assertJsonPath('results.0.id', (string) $pelangganTanpaFakturId);

        $this->actingAs($this->makeUser())
            ->getJson(route('laporan.pendapatan.buku-pembantu-piutang.search-coa', ['q' => 'Piutang']))
            ->assertOk()
            ->assertJsonPath('results.0.id', 'tanpa-akun')
            ->assertJsonPath('results.1.id', (string) $akunPiutangId);
    }

    private function tables(): array
    {
        return [
            'preferensi_perusahaan',
            'penerimaan_penjualan_rinci',
            'penerimaan_penjualan',
            'faktur_penjualan',
            'coa',
            'pelanggan',
        ];
    }

    private function createTables(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_pelanggan')->nullable();
            $table->string('nama_pelanggan');
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

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->unsignedBigInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->string('keterangan')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('nama_pasien')->nullable();
            $table->string('nomer_rekam_medis')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id');
            $table->unsignedBigInteger('akun_bank_id');
            $table->unsignedBigInteger('akun_piutang_id')->nullable();
            $table->string('nomer');
            $table->date('tanggal');
            $table->decimal('jumlah_pembayaran', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penerimaan_penjualan_id');
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
    }

    private function seedMasterData(): array
    {
        $pelangganId = $this->createCustomer('PLG-001', 'BPJS Kesehatan');
        $akunPiutangId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'tipe_coa' => 'Akun Piutang',
            'kode' => '112.01',
            'nama' => 'Piutang BPJS',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $akunBankId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'tipe_coa' => 'Kasbank',
            'kode' => '111.01',
            'nama' => 'Bank',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$pelangganId, $akunPiutangId, $akunBankId];
    }

    private function createCustomer(string $code, string $name): int
    {
        return DB::table('pelanggan')->insertGetId([
            'status_aktif' => true,
            'kode_pelanggan' => $code,
            'nama_pelanggan' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInvoice(
        int $pelangganId,
        string $nomor,
        string $tanggal,
        float $grandtotal,
        float $paid = 0,
        ?int $akunPiutangId = null,
    ): int {
        return DB::table('faktur_penjualan')->insertGetId([
            'pelanggan_id' => $pelangganId,
            'akun_piutang_id' => $akunPiutangId,
            'nomor_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'keterangan' => 'Tagihan pasien',
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $paid,
            'nama_pasien' => 'Pasien Contoh',
            'nomer_rekam_medis' => 'RM-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createReceipt(
        int $pelangganId,
        int $akunBankId,
        ?int $akunPiutangId,
        int $invoiceId,
        string $nomor,
        string $tanggal,
        float $nominal,
    ): int {
        $penerimaanId = DB::table('penerimaan_penjualan')->insertGetId([
            'pelanggan_id' => $pelangganId,
            'akun_bank_id' => $akunBankId,
            'akun_piutang_id' => $akunPiutangId,
            'nomer' => $nomor,
            'tanggal' => $tanggal,
            'jumlah_pembayaran' => $nominal,
            'keterangan' => 'Pembayaran penjamin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penerimaan_penjualan_rinci')->insert([
            'penerimaan_penjualan_id' => $penerimaanId,
            'faktur_penjualan_id' => $invoiceId,
            'nominal_bayar' => $nominal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $penerimaanId;
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
