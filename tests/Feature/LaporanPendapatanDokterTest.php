<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanPendapatanDokterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        $this->seedRows();
    }

    public function test_report_groups_income_by_doctor_and_revenue_account(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 4)
            ->assertJsonPath('totalBilling', 3)
            ->assertJsonPath('grandTotal', 650000);

        $rows = collect($response->json('data'));
        $igdDrA = $rows->first(fn (array $row) => $row['dokter'] === 'Dr. A'
            && $row['tanggal'] === '2026-09-01'
            && $row['kode_akun'] === '4101');

        $this->assertNotNull($igdDrA);
        $this->assertSame(1, $igdDrA['jumlah_billing']);
        $this->assertSame('100.000', $igdDrA['total_pendapatan']);
    }

    public function test_report_page_loads_poli_and_guarantor_selects_from_billing_api(): void
    {
        config()->set('services.billing_api.base_url', 'http://billing.test/api');
        config()->set('services.billing_api.username', 'tester');
        config()->set('services.billing_api.password', 'secret');
        Cache::flush();

        Http::fake([
            'http://billing.test/api/get-token' => Http::response([
                'status' => true,
                'token' => 'test-token',
                'expires_in' => 3600,
            ]),
            'http://billing.test/api/spesialis' => Http::response([
                'status' => true,
                'data' => [
                    ['ID' => 2, 'Spesialis' => 'Poli Anak'],
                    ['ID' => 1, 'Spesialis' => 'Poli Umum'],
                ],
            ]),
            'http://billing.test/api/pxrs' => Http::response([
                'status' => true,
                'data' => [
                    ['ID' => 45, 'PxRS' => 'ASKES/BPJS'],
                    ['ID' => 1, 'PxRS' => 'U/Px'],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter'));

        $response
            ->assertOk()
            ->assertSee('Semua poli')
            ->assertSee('Poli Anak')
            ->assertSee('Poli Umum')
            ->assertSee('Semua penjamin')
            ->assertSee('ASKES/BPJS')
            ->assertSee('Umum');

        Http::assertSent(fn ($request) => $request->url() === 'http://billing.test/api/spesialis');
        Http::assertSent(fn ($request) => $request->url() === 'http://billing.test/api/pxrs');
    }

    public function test_report_filters_doctor_poli_and_guarantor(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
                'poli' => 'Poli IGD',
                'penjamin' => 'BPJS',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonPath('totalBilling', 1)
            ->assertJsonPath('grandTotal', 150000)
            ->assertJsonPath('data.0.dokter', 'Dr. A')
            ->assertJsonPath('data.0.layanan', 'Pendapatan IGD');
    }

    public function test_non_doctor_executor_rows_are_excluded(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'search' => ['value' => 'Farmasi', 'regex' => 'false'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 0)
            ->assertJsonPath('totalBilling', 0)
            ->assertJsonPath('grandTotal', 0);
    }

    public function test_export_pdf_downloads_grouped_doctor_income_report(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.export-pdf', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="pendapatan-dokter-20260901-20260930.pdf"');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_export_requires_a_valid_date_range(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.dokter'))
            ->get(route('laporan.pendapatan.dokter.export-pdf', [
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.dokter'))
            ->assertSessionHasErrors(['endDate']);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }

    private function dataTableRequest(array $overrides = []): array
    {
        return array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }

    private function prepareTables(): void
    {
        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->id();
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
        });

        Schema::create('faktur_penjualan', function (Blueprint $table): void {
            $table->id();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur');
            $table->string('nama_poli')->nullable();
            $table->string('nama_penjamin')->nullable();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->unsignedBigInteger('pelaksana_id')->nullable();
            $table->unsignedBigInteger('coa_id');
            $table->decimal('subtotal', 15, 2);
        });
    }

    private function seedRows(): void
    {
        DB::table('pelaksana')->insert([
            ['id' => 1, 'no_proyek' => '1_DR_A', 'nama_pelaksana' => 'Dr. A', 'status_aktif' => true],
            ['id' => 2, 'no_proyek' => '2_DR_B', 'nama_pelaksana' => 'Dr. B', 'status_aktif' => true],
            ['id' => 3, 'no_proyek' => '3_APOTEKER', 'nama_pelaksana' => 'Apoteker A', 'status_aktif' => true],
        ]);

        DB::table('coa')->insert([
            ['id' => 1, 'parent_coa' => null, 'kode' => '4100', 'nama' => 'Pend. IGD'],
            ['id' => 2, 'parent_coa' => null, 'kode' => '4200', 'nama' => 'Pend. Unit Laborat'],
            ['id' => 3, 'parent_coa' => null, 'kode' => '4300', 'nama' => 'Pend. Farmasi'],
            ['id' => 11, 'parent_coa' => 1, 'kode' => '4101', 'nama' => 'Pendapatan IGD'],
            ['id' => 12, 'parent_coa' => 2, 'kode' => '4102', 'nama' => 'Pendapatan Laboratorium'],
            ['id' => 13, 'parent_coa' => 3, 'kode' => '4103', 'nama' => 'Pendapatan Farmasi'],
        ]);

        DB::table('faktur_penjualan')->insert([
            ['id' => 1, 'nomor_faktur' => 'INV-001', 'tanggal_faktur' => '2026-09-01', 'nama_poli' => 'Poli IGD', 'nama_penjamin' => 'BPJS'],
            ['id' => 2, 'nomor_faktur' => 'INV-002', 'tanggal_faktur' => '2026-09-02', 'nama_poli' => 'Poli Umum', 'nama_penjamin' => 'Umum'],
            ['id' => 3, 'nomor_faktur' => 'INV-003', 'tanggal_faktur' => '2026-09-03', 'nama_poli' => 'Poli IGD', 'nama_penjamin' => 'BPJS'],
            ['id' => 4, 'nomor_faktur' => 'INV-004', 'tanggal_faktur' => '2026-08-31', 'nama_poli' => 'Poli IGD', 'nama_penjamin' => 'BPJS'],
        ]);

        DB::table('faktur_penjualan_rinci')->insert([
            ['faktur_penjualan_id' => 1, 'pelaksana_id' => 1, 'coa_id' => 11, 'subtotal' => 100000],
            ['faktur_penjualan_id' => 1, 'pelaksana_id' => 1, 'coa_id' => 12, 'subtotal' => 50000],
            ['faktur_penjualan_id' => 1, 'pelaksana_id' => 3, 'coa_id' => 13, 'subtotal' => 70000],
            ['faktur_penjualan_id' => 2, 'pelaksana_id' => 1, 'coa_id' => 11, 'subtotal' => 200000],
            ['faktur_penjualan_id' => 3, 'pelaksana_id' => 2, 'coa_id' => 11, 'subtotal' => 300000],
            ['faktur_penjualan_id' => 4, 'pelaksana_id' => 2, 'coa_id' => 11, 'subtotal' => 900000],
        ]);
    }
}
