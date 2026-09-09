<?php

namespace Tests\Unit;

use App\Services\Bridging\BridgingPembelianService;
use App\Services\LogAktifitasService;
use PHPUnit\Framework\TestCase;

class BridgingPembelianServiceTest extends TestCase
{
    public function test_impor_banyak_pembelian_obat_rejects_unsupported_process_type_before_touching_database(): void
    {
        $service = new BridgingPembelianService($this->createMock(LogAktifitasService::class));

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
        $service = new BridgingPembelianService($this->createMock(LogAktifitasService::class));

        $result = $service->imporBanyakPembelianNonMedis(
            ['PNM-TEST-001'],
            'JurnalUmum',
            'TanggalInvoice',
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

    public function test_tanggal_pengakuan_nonmedis_uses_invoice_date(): void
    {
        $this->assertSame('2026-09-01', $this->resolveTanggalPengakuan([
            'tgl_faktur' => '2026-09-01',
            'tgl_pesan' => '2026-09-03',
        ], 'TanggalInvoice'));
    }

    public function test_tanggal_pengakuan_nonmedis_uses_barang_datang_date(): void
    {
        $this->assertSame('2026-09-03', $this->resolveTanggalPengakuan([
            'tgl_faktur' => '2026-09-01',
            'tgl_pesan' => '2026-09-03',
        ], 'TanggalBarangDatang'));
    }

    public function test_tanggal_pengakuan_nonmedis_falls_back_to_invoice_date_when_barang_datang_is_empty(): void
    {
        $this->assertSame('2026-09-01', $this->resolveTanggalPengakuan([
            'tgl_faktur' => '2026-09-01',
            'tgl_pesan' => '',
        ], 'TanggalBarangDatang'));
    }

    private function resolveTanggalPengakuan(array $tagihan, string $metode): string
    {
        $service = new BridgingPembelianService($this->createMock(LogAktifitasService::class));
        $method = new \ReflectionMethod($service, 'tentukanTanggalPengakuan');

        return $method->invoke($service, $tagihan, $metode);
    }
}
