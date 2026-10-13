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

class LaporanBukuPembantuHutangMutasiTest extends TestCase
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
            'name' => 'Tester Mutasi',
            'email' => 'tester-mutasi@example.com',
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

    public function test_initial_page_uses_current_month_period_and_shows_empty_state_without_supplier(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi'));

        $response->assertOk()
            ->assertViewHas('startDate', '2026-09-01')
            ->assertViewHas('endDate', '2026-09-15')
            ->assertViewHas('hasSelection', false)
            ->assertSee('Buku Pembantu Hutang (Mutasi)')
            ->assertSee('Pilih minimal satu supplier untuk menampilkan laporan.');
    }

    public function test_mutasi_calculates_saldo_awal_and_running_balance_correctly(): void
    {
        [$supplierId, $akunHutangId, $akunBankId] = $this->seedMasterData();

        // 1. Transaksi SEBELUM periode (sebelum 2026-09-01)
        // Faktur: +1000 (Kredit)
        $oldInvoiceId = $this->createInvoice(
            $supplierId,
            'FP-OLD',
            '2026-08-10',
            1000,
            paid: 0,
            akunHutangId: $akunHutangId,
        );
        // Pembayaran sebagian: -300 (Debit)
        $this->createPayment(
            $supplierId,
            $akunBankId,
            $akunHutangId,
            $oldInvoiceId,
            'BAYAR-OLD',
            '2026-08-20',
            300,
        );
        // Saldo awal per 2026-09-01 = 1000 - 300 = 700.

        // 2. Transaksi DALAM periode (2026-09-01 s/d 2026-09-30)
        // Faktur baru: +500 (Kredit)
        $newInvoiceId = $this->createInvoice(
            $supplierId,
            'FP-NEW',
            '2026-09-05',
            500,
            paid: 0,
            akunHutangId: $akunHutangId,
        );
        // Pembayaran faktur baru: -200 (Debit)
        $this->createPayment(
            $supplierId,
            $akunBankId,
            $akunHutangId,
            $newInvoiceId,
            'BAYAR-NEW',
            '2026-09-15',
            200,
        );

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
            ]));

        $response->assertOk()
            ->assertViewHas('hasSelection', true)
            ->assertSee('Saldo Awal')
            ->assertSee('700,00')   // Saldo awal
            ->assertSee('500,00')   // Total kredit (faktur baru)
            ->assertSee('200,00')   // Total debit (pembayaran)
            ->assertSee('1.000,00') // Saldo akhir: 700 + 500 - 200 = 1000
            ->assertSee('FP-NEW')
            ->assertSee('BAYAR-NEW');

        $report = app(LaporanPembelianService::class)->getBukuPembantuHutangMutasi(
            '2026-09-01',
            '2026-09-30',
            [$supplierId],
        );

        $this->assertSame(700.0, $report['summary']['saldo_awal']);
        $this->assertSame(500.0, $report['summary']['total_kredit']);
        $this->assertSame(200.0, $report['summary']['total_debit']);
        $this->assertSame(1000.0, $report['summary']['saldo_akhir']);
        $this->assertSame(1000.0, $report['cards'][0]['saldo_akhir']);
    }

    public function test_filter_status_saldo_masih_hutang_and_lunas(): void
    {
        [$firstSupplierId, $akunHutangId, $akunBankId] = $this->seedMasterData();
        $secondSupplierId = $this->createSupplier('SUP-002', 'CV Sumber Waras');

        // Supplier 1 masih memiliki sisa hutang 500
        $this->createInvoice($firstSupplierId, 'FP-HUTANG', '2026-09-05', 500, paid: 0, akunHutangId: $akunHutangId);

        // Supplier 2 sudah lunas (faktur 300, dibayar 300)
        $invLunasId = $this->createInvoice($secondSupplierId, 'FP-LUNAS', '2026-09-05', 300, paid: 0, akunHutangId: $akunHutangId);
        $this->createPayment($secondSupplierId, $akunBankId, $akunHutangId, $invLunasId, 'BAYAR-LUNAS', '2026-09-10', 300);

        // Test statusSaldo = masih-hutang
        $reportMasihHutang = app(LaporanPembelianService::class)->getBukuPembantuHutangMutasi(
            '2026-09-01',
            '2026-09-30',
            [$firstSupplierId, $secondSupplierId],
            statusSaldo: 'masih-hutang',
        );

        $this->assertCount(1, $reportMasihHutang['cards']);
        $this->assertSame('SUP-001', $reportMasihHutang['cards'][0]['kode_supplier']);

        // Test statusSaldo = lunas
        $reportLunas = app(LaporanPembelianService::class)->getBukuPembantuHutangMutasi(
            '2026-09-01',
            '2026-09-30',
            [$firstSupplierId, $secondSupplierId],
            statusSaldo: 'lunas',
        );

        $this->assertCount(1, $reportLunas['cards']);
        $this->assertSame('SUP-002', $reportLunas['cards'][0]['kode_supplier']);
    }

    public function test_export_csv_buku_pembantu_hutang_mutasi(): void
    {
        [$supplierId, $akunHutangId] = $this->seedMasterData();
        $this->createInvoice($supplierId, 'FP-MUT-CSV', '2026-09-05', 750, akunHutangId: $akunHutangId);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'supplierIds' => [$supplierId],
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=buku-pembantu-hutang-mutasi-20260901-20260930.csv');

        $content = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $response->streamedContent());
        $this->assertStringContainsString('"Kode Supplier","Nama Supplier",Akun,Tanggal,Nomor,Jenis,"Referensi Faktur",Keterangan,Debit,Kredit,Saldo', $content);
        $this->assertStringContainsString('SUP-001,"PT Kimia Farma","[211.01] Hutang Dagang",,,"Saldo Awal",,,0.00,0.00,0.00', $content);
        $this->assertStringContainsString('FP-MUT-CSV,Faktur,FP-MUT-CSV,"Pembelian obat",0.00,750.00,750.00', $content);
        $this->assertStringContainsString('SUP-001,"PT Kimia Farma",,,"Saldo Akhir PT Kimia Farma",,,,0.00,750.00,750.00', $content);
    }

    public function test_mutasi_middleware_blocks_access_without_permission(): void
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
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi'));

        $responseForbidden->assertForbidden();

        RolePermission::query()->create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
        ]);

        $responseAllowed = $this->actingAs($this->user)
            ->get(route('laporan.pembelian.buku-pembantu-hutang-mutasi'));

        $responseAllowed->assertOk();
    }
}
