<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\AccessModule;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Laporan\LaporanPembelianService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanBukuPembantuHutangTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        $this->createTables();

        $this->user = User::query()->create([
            'name' => 'Tester Pembelian',
            'email' => 'tester-laporan-pembelian@example.com',
            'password' => bcrypt('password'),
        ]);
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

    private function tables(): array
    {
        return [
            'pembayaran_pembelian_rinci',
            'pembayaran_pembelian',
            'faktur_pembelian',
            'preferensi_perusahaan',
            'supplier',
            'coa',
            'role_permissions',
            'access_modules',
            'roles',
            'users',
        ];
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->string('profile_picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->unique();
            $table->string('nama_supplier');
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('akun_hutang_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur');
            $table->text('keterangan')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pembayaran_pembelian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('akun_bank_id')->nullable();
            $table->unsignedBigInteger('akun_hutang_id')->nullable();
            $table->string('nomer_pembayaran');
            $table->date('tanggal');
            $table->decimal('total_bayar', 15, 2)->default(0);
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
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });
    }

    private function seedMasterData(): array
    {
        $supplierId = DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-001',
            'nama_supplier' => 'PT Kimia Farma',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunHutangId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'tipe_coa' => 'Utang Usaha',
            'kode' => '211.01',
            'nama' => 'Hutang Dagang',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunBankId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'tipe_coa' => 'Kasbank',
            'kode' => '111.01',
            'nama' => 'Bank BCA',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supplierId, $akunHutangId, $akunBankId];
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
        ?int $akunHutangId = null,
        ?string $keterangan = 'Pembelian obat',
    ): int {
        return DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'akun_hutang_id' => $akunHutangId,
            'nomer_faktur' => $nomor,
            'tanggal_faktur' => $tanggal,
            'keterangan' => $keterangan,
            'grandtotal' => $grandtotal,
            'sudah_terbayar' => $paid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPayment(
        int $supplierId,
        int $akunBankId,
        ?int $akunHutangId,
        int $invoiceId,
        string $nomor,
        string $tanggal,
        float $nominal,
    ): int {
        $pembayaranId = DB::table('pembayaran_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'akun_bank_id' => $akunBankId,
            'akun_hutang_id' => $akunHutangId,
            'nomer_pembayaran' => $nomor,
            'tanggal' => $tanggal,
            'total_bayar' => $nominal,
            'keterangan' => 'Pelunasan faktur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayaran_pembelian_rinci')->insert([
            'pembayaran_pembelian_id' => $pembayaranId,
            'faktur_pembelian_id' => $invoiceId,
            'nominal_bayar' => $nominal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $pembayaranId;
    }

    public function test_index_page_shows_pembelian_reports_menu_hub(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.index'));

        $response->assertOk()
            ->assertSee('Menu Laporan Pembelian')
            ->assertSee('Pusat Laporan Pembelian')
            ->assertSee('Rincian Buku Pembantu Hutang')
            ->assertSee('Rangkuman Buku Pembantu Hutang')
            ->assertSee('Buku Pembantu Hutang (Mutasi)');
    }

    public function test_initial_page_uses_today_and_loads_suppliers_with_open_payables(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'FP-OPEN', '2026-09-01', 1000);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang'));

        $response->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertViewHas('supplierIds', [])
            ->assertSee('Rincian Buku Pembantu Hutang')
            ->assertSee('RINCIAN BUKU PEMBANTU HUTANG')
            ->assertSee('FP-OPEN')
            ->assertSee('Pembelian obat')
            ->assertSee('PT KIMIA FARMA | SUP-001 (IDR)')
            ->assertSee('Print')
            ->assertSee('Export CSV');
    }

    public function test_supplier_filter_limits_report_and_supplier_options_available(): void
    {
        [$firstSupplierId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Kalbe Farma');
        $this->createInvoice($firstSupplierId, 'FP-FIRST', '2026-09-01', 100);
        $this->createInvoice($secondSupplierId, 'FP-SECOND', '2026-09-01', 200);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang', [
                'reportDate' => '2026-09-15',
                'supplierIds' => [$secondSupplierId],
            ]));

        $response->assertOk()
            ->assertSee('[SUP-001] PT Kimia Farma')
            ->assertSee('[SUP-002] PT Kalbe Farma')
            ->assertSee('FP-SECOND')
            ->assertDontSee('FP-FIRST');
    }

    public function test_suppliers_without_open_payables_are_not_reported(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'FP-PAID', '2026-09-01', 100, paid: 100);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang', ['reportDate' => '2026-09-15']));

        $response->assertOk()
            ->assertViewHas('cards', [])
            ->assertSee('Tidak ada hutang terbuka pada tanggal laporan.');

        $responseSummary = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.rangkuman-buku-pembantu-hutang', ['reportDate' => '2026-09-15']));

        $responseSummary->assertOk()
            ->assertViewHas('rows', [])
            ->assertSee('Tidak ada hutang terbuka pada tanggal laporan.');
    }

    public function test_historical_balance_ignores_later_payments_and_includes_direct_payment(): void
    {
        [$supplierId, $akunHutangId, $akunBankId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice(
            $supplierId,
            'FP-HISTORY',
            '2026-08-01',
            1000,
            paid: 600,
            akunHutangId: $akunHutangId,
        );
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BAYAR-BEFORE', '2026-09-10', 200);
        $this->createPayment($supplierId, $akunBankId, $akunHutangId, $invoiceId, 'BAYAR-AFTER', '2026-09-20', 300);

        $this->createInvoice($supplierId, 'FP-LUNAS', '2026-08-01', 100, paid: 100);

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutang('2026-09-15');
        $rows = collect($report['cards'][0]['rows'])->keyBy('nomor_referensi');

        // Invoice: 1000. Total payment before/on 2026-09-15 = 100 (direct) + 200 = 300. Sisa hutang = 700.
        $this->assertSame(700.0, $rows['FP-HISTORY']['sisa_hutang']);
        $this->assertSame(700.0, $rows['FP-HISTORY']['days_31_60']);
        $this->assertFalse($rows->has('FP-LUNAS'));
    }

    public function test_supplier_subtotals_and_grand_totals_match(): void
    {
        [$firstSupplierId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'PT Kalbe Farma');
        $this->createInvoice($firstSupplierId, 'FP-A', '2026-09-15', 100);
        $this->createInvoice($firstSupplierId, 'FP-B', '2026-06-16', 400);
        $this->createInvoice($secondSupplierId, 'FP-C', '2026-08-15', 250);

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

    public function test_detail_csv_exports_valid_data(): void
    {
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'FP-CSV', '2026-09-01', 1000);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=rincian-buku-pembantu-hutang-20260915.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('Tanggal,Tipe,"No. Referensi",Keterangan,"0 - 30 Hari","31 - 60 Hari","61 - 90 Hari","> 90 Hari"', $content);
        $this->assertStringContainsString('2026-09-01,FB,FP-CSV,"Pembelian obat",1000.00,,,', $content);
        $this->assertStringContainsString('"Saldo PT Kimia Farma",,1000.00,0.00,0.00,0.00', $content);
        $this->assertStringContainsString('"GRAND TOTAL",,1000.00,0.00,0.00,0.00', $content);
    }

    public function test_summary_page_and_csv_export(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        [$supplierId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'FP-SUM', '2026-09-01', 1000);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.rangkuman-buku-pembantu-hutang'));

        $response->assertOk()
            ->assertViewHas('reportDate', '2026-09-15')
            ->assertSee('RANGKUMAN BUKU PEMBANTU HUTANG')
            ->assertSee('PT KIMIA FARMA | SUP-001')
            ->assertSee('1.000,00');

        $responseCsv = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.rangkuman-buku-pembantu-hutang.export-csv', [
                'reportDate' => '2026-09-15',
            ]));

        $responseCsv->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=rangkuman-buku-pembantu-hutang-20260915.csv');
        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $responseCsv->streamedContent());
        $this->assertStringContainsString('SUP-001,"PT Kimia Farma",1000.00,0.00,0.00,0.00,1000.00', $content);
    }

    public function test_search_supplier_and_coa_json_endpoints(): void
    {
        [$supplierId] = $this->seedMasterData();

        $responseSupplier = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-supplier', ['q' => 'Kimia']));

        $responseSupplier->assertOk()
            ->assertJsonPath('results.0.text', '[SUP-001] PT Kimia Farma');

        $responseCoa = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->getJson(route('laporan.pembelian.buku-pembantu-hutang.search-coa', ['q' => 'Hutang']));

        $responseCoa->assertOk()
            ->assertJsonPath('results.0.id', 'tanpa-akun')
            ->assertJsonPath('results.1.text', '[211.01] Hutang Dagang');
    }

    public function test_middleware_blocks_access_without_permission(): void
    {
        $role = Role::query()->create([
            'nama' => 'User Non Laporan',
            'kode' => 'non-laporan',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'laporan.pembelian',
            'nama' => 'Laporan Pembelian',
            'group_nama' => 'Laporan',
            'urutan' => 145,
        ]);

        $this->user->update(['role_id' => $role->id]);

        $responseForbidden = $this->actingAs($this->user)
            ->get(route('laporan.pembelian.index'));

        $responseForbidden->assertForbidden();

        RolePermission::query()->create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
        ]);

        $responseAllowed = $this->actingAs($this->user)
            ->get(route('laporan.pembelian.index'));

        $responseAllowed->assertOk();
    }
}
