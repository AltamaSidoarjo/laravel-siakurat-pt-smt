<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\PreferensiPerusahaan;
use App\Models\TipeCoa;
use App\Models\User;
use App\Services\Laporan\LaporanKeuanganService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LabaRugiKomparasiBulananTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('preferensi_perusahaan');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('setting_rba');
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

        Schema::create('setting_rba', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->integer('tahun');
            $table->decimal('total_nominal', 15, 2)->default(0);
            $table->string('catatan')->nullable();
            $table->boolean('is_rinci')->default(false);
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

    public function test_generate_daftar_bulan_dalam_interval(): void
    {
        $service = app(LaporanKeuanganService::class);
        $months = $service->generateDaftarBulanKomparasi('2026-01-01', '2026-03-31');

        $this->assertCount(3, $months);

        $this->assertSame('2026-01', $months[0]['key']);
        $this->assertSame('January 2026', $months[0]['label']);
        $this->assertSame('2026-01-01', $months[0]['start']);
        $this->assertSame('2026-01-31', $months[0]['end']);

        $this->assertSame('2026-02', $months[1]['key']);
        $this->assertSame('February 2026', $months[1]['label']);
        $this->assertSame('2026-02-01', $months[1]['start']);
        $this->assertSame('2026-02-28', $months[1]['end']);

        $this->assertSame('2026-03', $months[2]['key']);
        $this->assertSame('March 2026', $months[2]['label']);
        $this->assertSame('2026-03-01', $months[2]['start']);
        $this->assertSame('2026-03-31', $months[2]['end']);
    }

    public function test_kalkulasi_persen_pertumbuhan_dan_edge_cases(): void
    {
        $service = app(LaporanKeuanganService::class);

        // Normal positive growth: (150 - 100) / 100 * 100 = 50.0%
        $this->assertSame(50.0, $service->hitungPersenPerubahan(150, 100));

        // Normal negative growth: (60 - 100) / 100 * 100 = -40.0%
        $this->assertSame(-40.0, $service->hitungPersenPerubahan(60, 100));

        // Baseline / bulan pertama: previous null = 0.0%
        $this->assertSame(0.0, $service->hitungPertumbuhanBulanan(100, null));

        // Edge case: prev = 0, curr != 0 -> 100.0%
        $this->assertSame(100.0, $service->hitungPersenPerubahan(50, 0));
        $this->assertSame(100.0, $service->hitungPertumbuhanBulanan(50, 0.0));

        // Edge case: prev = 0, curr == 0 -> 0.0%
        $this->assertSame(0.0, $service->hitungPersenPerubahan(0, 0));
        $this->assertSame(0.0, $service->hitungPertumbuhanBulanan(0.0, 0.0));

        // Negative previous: prev = -100, curr = -50 -> (-50 - (-100)) / 100 * 100 = 50.0%
        $this->assertSame(50.0, $service->hitungPersenPerubahan(-50, -100));

        // Negative previous to positive: prev = -100, curr = 50 -> (50 - (-100)) / 100 * 100 = 150.0%
        $this->assertSame(150.0, $service->hitungPersenPerubahan(50, -100));

        // Positive previous to negative: prev = 100, curr = -50 -> (-50 - 100) / 100 * 100 = -150.0%
        $this->assertSame(-150.0, $service->hitungPersenPerubahan(-50, 100));
    }

    public function test_laba_rugi_komparasi_bulanan_kalkulasi_laba_kotor_operasional_bersih_dan_pertumbuhan(): void
    {
        foreach ([
            'Pendapatan',
            'Beban Pokok Penjualan',
            'Beban',
            'Pendapatan lain',
            'Beban lain',
        ] as $tipe) {
            TipeCoa::query()->create([
                'nama' => $tipe,
                'status_aktif' => 1,
            ]);
        }

        // COA Setup
        $pendapatanParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.00',
            'nama' => 'Pendapatan Usaha',
            'is_postable' => false,
        ]);

        $pendapatanChild1 = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanParent->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01',
            'nama' => 'Pendapatan Pelayanan Rawat Jalan',
            'is_postable' => true,
        ]);

        $pendapatanChild2 = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanParent->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.02',
            'nama' => 'Pendapatan Farmasi',
            'is_postable' => true,
        ]);

        $bppLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Beban Pokok Penjualan',
            'kode' => '510.01',
            'nama' => 'Beban Pokok Farmasi',
            'is_postable' => true,
        ]);

        $bebanOpsLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Beban',
            'kode' => '520.01',
            'nama' => 'Beban Gaji Karyawan',
            'is_postable' => true,
        ]);

        $pendapatanLainLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Pendapatan lain',
            'kode' => '710.01',
            'nama' => 'Pendapatan Bunga Bank',
            'is_postable' => true,
        ]);

        $bebanLainLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Beban lain',
            'kode' => '810.01',
            'nama' => 'Beban Administrasi Bank',
            'is_postable' => true,
        ]);

        // Mutations: Januari 2026
        // Pendapatan RJ: 100M (K)
        // Pendapatan Farmasi: 50M (K) -> Total Pendapatan = 150M
        // BPP Farmasi: 40M (D) -> Total BPP = 40M
        // Laba Kotor = 150M - 40M = 110M
        // Beban Gaji: 30M (D) -> Total Beban Ops = 30M
        // Laba Operasional = 110M - 30M = 80M
        // Pendapatan Bunga: 5M (K) -> Total Pendapatan Lain = 5M
        // Beban Admin Bank: 1M (D) -> Total Beban Lain = 1M
        // Total Pendapatan (Beban Lain) = 5M - 1M = 4M
        // Laba Bersih = 80M + 4M = 84M
        $transaksiJan = [
            [$pendapatanChild1->id, '2026-01-10', 100_000_000, 'K'],
            [$pendapatanChild2->id, '2026-01-12', 50_000_000, 'K'],
            [$bppLeaf->id, '2026-01-15', 40_000_000, 'D'],
            [$bebanOpsLeaf->id, '2026-01-25', 30_000_000, 'D'],
            [$pendapatanLainLeaf->id, '2026-01-30', 5_000_000, 'K'],
            [$bebanLainLeaf->id, '2026-01-30', 1_000_000, 'D'],
        ];

        // Mutations: Februari 2026
        // Pendapatan RJ: 120M (K)
        // Pendapatan Farmasi: 80M (K) -> Total Pendapatan = 200M (growth = 33.33%)
        // BPP Farmasi: 50M (D) -> Total BPP = 50M (growth = 25.0%)
        // Laba Kotor = 200M - 50M = 150M (growth = 36.36%)
        // Beban Gaji: 35M (D) -> Total Beban Ops = 35M (growth = 16.67%)
        // Laba Operasional = 150M - 35M = 115M (growth = 43.75%)
        // Pendapatan Bunga: 6M (K) -> Total Pendapatan Lain = 6M (growth = 20.0%)
        // Beban Admin Bank: 2M (D) -> Total Beban Lain = 2M (growth = 100.0%)
        // Total Pendapatan (Beban Lain) = 6M - 2M = 4M (growth = 0.0%)
        // Laba Bersih = 115M + 4M = 119M (growth = 41.67%)
        $transaksiFeb = [
            [$pendapatanChild1->id, '2026-02-10', 120_000_000, 'K'],
            [$pendapatanChild2->id, '2026-02-12', 80_000_000, 'K'],
            [$bppLeaf->id, '2026-02-15', 50_000_000, 'D'],
            [$bebanOpsLeaf->id, '2026-02-25', 35_000_000, 'D'],
            [$pendapatanLainLeaf->id, '2026-02-28', 6_000_000, 'K'],
            [$bebanLainLeaf->id, '2026-02-28', 2_000_000, 'D'],
        ];

        foreach (array_merge($transaksiJan, $transaksiFeb) as [$coaId, $tanggal, $nominal, $tipeMutasi]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'tanggal' => $tanggal,
                'nomer' => 'BB-' . str_replace('-', '', $tanggal) . '-' . $coaId,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipeMutasi,
            ]);
        }

        $service = app(LaporanKeuanganService::class);
        $result = $service->getLabaRugiKomparasiBulanan('2026-01-01', '2026-02-28');

        $this->assertCount(2, $result['months']);
        $this->assertArrayHasKey('pendapatan', $result['sections']);
        $this->assertArrayHasKey('beban_pokok_pendapatan', $result['sections']);
        $this->assertArrayHasKey('beban_operasional', $result['sections']);
        $this->assertArrayHasKey('pendapatan_lain', $result['sections']);
        $this->assertArrayHasKey('beban_lain', $result['sections']);

        // Check Parent Account Aggregation in Section Pendapatan
        $pendapatanRows = collect($result['sections']['pendapatan']['rows']);
        $parentRow = $pendapatanRows->firstWhere('coa_id', $pendapatanParent->id);
        $this->assertNotNull($parentRow);
        $this->assertTrue($parentRow['has_children']);
        $this->assertSame(150_000_000.0, $parentRow['monthly']['2026-01']['nominal']);
        $this->assertSame(200_000_000.0, $parentRow['monthly']['2026-02']['nominal']);
        $this->assertSame(33.33, $parentRow['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(350_000_000.0, $parentRow['total']);

        // Summary: Total dari Pendapatan
        $totalPendapatan = $result['summary']['total_pendapatan'];
        $this->assertSame('Total dari Pendapatan', $totalPendapatan['label']);
        $this->assertSame(150_000_000.0, $totalPendapatan['monthly']['2026-01']['nominal']);
        $this->assertSame(0.0, $totalPendapatan['monthly']['2026-01']['persen_perubahan']);
        $this->assertSame(200_000_000.0, $totalPendapatan['monthly']['2026-02']['nominal']);
        $this->assertSame(33.33, $totalPendapatan['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(350_000_000.0, $totalPendapatan['total']);

        // Summary: Total dari Beban Pokok Pendapatan
        $totalBpp = $result['summary']['total_beban_pokok'];
        $this->assertSame('Total dari Beban Pokok Pendapatan', $totalBpp['label']);
        $this->assertSame(40_000_000.0, $totalBpp['monthly']['2026-01']['nominal']);
        $this->assertSame(50_000_000.0, $totalBpp['monthly']['2026-02']['nominal']);
        $this->assertSame(25.0, $totalBpp['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(90_000_000.0, $totalBpp['total']);

        // Summary: Laba Kotor = Total Pendapatan - Total Beban Pokok
        $labaKotor = $result['summary']['laba_kotor'];
        $this->assertSame('Laba Kotor', $labaKotor['label']);
        $this->assertSame(110_000_000.0, $labaKotor['monthly']['2026-01']['nominal']);
        $this->assertSame(0.0, $labaKotor['monthly']['2026-01']['persen_perubahan']);
        $this->assertSame(150_000_000.0, $labaKotor['monthly']['2026-02']['nominal']);
        $this->assertSame(36.36, $labaKotor['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(260_000_000.0, $labaKotor['total']);

        // Summary: Total dari Beban Operasional
        $totalBebanOps = $result['summary']['total_beban_operasional'];
        $this->assertSame('Total dari Beban Operasional', $totalBebanOps['label']);
        $this->assertSame(30_000_000.0, $totalBebanOps['monthly']['2026-01']['nominal']);
        $this->assertSame(35_000_000.0, $totalBebanOps['monthly']['2026-02']['nominal']);
        $this->assertSame(16.67, $totalBebanOps['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(65_000_000.0, $totalBebanOps['total']);

        // Summary: Laba Operasional = Laba Kotor - Total Beban Operasional
        $labaOps = $result['summary']['laba_operasional'];
        $this->assertSame('Laba Operasional', $labaOps['label']);
        $this->assertSame(80_000_000.0, $labaOps['monthly']['2026-01']['nominal']);
        $this->assertSame(0.0, $labaOps['monthly']['2026-01']['persen_perubahan']);
        $this->assertSame(115_000_000.0, $labaOps['monthly']['2026-02']['nominal']);
        $this->assertSame(43.75, $labaOps['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(195_000_000.0, $labaOps['total']);

        // Summary: Total Pendapatan Lain-lain
        $totalPendapatanLain = $result['summary']['total_pendapatan_lain'];
        $this->assertSame('Total Pendapatan Lain-lain', $totalPendapatanLain['label']);
        $this->assertSame(5_000_000.0, $totalPendapatanLain['monthly']['2026-01']['nominal']);
        $this->assertSame(6_000_000.0, $totalPendapatanLain['monthly']['2026-02']['nominal']);
        $this->assertSame(20.0, $totalPendapatanLain['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(11_000_000.0, $totalPendapatanLain['total']);

        // Summary: Total Beban Lain-lain
        $totalBebanLain = $result['summary']['total_beban_lain'];
        $this->assertSame('Total Beban Lain-lain', $totalBebanLain['label']);
        $this->assertSame(1_000_000.0, $totalBebanLain['monthly']['2026-01']['nominal']);
        $this->assertSame(2_000_000.0, $totalBebanLain['monthly']['2026-02']['nominal']);
        $this->assertSame(100.0, $totalBebanLain['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(3_000_000.0, $totalBebanLain['total']);

        // Summary: Total dari Pendapatan (Beban Lain-lain) = Pendapatan Lain - Beban Lain
        $totalPendBebanLain = $result['summary']['total_pendapatan_beban_lain'];
        $this->assertSame('Total dari Pendapatan (Beban Lain-lain)', $totalPendBebanLain['label']);
        $this->assertSame(4_000_000.0, $totalPendBebanLain['monthly']['2026-01']['nominal']);
        $this->assertSame(4_000_000.0, $totalPendBebanLain['monthly']['2026-02']['nominal']);
        $this->assertSame(0.0, $totalPendBebanLain['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(8_000_000.0, $totalPendBebanLain['total']);

        // Summary: Laba Bersih = Laba Operasional + Total Pendapatan (Beban Lain-lain)
        $labaBersih = $result['summary']['laba_bersih'];
        $this->assertSame('Laba Bersih', $labaBersih['label']);
        $this->assertSame(84_000_000.0, $labaBersih['monthly']['2026-01']['nominal']);
        $this->assertSame(0.0, $labaBersih['monthly']['2026-01']['persen_perubahan']);
        $this->assertSame(119_000_000.0, $labaBersih['monthly']['2026-02']['nominal']);
        $this->assertSame(41.67, $labaBersih['monthly']['2026-02']['persen_perubahan']);
        $this->assertSame(203_000_000.0, $labaBersih['total']);
    }

    public function test_laba_rugi_komparasi_bulanan_controller_and_view(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->get(route('laporan.keuangan.laba-rugi-komparasi-bulanan', [
                'startDate' => '2026-01-01',
                'endDate' => '2026-03-31',
            ]));

        $response->assertOk();
        $response->assertViewIs('laporan.keuangan.laba-rugi-komparasi-bulanan');
        $response->assertViewHasAll([
            'startDate',
            'endDate',
            'months',
            'sections',
        ]);
        $response->assertSee('Laba Rugi Komparasi Bulanan');
        $response->assertSee('January 2026');
        $response->assertSee('February 2026');
        $response->assertSee('March 2026');
        $response->assertSee('Laba Kotor');
        $response->assertSee('Laba Operasional');
        $response->assertSee('Laba Bersih');
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            "name" => "Tester",
            "email" => "tester@example.com",
        ]);
    }
}
