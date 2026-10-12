<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\Pelaksana;
use App\Models\Pelanggan;
use App\Models\User;
use App\Services\Bridging\BillingPendapatanApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class InvoicePendapatanEditFieldsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);

        $apiMock = Mockery::mock(BillingPendapatanApiService::class);
        $apiMock->shouldReceive('getDokterOptions')->andReturn(collect([
            ['nama' => 'Dr. Sp.A'],
        ]))->byDefault();
        $apiMock->shouldReceive('getSpesialisOptions')->andReturn(collect([
            ['nama' => 'Poli Anak'],
        ]))->byDefault();
        $apiMock->shouldReceive('getPenjaminOptions')->andReturn(collect([
            ['id' => '1', 'nama' => 'BPJS'],
        ]))->byDefault();
        $this->app->instance(BillingPendapatanApiService::class, $apiMock);
    }

    public function test_edit_view_renders_all_fields_as_editable_for_imported_invoice(): void
    {
        $penjamin = Pelanggan::query()->create([
            'kode_pelanggan' => '1',
            'nama_pelanggan' => 'BPJS',
            'status_aktif' => true,
        ]);
        $piutang = $this->createCoa('103001', 'Piutang Pasien', 'Piutang Usaha');
        $pendapatan = $this->createCoa('440401', 'Pendapatan Layanan', 'Pendapatan');

        $invoice = $this->createInvoice([
            'nomor_faktur' => 'INV-SIMRS-001',
            'pelanggan_id' => $penjamin->id,
            'akun_piutang_id' => $piutang->id,
            'nama_pasien' => 'Pasien Asli',
            'nomer_rekam_medis' => 'RM-001',
            'nama_dokter' => 'Dr. Asli',
            'nama_poli' => 'Poli Asli',
            'nomer_rawat' => 'INV-SIMRS-001',
        ], $pendapatan);

        DB::table('simrs_import_pendapatan')->insert([
            'nomer_billing' => 'INV-SIMRS-001',
            'tanggal_reg' => '2026-09-10',
            'total_tagihan' => 150_000,
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('pendapatan.invoice.edit', $invoice));

        $response->assertOk();
        $content = $response->getContent();

        // Verify nomor_faktur does not have readonly
        $this->assertMatchesRegularExpression('/<input[^>]+id="nomor_faktur"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+id="nomor_faktur"[^>]*readonly/', $content);

        // Verify pelanggan_id does not have disabled
        $this->assertMatchesRegularExpression('/<select[^>]+id="pelanggan_id"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]+id="pelanggan_id"[^>]*disabled/', $content);

        // Verify nama_pasien does not have readonly
        $this->assertMatchesRegularExpression('/<input[^>]+id="nama_pasien"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+id="nama_pasien"[^>]*readonly/', $content);

        // Verify nomer_rekam_medis does not have readonly
        $this->assertMatchesRegularExpression('/<input[^>]+id="nomer_rekam_medis"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+id="nomer_rekam_medis"[^>]*readonly/', $content);

        // Verify nama_dokter does not have disabled
        $this->assertMatchesRegularExpression('/<select[^>]+id="nama_dokter"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]+id="nama_dokter"[^>]*disabled/', $content);

        // Verify nama_poli does not have disabled
        $this->assertMatchesRegularExpression('/<select[^>]+id="nama_poli"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]+id="nama_poli"[^>]*disabled/', $content);
    }

    public function test_updating_imported_invoice_persists_all_changed_fields(): void
    {
        $penjaminAwal = Pelanggan::query()->create([
            'kode_pelanggan' => 'BPJS',
            'nama_pelanggan' => 'BPJS Kesehatan',
            'status_aktif' => true,
        ]);
        $penjaminBaru = Pelanggan::query()->create([
            'kode_pelanggan' => 'UMUM',
            'nama_pelanggan' => 'Pasien Umum',
            'status_aktif' => true,
        ]);
        $piutang = $this->createCoa('103001', 'Piutang Pasien', 'Piutang Usaha');
        $pendapatan = $this->createCoa('440401', 'Pendapatan Layanan', 'Pendapatan');

        $invoice = $this->createInvoice([
            'nomor_faktur' => 'INV-SIMRS-002',
            'pelanggan_id' => $penjaminAwal->id,
            'akun_piutang_id' => $piutang->id,
            'nama_pasien' => 'Pasien Awal',
            'nomer_rekam_medis' => 'RM-001',
            'nama_dokter' => 'Dr. Awal',
            'nama_poli' => 'Poli Awal',
            'nomer_rawat' => 'INV-SIMRS-002',
        ], $pendapatan);

        DB::table('simrs_import_pendapatan')->insert([
            'nomer_billing' => 'INV-SIMRS-002',
            'tanggal_reg' => '2026-09-10',
            'total_tagihan' => 150_000,
            'import_ke' => 'Invoice Pendapatan',
        ]);

        $detail = $invoice->rincian()->first();

        $payload = [
            'nomor_faktur' => 'INV-EDITED-999',
            'tanggal_faktur' => '2026-09-12',
            'pelanggan_id' => $penjaminBaru->id,
            'akun_piutang_id' => $piutang->id,
            'nama_pasien' => 'Pasien Diubah',
            'nomer_rekam_medis' => 'RM-BARU',
            'nama_dokter' => 'Dr. Sp.A',
            'nama_poli' => 'Poli Anak',
            'keterangan' => 'Keterangan diperbarui',
            'rincian' => [
                [
                    'id' => $detail->id,
                    'coa_id' => $pendapatan->id,
                    'kuantitas' => 2,
                    'harga' => 200_000,
                    'catatan' => 'Rincian baru',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('pendapatan.invoice.update', $invoice), $payload);

        $response->assertRedirect(route('pendapatan.invoice.read', $invoice));

        $invoice->refresh();

        $this->assertSame('INV-EDITED-999', $invoice->nomor_faktur);
        $this->assertSame($penjaminBaru->id, $invoice->pelanggan_id);
        $this->assertSame('Pasien Umum', $invoice->nama_penjamin);
        $this->assertSame('Pasien Diubah', $invoice->nama_pasien);
        $this->assertSame('RM-BARU', $invoice->nomer_rekam_medis);
        $this->assertSame('Dr. Sp.A', $invoice->nama_dokter);
        $this->assertSame('Poli Anak', $invoice->nama_poli);
        $this->assertSame('2026-09-12', $invoice->tanggal_faktur->format('Y-m-d'));
        $this->assertEquals(400_000, (float) $invoice->grandtotal);

        // Verify simrs_import_pendapatan total_tagihan was updated
        $this->assertDatabaseHas('simrs_import_pendapatan', [
            'nomer_billing' => 'INV-SIMRS-002',
            'total_tagihan' => 400_000,
        ]);

        // Verify bukubesar has new invoice number
        $this->assertDatabaseHas('bukubesar', [
            'sumber_transaksi' => 'Invoice Pendapatan',
            'sumber_id' => $invoice->id,
            'nomer' => 'INV-EDITED-999',
            'nominal' => 400_000,
            'tipe_mutasi' => 'D',
        ]);
    }

    public function test_updating_manual_invoice_persists_all_changed_fields(): void
    {
        $penjaminAwal = Pelanggan::query()->create([
            'kode_pelanggan' => 'P1',
            'nama_pelanggan' => 'Penjamin 1',
            'status_aktif' => true,
        ]);
        $penjaminBaru = Pelanggan::query()->create([
            'kode_pelanggan' => 'P2',
            'nama_pelanggan' => 'Penjamin 2',
            'status_aktif' => true,
        ]);
        $piutang = $this->createCoa('103001', 'Piutang Pasien', 'Piutang Usaha');
        $pendapatan = $this->createCoa('440401', 'Pendapatan Layanan', 'Pendapatan');

        $invoice = $this->createInvoice([
            'nomor_faktur' => 'INV-MANUAL-001',
            'pelanggan_id' => $penjaminAwal->id,
            'akun_piutang_id' => $piutang->id,
            'nama_pasien' => 'Pasien Manual',
            'nomer_rekam_medis' => 'RM-100',
            'nama_dokter' => 'Dr. Pertama',
            'nama_poli' => 'Poli Pertama',
            'nomer_rawat' => '',
        ], $pendapatan);

        $detail = $invoice->rincian()->first();

        $payload = [
            'nomor_faktur' => 'INV-MANUAL-EDITED',
            'tanggal_faktur' => '2026-09-15',
            'pelanggan_id' => $penjaminBaru->id,
            'akun_piutang_id' => $piutang->id,
            'nama_pasien' => 'Pasien Manual Baru',
            'nomer_rekam_medis' => 'RM-200',
            'nama_dokter' => 'Dr. Sp.A',
            'nama_poli' => 'Poli Anak',
            'keterangan' => 'Keterangan manual edit',
            'rincian' => [
                [
                    'id' => $detail->id,
                    'coa_id' => $pendapatan->id,
                    'kuantitas' => 3,
                    'harga' => 100_000,
                    'catatan' => 'Rincian manual',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('pendapatan.invoice.update', $invoice), $payload);

        $response->assertRedirect(route('pendapatan.invoice.read', $invoice));

        $invoice->refresh();

        $this->assertSame('INV-MANUAL-EDITED', $invoice->nomor_faktur);
        $this->assertSame($penjaminBaru->id, $invoice->pelanggan_id);
        $this->assertSame('Pasien Manual Baru', $invoice->nama_pasien);
        $this->assertSame('RM-200', $invoice->nomer_rekam_medis);
        $this->assertEquals(300_000, (float) $invoice->grandtotal);
    }

    private function createInvoice(array $attributes, Coa $pendapatan): FakturPenjualan
    {
        $invoice = new FakturPenjualan;
        $invoice->pelanggan_id = $attributes['pelanggan_id'];
        $invoice->akun_piutang_id = $attributes['akun_piutang_id'];
        $invoice->nomor_faktur = $attributes['nomor_faktur'];
        $invoice->tanggal_faktur = '2026-09-10';
        $invoice->tanggal_registrasi = '2026-09-10';
        $invoice->keterangan = 'Invoice Test';
        $invoice->grandtotal = 150_000;
        $invoice->sudah_terbayar = 0;
        $invoice->status_proses = 0;
        $invoice->created_by = 'Tester';
        $invoice->updated_by = 'Tester';
        $invoice->nama_pasien = $attributes['nama_pasien'];
        $invoice->nomer_rekam_medis = $attributes['nomer_rekam_medis'];
        $invoice->nama_dokter = $attributes['nama_dokter'];
        $invoice->nama_poli = $attributes['nama_poli'];
        $invoice->nomer_rawat = $attributes['nomer_rawat'] ?? '';
        $invoice->kode_penjamin = 'BPJS';
        $invoice->nama_penjamin = 'BPJS';
        $invoice->save();

        $invoice->rincian()->create([
            'coa_id' => $pendapatan->id,
            'kuantitas' => 1,
            'harga' => 150_000,
            'subtotal' => 150_000,
            'catatan' => 'Detail test',
        ]);

        return $invoice;
    }

    private function createCoa(string $kode, string $nama, string $tipe): Coa
    {
        return Coa::query()->create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe_coa' => $tipe,
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('pelanggan', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_pelanggan');
            $table->string('nama_pelanggan')->unique();
            $table->timestamps();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pelanggan_id');
            $table->unsignedInteger('akun_piutang_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur');
            $table->text('keterangan')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->unsignedTinyInteger('status_proses')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('nama_poli')->nullable();
            $table->string('nama_dokter')->nullable();
            $table->string('nama_pasien')->default('');
            $table->string('nomer_rawat')->default('');
            $table->string('nomer_rekam_medis')->nullable();
            $table->date('tanggal_registrasi');
            $table->string('kode_penjamin')->nullable();
            $table->string('nama_penjamin')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->unsignedInteger('pelaksana_id')->nullable();
            $table->string('kode_proyek')->nullable();
            $table->unsignedInteger('coa_id')->nullable();
            $table->decimal('kuantitas', 15, 2)->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan_rinci', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('faktur_penjualan_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
        });

        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id');
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun');
            $table->unsignedTinyInteger('periode_bulan');
            $table->string('nomer');
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('simrs_import_pendapatan', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomer_billing');
            $table->date('tanggal_reg')->nullable();
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

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
}
