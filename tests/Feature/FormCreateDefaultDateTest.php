<?php

namespace Tests\Feature;

use App\Models\JurnalUmum;
use App\Models\KasbankPembayaran;
use App\Models\KasbankPenerimaan;
use App\Models\PembayaranPembelian;
use App\Models\PenerimaanPenjualan;
use Tests\TestCase;

class FormCreateDefaultDateTest extends TestCase
{
    public function test_jurnal_umum_create_form_defaults_to_today_and_is_editable(): void
    {
        $today = date('Y-m-d');
        $view = $this->view('bukubesar.jurnal-umum._form', [
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('name="tanggal"', false);
        $view->assertSee('value="' . $today . '"', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" readonly', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" disabled', false);
    }

    public function test_jurnal_umum_edit_form_uses_existing_date(): void
    {
        $jurnalUmum = new JurnalUmum(['tanggal' => '2025-01-15', 'nomer' => 'JU-001']);
        $jurnalUmum->id = 1;
        $jurnalUmum->setRelation('rincian', collect());

        $view = $this->view('bukubesar.jurnal-umum._form', [
            'jurnalUmum' => $jurnalUmum,
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('value="2025-01-15"', false);
    }

    public function test_kasbank_penerimaan_create_form_defaults_to_today_and_is_editable(): void
    {
        $today = date('Y-m-d');
        $view = $this->view('kasbank.penerimaan._form', [
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('name="tanggal"', false);
        $view->assertSee('value="' . $today . '"', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" readonly', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" disabled', false);
    }

    public function test_kasbank_penerimaan_edit_form_uses_existing_date(): void
    {
        $kasbank = new KasbankPenerimaan(['tanggal' => '2025-02-20', 'nomer' => 'BKM-001']);
        $kasbank->id = 1;
        $kasbank->setRelation('rincian', collect());

        $view = $this->view('kasbank.penerimaan._form', [
            'kasbankPenerimaan' => $kasbank,
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('value="2025-02-20"', false);
    }

    public function test_kasbank_pembayaran_create_form_defaults_to_today_and_is_editable(): void
    {
        $today = date('Y-m-d');
        $view = $this->view('kasbank.pembayaran._form', [
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('name="tanggal"', false);
        $view->assertSee('value="' . $today . '"', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" readonly', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" disabled', false);
    }

    public function test_kasbank_pembayaran_edit_form_uses_existing_date(): void
    {
        $kasbank = new KasbankPembayaran(['tanggal' => '2025-03-10', 'nomer' => 'BKK-001']);
        $kasbank->id = 1;
        $kasbank->setRelation('rincian', collect());

        $view = $this->view('kasbank.pembayaran._form', [
            'kasbankPembayaran' => $kasbank,
            'coaOptions' => collect(),
            'templateOptions' => collect(),
        ]);

        $view->assertSee('value="2025-03-10"', false);
    }

    public function test_pendapatan_penerimaan_create_form_defaults_to_today_and_is_editable(): void
    {
        $today = date('Y-m-d');
        $view = $this->view('pendapatan.penerimaan._form', [
            'pelangganOptions' => collect(),
            'coaOptions' => collect(),
        ]);

        $view->assertSee('name="tanggal"', false);
        $view->assertSee('value="' . $today . '"', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" readonly', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" disabled', false);
    }

    public function test_pendapatan_penerimaan_edit_form_uses_existing_date(): void
    {
        $penerimaan = new PenerimaanPenjualan(['tanggal' => '2025-04-05', 'nomer' => 'PPD-001']);
        $penerimaan->id = 1;
        $penerimaan->setRelation('rincian', collect());

        $view = $this->view('pendapatan.penerimaan._form', [
            'penerimaanPendapatan' => $penerimaan,
            'pelangganOptions' => collect(),
            'coaOptions' => collect(),
        ]);

        $view->assertSee('value="2025-04-05"', false);
    }

    public function test_pembelian_pembayaran_create_form_defaults_to_today_and_is_editable(): void
    {
        $today = date('Y-m-d');
        $view = $this->view('pembelian.pembayaran._form', [
            'supplierOptions' => collect(),
            'coaOptions' => collect(),
        ]);

        $view->assertSee('name="tanggal"', false);
        $view->assertSee('value="' . $today . '"', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" readonly', false);
        $view->assertDontSee('name="tanggal" id="input_tanggal" class="form-control" value="' . $today . '" disabled', false);
    }

    public function test_pembelian_pembayaran_edit_form_uses_existing_date(): void
    {
        $pembayaran = new PembayaranPembelian(['tanggal' => '2025-05-12', 'nomer_pembayaran' => 'BYR-001']);
        $pembayaran->id = 1;
        $pembayaran->setRelation('rincian', collect());

        $view = $this->view('pembelian.pembayaran._form', [
            'pembayaranPembelian' => $pembayaran,
            'supplierOptions' => collect(),
            'coaOptions' => collect(),
        ]);

        $view->assertSee('value="2025-05-12"', false);
    }

    public function test_forms_preserve_old_input_when_validation_fails(): void
    {
        $session = app('session.store');
        $session->put('_old_input', ['tanggal' => '2024-12-31']);
        request()->setLaravelSession($session);

        $viewJU = $this->view('bukubesar.jurnal-umum._form', ['coaOptions' => collect(), 'templateOptions' => collect()]);
        $viewJU->assertSee('value="2024-12-31"', false);

        $viewBKM = $this->view('kasbank.penerimaan._form', ['coaOptions' => collect(), 'templateOptions' => collect()]);
        $viewBKM->assertSee('value="2024-12-31"', false);

        $viewBKK = $this->view('kasbank.pembayaran._form', ['coaOptions' => collect(), 'templateOptions' => collect()]);
        $viewBKK->assertSee('value="2024-12-31"', false);

        $viewPPD = $this->view('pendapatan.penerimaan._form', ['pelangganOptions' => collect(), 'coaOptions' => collect()]);
        $viewPPD->assertSee('value="2024-12-31"', false);

        $viewBYR = $this->view('pembelian.pembayaran._form', ['supplierOptions' => collect(), 'coaOptions' => collect()]);
        $viewBYR->assertSee('value="2024-12-31"', false);
    }
}
