<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_bridging_pendapatan_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pendapatan');

        $response
            ->assertOk()
            ->assertSee('Bridging Pendapatan');
    }

    public function test_bridging_pendapatan_obat_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pendapatan-obat');

        $response
            ->assertOk()
            ->assertSee('Bridging Pendapatan Obat');
    }

    public function test_bridging_pendapatan_obat_tarik_tagihan_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pendapatan-obat/tarik-tagihan');

        $response
            ->assertOk()
            ->assertSee('Tagihan Jual Obat SIMRS');
    }

    public function test_bridging_pembelian_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pembelian');

        $response
            ->assertOk()
            ->assertSee('Bridging Pembelian');
    }

    public function test_bridging_pembelian_tarik_obat_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pembelian/tarik-obat');

        $response
            ->assertOk()
            ->assertSee('Metode tanggal pengakuan:', false);
    }

    public function test_bridging_pembelian_tarik_nonmedis_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pembelian/tarik-nonmedis');

        $response
            ->assertOk()
            ->assertSee('Tagihan Pembelian Barang Non Medis SIMRS');
    }

    public function test_laporan_keuangan_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/laporan/keuangan');

        $response
            ->assertOk()
            ->assertSee('Menu Laporan Keuangan');
    }

    public function test_laporan_keuangan_rincian_transaksi_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/laporan/keuangan/rincian-transaksi-bukubesar');

        $response
            ->assertOk()
            ->assertSee('Rincian Transaksi Bukubesar');
    }

    public function test_laporan_keuangan_menu_lists_core_report_links(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/laporan/keuangan');

        $response
            ->assertOk()
            ->assertSee('Neraca Standard')
            ->assertSee('Laba Rugi Standard')
            ->assertDontSee('Laba Rugi Per Parent COA')
            ->assertDontSee('Neraca Per Parent COA');
    }
}
