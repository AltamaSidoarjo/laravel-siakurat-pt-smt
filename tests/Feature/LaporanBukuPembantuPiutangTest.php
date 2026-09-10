<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Laporan\LaporanPendapatanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
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
        foreach (['preferensi_perusahaan', 'penerimaan_penjualan_rinci', 'penerimaan_penjualan', 'faktur_penjualan', 'coa', 'pelanggan'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['preferensi_perusahaan', 'penerimaan_penjualan_rinci', 'penerimaan_penjualan', 'faktur_penjualan', 'coa', 'pelanggan'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_initial_page_does_not_load_customer_cards(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang'));

        $response
            ->assertOk()
            ->assertSee('Pilih minimal satu pelanggan')
            ->assertViewHas('cards', []);
    }

    public function test_report_calculates_opening_direct_payment_allocations_and_running_balance(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceLama = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-LAMA', '2026-08-20', 1000, 500);
        $invoicePeriode = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-PERIODE', '2026-09-05', 800, 500);

        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceLama, 'PNP-001', '2026-08-25', 400);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoicePeriode, 'PNP-002', '2026-09-10', 200);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceLama, 'PNP-003', '2026-09-12', 100);

        $report = app(LaporanPendapatanService::class)->getBukuPembantuPiutang(
            '2026-09-01',
            '2026-09-30',
            [$pelangganId],
        );

        $this->assertCount(1, $report['cards']);
        $card = $report['cards'][0];
        $this->assertSame(600.0, $card['saldo_awal']);
        $this->assertSame(800.0, $card['total_debit']);
        $this->assertSame(600.0, $card['total_kredit']);
        $this->assertSame(800.0, $card['saldo_akhir']);
        $this->assertSame(
            ['Faktur', 'Pembayaran langsung', 'Penerimaan', 'Penerimaan'],
            array_column($card['rows'], 'jenis'),
        );
        $this->assertSame([1400.0, 1100.0, 900.0, 800.0], array_column($card['rows'], 'saldo'));

        $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelangganIds' => [$pelangganId],
            ]))
            ->assertOk()
            ->assertSee('BPJS Kesehatan')
            ->assertSee('INV-PERIODE')
            ->assertSee('PNP-003');
    }

    public function test_account_and_status_filters_keep_zero_and_negative_balances_distinct(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceLunas = $this->createInvoice($pelangganId, null, 'INV-TUNAI', '2026-09-01', 200, 200);
        $invoicePiutang = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-PIUTANG', '2026-09-02', 500, 0);

        $service = app(LaporanPendapatanService::class);
        $tanpaAkun = $service->getBukuPembantuPiutang('2026-09-01', '2026-09-30', [$pelangganId], 'tanpa-akun');
        $this->assertSame(0.0, $tanpaAkun['cards'][0]['saldo_akhir']);
        $this->assertSame(['Faktur', 'Pembayaran langsung'], array_column($tanpaAkun['cards'][0]['rows'], 'jenis'));

        $masihPiutang = $service->getBukuPembantuPiutang('2026-09-01', '2026-09-30', [$pelangganId], (string) $akunPiutangId, 'masih-piutang');
        $this->assertSame(500.0, $masihPiutang['cards'][0]['saldo_akhir']);

        $lunas = $service->getBukuPembantuPiutang('2026-09-01', '2026-09-30', [$pelangganId], 'tanpa-akun', 'lunas');
        $this->assertCount(1, $lunas['cards']);

        $receiptId = $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoicePiutang, 'PNP-LEBIH', '2026-09-03', 550);
        DB::table('faktur_penjualan')->where('id', $invoicePiutang)->update(['sudah_terbayar' => 550]);
        $this->assertNotNull($receiptId);

        $negativeAll = $service->getBukuPembantuPiutang('2026-09-01', '2026-09-30', [$pelangganId], (string) $akunPiutangId, 'semua');
        $this->assertSame(-50.0, $negativeAll['cards'][0]['saldo_akhir']);
        $negativePaid = $service->getBukuPembantuPiutang('2026-09-01', '2026-09-30', [$pelangganId], (string) $akunPiutangId, 'lunas');
        $this->assertSame([], $negativePaid['cards']);
    }

    public function test_csv_uses_the_same_filters_and_running_balances(): void
    {
        [$pelangganId, $akunPiutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($pelangganId, $akunPiutangId, 'INV-CSV', '2026-09-05', 1000, 250);
        $this->createReceipt($pelangganId, $akunBankId, $akunPiutangId, $invoiceId, 'PNP-CSV', '2026-09-10', 250);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.buku-pembantu-piutang.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelangganIds' => [$pelangganId],
                'akunPiutang' => (string) $akunPiutangId,
                'statusSaldo' => 'semua',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=buku-pembantu-piutang-20260901-20260930.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('INV-CSV,Faktur,INV-CSV', $content);
        $this->assertStringContainsString('PNP-CSV,Penerimaan,INV-CSV', $content);
        $this->assertStringContainsString('1000.00,0.00,1000.00', $content);
        $this->assertStringContainsString('0.00,250.00,750.00', $content);
    }

    public function test_export_requires_dates_and_at_least_one_customer(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.buku-pembantu-piutang'))
            ->get(route('laporan.pendapatan.buku-pembantu-piutang.export-csv', [
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.buku-pembantu-piutang'))
            ->assertSessionHasErrors(['endDate', 'pelangganIds']);
    }

    public function test_customer_and_receivable_account_search_endpoints_return_select2_results(): void
    {
        [$pelangganId, $akunPiutangId] = $this->seedMasterData();
        $this->createInvoice($pelangganId, $akunPiutangId, 'INV-CARI', '2026-09-01', 100, 0);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('laporan.pendapatan.buku-pembantu-piutang.search-pelanggan', ['q' => 'BPJS']))
            ->assertOk()
            ->assertJsonPath('results.0.id', (string) $pelangganId)
            ->assertJsonPath('results.0.text', '[PLG-001] BPJS Kesehatan');

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('laporan.pendapatan.buku-pembantu-piutang.search-coa', ['q' => 'Piutang']))
            ->assertOk()
            ->assertJsonPath('results.0.id', 'tanpa-akun')
            ->assertJsonPath('results.1.id', (string) $akunPiutangId);
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
        $pelangganId = DB::table('pelanggan')->insertGetId([
            'status_aktif' => true,
            'kode_pelanggan' => 'PLG-001',
            'nama_pelanggan' => 'BPJS Kesehatan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function createInvoice(
        int $pelangganId,
        ?int $akunPiutangId,
        string $nomor,
        string $tanggal,
        float $grandtotal,
        float $sudahTerbayar,
    ): int {
        return DB::table('faktur_penjualan')->insertGetId([
            'pelanggan_id' => $pelangganId,
            'akun_piutang_id' => $akunPiutangId,
            'nomor_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'keterangan' => 'Tagihan pasien',
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $sudahTerbayar,
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
