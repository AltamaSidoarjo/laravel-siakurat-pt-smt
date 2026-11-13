<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\KasbankPembayaran;
use App\Models\Pelaksana;
use App\Models\User;
use App\Services\LogAktifitasService;
use App\Services\Pengaturan\PelaksanaManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class KasbankPembayaranPelaksanaTest extends TestCase
{
    private User $user;

    private Coa $coaPendapatan;

    private Coa $coaKas;

    private Pelaksana $pelaksanaAktif;

    private Pelaksana $pelaksanaNonaktif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $this->user = User::factory()->make([
            'name' => 'Kasir',
            'email' => 'kasir@example.com',
        ]);

        $this->coaPendapatan = Coa::query()->create([
            'kode' => '4101',
            'nama' => 'Pendapatan Visite',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaKas = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Utama',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->pelaksanaAktif = Pelaksana::query()->create([
            'no_proyek' => '1_DR_ALISSA',
            'nama_pelaksana' => 'Dr. Alissa',
            'status_aktif' => true,
        ]);

        $this->pelaksanaNonaktif = Pelaksana::query()->create([
            'no_proyek' => '1_DR_NONAKTIF',
            'nama_pelaksana' => 'Dr. Nonaktif',
            'status_aktif' => false,
        ]);
    }

    public function test_create_view_lists_pelaksana_options(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('kasbank.pembayaran.create'));

        $response->assertOk()
            ->assertSee('Dr. Alissa');
    }

    public function test_store_and_update_kasbank_pembayaran_persists_pelaksana_and_prints_it(): void
    {
        $storeResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('kasbank.pembayaran.store'), [
                'coa_id' => $this->coaKas->id,
                'nomer' => 'BK-RET-001',
                'tanggal' => '2026-11-13',
                'keterangan' => 'Pengembalian jasa dokter via kas',
                'total' => 200000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaAktif->id,
                        'nominal' => 200000,
                        'catatan' => 'Retur visite pasien A',
                    ],
                ],
            ]);

        $storeResponse->assertRedirect(route('kasbank.pembayaran.index'));

        $kasbank = KasbankPembayaran::query()->where('nomer', 'BK-RET-001')->firstOrFail();
        $this->assertDatabaseHas('kasbank_pembayaran_rinci', [
            'kasbank_pembayaran_id' => $kasbank->id,
            'coa_id' => $this->coaPendapatan->id,
            'pelaksana_id' => $this->pelaksanaAktif->id,
            'nominal' => 200000,
            'catatan' => 'Retur visite pasien A',
        ]);

        // Check print receipt contains doctor's name
        $printResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('kasbank.pembayaran.print', $kasbank));

        $printResponse->assertOk()
            ->assertSee('Dr. Alissa')
            ->assertSee('Pelaksana');

        // Check update
        $updateResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('kasbank.pembayaran.update', $kasbank), [
                'coa_id' => $this->coaKas->id,
                'nomer' => 'BK-RET-001',
                'tanggal' => '2026-11-13',
                'keterangan' => 'Pengembalian jasa dokter via kas revisi',
                'total' => 250000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaAktif->id,
                        'nominal' => 250000,
                        'catatan' => 'Retur visite revisi',
                    ],
                ],
            ]);

        $updateResponse->assertRedirect(route('kasbank.pembayaran.index'));

        $this->assertDatabaseHas('kasbank_pembayaran_rinci', [
            'kasbank_pembayaran_id' => $kasbank->id,
            'nominal' => 250000,
            'catatan' => 'Retur visite revisi',
        ]);
    }

    public function test_cannot_delete_pelaksana_used_in_kasbank_pembayaran(): void
    {
        $kasbank = KasbankPembayaran::query()->create([
            'coa_id' => $this->coaKas->id,
            'nomer' => 'BK-DEL-001',
            'tanggal' => '2026-11-13',
            'keterangan' => 'Test delete pelaksana',
            'total' => 100000,
        ]);

        $kasbank->rincian()->create([
            'coa_id' => $this->coaPendapatan->id,
            'pelaksana_id' => $this->pelaksanaAktif->id,
            'nominal' => 100000,
            'catatan' => 'Test lock pelaksana',
        ]);

        $logMock = Mockery::mock(LogAktifitasService::class);
        $service = new PelaksanaManagementService($logMock);

        $result = $service->delete($this->pelaksanaAktif);

        $this->assertFalse($result);
        $this->assertDatabaseHas('pelaksana', [
            'id' => $this->pelaksanaAktif->id,
        ]);
    }

    public function test_can_delete_pelaksana_not_used(): void
    {
        $unusedPelaksana = Pelaksana::query()->create([
            'no_proyek' => '1_DR_UNUSED',
            'nama_pelaksana' => 'Dr. Unused',
            'status_aktif' => true,
        ]);

        $logMock = Mockery::mock(LogAktifitasService::class);
        $logMock->shouldReceive('log')->once();
        $service = new PelaksanaManagementService($logMock);

        $result = $service->delete($unusedPelaksana);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('pelaksana', [
            'id' => $unusedPelaksana->id,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::dropIfExists('kasbank_pembayaran_rinci');
        Schema::dropIfExists('kasbank_pembayaran');
        Schema::dropIfExists('kasbank_pembayaran_template');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('faktur_penjualan_rinci');
        Schema::dropIfExists('pelaksana');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('preferensi_perusahaan');

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nilai')->nullable();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('pelaksana', function (Blueprint $table) {
            $table->id();
            $table->string('no_proyek')->nullable();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran_template', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_id')->constrained('coa');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasbank_pembayaran_id')->constrained('kasbank_pembayaran')->cascadeOnDelete();
            $table->foreignId('coa_id')->constrained('coa');
            $table->foreignId('pelaksana_id')->nullable()->constrained('pelaksana')->nullOnDelete();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_id')->constrained('coa');
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal')->nullable();
            $table->integer('periode_tahun')->nullable();
            $table->integer('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('tipe_mutasi', 5)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_user')->nullable();
            $table->string('modul')->nullable();
            $table->string('tipe')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelaksana_id')->nullable()->constrained('pelaksana')->nullOnDelete();
            $table->timestamps();
        });
    }
}
