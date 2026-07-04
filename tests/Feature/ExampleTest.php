<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_home_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/home');

        $response->assertRedirect(route('login'));
    }

    public function test_bridging_pendapatan_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pendapatan');

        $response
            ->assertOk()
            ->assertSee('Bridging Pendapatan');
    }

    public function test_home_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/home');

        $response
            ->assertOk()
            ->assertSee('Ringkasan kunjungan dan pendapatan SIMRS');
    }

    public function test_home_page_displays_company_name_from_preferences(): void
    {
        $this->preparePreferensiPerusahaanTable();

        \DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => 'RS Contoh Sehat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get('/home');

        $response
            ->assertOk()
            ->assertSee('RS Contoh Sehat');
    }

    public function test_home_page_displays_fallback_company_name_when_preferences_empty(): void
    {
        $this->preparePreferensiPerusahaanTable();

        \DB::table('preferensi_perusahaan')->insert([
            'nama_perusahaan' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get('/home');

        $response
            ->assertOk()
            ->assertSee(config('siakurat.rs_name'));
    }

    public function test_home_chart_endpoint_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/home/pendapatan-harian');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_bridging_pendapatan_obat_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pendapatan-obat');

        $response
            ->assertOk()
            ->assertSee('Bridging Pendapatan Obat');
    }

    public function test_bridging_pendapatan_obat_tarik_tagihan_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pendapatan-obat/tarik-tagihan');

        $response
            ->assertOk()
            ->assertSee('Tagihan Jual Obat SIMRS');
    }

    public function test_bridging_pembelian_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pembelian');

        $response
            ->assertOk()
            ->assertSee('Bridging Pembelian');
    }

    public function test_bridging_pembelian_tarik_obat_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pembelian/tarik-obat');

        $response
            ->assertOk()
            ->assertSee('Metode tanggal pengakuan:', false);
    }

    public function test_bridging_pembelian_tarik_nonmedis_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/bridging/pembelian/tarik-nonmedis');

        $response
            ->assertOk()
            ->assertSee('Tagihan Pembelian Barang Non Medis SIMRS');
    }

    public function test_laporan_keuangan_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/keuangan');

        $response
            ->assertOk()
            ->assertSee('Menu Laporan Keuangan');
    }

    public function test_laporan_keuangan_rincian_transaksi_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/keuangan/rincian-transaksi-bukubesar');

        $response
            ->assertOk()
            ->assertSee('Rincian Transaksi Bukubesar');
    }

    public function test_laporan_keuangan_bukubesar_page_requires_coa_selection_before_showing_data(): void
    {
        $this->prepareBukubesarTables();

        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/keuangan/bukubesar');

        $response
            ->assertOk()
            ->assertSee('Pilih minimal satu COA untuk menampilkan bukubesar.', false);
    }

    public function test_laporan_keuangan_bukubesar_search_coa_endpoint_returns_json(): void
    {
        $this->prepareBukubesarTables();

        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/keuangan/bukubesar/search-coa?q=kas');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_laporan_keuangan_menu_lists_core_report_links(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/keuangan');

        $response
            ->assertOk()
            ->assertSee('Neraca Standard')
            ->assertSee('Laba Rugi Standard')
            ->assertDontSee('Laba Rugi Per Parent COA')
            ->assertDontSee('Neraca Per Parent COA');
    }

    public function test_laporan_pendapatan_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/pendapatan');

        $response
            ->assertOk()
            ->assertSee('Menu Laporan Pendapatan')
            ->assertSee('Laporan Pendapatan Kunjungan')
            ->assertSee('Laporan Pendapatan Penjualan Obat');
    }

    public function test_laporan_pendapatan_kunjungan_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/pendapatan/kunjungan');

        $response
            ->assertOk()
            ->assertSee('Laporan Pendapatan Kunjungan')
            ->assertSee('Grand Total Tagihan')
            ->assertSee('grandTotalValue', false)
            ->assertSee("dom: 'Bfrltip'", false)
            ->assertSee('pageLength: 10', false);
    }

    public function test_laporan_pendapatan_penjualan_obat_page_can_be_opened_with_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/laporan/pendapatan/penjualan-obat');

        $response
            ->assertOk()
            ->assertSee('Laporan Pendapatan Penjualan Obat')
            ->assertSee("dom: 'Bfrltip'", false)
            ->assertSee('pageLength: 10', false);
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }

    private function prepareBukubesarTables(): void
    {
        $this->preparePreferensiPerusahaanTable();

        if (! Schema::hasTable('coa')) {
            Schema::create('coa', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('status_aktif')->nullable();
                $table->unsignedInteger('parent_coa')->nullable();
                $table->string('tipe_coa')->nullable();
                $table->string('kode');
                $table->string('nama');
                $table->string('deskripsi')->nullable();
                $table->boolean('is_postable')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bukubesar')) {
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
        }

        if (Schema::hasTable('coa') && Schema::hasTable('bukubesar')) {
            Schema::disableForeignKeyConstraints();
            \DB::table('bukubesar')->truncate();
            \DB::table('coa')->truncate();
            Schema::enableForeignKeyConstraints();
        }

        \DB::table('coa')->insert([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.01',
            'nama' => 'Kas Operasional',
            'deskripsi' => null,
            'is_postable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function preparePreferensiPerusahaanTable(): void
    {
        if (! Schema::hasTable('preferensi_perusahaan')) {
            Schema::create('preferensi_perusahaan', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('coa_id')->nullable();
                $table->string('nama_perusahaan')->nullable();
                $table->string('logo_perusahaan')->nullable();
                $table->timestamps();
            });
        }

        \DB::table('preferensi_perusahaan')->delete();
    }
}
