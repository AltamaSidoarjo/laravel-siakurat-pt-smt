<?php

namespace Tests\Feature;

use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class BridgingPendapatanLaboratGabungTest extends TestCase
{
    private BridgingPendapatanService $service;

    private ReflectionMethod $cariKodeLaborat;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.simrs', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('simrs');

        $this->buatTabelSimrs();

        $this->service = new BridgingPendapatanService(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class),
        );

        $this->cariKodeLaborat = new ReflectionMethod(BridgingPendapatanService::class, 'cariKodeLaborat');
        $this->cariKodeLaborat->setAccessible(true);
    }

    public function test_pencarian_langsung_diprioritaskan_tanpa_mengakses_ranap_gabung(): void
    {
        $this->simpanJenisPerawatan('LAB-LANGSUNG', 'Darah Lengkap');
        $this->simpanBilling('2026/02/20/000169', 'Darah Lengkap');
        DB::connection('simrs')->table('periksa_lab')->insert([
            'no_rawat' => '2026/02/20/000169',
            'kd_jenis_prw' => 'LAB-LANGSUNG',
        ]);

        Schema::connection('simrs')->drop('ranap_gabung');

        $this->assertSame(
            'LAB-LANGSUNG',
            $this->panggilCariKodeLaborat('2026/02/20/000169', 'Darah Lengkap'),
        );
    }

    public function test_fallback_ranap_gabung_menemukan_kode_laborat(): void
    {
        $this->simpanJenisPerawatan('ADHA', '(Idul Adha) Gula Darah Acak');
        $this->simpanBilling('2026/02/20/000169', '(Idul Adha) Gula Darah Acak');

        DB::connection('simrs')->table('ranap_gabung')->insert([
            'no_rawat' => '2026/02/20/000169',
            'no_rawat2' => '2026/02/20/000180',
        ]);
        DB::connection('simrs')->table('periksa_lab')->insert([
            'no_rawat' => '2026/02/20/000180',
            'kd_jenis_prw' => 'ADHA',
        ]);

        $this->assertSame(
            'ADHA',
            $this->panggilCariKodeLaborat('2026/02/20/000169', '(Idul Adha) Gula Darah Acak'),
        );
    }

    public function test_mengembalikan_null_jika_laborat_langsung_dan_gabungan_tidak_ditemukan(): void
    {
        $this->simpanJenisPerawatan('ADHA', '(Idul Adha) Gula Darah Acak');
        $this->simpanBilling('2026/02/20/000169', '(Idul Adha) Gula Darah Acak');

        $this->assertNull(
            $this->panggilCariKodeLaborat('2026/02/20/000169', '(Idul Adha) Gula Darah Acak'),
        );
    }

    private function buatTabelSimrs(): void
    {
        Schema::connection('simrs')->create('billing', function (Blueprint $table) {
            $table->string('no_rawat');
            $table->string('nm_perawatan');
        });

        Schema::connection('simrs')->create('jns_perawatan_lab', function (Blueprint $table) {
            $table->string('kd_jenis_prw')->primary();
            $table->string('nm_perawatan');
        });

        Schema::connection('simrs')->create('periksa_lab', function (Blueprint $table) {
            $table->string('no_rawat');
            $table->string('kd_jenis_prw');
        });

        Schema::connection('simrs')->create('ranap_gabung', function (Blueprint $table) {
            $table->string('no_rawat');
            $table->string('no_rawat2');
        });
    }

    private function simpanJenisPerawatan(string $kode, string $namaPerawatan): void
    {
        DB::connection('simrs')->table('jns_perawatan_lab')->insert([
            'kd_jenis_prw' => $kode,
            'nm_perawatan' => $namaPerawatan,
        ]);
    }

    private function simpanBilling(string $noRawat, string $namaPerawatan): void
    {
        DB::connection('simrs')->table('billing')->insert([
            'no_rawat' => $noRawat,
            'nm_perawatan' => $namaPerawatan,
        ]);
    }

    private function panggilCariKodeLaborat(string $noRawat, string $namaPerawatan): ?string
    {
        return $this->cariKodeLaborat->invoke($this->service, $noRawat, $namaPerawatan);
    }
}
