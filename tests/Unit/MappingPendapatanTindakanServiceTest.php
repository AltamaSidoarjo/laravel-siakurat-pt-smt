<?php

namespace Tests\Unit;

use App\Services\LogAktifitasService;
use App\Services\Pengaturan\MappingPendapatanTindakanService;
use PHPUnit\Framework\TestCase;

class MappingPendapatanTindakanServiceTest extends TestCase
{
    public function test_get_type_options_hides_operasi_from_ui_options(): void
    {
        $service = new MappingPendapatanTindakanService($this->createMock(LogAktifitasService::class));
        $typeOptions = collect($service->getTypeOptions());

        $this->assertTrue($typeOptions->contains(fn (array $item) => $item['key'] === 'rawat_jalan'));
        $this->assertTrue($typeOptions->contains(fn (array $item) => $item['key'] === 'kamar'));
        $this->assertFalse($typeOptions->contains(fn (array $item) => $item['key'] === 'operasi'));
    }

    public function test_normalisasi_teks_membersihkan_spasi_berlebih_dan_ujung(): void
    {
        $input = "  Tindakan   Medis   Spesialis  ";
        $this->assertSame('Tindakan Medis Spesialis', MappingPendapatanTindakanService::normalisasiTeks($input));
        $this->assertSame('', MappingPendapatanTindakanService::normalisasiTeks(null));
    }
}
