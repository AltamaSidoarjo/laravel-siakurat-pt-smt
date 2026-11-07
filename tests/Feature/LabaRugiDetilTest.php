<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\PreferensiPerusahaan;
use App\Models\TipeCoa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LabaRugiDetilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('tipe_coa');
        Schema::dropIfExists('coa');

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
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('preferensi_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('logo_perusahaan')->nullable();
            $table->timestamps();
        });

        PreferensiPerusahaan::query()->create([
            'nama_perusahaan' => 'RS SI Akurat',
            'logo_perusahaan' => null,
        ]);
    }

    public function test_laba_rugi_detil_renders_coa_links_to_bukubesar(): void
    {
        TipeCoa::query()->create(['nama' => 'Pendapatan', 'status_aktif' => 1]);
        TipeCoa::query()->create(['nama' => 'Beban', 'status_aktif' => 1]);

        $parentCoa = Coa::query()->create([
            'kode' => '4000',
            'nama' => 'Pendapatan Induk',
            'tipe_coa' => 'Pendapatan',
            'parent_coa' => null,
            'status_aktif' => 1,
        ]);

        $childCoa1 = Coa::query()->create([
            'kode' => '4001',
            'nama' => 'Pendapatan Tindakan Medis',
            'tipe_coa' => 'Pendapatan',
            'parent_coa' => $parentCoa->id,
            'status_aktif' => 1,
        ]);

        $childCoa2 = Coa::query()->create([
            'kode' => '5001',
            'nama' => 'Beban Kebersihan',
            'tipe_coa' => 'Beban',
            'parent_coa' => null,
            'status_aktif' => 1,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $childCoa1->id,
            'tanggal' => '2026-05-10',
            'sumber_transaksi' => 'Kas Masuk',
            'nominal' => 2500000,
            'tipe_mutasi' => 'K',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $childCoa2->id,
            'tanggal' => '2026-06-12',
            'sumber_transaksi' => 'Kas Keluar',
            'nominal' => 750000,
            'tipe_mutasi' => 'D',
        ]);

        $startDate = '2026-01-01';
        $endDate = '2026-11-07';

        $expectedLinkCoa1 = route('laporan.keuangan.bukubesar', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'coaIds' => [$childCoa1->id],
        ]);

        $expectedLinkCoa2 = route('laporan.keuangan.bukubesar', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'coaIds' => [$childCoa2->id],
        ]);

        $response = $this->actingAs($this->makeUser())
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.keuangan.laba-rugi-detil', [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]));

        $response->assertOk();
        $response->assertViewIs('laporan.keuangan.laba-rugi-detil');

        // Check that child COA 1 links to bukubesar
        $response->assertSee(e($expectedLinkCoa1), false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('4001');
        $response->assertSee('Pendapatan Tindakan Medis');

        // Check that child COA 2 also links to bukubesar
        $response->assertSee(e($expectedLinkCoa2), false);
        $response->assertSee('5001');
        $response->assertSee('Beban Kebersihan');

        // Parent COA should not be listed in detail report
        $response->assertDontSee('Pendapatan Induk');
    }

    public function test_laba_rugi_detil_renders_empty_state_when_no_data(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.keuangan.laba-rugi-detil', [
                'startDate' => '2026-01-01',
                'endDate' => '2026-11-07',
            ]));

        $response->assertOk();
        $response->assertSee('Tidak ada data untuk periode ini.');
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
