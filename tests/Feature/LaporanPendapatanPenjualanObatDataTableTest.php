<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanPendapatanPenjualanObatDataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSimrsImportPendapatanJualObatTable();
    }

    public function test_grand_total_is_returned_for_filtered_penjualan_obat_rows(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.load-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 11000);
    }

    public function test_grand_total_changes_when_global_search_filters_penjualan_obat_rows(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.load-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => 'Budi'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 5000);
    }

    public function test_grand_total_can_be_filtered_by_numeric_search(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.load-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => '3500'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 3500);
    }

    public function test_grand_total_excludes_rows_outside_selected_date_range_even_when_search_matches(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.load-data', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => 'Data Lama'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('grandTotal', 0);
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

    private function prepareSimrsImportPendapatanJualObatTable(): void
    {
        if (! Schema::hasTable('simrs_import_pendapatan_jual_obat')) {
            Schema::create('simrs_import_pendapatan_jual_obat', function (Blueprint $table) {
                $table->increments('id');
                $table->date('tanggal');
                $table->string('nama_pelanggan')->nullable();
                $table->text('keterangan')->nullable();
                $table->string('jenis_jual')->nullable();
                $table->decimal('ongkir', 15, 2)->default(0);
                $table->decimal('ppn', 15, 2)->default(0);
                $table->string('kode_gudang')->nullable();
                $table->string('kode_rekening')->nullable();
                $table->string('nama_rekening')->nullable();
                $table->string('nomer_transaksi');
                $table->decimal('grandtotal', 15, 2)->default(0);
                $table->string('import_ke')->nullable();
            });
        }

        DB::table('simrs_import_pendapatan_jual_obat')->truncate();
    }

    private function seedImportedPendapatanJualObatRows(): void
    {
        DB::table('simrs_import_pendapatan_jual_obat')->insert([
            [
                'tanggal' => '2026-05-10',
                'nama_pelanggan' => 'Andi Saputra',
                'keterangan' => 'Resep umum',
                'jenis_jual' => 'Tunai',
                'ongkir' => 0,
                'ppn' => 0,
                'kode_gudang' => 'GDG-01',
                'kode_rekening' => 'RK-01',
                'nama_rekening' => 'Kas',
                'nomer_transaksi' => 'TRX-001',
                'grandtotal' => 2500,
                'import_ke' => 'Jurnal Umum',
            ],
            [
                'tanggal' => '2026-05-11',
                'nama_pelanggan' => 'Budi Hartono',
                'keterangan' => 'Pembelian racikan',
                'jenis_jual' => 'Kredit',
                'ongkir' => 500,
                'ppn' => 0,
                'kode_gudang' => 'GDG-02',
                'kode_rekening' => 'RK-02',
                'nama_rekening' => 'Piutang',
                'nomer_transaksi' => 'TRX-002',
                'grandtotal' => 5000,
                'import_ke' => 'Invoice Pendapatan',
            ],
            [
                'tanggal' => '2026-05-12',
                'nama_pelanggan' => 'Siti Aminah',
                'keterangan' => 'Obat pulang',
                'jenis_jual' => 'Tunai',
                'ongkir' => 0,
                'ppn' => 100,
                'kode_gudang' => 'GDG-01',
                'kode_rekening' => 'RK-01',
                'nama_rekening' => 'Kas',
                'nomer_transaksi' => 'TRX-003',
                'grandtotal' => 3500,
                'import_ke' => 'Jurnal Umum',
            ],
            [
                'tanggal' => '2026-04-30',
                'nama_pelanggan' => 'Data Lama',
                'keterangan' => 'Transaksi lama',
                'jenis_jual' => 'Tunai',
                'ongkir' => 0,
                'ppn' => 0,
                'kode_gudang' => 'GDG-03',
                'kode_rekening' => 'RK-03',
                'nama_rekening' => 'Kas Lama',
                'nomer_transaksi' => 'TRX-004',
                'grandtotal' => 9000,
                'import_ke' => 'Jurnal Umum',
            ],
        ]);
    }
}
