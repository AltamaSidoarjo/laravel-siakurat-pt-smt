<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LaporanAktivitasDokterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        $this->seedRows();
    }

    public function test_page_loads_successfully_with_doctor_options(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $response
            ->assertOk()
            ->assertSee('Laporan Aktivitas Dokter')
            ->assertSee('Semua dokter / pelaksana')
            ->assertSee('Dr. A')
            ->assertSee('Dr. B');
    }

    public function test_report_calculates_grouped_transactions_and_running_mutasi(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $response
            ->assertOk()
            ->assertSee('DR. A')
            ->assertSee('DR. B')
            ->assertSee('PENDAPATAN RAWAT JALAN')
            ->assertSee('TINDAKAN OPERASI KHUSUS')
            ->assertSee('INV-001')
            ->assertSee('INV-002')
            ->assertSee('Poli Mata - BPJS - RM001 - Pasien Susan')
            ->assertSee('TOTAL PENDAPATAN RAWAT JALAN')
            ->assertSee('TOTAL TINDAKAN OPERASI KHUSUS')
            ->assertSee('TOTAL DR. A')
            ->assertSee('TOTAL DR. B');
    }

    public function test_report_filters_by_specific_doctor(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $response
            ->assertOk()
            ->assertSee('DR. A')
            ->assertDontSee('TOTAL DR. B');
    }

    public function test_export_pdf_downloads_landscape_document(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-pdf', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_export_excel_downloads_valid_spreadsheet(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $response->assertOk();

        $temporaryFile = tempnam(sys_get_temp_dir(), 'test_akt_doc_');
        file_put_contents($temporaryFile, $response->streamedContent());

        try {
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('AKTIVITAS DOKTER', $sheet->getCell('A2')->getValue());

            $foundDoctorA = false;
            $foundDoctorB = false;
            $foundCategory = false;

            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $valA = (string) $sheet->getCell('A'.$row)->getValue();
                if (str_contains($valA, 'DR. A')) {
                    $foundDoctorA = true;
                }
                if (str_contains($valA, 'DR. B')) {
                    $foundDoctorB = true;
                }
                if (str_contains($valA, 'TINDAKAN OPERASI KHUSUS')) {
                    $foundCategory = true;
                }
            }

            $this->assertTrue($foundDoctorA);
            $this->assertTrue($foundDoctorB);
            $this->assertTrue($foundCategory);

            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }
    }

    public function test_export_requires_valid_date_range(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.aktivitas-dokter'))
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-pdf', [
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.aktivitas-dokter'))
            ->assertSessionHasErrors(['endDate']);
    }

    public function test_jurnal_umum_return_appears_as_negative_nominal_and_reduces_mutasi(): void
    {
        DB::table('coa')->insert([
            ['id' => 201, 'parent_coa' => null, 'kode' => '1101', 'nama' => 'Kas Utama', 'tipe_coa' => 'Kasbank'],
        ]);

        DB::table('jurnal_umum')->insert([
            'id' => 10,
            'nomer' => 'JU-RET-01',
            'tanggal' => '2026-09-06',
            'keterangan' => 'Pengembalian jasa operasi',
        ]);

        DB::table('jurnal_umum_rinci')->insert([
            ['jurnal_umum_id' => 10, 'coa_id' => 101, 'pelaksana_id' => 1, 'debit' => 500000, 'kredit' => 0, 'catatan' => 'Retur jasa Dr. A'],
            // Akun lawan Kasbank tidak boleh muncul di laporan dokter meskipun pelaksana_id diisi
            ['jurnal_umum_id' => 10, 'coa_id' => 201, 'pelaksana_id' => 1, 'debit' => 0, 'kredit' => 500000, 'catatan' => 'Kas keluar'],
        ]);

        $webResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $webResponse
            ->assertOk()
            ->assertSee('JU-RET-01')
            ->assertSee(route('bukubesar.jurnal-umum.edit', 10))
            ->assertSee('Jurnal Umum')
            ->assertSee('Retur jasa Dr. A')
            ->assertSee('-Rp 500.000,00')
            ->assertSee('Rp 3.315.000,00')
            ->assertDontSee('Kas Utama');

        $pdfResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-pdf', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $pdfResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());

        $excelResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'test_akt_doc_ju_');
        file_put_contents($temporaryFile, $excelResponse->streamedContent());

        try {
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $foundJournalNominal = null;
            $doctorTotalNominal = null;
            $doctorTotalMutasi = null;

            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $colA = (string) $sheet->getCell('A'.$row)->getValue();
                $colC = (string) $sheet->getCell('C'.$row)->getValue();

                if ($colC === 'JU-RET-01') {
                    $foundJournalNominal = (float) $sheet->getCell('F'.$row)->getValue();
                }
                if (str_contains($colA, 'TOTAL JASA DR. A')) {
                    $doctorTotalNominal = (float) $sheet->getCell('F'.$row)->getValue();
                    $doctorTotalMutasi = (float) $sheet->getCell('G'.$row)->getValue();
                }
            }

            $this->assertSame(-500000.0, $foundJournalNominal);
            $this->assertSame(3315000.0, $doctorTotalNominal);
            $this->assertSame(3315000.0, $doctorTotalMutasi);

            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }
    }

    public function test_report_includes_kasbank_pembayaran_with_proper_badge_and_debit_mutasi(): void
    {
        DB::table('coa')->insert([
            ['id' => 103, 'parent_coa' => null, 'kode' => '5101', 'nama' => 'Beban Operasional', 'tipe_coa' => 'Beban'],
        ]);

        DB::table('kasbank_pembayaran')->insert([
            ['id' => 10, 'nomer' => 'BK-RET-01', 'tanggal' => '2026-09-08', 'keterangan' => 'Pengembalian fee via kasir', 'total' => 250000],
        ]);

        DB::table('kasbank_pembayaran_rinci')->insert([
            // Akun pendapatan: harus masuk dan mendebit jasa Dr. A (-200.000)
            [
                'kasbank_pembayaran_id' => 10,
                'coa_id' => 101, // Tindakan Operasi Khusus
                'pelaksana_id' => 1, // Dr. A
                'nominal' => 200000,
                'catatan' => 'Retur tindakan khusus pasien Susan',
            ],
            // Akun beban operasional: harus DIABAIKAN dari laporan aktivitas dokter
            [
                'kasbank_pembayaran_id' => 10,
                'coa_id' => 103, // Beban
                'pelaksana_id' => 1,
                'nominal' => 50000,
                'catatan' => 'Beban non-pendapatan',
            ],
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $response
            ->assertOk()
            ->assertSee('BK-RET-01')
            ->assertSee('Kasbank Pembayaran')
            ->assertSee(route('kasbank.pembayaran.edit', 10))
            ->assertSee('Retur tindakan khusus pasien Susan')
            ->assertSee('-Rp 200.000,00');

        $excelResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.aktivitas-dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'pelaksanaId' => 1,
            ]));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'test_akt_doc_bk_');
        file_put_contents($temporaryFile, $excelResponse->streamedContent());

        try {
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $foundBkNominal = null;
            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $colC = (string) $sheet->getCell('C'.$row)->getValue();
                $colD = (string) $sheet->getCell('D'.$row)->getValue();

                if ($colC === 'BK-RET-01') {
                    $foundBkNominal = (float) $sheet->getCell('F'.$row)->getValue();
                    $this->assertStringContainsString('[Kasbank Pembayaran]', $colD);
                }
            }

            $this->assertSame(-200000.0, $foundBkNominal);
            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
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
            $table->string('keterangan')->nullable();
            $table->string('nama_pasien')->nullable();
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
        ]);

        DB::table('coa')->insert([
            ['id' => 101, 'parent_coa' => null, 'kode' => '4201', 'nama' => 'Tindakan Operasi Khusus', 'tipe_coa' => 'Pendapatan'],
            ['id' => 102, 'parent_coa' => null, 'kode' => '4301', 'nama' => 'Pendapatan Rawat Jalan', 'tipe_coa' => 'Pendapatan'],
        ]);

        DB::table('faktur_penjualan')->insert([
            [
                'id' => 1,
                'nomor_faktur' => 'INV-001',
                'tanggal_faktur' => '2026-09-02',
                'nama_poli' => 'Poli Mata',
                'nama_penjamin' => 'BPJS',
                'keterangan' => 'Poli Mata - BPJS - RM001 - Pasien Susan',
                'nama_pasien' => 'Pasien Susan',
            ],
            [
                'id' => 2,
                'nomor_faktur' => 'INV-002',
                'tanggal_faktur' => '2026-09-05',
                'nama_poli' => 'Poli Mata',
                'nama_penjamin' => 'Umum',
                'keterangan' => 'Poli Mata - Umum - RM002 - Pasien Budi',
                'nama_pasien' => 'Pasien Budi',
            ],
            [
                'id' => 3,
                'nomor_faktur' => 'INV-003',
                'tanggal_faktur' => '2026-09-10',
                'nama_poli' => 'Poli Bedah',
                'nama_penjamin' => 'BPJS',
                'keterangan' => 'Poli Bedah - BPJS - RM003 - Pasien Charlie',
                'nama_pasien' => 'Pasien Charlie',
            ],
        ]);

        DB::table('faktur_penjualan_rinci')->insert([
            // Dr A: 2 rows in Tindakan Operasi Khusus, 1 row in Pendapatan Rawat Jalan
            ['faktur_penjualan_id' => 1, 'pelaksana_id' => 1, 'coa_id' => 101, 'subtotal' => 1845000],
            ['faktur_penjualan_id' => 2, 'pelaksana_id' => 1, 'coa_id' => 101, 'subtotal' => 1845000],
            ['faktur_penjualan_id' => 1, 'pelaksana_id' => 1, 'coa_id' => 102, 'subtotal' => 125000],
            // Dr B: 1 row in Tindakan Operasi Khusus
            ['faktur_penjualan_id' => 3, 'pelaksana_id' => 2, 'coa_id' => 101, 'subtotal' => 2500000],
        ]);
    }
}
