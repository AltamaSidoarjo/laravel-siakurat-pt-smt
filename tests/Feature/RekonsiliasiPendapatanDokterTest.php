<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RekonsiliasiPendapatanDokterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
        $this->seedRows();
    }

    public function test_rekonsiliasi_page_is_accessible(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $response->assertOk();
        $response->assertSee('Rekonsiliasi Pendapatan Dokter');
        $response->assertSee('Ringkasan Rekonsiliasi Per Akun COA');
    }

    public function test_rekonsiliasi_calculates_discrepancy_for_unassigned_doctor_transactions(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'hanyaBerselisih' => '1',
                'filter_applied' => '1',
            ]));

        $response->assertOk();

        /** @var \App\Services\Laporan\LaporanPendapatanService $service */
        $service = app(\App\Services\Laporan\LaporanPendapatanService::class);
        $data = $service->getRekonsiliasiDokterData('2026-09-01', '2026-09-30', null, true);

        $row4327 = collect($data['rows'])->firstWhere('kode_akun', '432720002');
        $this->assertNotNull($row4327);
        $this->assertSame(125000.0, (float) $row4327['total_buku_besar']);
        $this->assertSame(50000.0, (float) $row4327['total_pendapatan_dokter']);
        $this->assertSame(75000.0, (float) $row4327['total_tanpa_dokter']);
        $this->assertSame(75000.0, (float) $row4327['selisih']);
        $this->assertTrue($row4327['ada_selisih']);
        $this->assertSame(1, $row4327['jumlah_transaksi_tanpa_dokter']);

        // Summary assertions
        $this->assertSame(125000.0, (float) $data['summary']['totalBukuBesar']);
        $this->assertSame(50000.0, (float) $data['summary']['totalPendapatanDokter']);
        $this->assertSame(75000.0, (float) $data['summary']['totalSelisih']);
        $this->assertSame(1, $data['summary']['jumlahAkunBerselisih']);
    }

    public function test_load_detail_transaksi_tanpa_dokter_returns_json(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter.detail', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'coaId' => 11, // COA 432720002
            ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $details = $response->json('data');
        $this->assertCount(1, $details);

        $item = $details[0];
        $this->assertSame('INV-001', $item['nomor_ref']);
        $this->assertSame('Invoice Pendapatan', $item['sumber']);
        $this->assertSame('Pasien Rahasia', $item['nama_pasien']);
        $this->assertSame('DR_BARU', $item['kode_proyek']);
        $this->assertSame('Kode SIMRS: DR_BARU (Belum di Master)', $item['status_simrs']);
        $this->assertSame(75000.0, (float) $item['nominal']);
        $this->assertStringContainsString('pendapatan/invoice', $item['link_url']);
    }

    public function test_load_detail_transaksi_tanpa_dokter_supports_jurnal_umum_and_kasbank(): void
    {
        DB::table('jurnal_umum')->insert([
            'id' => 10,
            'nomer' => 'JU-001',
            'tanggal' => '2026-09-12',
            'keterangan' => 'Penyesuaian pendapatan jasa',
        ]);

        DB::table('jurnal_umum_rinci')->insert([
            'id' => 20,
            'jurnal_umum_id' => 10,
            'coa_id' => 11,
            'pelaksana_id' => null,
            'debit' => 0,
            'kredit' => 10000,
            'catatan' => 'Jurnal penyesuaian tanpa dokter',
        ]);

        DB::table('kasbank_pembayaran')->insert([
            'id' => 30,
            'nomer' => 'KBP-001',
            'tanggal' => '2026-09-15',
            'keterangan' => 'Biaya penyesuaian kas',
            'total' => 2000,
        ]);

        DB::table('kasbank_pembayaran_rinci')->insert([
            'id' => 40,
            'kasbank_pembayaran_id' => 30,
            'coa_id' => 11,
            'pelaksana_id' => null,
            'nominal' => 2000,
            'catatan' => 'Potongan kas tanpa dokter',
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter.detail', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
                'coaId' => 11,
            ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $details = collect($response->json('data'));
        $this->assertCount(3, $details);

        $juItem = $details->firstWhere('sumber', 'Jurnal Umum');
        $this->assertNotNull($juItem);
        $this->assertSame('JU-001', $juItem['nomor_ref']);
        $this->assertSame('Jurnal penyesuaian tanpa dokter', $juItem['catatan']);
        $this->assertSame(10000.0, (float) $juItem['nominal']);
        $this->assertSame('Buka Jurnal', $juItem['link_label']);
        $this->assertSame(route('bukubesar.jurnal-umum.edit', 10), $juItem['link_url']);

        $kbItem = $details->firstWhere('sumber', 'Kas & Bank Pembayaran');
        $this->assertNotNull($kbItem);
        $this->assertSame('KBP-001', $kbItem['nomor_ref']);
        $this->assertSame('Potongan kas tanpa dokter', $kbItem['catatan']);
        $this->assertSame(-2000.0, (float) $kbItem['nominal']);
        $this->assertSame('Buka Kas/Bank', $kbItem['link_label']);
        $this->assertSame(route('kasbank.pembayaran.edit', 30), $kbItem['link_url']);
    }

    public function test_export_excel_and_csv_streamed_responses(): void
    {
        $excelResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter.export-excel', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $excelResponse->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $excelResponse->headers->get('content-type')
        );

        $csvResponse = $this
            ->actingAs($this->makeUser())
            ->get(route('laporan.pendapatan.rekonsiliasi-dokter.export-csv', [
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-30',
            ]));

        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('content-type'));
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
        if (! Schema::hasTable('preferensi_perusahaan')) {
            Schema::create('preferensi_perusahaan', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_perusahaan')->nullable();
                $table->string('logo_perusahaan')->nullable();
            });
        }

        if (! Schema::hasTable('pelaksana')) {
            Schema::create('pelaksana', function (Blueprint $table): void {
                $table->id();
                $table->string('no_proyek')->unique();
                $table->string('nama_pelaksana');
                $table->boolean('status_aktif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coa')) {
            Schema::create('coa', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('parent_coa')->nullable();
                $table->string('kode');
                $table->string('nama');
                $table->string('tipe_coa')->nullable();
            });
        }

        if (! Schema::hasTable('bukubesar')) {
            Schema::create('bukubesar', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('coa_id');
                $table->date('tanggal');
                $table->string('tipe_mutasi', 1);
                $table->decimal('nominal', 15, 2);
                $table->string('sumber_transaksi')->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faktur_penjualan')) {
            Schema::create('faktur_penjualan', function (Blueprint $table): void {
                $table->id();
                $table->string('nomor_faktur');
                $table->date('tanggal_faktur');
                $table->string('nama_pasien')->nullable();
                $table->string('nomer_rekam_medis')->nullable();
                $table->string('nama_poli')->nullable();
                $table->string('nama_penjamin')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faktur_penjualan_rinci')) {
            Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('faktur_penjualan_id');
                $table->unsignedBigInteger('pelaksana_id')->nullable();
                $table->string('kode_proyek')->nullable();
                $table->unsignedBigInteger('coa_id');
                $table->decimal('subtotal', 15, 2);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jurnal_umum')) {
            Schema::create('jurnal_umum', function (Blueprint $table): void {
                $table->id();
                $table->string('nomer');
                $table->date('tanggal');
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jurnal_umum_rinci')) {
            Schema::create('jurnal_umum_rinci', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('jurnal_umum_id');
                $table->unsignedBigInteger('coa_id');
                $table->unsignedBigInteger('pelaksana_id')->nullable();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('kredit', 15, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('kasbank_pembayaran')) {
            Schema::create('kasbank_pembayaran', function (Blueprint $table): void {
                $table->id();
                $table->string('nomer');
                $table->date('tanggal');
                $table->text('keterangan')->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('kasbank_pembayaran_rinci')) {
            Schema::create('kasbank_pembayaran_rinci', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('kasbank_pembayaran_id');
                $table->unsignedBigInteger('coa_id');
                $table->unsignedBigInteger('pelaksana_id')->nullable();
                $table->decimal('nominal', 15, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }
    }

    private function seedRows(): void
    {
        DB::table('preferensi_perusahaan')->truncate();
        DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RSA Bojonegoro',
            'logo_perusahaan' => null,
        ]);

        DB::table('pelaksana')->truncate();
        DB::table('pelaksana')->insert([
            ['id' => 1, 'no_proyek' => '1_DR_A', 'nama_pelaksana' => 'Dr. A', 'status_aktif' => true],
        ]);

        DB::table('coa')->truncate();
        DB::table('coa')->insert([
            ['id' => 1, 'parent_coa' => null, 'kode' => '4000', 'nama' => 'Pendapatan', 'tipe_coa' => 'Pendapatan'],
            ['id' => 11, 'parent_coa' => 1, 'kode' => '432720002', 'nama' => 'Tindakan Spesialis', 'tipe_coa' => 'Pendapatan'],
        ]);

        // Buku besar: Total 125.000 Kredit
        DB::table('bukubesar')->truncate();
        DB::table('bukubesar')->insert([
            [
                'coa_id' => 11,
                'tanggal' => '2026-09-10',
                'tipe_mutasi' => 'K',
                'nominal' => 125000,
                'sumber_transaksi' => 'Invoice Pendapatan',
                'sumber_id' => 1,
                'keterangan' => 'Pendapatan tindakan',
            ],
        ]);

        // Faktur: INV-001
        DB::table('faktur_penjualan')->truncate();
        DB::table('faktur_penjualan')->insert([
            [
                'id' => 1,
                'nomor_faktur' => 'INV-001',
                'tanggal_faktur' => '2026-09-10',
                'nama_pasien' => 'Pasien Rahasia',
                'nomer_rekam_medis' => 'RM-001',
                'nama_poli' => 'Poli Penyakit Dalam',
                'nama_penjamin' => 'BPJS Kesehatan',
                'keterangan' => 'Billing rawat jalan',
            ],
        ]);

        // Faktur Rinci:
        // Item 1: 50.000 dengan dokter Dr. A
        // Item 2: 75.000 TANPA dokter (pelaksana_id NULL, kode_proyek DR_BARU)
        DB::table('faktur_penjualan_rinci')->truncate();
        DB::table('faktur_penjualan_rinci')->insert([
            [
                'faktur_penjualan_id' => 1,
                'pelaksana_id' => 1,
                'kode_proyek' => '1_DR_A',
                'coa_id' => 11,
                'subtotal' => 50000,
                'catatan' => 'Konsultasi dokter',
            ],
            [
                'faktur_penjualan_id' => 1,
                'pelaksana_id' => null,
                'kode_proyek' => 'DR_BARU',
                'coa_id' => 11,
                'subtotal' => 75000,
                'catatan' => 'Tindakan tambahan tanpa dokter',
            ],
        ]);
    }
}
