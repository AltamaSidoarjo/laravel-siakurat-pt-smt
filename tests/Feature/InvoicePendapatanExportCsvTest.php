<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\FakturPenjualan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePendapatanExportCsvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('faktur_penjualan');
        Schema::enableForeignKeyConstraints();

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->unsignedBigInteger('ppn_id')->nullable();
            $table->unsignedBigInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('ppn_persen', 5, 2)->default(0);
            $table->decimal('ppn_rupiah', 15, 2)->default(0);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('diskon_rupiah', 15, 2)->default(0);
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->integer('status_proses')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('kode_poli')->nullable();
            $table->string('nama_poli')->nullable();
            $table->string('jam_registrasi')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('kode_dokter')->nullable();
            $table->string('nama_dokter')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->string('nomer_rawat')->nullable();
            $table->string('nomer_rekam_medis')->nullable();
            $table->date('tanggal_registrasi')->nullable();
            $table->string('umur')->nullable();
            $table->string('kode_penjamin')->nullable();
            $table->string('nama_penjamin')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('faktur_penjualan');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_export_csv_contains_dokter_poli_and_penjamin(): void
    {
        DB::table('faktur_penjualan')->insert([
            'nomor_faktur' => '2026/11/01/000231',
            'tanggal_faktur' => '2026-11-01',
            'nama_dokter' => 'dr. Medy Pryjambodo Sp. A',
            'nomer_rekam_medis' => '2021068002',
            'nama_pasien' => 'ENZIO FINZA ARAZKA, AN',
            'nama_poli' => 'Klinik Spesialis Anak',
            'nama_penjamin' => 'BRILIFE',
            'grandtotal' => 424176.00,
            'sudah_terbayar' => 424176.00,
            'status_proses' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pendapatan.invoice.export-csv', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-24',
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=invoice-pendapatan-20261101-20261124.csv');

        $content = $this->normalizeStreamedContent($response->streamedContent());
        $lines = array_values(array_filter(explode("\n", $content), fn (string $line) => $line !== ''));

        $this->assertSame('Nomor,Tanggal,Dokter,"No. RM",Pasien,Poli,Penjamin,Nominal,"Sudah bayar","Kurang bayar",Status', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('dr. Medy Pryjambodo Sp. A', $lines[1]);
        $this->assertStringContainsString('Klinik Spesialis Anak', $lines[1]);
        $this->assertStringContainsString('BRILIFE', $lines[1]);
        $this->assertStringContainsString('2026/11/01/000231', $lines[1]);
        $this->assertStringContainsString('ENZIO FINZA ARAZKA, AN', $lines[1]);
        $this->assertStringContainsString('424176.00', $lines[1]);
        $this->assertStringContainsString('Sudah Lunas', $lines[1]);
    }

    public function test_export_csv_handles_null_values_for_dokter_poli_and_penjamin(): void
    {
        DB::table('faktur_penjualan')->insert([
            'nomor_faktur' => '2026/11/01/000999',
            'tanggal_faktur' => '2026-11-01',
            'nama_dokter' => null,
            'nomer_rekam_medis' => '999999',
            'nama_pasien' => 'PASIEN TANPA DOKTER',
            'nama_poli' => null,
            'nama_penjamin' => null,
            'grandtotal' => 100000.00,
            'sudah_terbayar' => 0.00,
            'status_proses' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pendapatan.invoice.export-csv', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-24',
            ]));

        $response->assertOk();

        $content = $this->normalizeStreamedContent($response->streamedContent());
        $lines = array_values(array_filter(explode("\n", $content), fn (string $line) => $line !== ''));

        $this->assertCount(2, $lines);
        $this->assertSame('2026/11/01/000999,2026-11-01,,999999,"PASIEN TANPA DOKTER",,,100000.00,0.00,100000.00,"Belum Lunas"', $lines[1]);
    }

    public function test_export_csv_filters_by_date_range(): void
    {
        DB::table('faktur_penjualan')->insert([
            [
                'nomor_faktur' => 'INV-IN-RANGE',
                'tanggal_faktur' => '2026-11-10',
                'nama_dokter' => 'dr. Range',
                'nomer_rekam_medis' => '111111',
                'nama_pasien' => 'Pasien Range',
                'nama_poli' => 'Poli Umum',
                'nama_penjamin' => 'Umum',
                'grandtotal' => 50000.00,
                'sudah_terbayar' => 50000.00,
                'status_proses' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nomor_faktur' => 'INV-OUT-RANGE',
                'tanggal_faktur' => '2026-10-25',
                'nama_dokter' => 'dr. Out',
                'nomer_rekam_medis' => '222222',
                'nama_pasien' => 'Pasien Out',
                'nama_poli' => 'Poli Gigi',
                'nama_penjamin' => 'BPJS',
                'grandtotal' => 75000.00,
                'sudah_terbayar' => 0.00,
                'status_proses' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->get(route('pendapatan.invoice.export-csv', [
                'startDate' => '2026-11-01',
                'endDate' => '2026-11-24',
            ]));

        $content = $this->normalizeStreamedContent($response->streamedContent());
        $this->assertStringContainsString('INV-IN-RANGE', $content);
        $this->assertStringNotContainsString('INV-OUT-RANGE', $content);
    }

    public function test_faktur_penjualan_model_accessors_for_dokter_poli_penjamin(): void
    {
        $faktur = new FakturPenjualan;
        $faktur->nama_dokter = 'dr. Medy Pryjambodo Sp. A';
        $faktur->nama_poli = 'Klinik Spesialis Anak';
        $faktur->nama_penjamin = 'BRILIFE';

        $this->assertSame('dr. Medy Pryjambodo Sp. A', $faktur->dokter);
        $this->assertSame('Klinik Spesialis Anak', $faktur->poli);
        $this->assertSame('BRILIFE', $faktur->penjamin);
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
}
