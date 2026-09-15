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

class LaporanBukuPembantuHutangMutasiTest extends TestCase
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

    public function test_index_page_shows_three_purchasing_report_submenus(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.index'))
            ->assertOk()
            ->assertSee(route('laporan.pembelian.buku-pembantu-hutang'))
            ->assertSee(route('laporan.pembelian.rangkuman-buku-pembantu-hutang'))
            ->assertSee(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->assertSee('Buku Pembantu Hutang (Mutasi)');
    }

    public function test_initial_page_uses_current_month_period_and_shows_empty_state_without_supplier(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->assertOk()
            ->assertViewHas('startDate', '2026-09-01')
            ->assertViewHas('endDate', '2026-09-15')
            ->assertViewHas('supplierIds', [])
            ->assertViewHas('hasSelection', false)
            ->assertSee('Buku Pembantu Hutang (Mutasi)')
            ->assertSee('Pilih minimal satu supplier untuk menampilkan laporan.');
    }

    public function test_supplier_select_renders_server_options_for_initial_page(): void
    {
        [$supplierId] = $this->seedMasterData();

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->assertOk()
            ->assertSee('class="form-select select2"', false)
            ->assertSee('value="'.$supplierId.'"', false)
            ->assertSee('[SUP-001] PT Sehat Farma');
    }

    public function test_page_renders_ledger_for_selected_supplier(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($supplierId, 'INV-LEDGER', '2026-09-02', 1000, dueDate: '2026-09-20');
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-LEDGER', '2026-09-10', 400);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
            ]))
            ->assertOk()
            ->assertViewHas('hasSelection', true)
            ->assertSee('BUKU PEMBANTU HUTANG')
            ->assertSee('Saldo Awal')
            ->assertSee('Saldo Akhir')
            ->assertSee('[SUP-001] PT Sehat Farma')
            ->assertSee('INV-LEDGER')
            ->assertSee('BYR-LEDGER');
    }

    public function test_service_computes_opening_balance_totals_running_saldo_and_order(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();

        // Invoice before the period contributes only to saldo awal.
        $oldInvoiceId = $this->createInvoice($supplierId, 'INV-OLD', '2026-08-05', 500, dueDate: '2026-08-20');
        // Invoice inside the period.
        $invoiceId = $this->createInvoice($supplierId, 'INV-NEW', '2026-09-02', 1000, dueDate: '2026-09-20');
        // Payment inside the period against the old invoice.
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-IN', '2026-09-10', 300);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $oldInvoiceId, 'BYR-OLD', '2026-08-25', 100);

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutangMutasi(
            startDate: '2026-09-01',
            endDate: '2026-09-30',
            supplierIds: [$supplierId],
        );

        $card = $report['cards'][0];

        // Saldo awal = old invoice 500 - old payment 100 = 400.
        $this->assertSame(400.0, $card['saldo_awal']);
        // Within period: kredit 1000 (new invoice), debit 300 (payment).
        $this->assertSame(1000.0, $card['total_kredit']);
        $this->assertSame(300.0, $card['total_debit']);
        // Saldo akhir = 400 + 1000 - 300 = 1100.
        $this->assertSame(1100.0, $card['saldo_akhir']);

        // Rows are ordered: invoice (2026-09-02) then payment (2026-09-10).
        $rows = $card['rows'];
        $this->assertSame('INV-NEW', $rows[0]['nomor']);
        $this->assertSame(1400.0, $rows[0]['saldo']); // 400 + 1000
        $this->assertSame('BYR-IN', $rows[1]['nomor']);
        $this->assertSame(1100.0, $rows[1]['saldo']); // 1400 - 300

        $this->assertSame(400.0, $report['summary']['saldo_awal']);
        $this->assertSame(1100.0, $report['summary']['saldo_akhir']);
    }

    public function test_status_saldo_filter_keeps_only_matching_suppliers(): void
    {
        [$firstSupplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Farma Dua');

        // First supplier still owes.
        $this->createInvoice($firstSupplierId, 'INV-OWES', '2026-09-02', 1000, dueDate: '2026-09-20');
        // Second supplier fully paid within period.
        $paidInvoiceId = $this->createInvoice($secondSupplierId, 'INV-PAID', '2026-09-02', 500, dueDate: '2026-09-20');
        $this->createPayment($secondSupplierId, $akunBankId, $akunHutangId, $paidInvoiceId, 'BYR-FULL', '2026-09-10', 500);

        $service = app(LaporanPembelianService::class);

        $masihHutang = $service->getBukuPembantuHutangMutasi('2026-09-01', '2026-09-30', [$firstSupplierId, $secondSupplierId], 'masih-hutang');
        $this->assertCount(1, $masihHutang['cards']);
        $this->assertSame('SUP-001', $masihHutang['cards'][0]['kode_supplier']);

        $lunas = $service->getBukuPembantuHutangMutasi('2026-09-01', '2026-09-30', [$firstSupplierId, $secondSupplierId], 'lunas');
        $this->assertCount(1, $lunas['cards']);
        $this->assertSame('SUP-002', $lunas['cards'][0]['kode_supplier']);

        $semua = $service->getBukuPembantuHutangMutasi('2026-09-01', '2026-09-30', [$firstSupplierId, $secondSupplierId], 'semua');
        $this->assertCount(2, $semua['cards']);
    }

    public function test_csv_export_uses_ledger_columns_and_period_filename(): void
    {
        [$supplierId, $akunBankId, $akunHutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($supplierId, 'INV-CSV', '2026-09-02', 1000, dueDate: '2026-09-20');
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BYR-CSV', '2026-09-10', 400);

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=buku-pembantu-hutang-mutasi-20260901-20260930.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Kode Supplier","Nama Supplier",Akun,Tanggal,Nomor,Jenis,"Referensi Faktur",Keterangan,Debit,Kredit,Saldo', $content);
        $this->assertStringContainsString('"Saldo Awal"', $content);
        $this->assertStringContainsString('INV-CSV', $content);
        $this->assertStringContainsString('BYR-CSV', $content);
    }

    public function test_request_requires_supplier_and_period_on_export(): void
    {
        $this->actingAs($this->makeUser())
            ->from(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi.export-csv'))
            ->assertRedirect(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->assertSessionHasErrors(['startDate', 'endDate', 'supplierIds']);
    }

    public function test_request_rejects_invalid_status_saldo(): void
    {
        [$supplierId] = $this->seedMasterData();

        $this->actingAs($this->makeUser())
            ->from(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
                'statusSaldo' => 'invalid-status',
            ]))
            ->assertRedirect(route('laporan.pembelian.buku-pembantu-hutang-mutasi'))
            ->assertSessionHasErrors(['statusSaldo']);
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
