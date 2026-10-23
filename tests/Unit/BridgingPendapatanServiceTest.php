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
    public function test_mapping_tindakan_sesuai_menormalisasi_nama_perawatan(): void
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
            'nm_perawatan' => "  ADMINISTRASI\t  RAWAT   JALAN  ",
            'coa_id' => 6,
        ]);

        $result = $method->invoke($service, $mapping, 'J000789', 'administrasi rawat jalan', 'Rawat Jalan');

        $this->assertTrue($result);
    }

    public function test_mapping_tindakan_tetap_memerlukan_nama_kode_dan_sumber_yang_sesuai(): void
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
            'nm_perawatan' => 'Administrasi Rawat Jalan',
            'coa_id' => 6,
        ]);

        $this->assertFalse($method->invoke($service, $mapping, 'J000789', 'Administrasi Rawat Inap', 'Rawat Jalan'));
        $this->assertFalse($method->invoke($service, $mapping, 'J000780', 'Administrasi Rawat Jalan', 'Rawat Jalan'));
        $this->assertFalse($method->invoke($service, $mapping, 'J000789', 'Administrasi Rawat Jalan', 'Rawat Inap'));
    }

    public function test_tentukan_akun_lawan_berhasil_dari_nota_kasir_murni(): void
    {
        $service = new class(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class)
        ) extends BridgingPendapatanService {
            public array $mockNota = [];

            protected function ambilRincianNotaKasir(string $statusLanjut, string $noRawat): Collection
            {
                return collect($this->mockNota);
            }
        };

        $service->mockNota = [
            ['nama_bayar' => 'Bayar Cash', 'kd_rek' => '111010', 'debet' => 300_000.0],
        ];

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'tentukanAkunLawanPendapatan');
        $method->setAccessible(true);

        $billing = [
            'no_rawat' => '2026/09/16/000001',
            'status_lanjut' => 'Ralan',
            'kode_penjamin' => 'A09',
            'penjamin' => 'UMUM',
        ];

        $details = new Collection([
            ['status_billing' => 'Ralan Dokter', 'total_biaya' => 300_000.0],
        ]);

        $mappingLawan = new Collection([
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '111010', 'coa_id' => 5]),
        ]);

        $coaLookup = new Collection([
            5 => new Coa(['id' => 5, 'kode' => '111.01', 'tipe_coa' => 'Kasbank']),
        ]);

        $result = $method->invoke($service, $billing, $details, $mappingLawan, $coaLookup);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['coa_id']);
        $this->assertSame(300_000.0, $result[0]['debit']);
    }

    public function test_tentukan_akun_lawan_berhasil_dari_piutang_penjamin_murni(): void
    {
        $service = new class(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class)
        ) extends BridgingPendapatanService {
            protected function ambilRincianNotaKasir(string $statusLanjut, string $noRawat): Collection
            {
                return collect();
            }

            protected function ambilRekeningPiutangPenjamin(string $statusLanjut, string $kodePenjamin, string $namaPenjamin): string
            {
                return '117003';
            }
        };

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'tentukanAkunLawanPendapatan');
        $method->setAccessible(true);

        $billing = [
            'no_rawat' => '2026/09/16/000002',
            'status_lanjut' => 'Ralan',
            'kode_penjamin' => 'BPJ',
            'penjamin' => 'BPJS Kesehatan',
        ];

        $details = new Collection([
            ['status_billing' => 'Ralan Dokter', 'total_biaya' => 500_000.0],
        ]);

        $mappingLawan = new Collection([
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '117003', 'coa_id' => 21]),
        ]);

        $coaLookup = new Collection([
            21 => new Coa(['id' => 21, 'kode' => '117.03', 'tipe_coa' => 'Piutang Usaha']),
        ]);

        $result = $method->invoke($service, $billing, $details, $mappingLawan, $coaLookup);

        $this->assertCount(1, $result);
        $this->assertSame(21, $result[0]['coa_id']);
        $this->assertSame(500_000.0, $result[0]['debit']);
    }

    public function test_tentukan_akun_lawan_berhasil_split_nota_kasir_dan_piutang(): void
    {
        $service = new class(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class)
        ) extends BridgingPendapatanService {
            protected function ambilRincianNotaKasir(string $statusLanjut, string $noRawat): Collection
            {
                return collect([
                    ['nama_bayar' => 'Bayar Cash', 'kd_rek' => '111010', 'debet' => 200_000.0],
                ]);
            }

            protected function ambilRekeningPiutangPenjamin(string $statusLanjut, string $kodePenjamin, string $namaPenjamin): string
            {
                return '117009';
            }
        };

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'tentukanAkunLawanPendapatan');
        $method->setAccessible(true);

        $billing = [
            'no_rawat' => '2026/09/16/000003',
            'status_lanjut' => 'Ranap',
            'kode_penjamin' => 'RSU',
            'penjamin' => 'Karyawan',
        ];

        $details = new Collection([
            ['status_billing' => 'Ranap Dokter', 'total_biaya' => 700_000.0],
        ]);

        $mappingLawan = new Collection([
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '111010', 'coa_id' => 5]),
            new MappingLawanPendapatanSimrs(['kode_coa_simrs' => '117009', 'coa_id' => 41]),
        ]);

        $coaLookup = new Collection([
            5 => new Coa(['id' => 5, 'kode' => '111.01', 'tipe_coa' => 'Kasbank']),
            41 => new Coa(['id' => 41, 'kode' => '117.09', 'tipe_coa' => 'Piutang']),
        ]);

        $result = $method->invoke($service, $billing, $details, $mappingLawan, $coaLookup);

        $this->assertCount(2, $result);
        $this->assertSame(5, $result[0]['coa_id']);
        $this->assertSame(200_000.0, $result[0]['debit']);
        $this->assertSame(41, $result[1]['coa_id']);
        $this->assertSame(500_000.0, $result[1]['debit']);
    }

    public function test_tentukan_akun_lawan_gagal_jika_total_cara_bayar_tidak_cocok_dengan_billing(): void
    {
        $service = new class(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class)
        ) extends BridgingPendapatanService {
            protected function ambilRincianNotaKasir(string $statusLanjut, string $noRawat): Collection
            {
                return collect([
                    ['nama_bayar' => 'Bayar Cash', 'kd_rek' => '111010', 'debet' => 350_000.0],
                ]);
            }
        };

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'tentukanAkunLawanPendapatan');
        $method->setAccessible(true);

        $billing = [
            'no_rawat' => '2026/09/16/000004',
            'status_lanjut' => 'Ralan',
            'kode_penjamin' => 'A09',
            'penjamin' => 'UMUM',
        ];

        // Billing target 300_000, tapi bayar nota 350_000 (tidak cocok)
        $details = new Collection([
            ['status_billing' => 'Ralan Dokter', 'total_biaya' => 300_000.0],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Akun lawan pendapatan SIMRS tidak cocok dengan total billing');

        $method->invoke($service, $billing, $details, collect(), collect());
    }

    public function test_tentukan_akun_lawan_gagal_jika_mapping_coa_belum_disetting(): void
    {
        $service = new class(
            $this->createMock(BukuBesarService::class),
            $this->createMock(LogAktifitasService::class)
        ) extends BridgingPendapatanService {
            protected function ambilRincianNotaKasir(string $statusLanjut, string $noRawat): Collection
            {
                return collect([
                    ['nama_bayar' => 'Bayar Baru', 'kd_rek' => '999999', 'debet' => 100_000.0],
                ]);
            }
        };

        $method = new ReflectionMethod(BridgingPendapatanService::class, 'tentukanAkunLawanPendapatan');
        $method->setAccessible(true);

        $billing = [
            'no_rawat' => '2026/09/16/000005',
            'status_lanjut' => 'Ralan',
            'kode_penjamin' => 'A09',
            'penjamin' => 'UMUM',
        ];

        $details = new Collection([
            ['status_billing' => 'Ralan Dokter', 'total_biaya' => 100_000.0],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapping akun lawan pendapatan belum disetting untuk kode COA SIMRS');
        $this->expectExceptionMessage('999999');

        $method->invoke($service, $billing, $details, collect(), collect());
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
