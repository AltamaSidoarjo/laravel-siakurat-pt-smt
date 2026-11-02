<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\JurnalUmum;
use App\Models\User;
use App\Services\Bukubesar\JurnalUmumService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JurnalUmumBalanceValidationTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->string('nomer');
            $table->text('keterangan')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('tipe_mutasi', 1);
            $table->string('sumber_transaksi');
            $table->unsignedSmallInteger('periode_tahun');
            $table->unsignedTinyInteger('periode_bulan');
            $table->timestamps();
        });
    }

    public function test_store_rejects_unbalanced_detail_rows_even_if_header_is_balanced(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('bukubesar.jurnal-umum.create'))
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-001',
                'tanggal' => '2026-05-20',
                'keterangan' => 'Uji unbalance detail',
                'debit' => 1_000_000,
                'kredit' => 1_000_000,
                'rincian' => [
                    ['coa_id' => $coaBeban, 'debit' => 1_000_000, 'kredit' => 0],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 500_000],
                ],
            ]);

        $response
            ->assertRedirect(route('bukubesar.jurnal-umum.create'))
            ->assertSessionHasErrors(['rincian']);
    }

    public function test_store_rejects_when_detail_sum_does_not_match_header(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('bukubesar.jurnal-umum.create'))
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-002',
                'tanggal' => '2026-05-20',
                'keterangan' => 'Header detail mismatch',
                'debit' => 1_000_000,
                'kredit' => 1_000_000,
                'rincian' => [
                    ['coa_id' => $coaBeban, 'debit' => 500_000, 'kredit' => 0],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 500_000],
                ],
            ]);

        $response
            ->assertRedirect(route('bukubesar.jurnal-umum.create'))
            ->assertSessionHasErrors(['debit', 'kredit']);
    }

    public function test_store_rejects_row_with_both_debit_and_kredit(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('bukubesar.jurnal-umum.create'))
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-003',
                'tanggal' => '2026-05-20',
                'keterangan' => 'Both debit and kredit',
                'debit' => 500_000,
                'kredit' => 500_000,
                'rincian' => [
                    ['coa_id' => $coaBeban, 'debit' => 500_000, 'kredit' => 500_000],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 500_000],
                ],
            ]);

        $response
            ->assertRedirect(route('bukubesar.jurnal-umum.create'))
            ->assertSessionHasErrors(['rincian.0.debit']);
    }

    public function test_store_rejects_row_with_neither_debit_nor_kredit(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();

        $response = $this
            ->actingAs($this->makeUser())
            ->from(route('bukubesar.jurnal-umum.create'))
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-004',
                'tanggal' => '2026-05-20',
                'keterangan' => 'Zero row',
                'debit' => 500_000,
                'kredit' => 500_000,
                'rincian' => [
                    ['coa_id' => $coaBeban, 'debit' => 500_000, 'kredit' => 0],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 500_000],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 0],
                ],
            ]);

        $response
            ->assertRedirect(route('bukubesar.jurnal-umum.create'))
            ->assertSessionHasErrors(['rincian.2.debit']);
    }

    public function test_store_successfully_creates_balanced_journal_and_syncs_bukubesar(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();

        $response = $this
            ->actingAs($this->makeUser())
            ->post(route('bukubesar.jurnal-umum.store'), [
                'nomer' => 'JU-005',
                'tanggal' => '2026-05-20',
                'keterangan' => 'Pembayaran Beban Operasional',
                'debit' => 750_000,
                'kredit' => 750_000,
                'rincian' => [
                    ['coa_id' => $coaBeban, 'debit' => 750_000, 'kredit' => 0, 'catatan' => 'Beban listrik'],
                    ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 750_000, 'catatan' => 'Kas keluar'],
                ],
            ]);

        $response->assertRedirect(route('bukubesar.jurnal-umum.index'));

        $this->assertDatabaseHas('jurnal_umum', [
            'nomer' => 'JU-005',
            'debit' => 750_000,
            'kredit' => 750_000,
        ]);

        $this->assertDatabaseCount('jurnal_umum_rinci', 2);
        $this->assertDatabaseCount('bukubesar', 2);

        $this->assertDatabaseHas('bukubesar', [
            'nomer' => 'JU-005',
            'coa_id' => $coaBeban,
            'tipe_mutasi' => 'D',
            'nominal' => 750_000,
        ]);

        $this->assertDatabaseHas('bukubesar', [
            'nomer' => 'JU-005',
            'coa_id' => $coaKas,
            'tipe_mutasi' => 'K',
            'nominal' => 750_000,
        ]);
    }

    public function test_service_validate_balance_throws_domain_exception_on_unbalanced_data(): void
    {
        [$coaKas, $coaBeban] = $this->seedCoa();
        $service = app(JurnalUmumService::class);

        $this->expectException(DomainException::class);

        $service->create([
            'nomer' => 'JU-FAIL',
            'tanggal' => '2026-05-20',
            'debit' => 500_000,
            'kredit' => 500_000,
            'rincian' => [
                ['coa_id' => $coaBeban, 'debit' => 500_000, 'kredit' => 0],
                ['coa_id' => $coaKas, 'debit' => 0, 'kredit' => 400_000],
            ],
        ]);
    }

    private function seedCoa(): array
    {
        $coaKas = DB::table('coa')->insertGetId([
            'status_aktif' => 1,
            'kode' => '111001',
            'nama' => 'Kas Utama',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coaBeban = DB::table('coa')->insertGetId([
            'status_aktif' => 1,
            'kode' => '510001',
            'nama' => 'Beban Operasional',
            'is_postable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$coaKas, $coaBeban];
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'id' => 1,
            'name' => 'Akuntan',
            'email' => 'akuntan@example.com',
        ]);
    }
}
