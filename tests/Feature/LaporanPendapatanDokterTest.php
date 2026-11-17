<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            ->assertJsonPath('recordsFiltered', 5)
            ->assertJsonPath('totalBilling', 3)
            ->assertJsonPath('grandTotal', 720000);

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
        config()->set('services.billing_api.base_url', 'https://billing.test');
        config()->set('services.billing_api.username', 'tester');
        config()->set('services.billing_api.password', 'secret');
        Cache::flush();

        Http::fake([
            'https://billing.test/api/auth/login' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'accessToken' => 'test-token',
                'refreshToken' => 'test-refresh-token',
                'accessTokenExpiresInMs' => 900000,
            ]),
            'https://billing.test/referensi/poli' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    ['id' => 2, 'nama' => 'Poli Anak'],
                    ['id' => 1, 'nama' => 'Poli Umum'],
                ],
            ]),
            'https://billing.test/referensi/penjamin' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    ['id' => 45, 'nama' => 'ASKES/BPJS'],
                    ['id' => 'umum', 'nama' => 'UMUM'],
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

        Http::assertSent(fn ($request) => $request->url() === 'https://billing.test/referensi/poli');
        Http::assertSent(fn ($request) => $request->url() === 'https://billing.test/referensi/penjamin');
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

    public function test_non_doctor_executor_rows_are_included(): void
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
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('totalBilling', 1)
            ->assertJsonPath('grandTotal', 70000)
            ->assertJsonPath('data.0.dokter', 'Apoteker A')
            ->assertJsonPath('data.0.layanan', 'Pendapatan Farmasi');
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

    public function test_export_excel_downloads_grouped_doctor_income_report(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename=pendapatan-dokter-20260901-20260930.xlsx');

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $temporaryFile = tempnam(sys_get_temp_dir(), 'pendapatan-dokter-');
        $this->assertNotFalse($temporaryFile);
        file_put_contents($temporaryFile, $content);

        try {
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('Pendapatan Dokter', $sheet->getTitle());
            $this->assertSame('PENDAPATAN DOKTER', $sheet->getCell('A2')->getValue());
            $this->assertSame('Kode Akun', $sheet->getCell('A5')->getValue());
            $this->assertSame('Keterangan', $sheet->getCell('B5')->getValue());
            $this->assertSame('Saldo', $sheet->getCell('C5')->getValue());
            $this->assertTrue($sheet->getShowGridlines());

            $doctorTotalRow = null;
            $grandTotalRow = null;

            foreach ($sheet->getRowIterator(6) as $row) {
                $label = $sheet->getCell('A'.$row->getRowIndex())->getValue();

                if ($label === 'Total Pendapatan Dokter Dr. A') {
                    $doctorTotalRow = $row->getRowIndex();
                }

                if ($label === 'Total Pendapatan Seluruh Dokter') {
                    $grandTotalRow = $row->getRowIndex();
                }
            }

            $this->assertNotNull($doctorTotalRow);
            $this->assertNotNull($grandTotalRow);
            $this->assertStringStartsWith('=', $sheet->getCell('C'.$doctorTotalRow)->getValue());
            $this->assertSame(350000.0, (float) $sheet->getCell('C'.$doctorTotalRow)->getCalculatedValue());
            $this->assertSame(720000.0, (float) $sheet->getCell('C'.$grandTotalRow)->getCalculatedValue());

            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }
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

    public function test_jurnal_umum_revenue_entries_adjust_doctor_income_without_changing_billing_count(): void
    {
        DB::table('coa')->insert([
            ['id' => 20, 'parent_coa' => null, 'kode' => '1101', 'nama' => 'Kas Utama', 'tipe_coa' => 'Kasbank'],
        ]);

        DB::table('jurnal_umum')->insert([
            ['id' => 1, 'nomer' => 'JU-001', 'tanggal' => '2026-09-02', 'keterangan' => 'Pengembalian jasa Dr. A'],
            ['id' => 2, 'nomer' => 'JU-002', 'tanggal' => '2026-09-04', 'keterangan' => 'Koreksi tanggal lain Dr. A'],
        ]);

        DB::table('jurnal_umum_rinci')->insert([
            // Debit pendapatan mengurangi pendapatan Dr. A pada 2026-09-02; kredit Kasbank diabaikan meski ada pelaksana_id
            ['jurnal_umum_id' => 1, 'coa_id' => 11, 'pelaksana_id' => 1, 'debit' => 50000, 'kredit' => 0, 'catatan' => 'Retur jasa'],
            ['jurnal_umum_id' => 1, 'coa_id' => 20, 'pelaksana_id' => 1, 'debit' => 0, 'kredit' => 50000, 'catatan' => 'Kas keluar'],
            // Tanggal tanpa invoice (jumlah_billing = 0, total_pendapatan = -30000)
            ['jurnal_umum_id' => 2, 'coa_id' => 11, 'pelaksana_id' => 1, 'debit' => 30000, 'kredit' => 0, 'catatan' => 'Retur susulan'],
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 6)
            ->assertJsonPath('totalBilling', 3)
            ->assertJsonPath('grandTotal', 640000);

        $rows = collect($response->json('data'));
        $drASep2 = $rows->first(fn (array $row) => $row['dokter'] === 'Dr. A'
            && $row['tanggal'] === '2026-09-02'
            && $row['kode_akun'] === '4101');
        $drASep4 = $rows->first(fn (array $row) => $row['dokter'] === 'Dr. A'
            && $row['tanggal'] === '2026-09-04'
            && $row['kode_akun'] === '4101');

        $this->assertNotNull($drASep2);
        $this->assertSame(1, $drASep2['jumlah_billing']);
        $this->assertSame('150.000', $drASep2['total_pendapatan']);

        $this->assertNotNull($drASep4);
        $this->assertSame(0, $drASep4['jumlah_billing']);
        $this->assertSame('-30.000', $drASep4['total_pendapatan']);

        // Filter Poli/Penjamin mengabaikan Jurnal Umum karena tidak punya atribut poli/penjamin
        $filtered = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
                'poli' => 'Poli IGD',
                'penjamin' => 'BPJS',
            ])));

        $filtered
            ->assertOk()
            ->assertJsonPath('totalBilling', 1)
            ->assertJsonPath('grandTotal', 150000);

        $excelResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'pendapatan-dokter-ju-');
        file_put_contents($temporaryFile, $excelResponse->streamedContent());

        try {
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $doctorTotal = null;
            $grandTotal = null;
            foreach ($sheet->getRowIterator(6) as $row) {
                $label = $sheet->getCell('A'.$row->getRowIndex())->getValue();
                if ($label === 'Total Pendapatan Dokter Dr. A') {
                    $doctorTotal = (float) $sheet->getCell('C'.$row->getRowIndex())->getCalculatedValue();
                }
                if ($label === 'Total Pendapatan Seluruh Dokter') {
                    $grandTotal = (float) $sheet->getCell('C'.$row->getRowIndex())->getCalculatedValue();
                }
            }

            $this->assertSame(270000.0, $doctorTotal);
            $this->assertSame(640000.0, $grandTotal);
            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }
    }

    public function test_report_includes_kasbank_pembayaran_adjustments_for_doctor(): void
    {
        DB::table('coa')->insert([
            ['id' => 21, 'parent_coa' => null, 'kode' => '1102', 'nama' => 'Bank Mandiri', 'tipe_coa' => 'Kasbank'],
            ['id' => 22, 'parent_coa' => null, 'kode' => '5101', 'nama' => 'Beban Operasional', 'tipe_coa' => 'Beban'],
        ]);

        DB::table('kasbank_pembayaran')->insert([
            ['id' => 1, 'nomer' => 'BK-001', 'tanggal' => '2026-09-02', 'keterangan' => 'Pengembalian via kas', 'total' => 70000],
        ]);

        DB::table('kasbank_pembayaran_rinci')->insert([
            // Akun pendapatan Dr. A: harus mengurangi pendapatan Dr. A pada 2026-09-02
            ['kasbank_pembayaran_id' => 1, 'coa_id' => 11, 'pelaksana_id' => 1, 'nominal' => 50000, 'catatan' => 'Retur visite kasir'],
            // Akun beban operasional dengan pelaksana_id: harus DIABAIKAN karena bukan akun pendapatan
            ['kasbank_pembayaran_id' => 1, 'coa_id' => 22, 'pelaksana_id' => 1, 'nominal' => 20000, 'catatan' => 'Beban dokter'],
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('totalBilling', 3)
            ->assertJsonPath('grandTotal', 670000); // 720000 - 50000

        $rows = collect($response->json('data'));
        $drARow = $rows->first(fn (array $row) => $row['dokter'] === 'Dr. A'
            && $row['tanggal'] === '2026-09-02'
            && $row['kode_akun'] === '4101');

        $this->assertNotNull($drARow);
        $this->assertSame(1, $drARow['jumlah_billing']);
        $this->assertSame('150.000', $drARow['total_pendapatan']); // 200k - 50k

        // Filter poli aktif -> Kasbank Pembayaran tidak masuk
        $filteredResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.dokter.load-data', $this->dataTableRequest([
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'poli' => 'Poli Umum',
            ])));

        $filteredResponse->assertOk();
        $filteredRows = collect($filteredResponse->json('data'));
        $drAFRow = $filteredRows->first(fn (array $row) => $row['dokter'] === 'Dr. A'
            && $row['tanggal'] === '2026-09-02');
        $this->assertSame('200.000', $drAFRow['total_pendapatan']);
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
            $table->string('tipe_coa')->nullable();
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

        Schema::create('jurnal_umum', function (Blueprint $table): void {
            $table->id();
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jurnal_umum_id');
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('pelaksana_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
        });

        Schema::create('kasbank_pembayaran', function (Blueprint $table): void {
            $table->id();
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
        });

        Schema::create('kasbank_pembayaran_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('kasbank_pembayaran_id');
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('pelaksana_id')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
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
            ['id' => 1, 'parent_coa' => null, 'kode' => '4100', 'nama' => 'Pend. IGD', 'tipe_coa' => 'Pendapatan'],
            ['id' => 2, 'parent_coa' => null, 'kode' => '4200', 'nama' => 'Pend. Unit Laborat', 'tipe_coa' => 'Pendapatan'],
            ['id' => 3, 'parent_coa' => null, 'kode' => '4300', 'nama' => 'Pend. Farmasi', 'tipe_coa' => 'Pendapatan'],
            ['id' => 11, 'parent_coa' => 1, 'kode' => '4101', 'nama' => 'Pendapatan IGD', 'tipe_coa' => 'Pendapatan'],
            ['id' => 12, 'parent_coa' => 2, 'kode' => '4102', 'nama' => 'Pendapatan Laboratorium', 'tipe_coa' => 'Pendapatan'],
            ['id' => 13, 'parent_coa' => 3, 'kode' => '4103', 'nama' => 'Pendapatan Farmasi', 'tipe_coa' => 'Pendapatan'],
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
