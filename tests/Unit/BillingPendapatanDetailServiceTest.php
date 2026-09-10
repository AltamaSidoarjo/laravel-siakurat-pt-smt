<?php

namespace Tests\Unit;

use App\Models\SimrsImportPendapatan;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Bridging\BillingPendapatanDetailService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BillingPendapatanDetailServiceTest extends TestCase
{
    private BillingPendapatanApiService $apiService;

    private BillingPendapatanDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->apiService = Mockery::mock(BillingPendapatanApiService::class);
        $this->service = new BillingPendapatanDetailService($this->apiService);
    }

    public function test_external_id_loads_detail_from_api(): void
    {
        $rows = collect([[
            'akun' => '410.01',
            'job' => 'JOB-1',
            'biaya' => 100000.0,
            'jumlah' => 2.0,
            'subtotal' => 200000.0,
        ]]);

        $this->apiService->shouldReceive('getRincianAkun')
            ->once()
            ->with('api-1')
            ->andReturn($rows);

        $result = $this->service->getDetail('api-1', null);

        $this->assertSame('api', $result['source']);
        $this->assertSame(200000.0, $result['grandTotal']);
        $this->assertSame($rows->all(), $result['data']);
    }

    public function test_imported_invoice_uses_local_details_without_calling_api(): void
    {
        $import = $this->createImport('BILL-LOCAL', 'Invoice Pendapatan');
        $coaId = DB::table('coa')->insertGetId([
            'kode' => '410.03',
        ]);
        $invoiceId = DB::table('faktur_penjualan')->insertGetId([
            'nomor_faktur' => 'BILL-LOCAL',
            'nomer_rawat' => 'BILL-LOCAL',
        ]);
        DB::table('faktur_penjualan_rinci')->insert([
            'faktur_penjualan_id' => $invoiceId,
            'coa_id' => $coaId,
            'kode_proyek' => 'JOB-LOCAL',
            'harga' => 75000,
            'kuantitas' => 2,
            'subtotal' => 150000,
        ]);

        $this->apiService->shouldNotReceive('getRincianAkun');

        $result = $this->service->getDetail(null, (int) $import->id);

        $this->assertSame('local_invoice', $result['source']);
        $this->assertSame(150000.0, $result['grandTotal']);
        $this->assertSame('410.03', $result['data'][0]['akun']);
        $this->assertSame('JOB-LOCAL', $result['data'][0]['job']);
        $this->assertSame(75000.0, $result['data'][0]['biaya']);
        $this->assertSame(2.0, $result['data'][0]['jumlah']);
        $this->assertSame(150000.0, $result['data'][0]['subtotal']);
    }

    public function test_imported_journal_is_unavailable_without_calling_api(): void
    {
        $import = $this->createImport('BILL-JOURNAL', 'Jurnal Umum');
        $this->apiService->shouldNotReceive('getRincianAkun');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Rincian billing tidak tersedia untuk data Jurnal Umum lama.');

        $this->service->getDetail(null, (int) $import->id);
    }

    private function createImport(
        string $number,
        string $destination,
    ): SimrsImportPendapatan {
        return SimrsImportPendapatan::query()->create([
            'nomer_billing' => $number,
            'tanggal_reg' => '2026-09-10',
            'import_ke' => $destination,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('simrs_import_pendapatan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer_billing');
            $table->date('tanggal_reg')->nullable();
            $table->string('import_ke')->nullable();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode');
        });

        Schema::create('faktur_penjualan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomor_faktur');
            $table->string('nomer_rawat')->nullable();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->unsignedInteger('coa_id')->nullable();
            $table->string('kode_proyek')->nullable();
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
        });
    }
}
