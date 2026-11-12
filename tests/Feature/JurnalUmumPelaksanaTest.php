<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\JurnalUmum;
use App\Models\Pelaksana;
use App\Models\User;
use App\Services\LogAktifitasService;
use App\Services\Pengaturan\PelaksanaManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class JurnalUmumPelaksanaTest extends TestCase
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
            'name' => 'Akuntan',
            'email' => 'akuntan@example.com',
        ]);

        $this->coaPendapatan = Coa::query()->create([
            'kode' => '4101',
            'nama' => 'Pendapatan Tindakan',
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
            'no_proyek' => '1_DR_AKTIF',
            'nama_pelaksana' => 'Dr. Aktif',
            'status_aktif' => true,
        ]);

        $this->pelaksanaNonaktif = Pelaksana::query()->create([
            'no_proyek' => '1_DR_NONAKTIF',
            'nama_pelaksana' => 'Dr. Nonaktif',
            'status_aktif' => false,
        ]);
    }

    public function test_create_view_lists_only_active_pelaksana(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('bukubesar.jurnal-umum.create'));

        $response->assertOk()
            ->assertSee('Dr. Aktif')
            ->assertDontSee('Dr. Nonaktif');
    }

    public function test_store_and_update_jurnal_umum_persists_pelaksana_and_prints_it(): void
    {
        $storeResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-RET-001',
                'tanggal' => '2026-11-10',
                'keterangan' => 'Pengembalian jasa dokter',
                'debit' => 150000,
                'kredit' => 150000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaAktif->id,
                        'debit' => 150000,
                        'kredit' => 0,
                        'catatan' => 'Retur jasa Dr. Aktif',
                    ],
                    [
                        'coa_id' => $this->coaKas->id,
                        'pelaksana_id' => null,
                        'debit' => 0,
                        'kredit' => 150000,
                        'catatan' => 'Pengembalian kas',
                    ],
                ],
            ]);

        $storeResponse->assertRedirect(route('bukubesar.jurnal-umum.index'));

        $jurnal = JurnalUmum::query()->where('nomer', 'JU-RET-001')->firstOrFail();

        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'jurnal_umum_id' => $jurnal->id,
            'coa_id' => $this->coaPendapatan->id,
            'pelaksana_id' => $this->pelaksanaAktif->id,
            'debit' => 150000,
            'kredit' => 0,
        ]);

        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'jurnal_umum_id' => $jurnal->id,
            'coa_id' => $this->coaKas->id,
            'pelaksana_id' => null,
            'debit' => 0,
            'kredit' => 150000,
        ]);

        $this->assertDatabaseCount('bukubesar', 2);

        $printResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('bukubesar.jurnal-umum.print', $jurnal));

        $printResponse->assertOk()
            ->assertSee('Dr. Aktif');

        $updateResponse = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('bukubesar.jurnal-umum.update', $jurnal), [
                'nomer' => 'JU-RET-001',
                'tanggal' => '2026-11-11',
                'keterangan' => 'Pengembalian jasa diperbarui',
                'debit' => 200000,
                'kredit' => 200000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaAktif->id,
                        'debit' => 200000,
                        'kredit' => 0,
                        'catatan' => 'Retur jasa update',
                    ],
                    [
                        'coa_id' => $this->coaKas->id,
                        'pelaksana_id' => '',
                        'debit' => 0,
                        'kredit' => 200000,
                        'catatan' => 'Kas keluar',
                    ],
                ],
            ]);

        $updateResponse->assertRedirect(route('bukubesar.jurnal-umum.index'));

        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'jurnal_umum_id' => $jurnal->id,
            'coa_id' => $this->coaPendapatan->id,
            'pelaksana_id' => $this->pelaksanaAktif->id,
            'debit' => 200000,
        ]);
    }

    public function test_inactive_pelaksana_is_rejected_on_store_but_preserved_on_edit(): void
    {
        $createAttempt = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-NONAKTIF',
                'tanggal' => '2026-11-10',
                'debit' => 50000,
                'kredit' => 50000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaNonaktif->id,
                        'debit' => 50000,
                        'kredit' => 0,
                    ],
                    [
                        'coa_id' => $this->coaKas->id,
                        'pelaksana_id' => null,
                        'debit' => 0,
                        'kredit' => 50000,
                    ],
                ],
            ]);

        $createAttempt->assertSessionHasErrors('rincian.0.pelaksana_id');
        $this->assertDatabaseCount('jurnal_umum', 0);

        $jurnal = JurnalUmum::query()->create([
            'nomer' => 'JU-LAMA',
            'tanggal' => '2026-11-01',
            'debit' => 50000,
            'kredit' => 50000,
        ]);
        $jurnal->rincian()->createMany([
            [
                'coa_id' => $this->coaPendapatan->id,
                'pelaksana_id' => $this->pelaksanaNonaktif->id,
                'debit' => 50000,
                'kredit' => 0,
            ],
            [
                'coa_id' => $this->coaKas->id,
                'pelaksana_id' => null,
                'debit' => 0,
                'kredit' => 50000,
            ],
        ]);

        $editView = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('bukubesar.jurnal-umum.edit', $jurnal));

        $editView->assertOk()
            ->assertSee('Dr. Nonaktif (Nonaktif)');

        $preserveAttempt = $this->actingAs($this->user)
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->put(route('bukubesar.jurnal-umum.update', $jurnal), [
                'nomer' => 'JU-LAMA',
                'tanggal' => '2026-11-01',
                'debit' => 60000,
                'kredit' => 60000,
                'rincian' => [
                    [
                        'coa_id' => $this->coaPendapatan->id,
                        'pelaksana_id' => $this->pelaksanaNonaktif->id,
                        'debit' => 60000,
                        'kredit' => 0,
                    ],
                    [
                        'coa_id' => $this->coaKas->id,
                        'pelaksana_id' => null,
                        'debit' => 0,
                        'kredit' => 60000,
                    ],
                ],
            ]);

        $preserveAttempt->assertRedirect(route('bukubesar.jurnal-umum.index'));
        $this->assertDatabaseHas('jurnal_umum_rinci', [
            'jurnal_umum_id' => $jurnal->id,
            'pelaksana_id' => $this->pelaksanaNonaktif->id,
            'debit' => 60000,
        ]);
    }

    public function test_pelaksana_referenced_in_jurnal_umum_cannot_be_deleted(): void
    {
        $jurnal = JurnalUmum::query()->create([
            'nomer' => 'JU-GUARD',
            'tanggal' => '2026-11-01',
            'debit' => 25000,
            'kredit' => 25000,
        ]);
        $jurnal->rincian()->create([
            'coa_id' => $this->coaPendapatan->id,
            'pelaksana_id' => $this->pelaksanaAktif->id,
            'debit' => 25000,
            'kredit' => 0,
        ]);

        $logger = Mockery::mock(LogAktifitasService::class);
        $logger->shouldReceive('log')->zeroOrMoreTimes();
        $pelaksanaService = new PelaksanaManagementService($logger);

        $this->assertFalse($pelaksanaService->delete($this->pelaksanaAktif));
        $this->assertDatabaseHas('pelaksana', ['id' => $this->pelaksanaAktif->id]);
    }

    private function prepareTables(): void
    {
        foreach ([
            'bukubesar',
            'faktur_penjualan_rinci',
            'jurnal_umum_rinci',
            'jurnal_umum',
            'jurnal_umum_template_rinci',
            'jurnal_umum_template',
            'pelaksana',
            'coa',
            'log_aktifitas',
            'preferensi_perusahaan',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->tinyInteger('status_aktif')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->id();
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table): void {
            $table->id();
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->unsignedBigInteger('pelaksana_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum_template', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum_template_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jurnal_umum_template_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('faktur_penjualan_rinci', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pelaksana_id')->nullable();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('coa_id');
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->string('ttd_direktur')->nullable();
            $table->string('ttd_kabag')->nullable();
            $table->timestamps();
        });

        DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RS Test',
            'ttd_direktur' => 'Direktur',
            'ttd_kabag' => 'Kabag',
        ]);
    }
}
