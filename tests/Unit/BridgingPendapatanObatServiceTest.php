<?php

namespace Tests\Unit;

use App\Services\Bridging\BridgingPendapatanObatService;
use PHPUnit\Framework\TestCase;

class BridgingPendapatanObatServiceTest extends TestCase
{
    public function test_impor_banyak_rejects_unsupported_process_type_before_touching_database(): void
    {
        $service = new BridgingPendapatanObatService();

        $result = $service->imporBanyak(['PJ-TEST-001'], 'InvoicePendapatan', 'tester');

        $this->assertSame([
            [
                'nomer_transaksi' => 'PJ-TEST-001',
                'berhasil' => false,
                'alasan_gagal' => 'Saat ini hanya import ke Jurnal Umum yang didukung.',
            ],
        ], $result);
    }
}
