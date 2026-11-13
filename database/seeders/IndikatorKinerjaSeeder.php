<?php

namespace Database\Seeders;

use App\Enums\IndikatorKinerja\BasisPembilang;
use App\Enums\IndikatorKinerja\BasisPenyebut;
use App\Enums\IndikatorKinerja\PeranCoa;
use App\Enums\IndikatorKinerja\PolaEvaluasi;
use App\Enums\IndikatorKinerja\SatuanIndikator;
use App\Enums\IndikatorKinerja\TipeKalkulasi;
use App\Models\Coa;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKinerjaMappingCoa;
use App\Models\IndikatorKinerjaVariabelManual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class IndikatorKinerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Variabel Manual Non-Akuntansi
        $manualVariables = [
            'bahv_jkn' => [
                'nama' => 'Persentase Lolos BAHV Pertama JKN',
                'satuan' => SatuanIndikator::PERSEN,
                'deskripsi' => 'Persentase berkas klaim BPJS Kesehatan yang disetujui pada Berita Acara Hasil Verifikasi pertama tanpa revisi / pending.',
            ],
            'sdm_non_organik' => [
                'nama' => 'Jumlah SDM Non Organik',
                'satuan' => SatuanIndikator::ORANG,
                'deskripsi' => 'Jumlah tenaga kerja / karyawan non-organik (kontrak, outsourcing, atau mitra) aktif pada periode pelaporan.',
            ],
        ];

        $persistedVariables = [];
        foreach ($manualVariables as $kode => $attributes) {
            $persistedVariables[$kode] = IndikatorKinerjaVariabelManual::updateOrCreate(
                ['kode' => $kode],
                $attributes
            );
        }

        // 2. 11 Indikator Baseline Eksekutif
        $indicators = [
            [
                'kode' => 'capaian_pendapatan',
                'nama' => 'Capaian Pendapatan',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::RBA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 100.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 90.00,
                'target_kuning_max' => 99.99,
                'target_merah_min' => null,
                'target_merah_max' => 90.00,
                'definisi_operasional' => 'Persentase realisasi total pendapatan operasional rumah sakit terhadap target RBA (Rencana Bisnis dan Anggaran) proporsional pada periode berjalan.',
                'urutan' => 1,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '4', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'ebitda',
                'nama' => 'EBITDA',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::SURPLUS,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 22.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 15.00,
                'target_kuning_max' => 22.00,
                'target_merah_min' => null,
                'target_merah_max' => 15.00,
                'definisi_operasional' => 'Earning Before Interest, Taxes, Depreciation, and Amortization terhadap pendapatan operasional. Dihitung dari surplus laba bersih ditambah kembali biaya penyusutan dan penghapusan piutang.',
                'urutan' => 2,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '706', 'peran' => PeranCoa::ADDITION, 'is_excluded' => false],
                    ['kode' => '909.03', 'peran' => PeranCoa::ADDITION, 'is_excluded' => false],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'profitabilitas',
                'nama' => 'Profitabilitas',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::SURPLUS,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 15.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 10.00,
                'target_kuning_max' => 15.00,
                'target_merah_min' => null,
                'target_merah_max' => 10.00,
                'definisi_operasional' => 'Margin laba bersih (Net Profit Margin) operasional rumah sakit dibandingkan dengan total pendapatan operasional periode berjalan.',
                'urutan' => 3,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'beban_pegawai',
                'nama' => 'Beban Pegawai',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::TARGET_BAND,
                'target_hijau_min' => 20.00,
                'target_hijau_max' => 25.00,
                'target_kuning_min' => 25.00,
                'target_kuning_max' => 30.00,
                'target_merah_min' => null,
                'target_merah_max' => null,
                'definisi_operasional' => 'Rasio total biaya pegawai (SDI/SDM) terhadap total pendapatan operasional. Memiliki rentang target optimal 20% - 25% (peringatan 25% - 30%).',
                'urutan' => 4,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '6', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'beban_farmasi',
                'nama' => 'Beban Farmasi',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
                'target_hijau_min' => null,
                'target_hijau_max' => 70.00,
                'target_kuning_min' => 70.01,
                'target_kuning_max' => 75.00,
                'target_merah_min' => 75.01,
                'target_merah_max' => null,
                'definisi_operasional' => 'Rasio total belanja / beban obat dan perbekalan farmasi terhadap total pendapatan farmasi operasional periode berjalan.',
                'urutan' => 5,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '505', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '405', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'beban_operasional',
                'nama' => 'Beban Operasional',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
                'target_hijau_min' => null,
                'target_hijau_max' => 46.00,
                'target_kuning_min' => 46.01,
                'target_kuning_max' => 50.00,
                'target_merah_min' => 50.01,
                'target_merah_max' => null,
                'definisi_operasional' => 'Rasio total beban operasional umum dan administrasi rumah sakit terhadap total pendapatan operasional periode berjalan.',
                'urutan' => 6,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '5', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'collection_period',
                'nama' => 'Collection Period',
                'satuan' => SatuanIndikator::HARI,
                'tipe_kalkulasi' => TipeKalkulasi::TURNOVER,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
                'target_hijau_min' => null,
                'target_hijau_max' => 40.00,
                'target_kuning_min' => 40.01,
                'target_kuning_max' => 60.00,
                'target_merah_min' => 60.01,
                'target_merah_max' => null,
                'definisi_operasional' => 'Rata-rata jangka waktu (dalam hari) yang dibutuhkan rumah sakit untuk mencairkan saldo piutang pelayanan menjadi kas / bank.',
                'urutan' => 7,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '112', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'kualitas_klaim_jkn',
                'nama' => 'Kualitas Klaim JKN',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::MANUAL,
                'basis_pembilang' => BasisPembilang::NONE,
                'basis_penyebut' => BasisPenyebut::NONE,
                'variabel_manual_id' => $persistedVariables['bahv_jkn']->id,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 90.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 85.00,
                'target_kuning_max' => 90.00,
                'target_merah_min' => null,
                'target_merah_max' => 85.00,
                'definisi_operasional' => 'Persentase berkas klaim BPJS Kesehatan yang berhasil lolos pada Berita Acara Hasil Verifikasi (BAHV) pertama tanpa dispute atau revisi pending.',
                'urutan' => 8,
                'status_aktif' => true,
                'mappings' => [],
            ],
            [
                'kode' => 'perputaran_persediaan',
                'nama' => 'Perputaran Persediaan',
                'satuan' => SatuanIndikator::HARI,
                'tipe_kalkulasi' => TipeKalkulasi::TURNOVER,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::LOWER_IS_BETTER,
                'target_hijau_min' => null,
                'target_hijau_max' => 60.00,
                'target_kuning_min' => 60.01,
                'target_kuning_max' => 90.00,
                'target_merah_min' => 90.01,
                'target_merah_max' => null,
                'definisi_operasional' => 'Rata-rata waktu (dalam hari) perputaran persediaan barang medis / logistik (tidak termasuk persediaan donasi / hibah) hingga terpakai dalam pelayanan.',
                'urutan' => 9,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '113', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '113.04', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => true],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'beban_dana_sosial',
                'nama' => 'Beban Dana Sosial',
                'satuan' => SatuanIndikator::PERSEN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::COA,
                'variabel_manual_id' => null,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 1.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 0.50,
                'target_kuning_max' => 1.00,
                'target_merah_min' => null,
                'target_merah_max' => 0.50,
                'definisi_operasional' => 'Persentase alokasi beban dana sosial kemasyarakatan / kepedulian terhadap total pendapatan operasional rumah sakit.',
                'urutan' => 10,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '905', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                    ['kode' => '4', 'peran' => PeranCoa::DENOMINATOR, 'is_excluded' => false],
                ],
            ],
            [
                'kode' => 'produktifitas_karyawan',
                'nama' => 'Produktifitas Karyawan',
                'satuan' => SatuanIndikator::JUTA_PER_ORG_BLN,
                'tipe_kalkulasi' => TipeKalkulasi::RASIO,
                'basis_pembilang' => BasisPembilang::COA,
                'basis_penyebut' => BasisPenyebut::MANUAL,
                'variabel_manual_id' => $persistedVariables['sdm_non_organik']->id,
                'pola_evaluasi' => PolaEvaluasi::HIGHER_IS_BETTER,
                'target_hijau_min' => 22.00,
                'target_hijau_max' => null,
                'target_kuning_min' => 15.00,
                'target_kuning_max' => 22.00,
                'target_merah_min' => null,
                'target_merah_max' => 15.00,
                'definisi_operasional' => 'Rata-rata pendapatan operasional yang dihasilkan per orang staf non-organik per bulan (dalam satuan Juta Rupiah per orang per bulan).',
                'urutan' => 11,
                'status_aktif' => true,
                'mappings' => [
                    ['kode' => '4', 'peran' => PeranCoa::NUMERATOR, 'is_excluded' => false],
                ],
            ],
        ];

        foreach ($indicators as $data) {
            $mappings = $data['mappings'] ?? [];
            unset($data['mappings']);

            $indicator = IndikatorKinerja::updateOrCreate(
                ['kode' => $data['kode']],
                $data
            );

            foreach ($mappings as $map) {
                $coa = $this->findCoa($map['kode']);
                if ($coa) {
                    IndikatorKinerjaMappingCoa::updateOrCreate(
                        [
                            'indikator_id' => $indicator->id,
                            'coa_id' => $coa->id,
                            'peran' => $map['peran'],
                        ],
                        [
                            'is_excluded' => $map['is_excluded'],
                        ]
                    );
                }
            }
        }
    }

    /**
     * Cari COA berdasarkan kode dengan penanganan toleransi whitespace atau tab.
     */
    protected function findCoa(string $kode): ?Coa
    {
        if (! Schema::hasTable('coa')) {
            return null;
        }

        return Coa::where('kode', $kode)
            ->orWhere('kode', $kode."\t")
            ->orWhere('kode', $kode.' ')
            ->first();
    }
}
