<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPembelianTagihanObatDataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.simrs', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('simrs');

        $this->prepareMainTables();
        $this->prepareSimrsTables();
    }

    public function test_load_tagihan_obat_returns_valid_datatables_payload(): void
    {
        $this->seedTagihanObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pembelian.load-tagihan-obat', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('draw', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_load_tagihan_obat_filters_by_column_search(): void
    {
        $this->seedTagihanObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pembelian.load-tagihan-obat', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'columns' => $this->tagihanObatColumns([
                    6 => ['search' => ['value' => 'Sumber Waras']],
                ]),
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.no_faktur', 'OBT-002');
    }

    public function test_load_tagihan_obat_combines_date_range_global_search_and_column_search(): void
    {
        $this->seedTagihanObatRows();

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pembelian.load-tagihan-obat', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
                'search' => ['value' => 'PO-02'],
                'columns' => $this->tagihanObatColumns([
                    8 => ['search' => ['value' => 'Lunas']],
                ]),
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.no_faktur', 'OBT-002');
    }

    public function test_load_tagihan_obat_excludes_already_imported_rows(): void
    {
        $this->seedTagihanObatRows();

        DB::table('faktur_pembelian')->insert([
            'nomer_faktur' => 'OBT-001',
            'tanggal_faktur' => '2026-05-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pembelian.load-tagihan-obat', $this->dataTableRequest([
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-31',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonMissing(['no_faktur' => 'OBT-001'])
            ->assertJsonPath('data.0.no_faktur', 'OBT-002');
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }

    private function dataTableRequest(array $overrides = []): array
    {
        return array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => [
                'value' => '',
                'regex' => 'false',
            ],
            'order' => [
                [
                    'column' => 3,
                    'dir' => 'desc',
                ],
            ],
            'columns' => $this->tagihanObatColumns(),
        ], $overrides);
    }

    private function tagihanObatColumns(array $overrides = []): array
    {
        $columns = [
            ['data' => 'no_faktur', 'name' => 'p.no_faktur', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'no_faktur', 'name' => 'p.no_faktur', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'no_order', 'name' => 'p.no_order', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'tgl_faktur', 'name' => 'p.tgl_faktur', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'tgl_pesan', 'name' => 'p.tgl_pesan', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'tgl_tempo', 'name' => 'p.tgl_tempo', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'nama_suplier', 'name' => 's.nama_suplier', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'kd_bangsal', 'name' => 'p.kd_bangsal', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'status', 'name' => 'p.status', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ['data' => 'tagihan', 'name' => 'p.tagihan', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
        ];

        foreach ($overrides as $index => $override) {
            $columns[$index] = array_replace_recursive($columns[$index], $override);
        }

        return $columns;
    }

    private function prepareMainTables(): void
    {
        if (! Schema::hasTable('faktur_pembelian')) {
            Schema::create('faktur_pembelian', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nomer_faktur');
                $table->date('tanggal_faktur')->nullable();
                $table->timestamps();
            });
        }

        DB::table('faktur_pembelian')->truncate();
    }

    private function prepareSimrsTables(): void
    {
        $schema = Schema::connection('simrs');

        if (! $schema->hasTable('pemesanan')) {
            $schema->create('pemesanan', function (Blueprint $table) {
                $table->string('no_faktur')->primary();
                $table->string('no_order')->nullable();
                $table->date('tgl_faktur');
                $table->date('tgl_pesan')->nullable();
                $table->date('tgl_tempo')->nullable();
                $table->string('kode_suplier')->nullable();
                $table->string('kd_bangsal')->nullable();
                $table->string('status')->nullable();
                $table->decimal('ppn', 15, 2)->default(0);
                $table->decimal('tagihan', 15, 2)->default(0);
            });
        }

        if (! $schema->hasTable('datasuplier')) {
            $schema->create('datasuplier', function (Blueprint $table) {
                $table->string('kode_suplier')->primary();
                $table->string('nama_suplier')->nullable();
            });
        }

        DB::connection('simrs')->table('pemesanan')->delete();
        DB::connection('simrs')->table('datasuplier')->delete();
    }

    private function seedTagihanObatRows(): void
    {
        DB::connection('simrs')->table('datasuplier')->insert([
            ['kode_suplier' => 'SUP-001', 'nama_suplier' => 'Mitra Sehat'],
            ['kode_suplier' => 'SUP-002', 'nama_suplier' => 'Sumber Waras'],
        ]);

        DB::connection('simrs')->table('pemesanan')->insert([
            [
                'no_faktur' => 'OBT-001',
                'no_order' => 'PO-01',
                'tgl_faktur' => '2026-05-10',
                'tgl_pesan' => '2026-05-09',
                'tgl_tempo' => '2026-05-20',
                'kode_suplier' => 'SUP-001',
                'kd_bangsal' => 'BG-01',
                'status' => 'Belum Lunas',
                'ppn' => 1100,
                'tagihan' => 125000,
            ],
            [
                'no_faktur' => 'OBT-002',
                'no_order' => 'PO-02',
                'tgl_faktur' => '2026-05-11',
                'tgl_pesan' => '2026-05-10',
                'tgl_tempo' => '2026-05-25',
                'kode_suplier' => 'SUP-002',
                'kd_bangsal' => 'BG-02',
                'status' => 'Lunas',
                'ppn' => 2200,
                'tagihan' => 250000,
            ],
            [
                'no_faktur' => 'OBT-003',
                'no_order' => 'PO-03',
                'tgl_faktur' => '2026-04-29',
                'tgl_pesan' => '2026-04-28',
                'tgl_tempo' => '2026-05-05',
                'kode_suplier' => 'SUP-002',
                'kd_bangsal' => 'BG-03',
                'status' => 'Lunas',
                'ppn' => 3300,
                'tagihan' => 375000,
            ],
        ]);
    }
}
