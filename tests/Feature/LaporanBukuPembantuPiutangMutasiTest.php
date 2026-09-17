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

class LaporanBukuPembantuPiutangMutasiTest extends TestCase
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

    public function test_index_page_shows_piutang_mutasi_submenu(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.index'))
            ->assertOk()
            ->assertSee(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->assertSee('Buku Pembantu Piutang (Mutasi)');
    }

    public function test_initial_page_uses_current_month_period_and_shows_empty_state_without_pelanggan(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->assertOk()
            ->assertViewHas('startDate', '2026-09-01')
            ->assertViewHas('endDate', '2026-09-15')
            ->assertViewHas('pelangganIds', [])
            ->assertViewHas('hasSelection', false)
            ->assertSee('Buku Pembantu Piutang (Mutasi)')
            ->assertSee('Pilih minimal satu pelanggan untuk menampilkan laporan.');
    }

    public function test_pelanggan_select_renders_server_options(): void
    {
        [$pelangganId] = $this->seedMasterData();

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->assertOk()
            ->assertSee('class="form-select select2"', false)
            ->assertSee('value="'.$pelangganId.'"', false)
            ->assertSee('[PLG-001] BPJS Kesehatan');
    }

    public function test_page_renders_ledger_and_transaction_links_for_selected_pelanggan(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-LEDGER', '2026-09-02', 1000);
        $penerimaanId = $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-LEDGER', '2026-09-10', 400);

        $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelangganIds' => [$pelangganId],
            ]))
            ->assertOk()
            ->assertViewHas('hasSelection', true)
            ->assertSee('BUKU PEMBANTU PIUTANG')
            ->assertSee('[PLG-001] BPJS Kesehatan')
            ->assertSee('[112.01] Piutang BPJS')
            ->assertSee('INV-LEDGER')
            ->assertSee('PNP-LEDGER')
            ->assertSee(route('pendapatan.invoice.read', $invoiceId))
            ->assertSee(route('pendapatan.penerimaan.print', $penerimaanId));
    }

    public function test_service_computes_opening_balance_period_totals_running_saldo_and_order(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();

        $oldInvoiceId = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-OLD', '2026-08-05', 500);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $oldInvoiceId, 'PNP-OLD', '2026-08-25', 100);
        $newInvoiceId = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-NEW', '2026-09-02', 1000);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $newInvoiceId, 'PNP-IN', '2026-09-10', 300);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $newInvoiceId, 'PNP-AFTER', '2026-10-01', 200);

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutangMutasi(
            startDate: '2026-09-01',
            endDate: '2026-09-30',
            pelangganIds: [$pelangganId],
        );

        $card = $report['cards'][0];

        $this->assertSame(400.0, $card['saldo_awal']);
        $this->assertSame(1000.0, $card['total_debit']);
        $this->assertSame(300.0, $card['total_kredit']);
        $this->assertSame(1100.0, $card['saldo_akhir']);
        $this->assertSame('INV-NEW', $card['rows'][0]['nomor']);
        $this->assertSame(1400.0, $card['rows'][0]['saldo']);
        $this->assertSame('PNP-IN', $card['rows'][1]['nomor']);
        $this->assertSame(1100.0, $card['rows'][1]['saldo']);
        $this->assertSame(400.0, $report['summary']['saldo_awal']);
        $this->assertSame(1100.0, $report['summary']['saldo_akhir']);
    }

    public function test_direct_receipt_is_included_without_double_counting_allocated_receipts(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice(
            $pelangganId,
            $akunPiutangId,
            'INV-DIRECT',
            '2026-09-02',
            1000,
            paid: 200,
        );
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-ALLOCATED', '2026-09-10', 400);

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutangMutasi(
            '2026-09-01',
            '2026-09-30',
            [$pelangganId],
        );

        $card = $report['cards'][0];

        $this->assertSame(1000.0, $card['total_debit']);
        $this->assertSame(600.0, $card['total_kredit']);
        $this->assertSame(400.0, $card['saldo_akhir']);
        $this->assertSame(['Faktur', 'Penerimaan langsung', 'Penerimaan'], array_column($card['rows'], 'jenis'));
    }

    public function test_status_saldo_filter_keeps_only_matching_pelanggan(): void
    {
        [$firstPelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $secondPelangganId = $this->createCustomer('PLG-002', 'Asuransi Sehat');

        $this->createInvoice($firstPelangganId, $akunPiutangId, 'INV-OPEN', '2026-09-02', 1000);
        $paidInvoiceId = $this->createInvoice($secondPelangganId, $akunPiutangId, 'INV-PAID', '2026-09-02', 500);
        $this->createReceipt($secondPelangganId, $akunBankId, $akunPiutangId, $paidInvoiceId, 'PNP-FULL', '2026-09-10', 500);

        $service = app(LaporanPendapatanService::class);

        $masihPiutang = $service->getBukuPembantuPiutangMutasi('2026-09-01', '2026-09-30', [$firstPelangganId, $secondPelangganId], 'masih-piutang');
        $this->assertCount(1, $masihPiutang['cards']);
        $this->assertSame('PLG-001', $masihPiutang['cards'][0]['kode_pelanggan']);

        $lunas = $service->getBukuPembantuPiutangMutasi('2026-09-01', '2026-09-30', [$firstPelangganId, $secondPelangganId], 'lunas');
        $this->assertCount(1, $lunas['cards']);
        $this->assertSame('PLG-002', $lunas['cards'][0]['kode_pelanggan']);

        $semua = $service->getBukuPembantuPiutangMutasi('2026-09-01', '2026-09-30', [$firstPelangganId, $secondPelangganId], 'semua');
        $this->assertCount(2, $semua['cards']);
    }

    public function test_csv_export_uses_ledger_columns_and_period_filename(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-CSV', '2026-09-02', 1000);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-CSV', '2026-09-10', 400);

        $response = $this->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelangganIds' => [$pelangganId],
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=buku-pembantu-piutang-mutasi-20260901-20260930.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Kode Pelanggan","Nama Pelanggan",Akun,Tanggal,Nomor,Jenis,"Referensi Faktur",Keterangan,Debit,Kredit,Saldo', $content);
        $this->assertStringContainsString('"Saldo Awal"', $content);
        $this->assertStringContainsString('INV-CSV', $content);
        $this->assertStringContainsString('PNP-CSV', $content);
    }

    public function test_request_requires_pelanggan_and_period_on_export(): void
    {
        $this->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi.export-csv'))
            ->assertRedirect(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->assertSessionHasErrors(['startDate', 'endDate', 'pelangganIds']);
    }

    public function test_request_rejects_invalid_date_range_pelanggan_and_status(): void
    {
        $this->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->get(route('laporan.pendapatan.buku-pembantu-piutang-mutasi', [
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
                'pelangganIds' => [999999],
                'statusSaldo' => 'invalid-status',
            ]))
            ->assertRedirect(route('laporan.pendapatan.buku-pembantu-piutang-mutasi'))
            ->assertSessionHasErrors(['endDate', 'pelangganIds.0', 'statusSaldo']);
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
            $table->string('kode');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->unsignedBigInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
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
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
    }

    private function seedMasterData(): array
    {
        $pelangganId = $this->createCustomer('PLG-001', 'BPJS Kesehatan');
        $akunPiutangId = DB::table('coa')->insertGetId([
            'kode' => '112.01', 'nama' => 'Piutang BPJS', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $akunBankId = DB::table('coa')->insertGetId([
            'kode' => '111.01', 'nama' => 'Bank', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$pelangganId, $akunPiutangId, $akunBankId];
    }

    private function createCustomer(string $kode, string $nama): int
    {
        return DB::table('pelanggan')->insertGetId([
            'status_aktif' => true,
            'kode_pelanggan' => $kode,
            'nama_pelanggan' => $nama,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInvoice(
        int $pelangganId,
        int $akunPiutangId,
        string $nomor,
        string $tanggal,
        float $grandtotal,
        float $paid = 0,
    ): int {
        return DB::table('faktur_penjualan')->insertGetId([
            'pelanggan_id' => $pelangganId,
            'akun_piutang_id' => $akunPiutangId,
            'nomor_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'keterangan' => 'Tagihan pasien',
            'nama_pasien' => 'Pasien Contoh',
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $paid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createReceipt(
        int $pelangganId,
        int $akunBankId,
        int $akunPiutangId,
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
            'keterangan' => 'Penerimaan pelanggan',
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
        DB::table('faktur_penjualan')->where('id', $invoiceId)->increment('sudah_terbayar', $nominal);

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
