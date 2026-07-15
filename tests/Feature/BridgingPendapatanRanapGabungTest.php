<?php

namespace Tests\Feature;

use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class BridgingPendapatanRanapGabungTest extends TestCase
{
    private BridgingPendapatanService $service;

    private ReflectionMethod $cariKodeRanap;

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

        $this->cariKodeRanap = new ReflectionMethod(BridgingPendapatanService::class, 'cariKodeRanap');
        $this->cariKodeRanap->setAccessible(true);
    }

    public function test_pencarian_langsung_diprioritaskan_tanpa_mengakses_ranap_gabung(): void
    {
        $this->simpanJenisPerawatan('RANAP-LANGSUNG', 'Visite Spesialis');
        $this->simpanBilling('2026/03/31/000050', 'Visite Spesialis');
        DB::connection('simrs')->table('rawat_inap_drpr')->insert([
            'no_rawat' => '2026/03/31/000050',
            'kd_jenis_prw' => 'RANAP-LANGSUNG',
        ]);

        Schema::connection('simrs')->drop('ranap_gabung');

        $this->assertSame(
            'RANAP-LANGSUNG',
            $this->panggilCariKodeRanap('Ranap Dokter Paramedis', '2026/03/31/000050', 'Visite Spesialis'),
        );
    }

    #[DataProvider('statusRanapProvider')]
    public function test_fallback_ranap_gabung_menemukan_kode_tindakan(
        string $status,
        string $namaTabel,
    ): void {
        $this->simpanJenisPerawatan('RANAP647', 'Visite Spesialis');
        $this->simpanBilling('2026/03/31/000050', 'Visite Spesialis');

        DB::connection('simrs')->table('ranap_gabung')->insert([
            'no_rawat' => '2026/03/31/000050',
            'no_rawat2' => '2026/03/31/000064',
        ]);
        DB::connection('simrs')->table($namaTabel)->insert([
            'no_rawat' => '2026/03/31/000064',
            'kd_jenis_prw' => 'RANAP647',
        ]);

        $this->assertSame(
            'RANAP647',
            $this->panggilCariKodeRanap($status, '2026/03/31/000050', 'Visite Spesialis'),
        );
    }

    public function test_mengembalikan_null_jika_tindakan_langsung_dan_gabungan_tidak_ditemukan(): void
    {
        $this->simpanJenisPerawatan('RANAP647', 'Visite Spesialis');
        $this->simpanBilling('2026/03/31/000050', 'Visite Spesialis');

        $this->assertNull(
            $this->panggilCariKodeRanap('Ranap Dokter Paramedis', '2026/03/31/000050', 'Visite Spesialis'),
        );
    }

    public static function statusRanapProvider(): array
    {
        return [
            'ranap dokter' => ['Ranap Dokter', 'rawat_inap_dr'],
            'ranap paramedis' => ['Ranap Paramedis', 'rawat_inap_pr'],
            'ranap dokter paramedis' => ['Ranap Dokter Paramedis', 'rawat_inap_drpr'],
        ];
    }

    private function buatTabelSimrs(): void
    {
        Schema::connection('simrs')->create('billing', function (Blueprint $table) {
            $table->string('no_rawat');
            $table->string('nm_perawatan');
        });

        Schema::connection('simrs')->create('jns_perawatan_inap', function (Blueprint $table) {
            $table->string('kd_jenis_prw')->primary();
            $table->string('nm_perawatan');
        });

        Schema::connection('simrs')->create('ranap_gabung', function (Blueprint $table) {
            $table->string('no_rawat');
            $table->string('no_rawat2');
        });

        foreach (['rawat_inap_dr', 'rawat_inap_pr', 'rawat_inap_drpr'] as $namaTabel) {
            Schema::connection('simrs')->create($namaTabel, function (Blueprint $table) {
                $table->string('no_rawat');
                $table->string('kd_jenis_prw');
            });
        }
    }

    private function simpanJenisPerawatan(string $kode, string $namaPerawatan): void
    {
        DB::connection('simrs')->table('jns_perawatan_inap')->insert([
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

    private function panggilCariKodeRanap(
        string $status,
        string $noRawat,
        string $namaPerawatan,
    ): ?string {
        return $this->cariKodeRanap->invoke($this->service, $status, $noRawat, $namaPerawatan);
    }
}
