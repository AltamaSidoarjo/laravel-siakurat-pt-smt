<?php

namespace Tests\Unit;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\StatusEvaluasi;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaManualData;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\IndikatorKinerjaVariabelManual;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndikatorKinerjaModelTest extends TestCase
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

    public function test_can_create_indikator_with_enum_casts(): void
    {
        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'sdm_non_organik',
            'nama' => 'Jumlah SDM Non Organik',
            'satuan' => SatuanIndikator::ORANG,
        ]);

        $indikator = IndikatorKinerja::create([
            'kode' => 'capaian_pendapatan',
            'nama' => 'Capaian Pendapatan',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::RBA,
            'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
            'target_hijau_min' => 100.00,
            'target_hijau_max' => null,
            'target_kuning_min' => 90.00,
            'target_kuning_max' => 100.00,
            'target_merah_max' => 90.00,
            'status_aktif' => true,
        ]);

        $this->assertInstanceOf(SatuanIndikator::class, $variabel->satuan);
        $this->assertEquals(SatuanIndikator::ORANG, $variabel->satuan);
        $this->assertInstanceOf(SatuanIndikator::class, $indikator->satuan);
        $this->assertEquals(SatuanIndikator::PERSEN, $indikator->satuan);
        $this->assertInstanceOf(TipeKalkulasi::class, $indikator->tipe_kalkulasi);
        $this->assertInstanceOf(BasisPembilang::class, $indikator->basis_pembilang);
        $this->assertInstanceOf(BasisPenyebut::class, $indikator->basis_penyebut);
        $this->assertInstanceOf(PolaEvaluasi::class, $indikator->pola_evaluasi);
        $this->assertTrue($indikator->status_aktif);
        $this->assertEquals('100.00', $indikator->target_hijau_min);
        $this->assertEquals('90.00', $indikator->target_kuning_min);
    }

    public function test_can_create_mapping_coa_and_manual_data_with_casts_and_relationships(): void
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

        $coa = Coa::create([
            'kode' => '112',
            'nama' => 'Piutang Pelayanan',
            'tipe_coa' => 'Piutang',
        ]);

        $variabel = IndikatorKinerjaVariabelManual::create([
            'kode' => 'bahv_jkn',
            'nama' => 'Persentase Lolos BAHV Pertama JKN',
            'satuan' => SatuanIndikator::PERSEN,
        ]);

        $manualData = IndikatorKinerjaManualData::create([
            'variabel_id' => $variabel->id,
            'tahun' => 2026,
            'bulan' => 5,
            'nilai' => 92.50,
            'catatan' => 'Verifikasi Mei',
        ]);

        $this->assertEquals(2026, $manualData->tahun);
        $this->assertEquals(5, $manualData->bulan);
        $this->assertEquals('92.50', $manualData->nilai);
        $this->assertEquals($variabel->id, $manualData->variabel->id);
        $this->assertCount(1, $variabel->manualData);

        $indikator = IndikatorKinerja::create([
            'kode' => 'collection_period',
            'nama' => 'Collection Period',
            'satuan' => SatuanIndikator::HARI,
            'tipe_kalkulasi' => TipeKalkulasi::TURNOVER,
            'basis_pembilang' => BasisPembilang::COA,
            'basis_penyebut' => BasisPenyebut::COA,
            'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
            'status_aktif' => true,
        ]);

        $mapping = IndikatorKinerjaMappingCoa::create([
            'indikator_id' => $indikator->id,
            'coa_id' => $coa->id,
            'peran' => PeranCoa::NUMERATOR,
            'is_excluded' => false,
        ]);

        $this->assertInstanceOf(PeranCoa::class, $mapping->peran);
        $this->assertEquals(PeranCoa::NUMERATOR, $mapping->peran);
        $this->assertFalse($mapping->is_excluded);
        $this->assertEquals($indikator->id, $mapping->indikator->id);
        $this->assertEquals($coa->id, $mapping->coa->id);
        $this->assertCount(1, $indikator->mappingCoa);
    }

    public function test_scope_aktif_and_ordering(): void
    {
        IndikatorKinerja::create([
            'kode' => 'ind_2',
            'nama' => 'Indikator Dua',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'urutan' => 2,
            'status_aktif' => true,
        ]);

        IndikatorKinerja::create([
            'kode' => 'ind_1',
            'nama' => 'Indikator Satu',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'urutan' => 1,
            'status_aktif' => true,
        ]);

        IndikatorKinerja::create([
            'kode' => 'ind_inactive',
            'nama' => 'Indikator Nonaktif',
            'satuan' => SatuanIndikator::PERSEN,
            'tipe_kalkulasi' => TipeKalkulasi::RASIO,
            'urutan' => 0,
            'status_aktif' => false,
        ]);

        $active = IndikatorKinerja::query()->aktif()->get();

        $this->assertCount(2, $active);
        $this->assertEquals('ind_1', $active->first()->kode);
        $this->assertEquals('ind_2', $active->last()->kode);
    }

    public function test_enum_labels_and_helpers(): void
    {
        $this->assertEquals('Persentase (%)', SatuanIndikator::PERSEN->label());
        $this->assertEquals('Hari', SatuanIndikator::HARI->label());
        $this->assertEquals('Juta / Orang / Bulan', SatuanIndikator::JUTA_PER_ORG_BLN->label());

        $this->assertEquals('Rasio (Pembagian Standar)', TipeKalkulasi::RASIO->label());
        $this->assertEquals('Turnover (Perputaran Hari)', TipeKalkulasi::TURNOVER->label());
        $this->assertEquals('Manual (Entri Non-Akuntansi)', TipeKalkulasi::MANUAL->label());

        $this->assertEquals('Akun COA (Mutasi / Saldo Akumulatif)', BasisPembilang::COA->label());
        $this->assertEquals('Target RBA (Rencana Bisnis dan Anggaran)', BasisPenyebut::RBA->label());

        $this->assertEquals('Higher is Better (Makin Tinggi Makin Baik)', PolaEvaluasi::HIGHER_IS_BETTER->label());
        $this->assertEquals('Target Band (Rentang Target Optimal)', PolaEvaluasi::TARGET_BAND->label());

        $this->assertEquals('Numerator (Pembilang)', PeranCoa::NUMERATOR->label());
        $this->assertEquals('Addition (Penambah / Add-back)', PeranCoa::ADDITION->label());

        $this->assertEquals('Hijau (Baik / Efisien)', StatusEvaluasi::HIJAU->label());
        $this->assertEquals('success', StatusEvaluasi::HIJAU->badgeClass());
        $this->assertEquals('warning', StatusEvaluasi::KUNING->badgeClass());
        $this->assertEquals('danger', StatusEvaluasi::MERAH->badgeClass());
        $this->assertEquals('secondary', StatusEvaluasi::NETRAL->badgeClass());
    }
}
