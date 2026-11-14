<?php

namespace Tests\Feature;

use App\Enums\IndikatorKinerja\PeranCoa;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\IndikatorKinerjaVariabelManual;
use Database\Seeders\IndikatorKinerjaSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndikatorKinerjaSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => [
                'database/migrations/2026_11_13_000001_create_indikator_kinerja_variabel_manual_table.php',
                'database/migrations/2026_11_13_000002_create_indikator_kinerja_table.php',
                'database/migrations/2026_11_13_000003_create_indikator_kinerja_mapping_coa_table.php',
                'database/migrations/2026_11_13_000004_create_indikator_kinerja_manual_data_table.php',
            ],
            '--realpath' => false,
        ];
    }

    public function test_seeder_populates_11_baseline_indicators_and_manual_variables(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);

        $this->assertEquals(2, IndikatorKinerjaVariabelManual::count());
        $this->assertEquals(11, IndikatorKinerja::count());

        $expectedCodes = [
            'capaian_pendapatan',
            'ebitda',
            'profitabilitas',
            'beban_pegawai',
            'beban_farmasi',
            'beban_operasional',
            'collection_period',
            'kualitas_klaim_jkn',
            'perputaran_persediaan',
            'beban_dana_sosial',
            'produktifitas_karyawan',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('indikator_kinerja', ['kode' => $code]);
        }

        $bahv = IndikatorKinerjaVariabelManual::where('kode', 'bahv_jkn')->first();
        $this->assertNotNull($bahv);
        $sdm = IndikatorKinerjaVariabelManual::where('kode', 'sdm_non_organik')->first();
        $this->assertNotNull($sdm);

        $klaim = IndikatorKinerja::where('kode', 'kualitas_klaim_jkn')->first();
        $this->assertEquals($bahv->id, $klaim->variabel_manual_id);

        $prod = IndikatorKinerja::where('kode', 'produktifitas_karyawan')->first();
        $this->assertEquals($sdm->id, $prod->variabel_manual_id);
    }

    public function test_seeder_maps_coa_when_coa_table_and_records_exist(): void
    {
        if (! Schema::hasTable('coa')) {
            Schema::create('coa', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('kode');
                $table->string('nama');
                $table->string('tipe_coa')->nullable();
                $table->timestamps();
            });
        }

        $coaCodes = [
            '4' => 'Pendapatan Operasional',
            '706' => 'Biaya Penyusutan',
            '909.03' => 'Biaya Penghapusan Piutang',
            '6' => 'Biaya SDI',
            '505' => 'Biaya Farmasi',
            '405' => 'Pendapatan Instalasi Farmasi',
            '5' => 'Biaya Operasional',
            '112' => 'Piutang Pelayanan',
            '113' => 'Persediaan',
            '113.04' => 'Persediaan Gizi',
            '905' => 'Biaya Dakwah',
        ];

        foreach ($coaCodes as $code => $nama) {
            Coa::create(['kode' => $code, 'nama' => $nama]);
        }

        $this->seed(IndikatorKinerjaSeeder::class);

        $persediaan = IndikatorKinerja::where('kode', 'perputaran_persediaan')->first();
        $this->assertNotNull($persediaan);

        $mappings = IndikatorKinerjaMappingCoa::where('indikator_id', $persediaan->id)->get();
        $this->assertCount(3, $mappings);

        $coa113 = Coa::firstWhere('kode', '113');
        $coa11304 = Coa::firstWhere('kode', '113.04');
        $coa4 = Coa::firstWhere('kode', '4');

        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $persediaan->id,
            'coa_id' => $coa113->id,
            'peran' => PeranCoa::NUMERATOR->value,
            'is_excluded' => false,
        ]);

        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $persediaan->id,
            'coa_id' => $coa11304->id,
            'peran' => PeranCoa::NUMERATOR->value,
            'is_excluded' => true,
        ]);

        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $persediaan->id,
            'coa_id' => $coa4->id,
            'peran' => PeranCoa::DENOMINATOR->value,
            'is_excluded' => false,
        ]);

        // EBITDA mappings
        $ebitda = IndikatorKinerja::where('kode', 'ebitda')->first();
        $coa706 = Coa::firstWhere('kode', '706');
        $coa90903 = Coa::firstWhere('kode', '909.03');

        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $ebitda->id,
            'coa_id' => $coa706->id,
            'peran' => PeranCoa::ADDITION->value,
            'is_excluded' => false,
        ]);

        $this->assertDatabaseHas('indikator_kinerja_mapping_coa', [
            'indikator_id' => $ebitda->id,
            'coa_id' => $coa90903->id,
            'peran' => PeranCoa::ADDITION->value,
            'is_excluded' => false,
        ]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(IndikatorKinerjaSeeder::class);
        $this->seed(IndikatorKinerjaSeeder::class);

        $this->assertEquals(2, IndikatorKinerjaVariabelManual::count());
        $this->assertEquals(11, IndikatorKinerja::count());
    }
}
