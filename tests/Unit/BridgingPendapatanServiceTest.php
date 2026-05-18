<?php

namespace Tests\Unit;

use App\Models\MappingPendapatan;
use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BridgingPendapatanServiceTest extends TestCase
{
    public function test_mapping_tindakan_sesuai_mengabaikan_spasi_belakang_pada_nama_perawatan(): void
    {
        $service = new BridgingPendapatanService(
            $this->getMockBuilder(BukuBesarService::class)->disableOriginalConstructor()->getMock()
        );

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'mappingTindakanSesuai');
        $method->setAccessible(true);

        $mapping = new MappingPendapatan([
            'kode_jenis_perawatan' => 'J000789',
            'sumber_tindakan' => 'Rawat Jalan',
            'nm_perawatan' => 'Administrasi   ',
            'coa_id' => 6,
        ]);

        $result = $method->invoke($service, $mapping, 'J000789', 'Administrasi', 'Rawat Jalan');

        $this->assertTrue($result);
    }
}
