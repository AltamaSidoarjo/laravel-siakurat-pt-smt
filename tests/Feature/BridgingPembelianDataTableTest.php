<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BridgingPembelianDataTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();
    }

    public function test_grand_total_uses_all_rows_in_selected_date_range(): void
    {
        $this->seedImportedInvoices();

        $this->getImportedData([
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-31',
        ])
            ->assertOk()
            ->assertJsonPath('grandTotal', 11000);
    }

    public function test_grand_total_changes_when_global_search_filters_by_supplier(): void
    {
        $this->seedImportedInvoices();

        $this->getImportedData([
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-31',
            'search' => ['value' => 'Sumber Waras'],
        ])
            ->assertOk()
            ->assertJsonPath('grandTotal', 5000);
    }

    public function test_grand_total_can_be_filtered_by_numeric_search(): void
    {
        $this->seedImportedInvoices();

        $this->getImportedData([
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-31',
            'search' => ['value' => '3500'],
        ])
            ->assertOk()
            ->assertJsonPath('grandTotal', 3500);
    }

    public function test_grand_total_excludes_rows_outside_selected_date_range(): void
    {
        $this->seedImportedInvoices();

        $this->getImportedData([
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-31',
            'search' => ['value' => 'INV-LAMA'],
        ])
            ->assertOk()
            ->assertJsonPath('grandTotal', 0);
    }

    private function getImportedData(array $overrides)
    {
        return $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pembelian.load-imported-data', $this->dataTableRequest($overrides)));
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
        ], $overrides);
    }

    private function prepareTables(): void
    {
        if (! Schema::hasTable('supplier')) {
            Schema::create('supplier', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nama_supplier')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('faktur_pembelian')) {
            Schema::create('faktur_pembelian', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('supplier_id')->nullable();
                $table->string('nomer_faktur');
                $table->date('tanggal_faktur')->nullable();
                $table->date('tanggal_jatuh_tempo')->nullable();
                $table->string('kode_bangsal')->nullable();
                $table->string('kategori_faktur')->nullable();
                $table->decimal('grandtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        DB::table('faktur_pembelian')->truncate();
        DB::table('supplier')->truncate();
    }

    private function seedImportedInvoices(): void
    {
        DB::table('supplier')->insert([
            ['id' => 1, 'nama_supplier' => 'Mitra Sehat', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nama_supplier' => 'Sumber Waras', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('faktur_pembelian')->insert([
            [
                'supplier_id' => 1,
                'nomer_faktur' => 'INV-001',
                'tanggal_faktur' => '2026-05-10',
                'tanggal_jatuh_tempo' => '2026-05-20',
                'kode_bangsal' => 'BG-01',
                'kategori_faktur' => 'Obat',
                'grandtotal' => 2500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'supplier_id' => 2,
                'nomer_faktur' => 'INV-002',
                'tanggal_faktur' => '2026-05-11',
                'tanggal_jatuh_tempo' => '2026-05-25',
                'kode_bangsal' => 'BG-02',
                'kategori_faktur' => 'BHP',
                'grandtotal' => 5000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'supplier_id' => 1,
                'nomer_faktur' => 'INV-003',
                'tanggal_faktur' => '2026-05-12',
                'tanggal_jatuh_tempo' => '2026-05-26',
                'kode_bangsal' => 'BG-01',
                'kategori_faktur' => 'Obat',
                'grandtotal' => 3500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'supplier_id' => 2,
                'nomer_faktur' => 'INV-LAMA',
                'tanggal_faktur' => '2026-04-30',
                'tanggal_jatuh_tempo' => '2026-05-10',
                'kode_bangsal' => 'BG-03',
                'kategori_faktur' => 'Obat',
                'grandtotal' => 9000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
