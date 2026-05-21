<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PenerimaanPendapatanSelisihTarifTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('penerimaan_penjualan_rinci');
        Schema::dropIfExists('penerimaan_penjualan');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('pelanggan');
        Schema::enableForeignKeyConstraints();

        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->string('kode_pelanggan')->nullable();
            $table->string('nama_pelanggan');
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->boolean('status_aktif')->default(true);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('faktur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->string('nomor_faktur');
            $table->date('tanggal_faktur')->nullable();
            $table->decimal('grandtotal', 15, 2)->default(0);
            $table->decimal('sudah_terbayar', 15, 2)->default(0);
            $table->string('nama_pasien')->nullable();
            $table->string('nomer_rekam_medis')->nullable();
            $table->string('status_proses')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelanggan_id');
            $table->unsignedBigInteger('akun_bank_id');
            $table->unsignedBigInteger('akun_piutang_id');
            $table->unsignedBigInteger('akun_selisih_tarif_id')->nullable();
            $table->string('nomer');
            $table->date('tanggal');
            $table->decimal('jumlah_pembayaran', 15, 2)->default(0);
            $table->decimal('selisih_tarif', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('penerimaan_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penerimaan_penjualan_id');
            $table->unsignedBigInteger('faktur_penjualan_id');
            $table->decimal('nominal_bayar', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id');
            $table->date('tanggal');
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('ttd_kabag')->nullable();
            $table->string('ttd_direktur')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('penerimaan_penjualan_rinci');
        Schema::dropIfExists('penerimaan_penjualan');
        Schema::dropIfExists('faktur_penjualan');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('pelanggan');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_it_creates_journal_without_selisih_tarif(): void
    {
        [$pelangganId, $akunBankId, $akunPiutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($pelangganId, 5_000_000);

        $response = $this
            ->actingAs($this->makeUser())
            ->post(route('pendapatan.penerimaan.store'), $this->buildPayload(
                pelangganId: $pelangganId,
                akunBankId: $akunBankId,
                akunPiutangId: $akunPiutangId,
                invoiceId: $invoiceId,
                jumlahPembayaran: 5_000_000,
                nominalBayar: 5_000_000,
            ));

        $response->assertRedirect(route('pendapatan.penerimaan.index'));

        $this->assertDatabaseHas('penerimaan_penjualan', [
            'nomer' => 'PPD-001',
            'selisih_tarif' => 0,
            'akun_selisih_tarif_id' => null,
        ]);

        $penerimaanId = \DB::table('penerimaan_penjualan')->value('id');

        $this->assertDatabaseCount('bukubesar', 2);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'sumber_transaksi' => 'Penerimaan Pendapatan',
            'coa_id' => $akunBankId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'D',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'sumber_transaksi' => 'Penerimaan Pendapatan',
            'coa_id' => $akunPiutangId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'K',
        ]);
    }

    public function test_it_creates_journal_with_selisih_tarif(): void
    {
        [$pelangganId, $akunBankId, $akunPiutangId, $akunSelisihId] = $this->seedMasterData(withSelisih: true);
        $invoiceId = $this->createInvoice($pelangganId, 4_500_000);

        $response = $this
            ->actingAs($this->makeUser())
            ->post(route('pendapatan.penerimaan.store'), $this->buildPayload(
                pelangganId: $pelangganId,
                akunBankId: $akunBankId,
                akunPiutangId: $akunPiutangId,
                invoiceId: $invoiceId,
                jumlahPembayaran: 5_000_000,
                nominalBayar: 4_500_000,
                selisihTarif: 500_000,
                akunSelisihTarifId: $akunSelisihId,
            ));

        $response->assertRedirect(route('pendapatan.penerimaan.index'));

        $penerimaanId = \DB::table('penerimaan_penjualan')->value('id');

        $this->assertDatabaseHas('penerimaan_penjualan', [
            'id' => $penerimaanId,
            'selisih_tarif' => 500_000,
            'akun_selisih_tarif_id' => $akunSelisihId,
        ]);

        $this->assertDatabaseCount('bukubesar', 3);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunBankId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'D',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunPiutangId,
            'nominal' => 4_500_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunSelisihId,
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
        ]);
    }

    public function test_it_updates_journal_when_selisih_changes(): void
    {
        [$pelangganId, $akunBankId, $akunPiutangId, $akunSelisihId] = $this->seedMasterData(withSelisih: true);
        $invoiceId = $this->createInvoice($pelangganId, 5_000_000);

        // Store tanpa selisih via HTTP
        $this
            ->actingAs($this->makeUser())
            ->post(route('pendapatan.penerimaan.store'), $this->buildPayload(
                pelangganId: $pelangganId,
                akunBankId: $akunBankId,
                akunPiutangId: $akunPiutangId,
                invoiceId: $invoiceId,
                jumlahPembayaran: 5_000_000,
                nominalBayar: 5_000_000,
            ));

        $penerimaanId = \DB::table('penerimaan_penjualan')->value('id');
        $this->assertDatabaseCount('bukubesar', 2);

        // Update dengan selisih 500_000 via service langsung (bypass route model binding)
        $penerimaan = \App\Models\PenerimaanPenjualan::find($penerimaanId);
        $service = app(\App\Services\Pendapatan\PenerimaanPendapatanService::class);
        $service->update($penerimaan, [
            'pelanggan_id' => $pelangganId,
            'akun_bank_id' => $akunBankId,
            'akun_piutang_id' => $akunPiutangId,
            'akun_selisih_tarif_id' => $akunSelisihId,
            'nomer' => 'PPD-001-REV',
            'tanggal' => '2026-05-20',
            'jumlah_pembayaran' => 5_500_000,
            'selisih_tarif' => 500_000,
            'keterangan' => null,
            'rincian' => [
                [
                    'faktur_penjualan_id' => $invoiceId,
                    'nominal_bayar' => 5_000_000,
                    'check' => '1',
                ],
            ],
        ]);

        $this->assertDatabaseCount('bukubesar', 3);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunBankId,
            'nominal' => 5_500_000,
            'tipe_mutasi' => 'D',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunPiutangId,
            'nominal' => 5_000_000,
            'tipe_mutasi' => 'K',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'sumber_id' => $penerimaanId,
            'coa_id' => $akunSelisihId,
            'nominal' => 500_000,
            'tipe_mutasi' => 'K',
        ]);
    }

    public function test_it_validates_jumlah_pembayaran_equals_total_rincian_plus_selisih(): void
    {
        [$pelangganId, $akunBankId, $akunPiutangId, $akunSelisihId] = $this->seedMasterData(withSelisih: true);
        $invoiceId = $this->createInvoice($pelangganId, 4_500_000);

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('pendapatan.penerimaan.create'))
            ->post(route('pendapatan.penerimaan.store'), $this->buildPayload(
                pelangganId: $pelangganId,
                akunBankId: $akunBankId,
                akunPiutangId: $akunPiutangId,
                invoiceId: $invoiceId,
                jumlahPembayaran: 5_000_000,
                nominalBayar: 4_500_000,
                selisihTarif: 0,
            ));

        $response
            ->assertRedirect(route('pendapatan.penerimaan.create'))
            ->assertSessionHasErrors(['jumlah_pembayaran']);
    }

    public function test_it_requires_akun_selisih_tarif_when_selisih_greater_than_zero(): void
    {
        [$pelangganId, $akunBankId, $akunPiutangId] = $this->seedMasterData();
        $invoiceId = $this->createInvoice($pelangganId, 4_500_000);

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('pendapatan.penerimaan.create'))
            ->post(route('pendapatan.penerimaan.store'), $this->buildPayload(
                pelangganId: $pelangganId,
                akunBankId: $akunBankId,
                akunPiutangId: $akunPiutangId,
                invoiceId: $invoiceId,
                jumlahPembayaran: 5_000_000,
                nominalBayar: 4_500_000,
                selisihTarif: 500_000,
                akunSelisihTarifId: null,
            ));

        $response
            ->assertRedirect(route('pendapatan.penerimaan.create'))
            ->assertSessionHasErrors(['akun_selisih_tarif_id']);
    }

    // --- helpers ---

    private function seedMasterData(bool $withSelisih = false): array
    {
        $pelangganId = \DB::table('pelanggan')->insertGetId([
            'status_aktif' => true,
            'kode_pelanggan' => 'PLG-001',
            'nama_pelanggan' => 'BPJS Kesehatan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunBankId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '111001',
            'nama' => 'Bank BCA',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $akunPiutangId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '120001',
            'nama' => 'Piutang Usaha',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $withSelisih) {
            return [$pelangganId, $akunBankId, $akunPiutangId];
        }

        $akunSelisihId = \DB::table('coa')->insertGetId([
            'status_aktif' => true,
            'kode' => '410001',
            'nama' => 'Selisih Tarif',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$pelangganId, $akunBankId, $akunPiutangId, $akunSelisihId];
    }

    private function createInvoice(int $pelangganId, int $grandTotal): int
    {
        return \DB::table('faktur_penjualan')->insertGetId([
            'pelanggan_id' => $pelangganId,
            'nomor_faktur' => 'INV-PP-0001',
            'tanggal_faktur' => '2026-05-20',
            'grandtotal' => $grandTotal,
            'sudah_terbayar' => 0,
            'nama_pasien' => 'Pasien Contoh',
            'nomer_rekam_medis' => 'RM-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildPayload(
        int $pelangganId,
        int $akunBankId,
        int $akunPiutangId,
        int $invoiceId,
        int $jumlahPembayaran,
        int $nominalBayar,
        int $selisihTarif = 0,
        ?int $akunSelisihTarifId = null,
        string $nomer = 'PPD-001',
    ): array {
        return [
            'pelanggan_id' => $pelangganId,
            'akun_bank_id' => $akunBankId,
            'akun_piutang_id' => $akunPiutangId,
            'akun_selisih_tarif_id' => $akunSelisihTarifId,
            'nomer' => $nomer,
            'tanggal' => '2026-05-20',
            'jumlah_pembayaran' => $jumlahPembayaran,
            'selisih_tarif' => $selisihTarif,
            'keterangan' => 'Penerimaan dari penjamin',
            'rincian' => [
                [
                    'faktur_penjualan_id' => $invoiceId,
                    'nominal_bayar' => $nominalBayar,
                    'check' => '1',
                ],
            ],
        ];
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
