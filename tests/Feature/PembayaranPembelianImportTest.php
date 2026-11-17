<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PembayaranPembelianImportTest extends TestCase
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

    public function test_import_form_can_be_rendered(): void
    {
        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.pembayaran.import.form'));

        $response->assertOk();
        $response->assertSee('Import Pembayaran Pembelian');
        $response->assertSee('Unduh Template');
        $response->assertSee(route('pembelian.pembayaran.import.template'));
    }

    public function test_template_download_returns_xlsx_with_five_sheets(): void
    {
        $this->seedMasterData();

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pembelian.pembayaran.import.template'));

        $response->assertOk();
        $response->assertDownload('template-import-pembayaran-pembelian.xlsx');
    }

    public function test_successful_import_creates_payments_updates_invoices_and_ledger(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();

        $inv1 = $this->createInvoice($sup1, 1_000_000, 0, 'INV-001', $hutang);
        $inv2 = $this->createInvoice($sup1, 2_000_000, 500_000, 'INV-002', $hutang);

        // File berisi 2 baris untuk 1 pembayaran PB-001 (melunasi 2 faktur)
        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-001', 1_000_000, 'Bayar faktur obat 1'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-002', 1_500_000, 'Bayar faktur obat 2'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertRedirect(route('pembelian.pembayaran.index'));
        $response->assertSessionHas('success');

        // Pastikan pembayaran dibuat
        $this->assertDatabaseHas('pembayaran_pembelian', [
            'nomer_pembayaran' => 'PB-001',
            'supplier_id' => $sup1,
            'akun_bank_id' => $bank,
            'akun_hutang_id' => $hutang,
            'total_bayar' => 2_500_000,
            'potongan_admin' => 0,
        ]);

        $pembayaranId = DB::table('pembayaran_pembelian')->where('nomer_pembayaran', 'PB-001')->value('id');

        // Pastikan 2 baris rincian tersimpan
        $this->assertDatabaseCount('pembayaran_pembelian_rinci', 2);
        $this->assertDatabaseHas('pembayaran_pembelian_rinci', [
            'pembayaran_pembelian_id' => $pembayaranId,
            'faktur_pembelian_id' => $inv1,
            'nominal_bayar' => 1_000_000,
        ]);
        $this->assertDatabaseHas('pembayaran_pembelian_rinci', [
            'pembayaran_pembelian_id' => $pembayaranId,
            'faktur_pembelian_id' => $inv2,
            'nominal_bayar' => 1_500_000,
        ]);

        // Pastikan invoice sudah_terbayar bertambah
        $this->assertEquals(1_000_000, (float) DB::table('faktur_pembelian')->where('id', $inv1)->value('sudah_terbayar'));
        $this->assertEquals(2_000_000, (float) DB::table('faktur_pembelian')->where('id', $inv2)->value('sudah_terbayar'));

        // Pastikan buku besar tersinkron (Debit Hutang, Kredit Bank)
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $hutang,
            'nominal' => 2_500_000,
            'tipe_mutasi' => 'D',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $pembayaranId,
            'coa_id' => $bank,
            'nominal' => 2_500_000,
            'tipe_mutasi' => 'K',
        ]);

        // Pastikan log aktivitas tercatat
        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Pembayaran Pembelian',
            'tipe' => 'create',
        ]);
    }

    public function test_import_auto_resolves_akun_hutang_from_invoice_when_kode_coa_hutang_is_blank(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $inv1 = $this->createInvoice($sup1, 500_000, 0, 'INV-AUTO-1', $hutang);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-AUTO-01', '2026-10-31', 'SUP-001', '111001', '', 'INV-AUTO-1', 500_000, 'Test auto hutang'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertRedirect(route('pembelian.pembayaran.index'));
        $this->assertDatabaseHas('pembayaran_pembelian', [
            'nomer_pembayaran' => 'PB-AUTO-01',
            'akun_hutang_id' => $hutang,
        ]);
    }

    public function test_import_fails_and_rolls_back_when_invoice_not_found(): void
    {
        $this->seedMasterData();

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-GHO-999', 500_000, 'Faktur fiktif'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertRedirect(route('pembelian.pembayaran.import.form'));
        $response->assertSessionHasErrors('file');

        $this->assertDatabaseEmpty('pembayaran_pembelian');
    }

    public function test_import_fails_when_invoice_belongs_to_different_supplier(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $sup2 = DB::table('supplier')->insertGetId([
            'status_aktif' => true,
            'kode_supplier' => 'SUP-002',
            'nama_supplier' => 'PT Supplier Lain',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invSup2 = $this->createInvoice($sup2, 1_000_000, 0, 'INV-SUP2-001', $hutang);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-SUP2-001', 500_000, 'Supplier mismatch'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('pembayaran_pembelian');
    }

    public function test_import_fails_when_invoice_already_paid(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $invLunas = $this->createInvoice($sup1, 1_000_000, 1_000_000, 'INV-LUNAS-01', $hutang);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-LUNAS-01', 500_000, 'Bayar faktur lunas'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('pembayaran_pembelian');
    }

    public function test_import_fails_when_nominal_exceeds_remaining_balance(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $inv = $this->createInvoice($sup1, 1_000_000, 700_000, 'INV-SISA-300', $hutang);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-001', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-SISA-300', 400_000, 'Bayar lebih besar dari sisa'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('pembayaran_pembelian');
    }

    public function test_import_fails_when_header_data_inconsistent_across_same_payment_number(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $this->createInvoice($sup1, 1_000_000, 0, 'INV-INC-1', $hutang);
        $this->createInvoice($sup1, 1_000_000, 0, 'INV-INC-2', $hutang);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-INC-01', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-INC-1', 500_000, 'Baris 1'],
            ['PB-INC-01', '2026-11-01', 'SUP-001', '111001', '210001', 'INV-INC-2', 500_000, 'Baris 2 beda tanggal'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('pembayaran_pembelian');
    }

    public function test_import_fails_when_payment_number_already_exists(): void
    {
        [$sup1, $bank, $hutang] = $this->seedMasterData();
        $inv = $this->createInvoice($sup1, 1_000_000, 0, 'INV-EXIST-1', $hutang);

        DB::table('pembayaran_pembelian')->insert([
            'supplier_id' => $sup1,
            'akun_bank_id' => $bank,
            'akun_hutang_id' => $hutang,
            'nomer_pembayaran' => 'PB-DUP-01',
            'tanggal' => '2026-10-31',
            'total_bayar' => 1_000_000,
            'potongan_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = $this->makeXlsx([
            ['nomer_pembayaran', 'tanggal', 'kode_supplier', 'kode_coa_bank', 'kode_coa_hutang', 'nomer_faktur', 'nominal_bayar', 'keterangan'],
            ['PB-DUP-01', '2026-10-31', 'SUP-001', '111001', '210001', 'INV-EXIST-1', 500_000, 'Duplicate number'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->from(route('pembelian.pembayaran.import.form'))
            ->post(route('pembelian.pembayaran.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
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

        $akunBankId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '111001',
            'nama' => 'Bank BCA',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunHutangId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '210001',
            'nama' => 'Hutang Usaha',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supplierId, $akunBankId, $akunHutangId];
    }

    private function createInvoice(
        int $supplierId,
        int|float $grandTotal,
        int|float $sudahTerbayar = 0,
        string $nomorFaktur = 'INV-PB-0001',
        ?int $akunHutangId = null,
    ): int {
        return DB::table('faktur_pembelian')->insertGetId([
            'supplier_id' => $supplierId,
            'akun_hutang_id' => $akunHutangId,
            'nomer_faktur' => $nomorFaktur,
            'tanggal_faktur' => '2026-10-20',
            'grandtotal' => $grandTotal,
            'sudah_terbayar' => $sudahTerbayar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'pb_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
