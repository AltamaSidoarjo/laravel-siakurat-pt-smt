<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HomeDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeDashboardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSimrsImportPendapatanTable();
    }

    public function test_distribusi_poli_groups_categories_after_the_top_ten_as_lainnya(): void
    {
        foreach (range(1, 12) as $index) {
            $this->insertVisits(sprintf('Poli %02d', $index), 13 - $index);
        }

        $result = app(HomeDashboardService::class)
            ->getDistribusiPoli('2026-09-01', '2026-09-30');

        $this->assertCount(11, $result);
        $this->assertSame(
            array_map(fn (int $index) => sprintf('Poli %02d', $index), range(1, 10)),
            $result->take(10)->pluck('poli')->all()
        );
        $this->assertSame(['poli' => 'Lainnya', 'total' => 3], $result->last());
    }

    public function test_dashboard_renders_the_ranking_chart_descriptions(): void
    {
        $response = $this
            ->actingAs(User::factory()->make())
            ->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('10 poli dengan kunjungan terbanyak dan akumulasi poli lainnya.')
            ->assertSee('10 dokter dengan jumlah pasien terbanyak.');
    }

    public function test_distribusi_poli_does_not_add_lainnya_for_ten_or_fewer_categories(): void
    {
        foreach (range(1, 10) as $index) {
            $this->insertVisits(sprintf('Poli %02d', $index), 11 - $index);
        }

        $result = app(HomeDashboardService::class)
            ->getDistribusiPoli('2026-09-01', '2026-09-30');

        $this->assertCount(10, $result);
        $this->assertFalse($result->contains('poli', 'Lainnya'));
    }

    public function test_distribusi_poli_endpoint_applies_date_filter_and_keeps_descending_order(): void
    {
        $this->insertVisits('Poli Anak', 2, '2026-09-10');
        $this->insertVisits('Poli Umum', 4, '2026-09-11');
        $this->insertVisits('Poli Di Luar Rentang', 8, '2026-08-31');

        $response = $this
            ->actingAs(User::factory()->make())
            ->getJson(route('home.poli', [
                'dariTanggal' => '2026-09-01',
                'sampaiTanggal' => '2026-09-30',
            ]));

        $response
            ->assertOk()
            ->assertExactJson([
                ['poli' => 'Poli Umum', 'total' => 4],
                ['poli' => 'Poli Anak', 'total' => 2],
            ]);
    }

    public function test_top_dokter_remains_limited_to_ten_and_sorted_descending(): void
    {
        foreach (range(1, 12) as $index) {
            $this->insertVisits('Poli Umum', 13 - $index, '2026-09-10', sprintf('Dokter %02d', $index));
        }

        $result = app(HomeDashboardService::class)
            ->getTopDokter('2026-09-01', '2026-09-30');

        $this->assertCount(10, $result);
        $this->assertSame('Dokter 01', $result->first()['dokter']);
        $this->assertSame(12, $result->first()['total']);
        $this->assertSame('Dokter 10', $result->last()['dokter']);
        $this->assertSame(3, $result->last()['total']);
    }

    private function insertVisits(
        string $poli,
        int $total,
        string $date = '2026-09-10',
        string $doctor = 'Dokter Penguji',
    ): void {
        $rows = [];

        foreach (range(1, $total) as $sequence) {
            $rows[] = [
                'nomer_billing' => sprintf('%s-%s-%03d', str_replace(' ', '-', $poli), str_replace(' ', '-', $doctor), $sequence),
                'tanggal_reg' => $date,
                'user_importer' => 'penguji',
                'import_time' => $date.' 08:00:00',
                'dokter' => $doctor,
                'poli' => $poli,
                'total_tagihan' => 0,
            ];
        }

        DB::table('simrs_import_pendapatan')->insert($rows);
    }

    private function prepareSimrsImportPendapatanTable(): void
    {
        if (! Schema::hasTable('simrs_import_pendapatan')) {
            Schema::create('simrs_import_pendapatan', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nomer_billing');
                $table->date('tanggal_reg');
                $table->string('dokter')->nullable();
                $table->string('poli')->nullable();
                $table->decimal('total_tagihan', 15, 2)->default(0);
            });
        }

        DB::table('simrs_import_pendapatan')->truncate();
    }
}
