<?php

namespace Tests\Unit;

use App\Services\Bridging\BridgingPembelianService;
use PHPUnit\Framework\TestCase;

class BridgingPembelianServiceTest extends TestCase
{
    public function test_impor_banyak_pembelian_obat_rejects_unsupported_process_type_before_touching_database(): void
    {
        $service = new BridgingPembelianService();

        $result = $service->imporBanyakPembelianObat(
            ['PB-TEST-001'],
            'JurnalUmum',
            'TanggalInvoice',
            'tester',
        );

        $this->assertSame([
            [
                'nomer_transaksi' => 'PB-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ],
        ], $result);
    }

    public function test_impor_banyak_pembelian_nonmedis_rejects_unsupported_process_type_before_touching_database(): void
    {
        $service = new BridgingPembelianService();

        $result = $service->imporBanyakPembelianNonMedis(
            ['PNM-TEST-001'],
            'JurnalUmum',
            'tester',
        );

        $this->assertSame([
            [
                'nomer_transaksi' => 'PNM-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Invoice Pembelian yang didukung.',
            ],
        ], $result);
    }
}
