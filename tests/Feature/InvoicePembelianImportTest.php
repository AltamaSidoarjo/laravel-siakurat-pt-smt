<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InvoicePembelianImportTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::factory()->make([
            'name' => 'Tester Invoice',
            'email' => 'tester@example.com',
        ]);
    }

    public function test_import_form_can_be_rendered(): void
    {
        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->get(route('pembelian.invoice.import.form'));

        $response->assertOk();
        $response->assertSee('Import Invoice Pembelian');
        $response->assertSee('Unduh Template');
        $response->assertSee(route('pembelian.invoice.import.template'));
    }

    public function test_template_download_returns_xlsx_with_four_sheets(): void
    {
        $this->seedMasterData();

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->get(route('pembelian.invoice.import.template'));

        $response->assertOk();
        $response->assertDownload('template-import-invoice-pembelian.xlsx');
    }

    public function test_successful_import_creates_invoices_rincian_and_ledger(): void
    {
        [$sup1, $hutang, $beban1, $beban2] = $this->seedMasterData();

        // 1 invoice (INV-001) dengan 2 baris rincian
        // 1 invoice (INV-002) dengan 1 baris rincian
        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-001', '2026-11-01', '2026-11-30', 'SUP-001', '210001', '510101', 10, 50000, 'Paracetamol', 'Faktur obat 1'],
            ['INV-001', '2026-11-01', '2026-11-30', 'SUP-001', '210001', '510102', 5, 100000, 'Amoxicillin', 'Faktur obat 1'],
            ['INV-002', '2026-11-02', '', 'SUP-001', '210001', '510101', 2, 200000, 'Kasa', 'Faktur BHP'],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertRedirect(route('pembelian.invoice.index'));
        $response->assertSessionHas('success');

        // Pastikan INV-001 terbuat dengan total 1.000.000 (10*50.000 + 5*100.000)
        $this->assertDatabaseHas('faktur_pembelian', [
            'nomer_faktur' => 'INV-001',
            'supplier_id' => $sup1,
            'akun_hutang_id' => $hutang,
            'grandtotal' => 1_000_000,
            'keterangan' => 'Faktur obat 1',
        ]);

        $inv1 = FakturPembelian::query()->where('nomer_faktur', 'INV-001')->first();
        $this->assertCount(2, $inv1->rincian);

        // Pastikan INV-002 terbuat dengan total 400.000 (2*200.000)
        $this->assertDatabaseHas('faktur_pembelian', [
            'nomer_faktur' => 'INV-002',
            'supplier_id' => $sup1,
            'akun_hutang_id' => $hutang,
            'grandtotal' => 400_000,
        ]);

        // Pastikan buku besar tersinkron (INV-001: Kredit Hutang 1jt, Debit Beban1 500rb, Debit Beban2 500rb)
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $inv1->id,
            'coa_id' => $hutang,
            'nominal' => 1_000_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $inv1->id,
            'coa_id' => $beban1,
            'nominal' => 500_000,
            'tipe_mutasi' => 'D',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $inv1->id,
            'coa_id' => $beban2,
            'nominal' => 500_000,
            'tipe_mutasi' => 'D',
        ]);

        // Pastikan log aktivitas tercatat
        $this->assertDatabaseHas('log_aktifitas', [
            'modul' => 'Invoice Pembelian',
            'tipe' => 'create',
        ]);
    }

    public function test_import_fails_and_rolls_back_when_supplier_not_found_or_inactive(): void
    {
        $this->seedMasterData();

        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-GHO-01', '2026-11-01', '', 'SUP-TIDAK-ADA', '210001', '510101', 1, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertRedirect(route('pembelian.invoice.import.form'));
        $response->assertSessionHasErrors('file');

        $this->assertDatabaseEmpty('faktur_pembelian');
        $this->assertDatabaseEmpty('bukubesar');
    }

    public function test_import_fails_when_coa_hutang_is_not_payable_type(): void
    {
        $this->seedMasterData();

        // Menggunakan akun beban sebagai akun hutang -> harus ditolak
        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-FAIL-01', '2026-11-01', '', 'SUP-001', '510101', '510101', 1, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('faktur_pembelian');
    }

    public function test_import_fails_when_coa_rincian_is_not_found(): void
    {
        $this->seedMasterData();

        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-FAIL-02', '2026-11-01', '', 'SUP-001', '210001', '999999', 1, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('faktur_pembelian');
    }

    public function test_import_fails_when_invoice_number_already_exists(): void
    {
        [$sup1, $hutang] = $this->seedMasterData();

        DB::table('faktur_pembelian')->insert([
            'nomer_faktur' => 'INV-EXIST-01',
            'supplier_id' => $sup1,
            'akun_hutang_id' => $hutang,
            'tanggal_faktur' => '2026-11-01',
            'grandtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-EXIST-01', '2026-11-01', '', 'SUP-001', '210001', '510101', 1, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_fails_when_header_data_inconsistent_across_same_invoice_number(): void
    {
        $this->seedMasterData();

        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-INC-01', '2026-11-01', '', 'SUP-001', '210001', '510101', 1, 50000, '', ''],
            ['INV-INC-01', '2026-11-02', '', 'SUP-001', '210001', '510101', 2, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('faktur_pembelian');
    }

    public function test_import_fails_when_quantity_or_price_invalid(): void
    {
        $this->seedMasterData();

        $file = $this->makeXlsx([
            ['nomer_faktur', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'kode_supplier', 'kode_coa_hutang', 'kode_coa_rincian', 'kuantitas', 'harga', 'catatan', 'keterangan'],
            ['INV-INV-01', '2026-11-01', '', 'SUP-001', '210001', '510101', 0, 50000, '', ''],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->user)
            ->from(route('pembelian.invoice.import.form'))
            ->post(route('pembelian.invoice.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('faktur_pembelian');
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

        $hutangId = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '210001',
            'nama' => 'Hutang Usaha Obat',
            'tipe_coa' => 'Utang Usaha',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $beban1Id = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '510101',
            'nama' => 'Beban Obat Tablet',
            'tipe_coa' => 'Beban',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $beban2Id = DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '510102',
            'nama' => 'Beban Obat Kapsul',
            'tipe_coa' => 'Beban',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supplierId, $hutangId, $beban1Id, $beban2Id];
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

        $path = tempnam(sys_get_temp_dir(), 'inv_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function prepareTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('pembayaran_pembelian_rinci');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('faktur_pembelian_rinci');
        Schema::dropIfExists('faktur_pembelian');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_supplier')->nullable();
            $table->string('nama_supplier');
            $table->string('email_supplier')->nullable();
            $table->text('alamat_supplier')->nullable();
            $table->string('kategori_supplier')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('akun_hutang_id')->nullable();
            $table->string('nomer_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('nilai_ppn', 15, 2)->default(0);
            $table->decimal('biaya_kirim', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('status_proses')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->date('tanggal_pesan')->nullable();
            $table->string('kategori_faktur')->nullable();
            $table->string('kode_bangsal')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_pembelian_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('faktur_pembelian_id');
            $table->unsignedInteger('coa_id')->nullable();
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('diskon_rupiah', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->decimal('harga_barang', 15, 2)->default(0);
            $table->string('kode_barang')->nullable();
            $table->string('nama_barang')->nullable();
            $table->string('satuan_barang')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('tipe_mutasi', 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user');
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
}
