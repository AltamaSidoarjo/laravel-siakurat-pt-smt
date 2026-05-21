<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPendapatanDataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSimrsImportPendapatanTable();
    }

    public function test_grand_total_uses_filtered_rows_without_global_search(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.load-imported-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 6000.0);
    }

    public function test_grand_total_changes_when_global_search_filters_rows(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.load-imported-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => 'Budi'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 2000.0);
    }

    public function test_grand_total_is_zero_when_global_search_finds_no_rows(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.load-imported-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => 'tidak-ada-data'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 0.0);
    }

    public function test_grand_total_uses_intersection_of_form_filters_and_global_search(): void
    {
        $this->seedImportedPendapatanRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.load-imported-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'poli' => 'Anak',
                'penjamin' => 'BPJS',
                'search' => ['value' => 'Siti'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 3000.0);
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
            'length' => 10,
            'search' => [
                'value' => '',
                'regex' => 'false',
            ],
        ], $overrides);
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
