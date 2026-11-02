<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Coa;
use App\Models\User;
use App\Services\Bukubesar\CoaService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaDeletionIntegrityTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mapping_pendapatan_tindakan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('jurnal_umum_rinci');
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

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('mapping_pendapatan_tindakan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kd_jenis_prw');
            $table->unsignedInteger('coa_id');
            $table->timestamps();
        });
    }

    public function test_it_rejects_deletion_of_parent_coa_with_children(): void
    {
        $parentId = DB::table('coa')->insertGetId([
            'kode' => '100000',
            'nama' => 'Aset Lancar Induk',
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coa')->insert([
            'kode' => '100001',
            'nama' => 'Kas Anak',
            'parent_coa' => $parentId,
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parent = Coa::query()->find($parentId);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->delete(route('bukubesar.coa.destroy', $parent));

        $response
            ->assertRedirect(route('bukubesar.coa.edit', $parent))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('coa', ['id' => $parentId]);
    }

    public function test_it_rejects_deletion_of_coa_having_bukubesar_transactions(): void
    {
        $coaId = DB::table('coa')->insertGetId([
            'kode' => '111001',
            'nama' => 'Kas Operasional',
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bukubesar')->insert([
            'coa_id' => $coaId,
            'sumber_id' => 1,
            'tanggal' => '2026-05-20',
            'nomer' => 'JU-001',
            'keterangan' => 'Kas masuk',
            'nominal' => 500_000,
            'tipe_mutasi' => 'D',
            'sumber_transaksi' => 'Jurnal Umum',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coa = Coa::query()->find($coaId);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->delete(route('bukubesar.coa.destroy', $coa));

        $response
            ->assertRedirect(route('bukubesar.coa.edit', $coa))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('coa', ['id' => $coaId]);
    }

    public function test_it_rejects_deletion_of_coa_referenced_in_system_mapping(): void
    {
        $coaId = DB::table('coa')->insertGetId([
            'kode' => '410001',
            'nama' => 'Pendapatan Tindakan Rawat Jalan',
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mapping_pendapatan_tindakan')->insert([
            'kd_jenis_prw' => 'T001',
            'coa_id' => $coaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coa = Coa::query()->find($coaId);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->delete(route('bukubesar.coa.destroy', $coa));

        $response
            ->assertRedirect(route('bukubesar.coa.edit', $coa))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('coa', ['id' => $coaId]);
    }

    public function test_it_successfully_deletes_clean_leaf_coa(): void
    {
        $coaId = DB::table('coa')->insertGetId([
            'kode' => '999999',
            'nama' => 'Akun Sementara Tanpa Transaksi',
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coa = Coa::query()->find($coaId);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->delete(route('bukubesar.coa.destroy', $coa));

        $response
            ->assertRedirect(route('bukubesar.coa.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('coa', ['id' => $coaId]);
    }

    public function test_service_delete_throws_domain_exception_when_integrity_check_fails(): void
    {
        $coaId = DB::table('coa')->insertGetId([
            'kode' => '111002',
            'nama' => 'Kas Service Integrity Test',
            'status_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bukubesar')->insert([
            'coa_id' => $coaId,
            'sumber_id' => 1,
            'tanggal' => '2026-05-20',
            'nomer' => 'JU-002',
            'keterangan' => 'Kas masuk',
            'nominal' => 250_000,
            'tipe_mutasi' => 'D',
            'sumber_transaksi' => 'Jurnal Umum',
            'periode_tahun' => 2026,
            'periode_bulan' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coa = Coa::query()->find($coaId);
        $service = app(CoaService::class);

        $this->expectException(DomainException::class);

        $service->delete($coa);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'id' => 1,
            'name' => 'Admin Akuntansi',
            'email' => 'admin@example.com',
        ]);
    }
}
