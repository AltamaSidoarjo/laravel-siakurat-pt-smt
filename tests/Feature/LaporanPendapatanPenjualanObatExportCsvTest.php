<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanPendapatanPenjualanObatExportCsvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSimrsImportPendapatanJualObatTable();
    }

    public function test_export_csv_downloads_filtered_penjualan_obat_rows_with_expected_headers(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.export-csv', [
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=pendapatan-penjualan-obat-20260501-20260531.csv');

        $content = $this->normalizeStreamedContent($response->streamedContent());
        $lines = array_values(array_filter(explode("\n", $content), fn (string $line) => $line !== ''));

        $this->assertSame('"No. Transaksi",Tanggal,Pelanggan,Jenis,Gudang,Rekening,Keterangan,Ongkir,PPN,Nominal', $lines[0]);
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('TRX-001,2026-05-10,"Andi Saputra",Tunai,GDG-01,Kas,"Resep umum",0.00,0.00,2500.00', $content);
        $this->assertStringContainsString('TRX-002,2026-05-11,"Budi Hartono",Kredit,GDG-02,Piutang,"Pembelian racikan",500.00,0.00,5000.00', $content);
        $this->assertStringContainsString('TRX-003,2026-05-12,"Siti Aminah",Tunai,GDG-01,Kas,"Obat pulang",0.00,100.00,3500.00', $content);
        $this->assertStringNotContainsString('TRX-004', $content);
    }

    public function test_export_csv_uses_stable_id_order(): void
    {
        $this->seedImportedPendapatanJualObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.penjualan-obat.export-csv', [
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ]));

        $content = $this->normalizeStreamedContent($response->streamedContent());

        $this->assertTrue(
            strpos($content, 'TRX-001') < strpos($content, 'TRX-002')
            && strpos($content, 'TRX-002') < strpos($content, 'TRX-003')
        );
    }

    public function test_export_csv_requires_valid_dates(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.penjualan-obat'))
            ->get(route('laporan.pendapatan.penjualan-obat.export-csv', [
                'startDate' => '',
                'endDate' => '2026-05-31',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.penjualan-obat'))
            ->assertSessionHasErrors(['startDate']);
    }

    public function test_export_csv_requires_end_date_after_or_equal_start_date(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('laporan.pendapatan.penjualan-obat'))
            ->get(route('laporan.pendapatan.penjualan-obat.export-csv', [
                'startDate' => '2026-05-31',
                'endDate' => '2026-05-01',
            ]));

        $response
            ->assertRedirect(route('laporan.pendapatan.penjualan-obat'))
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
