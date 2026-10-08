<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePendapatanPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('faktur_penjualan_rinci');
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
            $table->time('jam_registrasi')->nullable();
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

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('diskon_rupiah', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->decimal('harga', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('shortname')->nullable();
            $table->string('npwp_perusahaan')->nullable();
            $table->string('no_telp_perusahaan')->nullable();
            $table->string('email_perusahaan')->nullable();
            $table->string('nama_penandatangan')->nullable();
            $table->text('alamat_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->string('ttd_kabag')->nullable();
            $table->string('ttd_direktur')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('faktur_penjualan');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_invoice_pendapatan_print_page_renders_header_and_detail(): void
    {
        $invoiceId = DB::table('faktur_penjualan')->insertGetId([
            'nomor_faktur' => 'INV-PD-0001',
            'tanggal_faktur' => '2026-05-19',
            'nama_pasien' => 'Tn. Budi Santoso',
            'nomer_rekam_medis' => 'RM-123456',
            'nama_dokter' => 'dr. Anton Sp.PD',
            'nama_poli' => 'Poli Penyakit Dalam',
            'nama_penjamin' => 'BPJS Kesehatan',
            'keterangan' => 'Pemeriksaan Rawat Jalan',
            'sudah_terbayar' => 0,
            'grandtotal' => 175000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('faktur_penjualan_rinci')->insert([
            'faktur_penjualan_id' => $invoiceId,
            'catatan' => 'Konsultasi Dokter Spesialis',
            'kuantitas' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('faktur_penjualan_rinci')->insert([
            'faktur_penjualan_id' => $invoiceId,
            'catatan' => 'EKG Jantung',
            'kuantitas' => 1,
            'harga' => 75000,
            'subtotal' => 75000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RS PKU Muhammadiyah Sruweng',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('pendapatan.invoice.print', $invoiceId));

        $response
            ->assertOk()
            ->assertSee('Invoice Pendapatan')
            ->assertSee('INV-PD-0001')
            ->assertSee('RS PKU Muhammadiyah Sruweng')
            ->assertSee('Tn. Budi Santoso')
            ->assertSee('RM-123456')
            ->assertSee('dr. Anton Sp.PD')
            ->assertSee('Poli Penyakit Dalam')
            ->assertSee('BPJS Kesehatan')
            ->assertSee('Konsultasi Dokter Spesialis')
            ->assertSee('EKG Jantung')
            ->assertSee('175.000');
    }

    public function test_invoice_pendapatan_read_page_contains_print_and_export_excel(): void
    {
        $invoiceId = DB::table('faktur_penjualan')->insertGetId([
            'nomor_faktur' => 'INV-PD-0002',
            'tanggal_faktur' => '2026-05-20',
            'nama_pasien' => 'Ny. Siti',
            'keterangan' => 'Laboratorium',
            'sudah_terbayar' => 50000,
            'grandtotal' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('faktur_penjualan_rinci')->insert([
            'faktur_penjualan_id' => $invoiceId,
            'catatan' => 'Darah Lengkap',
            'kuantitas' => 1,
            'harga' => 50000,
            'subtotal' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('pendapatan.invoice.read', $invoiceId));

        $response
            ->assertOk()
            ->assertSee(route('pendapatan.invoice.print', $invoiceId))
            ->assertSee('Print')
            ->assertSee('btn_export_excel')
            ->assertSee('Export Excel')
            ->assertSee('DetailInvoicePendapatan_INV-PD-0002.xlsx');
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Petugas Kasir',
            'email' => 'kasir@example.com',
        ]);
    }
}
