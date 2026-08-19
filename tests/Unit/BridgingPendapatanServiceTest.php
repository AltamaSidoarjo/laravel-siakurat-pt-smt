<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Models\MappingLawanPendapatanSimrs;
use App\Models\MappingPendapatan;
use App\Services\Bridging\BridgingPendapatanService;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BridgingPendapatanServiceTest extends TestCase
{
    public function test_mapping_tindakan_sesuai_mengabaikan_spasi_belakang_pada_nama_perawatan(): void
    {
        $service = new BridgingPendapatanService(
            $this->getMockBuilder(BukuBesarService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(LogAktifitasService::class)->disableOriginalConstructor()->getMock(),
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

    public function test_pilih_akun_lawan_pendapatan_simrs_memprioritaskan_exact_match(): void
    {
        $service = new BridgingPendapatanService(
            $this->getMockBuilder(BukuBesarService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(LogAktifitasService::class)->disableOriginalConstructor()->getMock(),
        );

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'pilihAkunLawanPendapatanSimrs');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            new Collection([
                ['kd_rek' => '111.01', 'debet' => 3055600.0],
                ['kd_rek' => '111.02', 'debet' => 529804.0],
            ]),
            3055600.0,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('111.01', $result->first()['kd_rek']);
        $this->assertSame(3055600.0, $result->first()['debet']);
    }

    public function test_prioritaskan_akun_lawan_berdasarkan_tipe_coa_bukan_awalan_kode(): void
    {
        $service = new BridgingPendapatanService(
            $this->getMockBuilder(BukuBesarService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(LogAktifitasService::class)->disableOriginalConstructor()->getMock(),
        );

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'prioritaskanAkunLawanKasAtauPiutang');
        $method->setAccessible(true);

        $mappingLawan = new Collection([
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '111001', 'coa_id' => 2]),
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '112003', 'coa_id' => 7]),
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '115001', 'coa_id' => 44]),
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '450001', 'coa_id' => 1221]),
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '999001', 'coa_id' => 55]),
        ]);

        $coaLookup = new Collection([
            2 => new Coa(['id' => 2, 'kode' => 'KAS-01', 'tipe_coa' => 'Kasbank']),
            7 => new Coa(['id' => 7, 'kode' => 'BANK-03', 'tipe_coa' => 'Kasbank']),
            44 => new Coa(['id' => 44, 'kode' => '113.01', 'tipe_coa' => 'Persediaan']),
            1221 => new Coa(['id' => 1221, 'kode' => '405.04', 'tipe_coa' => 'Pendapatan']),
            55 => new Coa(['id' => 55, 'kode' => '111.99', 'tipe_coa' => 'Aktiva Lancar lainnya']),
        ]);

        $result = $method->invoke(
            $service,
            new Collection([
                ['kd_rek' => '111001', 'debet' => 56000.0],
                ['kd_rek' => '112003', 'debet' => 3000000.0],
                ['kd_rek' => '115001', 'debet' => 264702.0],
                ['kd_rek' => '450001', 'debet' => 264702.0],
                ['kd_rek' => '999001', 'debet' => 100000.0],
            ]),
            $mappingLawan,
            $coaLookup,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame(['111001', '112003'], $result->pluck('kd_rek')->all());
    }

    public function test_prioritaskan_akun_lawan_gagal_jika_ada_akun_yang_belum_dimapping(): void
    {
        $service = $this->createService();
        $method = new ReflectionMethod(BridgingPendapatanService::class, 'prioritaskanAkunLawanKasAtauPiutang');
        $method->setAccessible(true);

        $mappingLawan = new Collection([
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '111001', 'coa_id' => 2]),
        ]);

        $coaLookup = new Collection([
            2 => new Coa(['id' => 2, 'kode' => 'KAS-01', 'tipe_coa' => 'Kasbank']),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapping akun lawan pendapatan belum disetting untuk kode COA SIMRS');
        $this->expectExceptionMessage('113009');

        $method->invoke(
            $service,
            new Collection([
                ['kd_rek' => '111001', 'debet' => 139000.0],
                ['kd_rek' => '113009', 'debet' => 50000.0],
            ]),
            $mappingLawan,
            $coaLookup,
        );
    }

    public function test_informasi_invoice_menghitung_pembayaran_dari_tipe_kasbank_bukan_kode_coa(): void
    {
        $service = $this->createService();
        $method = new ReflectionMethod(BridgingPendapatanService::class, 'resolveInformasiAkunLawanInvoice');
        $method->setAccessible(true);

        $result = $method->invoke($service, new Collection([
            [
                'debit' => 750_000.0,
                'coa' => new Coa(['id' => 10, 'kode' => 'BANK-UTAMA', 'tipe_coa' => 'kAsBaNk']),
            ],
            [
                'debit' => 250_000.0,
                'coa' => new Coa(['id' => 11, 'kode' => '111.99', 'tipe_coa' => 'Aktiva Lancar lainnya']),
            ],
        ]));

        $this->assertSame(750_000.0, $result['sudah_terbayar']);
        $this->assertNull($result['akun_piutang_id']);
    }

    public function test_informasi_invoice_menentukan_akun_piutang_dari_tipe_coa_bukan_kode_coa(): void
    {
        $service = $this->createService();
        $method = new ReflectionMethod(BridgingPendapatanService::class, 'resolveInformasiAkunLawanInvoice');
        $method->setAccessible(true);

        $piutangTanpaPrefix = $method->invoke($service, new Collection([
            [
                'debit' => 1_000_000.0,
                'coa' => (new Coa)->forceFill([
                    'id' => 20,
                    'kode' => 'AR-BPJS',
                    'tipe_coa' => 'PiUtAnG Usaha',
                ]),
            ],
        ]));
        $bukanPiutangDenganPrefix = $method->invoke($service, new Collection([
            [
                'debit' => 1_000_000.0,
                'coa' => new Coa(['id' => 21, 'kode' => '112.01', 'tipe_coa' => 'Aktiva Lancar lainnya']),
            ],
        ]));

        $this->assertSame(20, $piutangTanpaPrefix['akun_piutang_id']);
        $this->assertSame(0.0, $piutangTanpaPrefix['sudah_terbayar']);
        $this->assertNull($bukanPiutangDenganPrefix['akun_piutang_id']);
    }

    public function test_informasi_invoice_akun_campuran_hanya_menghitung_kasbank_sebagai_pembayaran(): void
    {
        $service = $this->createService();
        $method = new ReflectionMethod(BridgingPendapatanService::class, 'resolveInformasiAkunLawanInvoice');
        $method->setAccessible(true);

        $result = $method->invoke($service, new Collection([
            [
                'debit' => 400_000.0,
                'coa' => new Coa(['id' => 30, 'kode' => 'CASH-01', 'tipe_coa' => 'Kasbank']),
            ],
            [
                'debit' => 600_000.0,
                'coa' => new Coa(['id' => 31, 'kode' => 'RECEIVABLE-01', 'tipe_coa' => 'Akun Piutang']),
            ],
        ]));

        $this->assertSame(400_000.0, $result['sudah_terbayar']);
        $this->assertNull($result['akun_piutang_id']);
    }

    private function createService(): BridgingPendapatanService
    {
        return new BridgingPendapatanService(
            $this->getMockBuilder(BukuBesarService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(LogAktifitasService::class)->disableOriginalConstructor()->getMock(),
        );
    }
}
