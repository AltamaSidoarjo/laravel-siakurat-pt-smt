<?php

namespace Tests\Unit;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\StatusEvaluasi;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaManualData;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\IndikatorKinerjaVariabelManual;
use App\Services\IndikatorKinerja\IndikatorKinerjaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndikatorKinerjaServiceTest extends TestCase
{
    use RefreshDatabase;

    private IndikatorKinerjaService $service;

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

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('coa')) {
            Schema::create('coa', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('kode');
                $table->string('nama');
                $table->unsignedInteger('parent_coa')->nullable();
                $table->string('tipe_coa')->nullable();
                $table->integer('status_aktif')->default(1);
                $table->boolean('is_postable')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bukubesar')) {
            Schema::create('bukubesar', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('coa_id');
                $table->unsignedInteger('sumber_id')->nullable();
                $table->date('tanggal');
                $table->integer('periode_tahun')->nullable();
                $table->integer('periode_bulan')->nullable();
                $table->string('nomer')->nullable();
                $table->string('sumber_transaksi')->nullable();
                $table->decimal('nominal', 15, 2)->default(0);
                $table->string('tipe_mutasi', 2);
                $table->string('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('setting_rba')) {
            Schema::create('setting_rba', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('coa_id');
                $table->integer('tahun');
                $table->decimal('total_nominal', 15, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->boolean('is_rinci')->default(false);
                $table->timestamps();
            });
        }

        $this->service = app(IndikatorKinerjaService::class);
        $this->service->clearCoaTreeCache();
    }

    public function test_safe_divide_returns_null_on_zero_or_null_denominator(): void
    {
        $this->assertNull($this->service->safeDivide(100, 0));
        $this->assertNull($this->service->safeDivide(100, null));
        $this->assertNull($this->service->safeDivide(null, 10));
        $this->assertEquals(50.0, $this->service->safeDivide(100, 2));
    }

    public function test_evaluasi_status_higher_is_better(): void
    {
        $indikator = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.0,
            'target_kuning_min' => 90.0,
        ]);

        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(105.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(95.0, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(85.0, $indikator));
        $this->assertEquals(StatusEvaluasi::NETRAL, $this->service->evaluasiStatus(null, $indikator));
    }

    public function test_evaluasi_status_lower_is_better(): void
    {
        $indikator = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
            'target_hijau_max' => 40.0,
            'target_kuning_max' => 60.0,
        ]);

        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(35.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(50.0, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(75.0, $indikator));
        $this->assertEquals(StatusEvaluasi::NETRAL, $this->service->evaluasiStatus(null, $indikator));
    }

    public function test_evaluasi_status_target_band_with_fallback(): void
    {
        $indikator = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::TARGET_BAND,
            'target_hijau_min' => 20.0,
            'target_hijau_max' => 25.0,
            'target_kuning_min' => 25.0,
            'target_kuning_max' => 30.0,
        ]);

        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(22.0, $indikator));
        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(25.0, $indikator));
        // Fractional rounding & boundary check
        $this->assertEquals(StatusEvaluasi::HIJAU, $this->service->evaluasiStatus(25.004, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(25.006, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(25.01, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(28.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(30.0, $indikator));
        $this->assertEquals(StatusEvaluasi::KUNING, $this->service->evaluasiStatus(30.004, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(30.006, $indikator));
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(35.0, $indikator)); // >30
        $this->assertEquals(StatusEvaluasi::MERAH, $this->service->evaluasiStatus(15.0, $indikator)); // <20

        $indikatorKosong = new IndikatorKinerja([
            'pola_evaluasi' => PolaEvaluasi::TARGET_BAND,
            'target_hijau_min' => null,
            'target_hijau_max' => null,
            'target_kuning_min' => null,
            'target_kuning_max' => null,
        ]);
        $this->assertEquals(StatusEvaluasi::NETRAL, $this->service->evaluasiStatus(22.0, $indikatorKosong));
    }

    public function test_tren_bulanan_returns_null_for_future_months_in_current_year(): void
    {
        $indikator = IndikatorKinerja::create([
            'kode' => 'capaian_pendapatan',
            'nama' => 'Capaian Pendapatan',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::RBA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.0,
        ]);

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $tren = $this->service->getTrenBulanan('capaian_pendapatan', $currentYear);

        $this->assertArrayHasKey('indikator', $tren);
        $this->assertArrayHasKey('data', $tren);
        $this->assertCount(12, $tren['data']);

        if ($currentMonth < 12) {
            $futureMonth = $currentMonth + 1;
            $entry = collect($tren['data'])->firstWhere('bulan', $futureMonth);
            $this->assertNotNull($entry);
            $this->assertNull($entry['nilai']);
            $this->assertEquals(StatusEvaluasi::NETRAL, $entry['status']);
        }

        // Test future year returns all nulls without querying
        $futureYear = $currentYear + 1;
        $trenFuture = $this->service->getTrenBulanan('capaian_pendapatan', $futureYear);
        $this->assertCount(12, $trenFuture['data']);
        foreach ($trenFuture['data'] as $monthData) {
            $this->assertNull($monthData['nilai']);
            $this->assertEquals(StatusEvaluasi::NETRAL, $monthData['status']);
        }
    }

    public function test_kumpulkan_leaf_descendant_coa_ids_excludes_excluded_accounts(): void
    {
        // Root: 113 (Persediaan)
        $parent = Coa::create([
            'kode' => '113',
            'nama' => 'Persediaan',
            'tipe_coa' => 'Persediaan',
            'status_aktif' => 1,
            'is_postable' => 0,
        ]);

        // Leaf 1: 113.01
        $leaf1 = Coa::create([
            'kode' => '113.01',
            'nama' => 'Persediaan Farmasi',
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Persediaan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Branch: 113.04 (Excluded)
        $branchExcluded = Coa::create([
            'kode' => '113.04',
            'nama' => 'Persediaan RT',
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Persediaan',
            'status_aktif' => 1,
            'is_postable' => 0,
        ]);

        // Leaf under excluded branch
        $leafUnderExcluded = Coa::create([
            'kode' => '113.04.01',
            'nama' => 'Persediaan Dapur',
            'parent_coa' => $branchExcluded->id,
            'tipe_coa' => 'Persediaan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $result = $this->service->kumpulkanLeafDescendantCoaIds(
            [$parent->id],
            [$branchExcluded->id]
        );

        $this->assertContains($leaf1->id, $result);
        $this->assertNotContains($branchExcluded->id, $result);
        $this->assertNotContains($leafUnderExcluded->id, $result);
    }

    public function test_kumpulkan_leaf_descendant_coa_ids_handles_cycles_gracefully(): void
    {
        $coaA = Coa::create([
            'kode' => '998',
            'nama' => 'Node A',
            'tipe_coa' => 'Aktiva Lainnya',
            'status_aktif' => 1,
            'is_postable' => 0,
        ]);

        $coaB = Coa::create([
            'kode' => '999',
            'nama' => 'Node B',
            'parent_coa' => $coaA->id,
            'tipe_coa' => 'Aktiva Lainnya',
            'status_aktif' => 1,
            'is_postable' => 0,
        ]);

        // Create artificial cycle: A's parent is B
        $coaA->update(['parent_coa' => $coaB->id]);
        $this->service->clearCoaTreeCache();

        $result = $this->service->kumpulkanLeafDescendantCoaIds([$coaA->id]);
        $this->assertIsArray($result);
    }

    public function test_cached_coa_tree_memoization(): void
    {
        $this->service->clearCoaTreeCache();
        $tree1 = $this->service->getCachedCoaTree();
        $tree2 = $this->service->getCachedCoaTree();
        $this->assertSame($tree1, $tree2);
    }

    public function test_turnover_calculation_with_cumulative_balance(): void
    {
        $coaPiutang = Coa::create([
            'kode' => '112.01',
            'nama' => 'Piutang Pasien',
            'tipe_coa' => 'Piutang Usaha',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaPendapatan = Coa::create([
            'kode' => '401.01',
            'nama' => 'Pendapatan Rawat Inap',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Piutang transaction before or on end date (Cumulative saldo = 100.000.000)
        BukuBesar::create([
            'coa_id' => $coaPiutang->id,
            'tanggal' => '2026-01-15',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Jurnal Umum',
            'sumber_id' => 1,
            'tipe_mutasi' => 'D',
            'nominal' => 100000000,
        ]);

        // Pendapatan transaction in 2026-01 (Mutasi = 300.000.000)
        BukuBesar::create([
            'coa_id' => $coaPendapatan->id,
            'tanggal' => '2026-01-20',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'sumber_id' => 1,
            'tipe_mutasi' => 'K',
            'nominal' => 300000000,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'collection_period',
            'nama' => 'Collection Period',
            'satuan' => SatuanIndikator::HARI,
            'tipe_kalkulasi' => TipeKalkulasi::TURNOVER,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
            'target_hijau_max' => 40.0,
            'target_kuning_max' => 60.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPiutang->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPendapatan->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        // Evaluasi Jan 1 to Jan 31 (31 days): (100.000.000 / 300.000.000) * 31 = 10.33 Hari
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
        $this->assertEquals(10.33, $eval['nilai']);
        $this->assertEquals(100000000.0, $eval['pembilang']);
        $this->assertEquals(300000000.0, $eval['penyebut']);
    }

    public function test_scaling_produktivitas_karyawan_divides_by_one_million(): void
    {
        $coaPendapatan = Coa::create([
            'kode' => '401.01',
            'nama' => 'Pendapatan Layanan',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // 2.200.000.000 pendapatan in Jan 2026
        BukuBesar::create([
            'coa_id' => $coaPendapatan->id,
            'tanggal' => '2026-01-20',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'sumber_id' => 1,
            'tipe_mutasi' => 'K',
            'nominal' => 2200000000,
        ]);

        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'sdm_non_organik',
            'nama' => 'SDM Non Organik',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        IndikatorKinerjaManualData::create([
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 1,
            'nilai' => 100.0, // 100 orang
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'produktifitas_karyawan',
            'nama' => 'Produktifitas Karyawan',
            'satuan' => SatuanIndikator::JUTA_PER_ORG_BLN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::MANUAL,
            'variabel_manual_id' => $variabel->id,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 22.0,
            'target_kuning_min' => 15.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPendapatan->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        // Evaluasi Jan 1 to Jan 31 (1 month):
        // 2.200.000.000 / 1 month / 100 org = 22.000.000 Rp/org/bln -> 22.00 Juta/org/bln
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(22.0, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
    }

    public function test_ebitda_adds_depreciation_to_surplus(): void
    {
        $coaPendapatan = Coa::create([
            'kode' => '401',
            'nama' => 'Pendapatan',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaBeban = Coa::create([
            'kode' => '501',
            'nama' => 'Beban Umum',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaPenyusutan = Coa::create([
            'kode' => '706',
            'nama' => 'Beban Penyusutan',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Pendapatan = 1.000.000.000
        BukuBesar::create([
            'coa_id' => $coaPendapatan->id,
            'tanggal' => '2026-01-10',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Invoice Pendapatan',
            'sumber_id' => 1,
            'tipe_mutasi' => 'K',
            'nominal' => 1000000000,
        ]);

        // Beban Umum = 800.000.000
        BukuBesar::create([
            'coa_id' => $coaBeban->id,
            'tanggal' => '2026-01-15',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Jurnal Umum',
            'sumber_id' => 1,
            'tipe_mutasi' => 'D',
            'nominal' => 800000000,
        ]);

        // Beban Penyusutan = 50.000.000
        BukuBesar::create([
            'coa_id' => $coaPenyusutan->id,
            'tanggal' => '2026-01-20',
            'periode_tahun' => 2026,
            'periode_bulan' => 1,
            'sumber_transaksi' => 'Jurnal Umum',
            'sumber_id' => 2,
            'tipe_mutasi' => 'D',
            'nominal' => 50000000,
        ]);

        // Surplus = 1.000.000.000 - (800.000.000 + 50.000.000) = 150.000.000
        // EBITDA = Surplus (150.000.000) + Addition Penyusutan (50.000.000) = 200.000.000
        // Rasio EBITDA terhadap Pendapatan (COA 4) = 200.000.000 / 1.000.000.000 * 100 = 20.0%

        $indikator = IndikatorKinerja::create([
            'kode' => 'ebitda',
            'nama' => 'EBITDA',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::SURPLUS,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 22.0,
            'target_kuning_min' => 15.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPenyusutan->id,
            'peran' => PeranCoa::ADDITION,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPendapatan->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(20.0, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::KUNING, $eval['status']);
        $this->assertEquals(200000000.0, $eval['pembilang']);
        $this->assertEquals(1000000000.0, $eval['penyebut']);
    }

    public function test_manual_indicator_fallback_to_latest_available(): void
    {
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'bahv_jkn',
            'nama' => 'Kualitas Klaim JKN',
            'satuan' => SatuanIndikator::PERSEN,
        ]);

        // Input data for 2026-01
        IndikatorKinerjaManualData::create([
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 1,
            'nilai' => 93.5,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'kualitas_klaim_jkn',
            'nama' => 'Kualitas Klaim JKN',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::MANUAL,
            'basis_pembilang' => BasisPembilang::NONE,
            'basis_penyebut' => BasisPenyebut::NONE,
            'variabel_manual_id' => $variabel->id,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 90.0,
            'target_kuning_min' => 85.0,
            'status_aktif' => true,
        ]);

        // Query for 2026-03 (data in 2026-03 is not entered yet, should fallback to 2026-01)
        $eval = $this->service->evaluasiIndikator($indikator, '2026-03-01', '2026-03-31');

        $this->assertEquals(93.5, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
        $this->assertStringContainsString('Jan 2026', (string) $eval['catatan']);
    }

    public function test_get_ringkasan_status(): void
    {
        IndikatorKinerja::create([
            'kode' => 'ind_hijau',
            'nama' => 'Indikator Hijau',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::MANUAL,
            'basis_pembilang' => BasisPembilang::NONE,
            'basis_penyebut' => BasisPenyebut::NONE,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 80.0,
            'status_aktif' => true,
        ]);

        $summary = $this->service->getRingkasanStatus('2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('hijau', $summary);
        $this->assertArrayHasKey('kuning', $summary);
        $this->assertArrayHasKey('merah', $summary);
        $this->assertArrayHasKey('netral', $summary);
        $this->assertEquals(1, $summary['total']);
        $this->assertEquals(1, $summary['netral']); // no data -> netral
    }

    public function test_current_ratio_calculation_with_balance_sheet_accounts(): void
    {
        $coaAsetLancar = Coa::create([
            'kode' => '11',
            'nama' => 'Aset Lancar',
            'tipe_coa' => 'Aset Lancar lainnya',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaKewajibanLancar = Coa::create([
            'kode' => '21',
            'nama' => 'Kewajiban Lancar',
            'tipe_coa' => 'Liabilitas Jangka Pendek',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Aset Lancar: 50jt in 2025 (prior period) + 100jt in Jan 2026 = 150jt ending balance
        BukuBesar::create([
            'coa_id' => $coaAsetLancar->id,
            'tanggal' => '2025-12-31',
            'tipe_mutasi' => 'D',
            'nominal' => 50000000,
        ]);
        BukuBesar::create([
            'coa_id' => $coaAsetLancar->id,
            'tanggal' => '2026-01-15',
            'tipe_mutasi' => 'D',
            'nominal' => 100000000,
        ]);

        // Kewajiban Lancar: 30jt in 2025 + 70jt in Jan 2026 = 100jt ending balance (Kredit normal)
        BukuBesar::create([
            'coa_id' => $coaKewajibanLancar->id,
            'tanggal' => '2025-12-31',
            'tipe_mutasi' => 'K',
            'nominal' => 30000000,
        ]);
        BukuBesar::create([
            'coa_id' => $coaKewajibanLancar->id,
            'tanggal' => '2026-01-20',
            'tipe_mutasi' => 'K',
            'nominal' => 70000000,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'current_ratio',
            'nama' => 'Current Ratio',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 150.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaAsetLancar->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaKewajibanLancar->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        // Evaluasi Jan 1 to Jan 31: 150jt / 100jt * 100% = 150.0%
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(150000000.0, $eval['pembilang']);
        $this->assertEquals(100000000.0, $eval['penyebut']);
        $this->assertEquals(150.0, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
    }

    public function test_roe_calculation_with_surplus_and_equity_balance(): void
    {
        $coaPendapatan = Coa::create([
            'kode' => '400',
            'nama' => 'Pendapatan Usaha',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaBeban = Coa::create([
            'kode' => '500',
            'nama' => 'Beban Operasional',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaEkuitas = Coa::create([
            'kode' => '300',
            'nama' => 'Modal Sendiri',
            'tipe_coa' => 'Ekuitas',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Surplus Jan 2026 = 500jt - 400jt = 100jt
        BukuBesar::create([
            'coa_id' => $coaPendapatan->id,
            'tanggal' => '2026-01-10',
            'tipe_mutasi' => 'K',
            'nominal' => 500000000,
        ]);
        BukuBesar::create([
            'coa_id' => $coaBeban->id,
            'tanggal' => '2026-01-15',
            'tipe_mutasi' => 'D',
            'nominal' => 400000000,
        ]);

        // Ekuitas = 1.000.000.000 (posted in 2025, no mutation in Jan 2026)
        BukuBesar::create([
            'coa_id' => $coaEkuitas->id,
            'tanggal' => '2025-12-01',
            'tipe_mutasi' => 'K',
            'nominal' => 1000000000,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'roe',
            'nama' => 'ROE',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::SURPLUS,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 10.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaEkuitas->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        // Surplus (100jt) / Ekuitas (1M) * 100% = 10.0%
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(100000000.0, $eval['pembilang']);
        $this->assertEquals(1000000000.0, $eval['penyebut']);
        $this->assertEquals(10.0, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
    }

    public function test_capital_employed_denominator_summation(): void
    {
        $coaPendapatan = Coa::create([
            'kode' => '400',
            'nama' => 'Pendapatan Usaha',
            'tipe_coa' => 'Pendapatan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaEkuitas = Coa::create([
            'kode' => '300',
            'nama' => 'Modal Sendiri',
            'tipe_coa' => 'Ekuitas',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaHutangJangkaPanjang = Coa::create([
            'kode' => '220',
            'nama' => 'Hutang Jangka Panjang',
            'tipe_coa' => 'Hutang Jangka Panjang',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        // Pendapatan in Jan 2026 = 200.000.000
        BukuBesar::create([
            'coa_id' => $coaPendapatan->id,
            'tanggal' => '2026-01-15',
            'tipe_mutasi' => 'K',
            'nominal' => 200000000,
        ]);

        // Modal = 600.000.000, Hutang JP = 400.000.000 -> Capital Employed = 1.000.000.000
        BukuBesar::create([
            'coa_id' => $coaEkuitas->id,
            'tanggal' => '2025-10-01',
            'tipe_mutasi' => 'K',
            'nominal' => 600000000,
        ]);
        BukuBesar::create([
            'coa_id' => $coaHutangJangkaPanjang->id,
            'tanggal' => '2025-11-01',
            'tipe_mutasi' => 'K',
            'nominal' => 400000000,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'tato',
            'nama' => 'TATO',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 20.0,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaPendapatan->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaEkuitas->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaHutangJangkaPanjang->id,
            'peran' => PeranCoa::DENOMINATOR,
            'is_excluded' => false,
        ]);

        // Pendapatan (200jt) / Capital Employed (1M) * 100% = 20.0%
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(200000000.0, $eval['pembilang']);
        $this->assertEquals(1000000000.0, $eval['penyebut']);
        $this->assertEquals(20.0, $eval['nilai']);
        $this->assertEquals(StatusEvaluasi::HIJAU, $eval['status']);
    }

    public function test_contra_asset_reduces_total_asset_balance(): void
    {
        $coaAsetTetap = Coa::create([
            'kode' => '12',
            'nama' => 'Aset Tetap',
            'tipe_coa' => 'Aset Tetap',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        $coaAkumPenyusutan = Coa::create([
            'kode' => '129',
            'nama' => 'Akumulasi Penyusutan',
            'tipe_coa' => 'Akumulasi Penyusutan',
            'status_aktif' => 1,
            'is_postable' => 1,
        ]);

        BukuBesar::create([
            'coa_id' => $coaAsetTetap->id,
            'tanggal' => '2025-05-01',
            'tipe_mutasi' => 'D',
            'nominal' => 100000000,
        ]);

        BukuBesar::create([
            'coa_id' => $coaAkumPenyusutan->id,
            'tanggal' => '2025-12-31',
            'tipe_mutasi' => 'K',
            'nominal' => 20000000,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'net_asset',
            'nama' => 'Net Asset',
            'satuan' => SatuanIndikator::RUPIAH,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::NONE,
            'status_aktif' => true,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaAsetTetap->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coaAkumPenyusutan->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        // Net Asset = 100jt (Debet) - 20jt (Kredit) = 80jt
        $eval = $this->service->evaluasiIndikator($indikator, '2026-01-01', '2026-01-31');

        $this->assertEquals(80000000.0, $eval['pembilang']);
    }
}
