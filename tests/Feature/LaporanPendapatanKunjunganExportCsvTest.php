<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanPendapatanKunjunganExportCsvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSimrsImportPendapatanTable();
    }

    public function test_export_csv_downloads_filtered_kunjungan_rows_with_expected_headers(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.kunjungan.export-csv', [
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=pendapatan-kunjungan-20260501-20260531.csv');

        $content = $this->normalizeStreamedContent($response->streamedContent());
        $lines = array_values(array_filter(explode("\n", $content), fn (string $line) => $line !== ''));

        $this->assertSame('"No. Billing","Tanggal Registrasi",Pasien,"Status Layanan",Dokter,Poli,Penjamin,Nominal', $lines[0]);
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('BILL-001,2026-05-10,"Andi Saputra",Ralan,"Dr. Andi","Poli Umum",Umum,1000.00', $content);
        $this->assertStringContainsString('BILL-002,2026-05-11,"Budi Hartono",Ranap,"Dr. Budi","Poli Bedah",Asuransi,2000.00', $content);
        $this->assertStringContainsString('BILL-003,2026-05-12,"Siti Aminah",Ralan,"Dr. Siti","Poli Anak",BPJS,3000.00', $content);
        $this->assertStringNotContainsString('BILL-004', $content);
    }

    public function test_export_csv_uses_stable_id_order(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.kunjungan.export-csv', [
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ]));

        $content = $this->normalizeStreamedContent($response->streamedContent());

        $this->assertTrue(
            strpos($content, 'BILL-001') < strpos($content, 'BILL-002')
            && strpos($content, 'BILL-002') < strpos($content, 'BILL-003')
        );
    }

    public function test_export_csv_requires_valid_dates(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.kunjungan'))
            ->get(route('laporan.pendapatan.kunjungan.export-csv', [
                'startDate' => '',
                'endDate' => '2026-05-31',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.kunjungan'))
            ->assertSessionHasErrors(['startDate']);
    }

    public function test_export_csv_requires_end_date_after_or_equal_start_date(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.kunjungan'))
            ->get(route('laporan.pendapatan.kunjungan.export-csv', [
                'startDate' => '2026-05-31',
                'endDate' => '2026-05-01',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.kunjungan'))
            ->assertSessionHasErrors(['endDate']);
    }

    private function normalizeStreamedContent(string $content): string
    {
        return str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $content);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }

    private function prepareSimrsImportPendapatanTable(): void
    {
        if (! Schema::hasTable('simrs_import_pendapatan')) {
            Schema::create('simrs_import_pendapatan', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nomer_billing');
                $table->date('tanggal_reg');
                $table->string('user_importer')->nullable();
                $table->dateTime('import_time')->nullable();
                $table->string('dokter')->nullable();
                $table->string('nama_pasien')->nullable();
                $table->string('penjamin')->nullable();
                $table->string('poli')->nullable();
                $table->string('status_layanan')->nullable();
                $table->decimal('total_tagihan', 15, 2)->default(0);
                $table->text('alamat')->nullable();
                $table->string('jam_reg')->nullable();
                $table->string('kode_dokter')->nullable();
                $table->string('kode_penjamin')->nullable();
                $table->string('kode_poli')->nullable();
                $table->string('nama_kabupaten')->nullable();
                $table->string('nama_kecamatan')->nullable();
                $table->string('nama_kelurahan')->nullable();
                $table->string('no_rekam_medis')->nullable();
                $table->string('diagnosa_penyakit')->nullable();
                $table->string('kamar_inap')->nullable();
                $table->string('import_ke')->nullable();
            });
        }

        DB::table('simrs_import_pendapatan')->truncate();
    }

    private function seedImportedPendapatanRows(): void
    {
        DB::table('simrs_import_pendapatan')->insert([
            [
                'nomer_billing' => 'BILL-001',
                'tanggal_reg' => '2026-05-10',
                'user_importer' => 'tester',
                'import_time' => '2026-05-10 10:00:00',
                'dokter' => 'Dr. Andi',
                'nama_pasien' => 'Andi Saputra',
                'penjamin' => 'Umum',
                'poli' => 'Poli Umum',
                'status_layanan' => 'Ralan',
                'total_tagihan' => 1000,
                'import_ke' => 'Jurnal Umum',
            ],
            [
                'nomer_billing' => 'BILL-002',
                'tanggal_reg' => '2026-05-11',
                'user_importer' => 'tester',
                'import_time' => '2026-05-11 10:00:00',
                'dokter' => 'Dr. Budi',
                'nama_pasien' => 'Budi Hartono',
                'penjamin' => 'Asuransi',
                'poli' => 'Poli Bedah',
                'status_layanan' => 'Ranap',
                'total_tagihan' => 2000,
                'import_ke' => 'Invoice Pendapatan',
            ],
            [
                'nomer_billing' => 'BILL-003',
                'tanggal_reg' => '2026-05-12',
                'user_importer' => 'tester',
                'import_time' => '2026-05-12 10:00:00',
                'dokter' => 'Dr. Siti',
                'nama_pasien' => 'Siti Aminah',
                'penjamin' => 'BPJS',
                'poli' => 'Poli Anak',
                'status_layanan' => 'Ralan',
                'total_tagihan' => 3000,
                'import_ke' => 'Jurnal Umum',
            ],
            [
                'nomer_billing' => 'BILL-004',
                'tanggal_reg' => '2026-04-30',
                'user_importer' => 'tester',
                'import_time' => '2026-04-30 10:00:00',
                'dokter' => 'Dr. Luar',
                'nama_pasien' => 'Data Lama',
                'penjamin' => 'BPJS',
                'poli' => 'Poli Anak',
                'status_layanan' => 'Ralan',
                'total_tagihan' => 4000,
                'import_ke' => 'Jurnal Umum',
            ],
        ]);
    }
}
