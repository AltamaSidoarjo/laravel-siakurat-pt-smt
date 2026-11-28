<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\ReferensiKelompokArusKas;
use Illuminate\Database\Seeder;

class ReferensiKelompokArusKasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $standardItems = [
            // Operasi (10)
            [
                'aktivitas' => 'operasi',
                'nama' => 'Penerimaan kas dari pasien non BPJS Kesehatan',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Penerimaan klaim BPJS Kesehatan',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Penerimaan lainnya',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran gaji karyawan & jasa medis dokter',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran pemasok obat, alat kesehatan & BHP',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran listrik, air, internet',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran marketing & PKRS',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran dana dakwah',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran pajak',
            ],
            [
                'aktivitas' => 'operasi',
                'nama' => 'Pembayaran beban administrasi, umum & operasional lainnya',
            ],
            // Investasi (2)
            [
                'aktivitas' => 'investasi',
                'nama' => 'Pembelian & pembayaran hutang aset tetap',
            ],
            [
                'aktivitas' => 'investasi',
                'nama' => 'Penerimaan penjualan aset tetap bekas',
            ],
            // Pendanaan (2)
            [
                'aktivitas' => 'pendanaan',
                'nama' => 'Penerimaan pinjaman/pembiayaan bank',
            ],
            [
                'aktivitas' => 'pendanaan',
                'nama' => 'Pembayaran angsuran pokok & bunga pinjaman',
            ],
        ];

        foreach ($standardItems as $item) {
            ReferensiKelompokArusKas::updateOrCreate(
                [
                    'aktivitas' => $item['aktivitas'],
                    'nama' => $item['nama'],
                ],
                [
                    'status_aktif' => true,
                ]
            );
        }

        $legacyItems = Coa::whereNotNull('arus_kas_kelompok')
            ->whereNotNull('arus_kas_aktivitas')
            ->select('arus_kas_aktivitas', 'arus_kas_kelompok')
            ->distinct()
            ->get();

        foreach ($legacyItems as $legacy) {
            $aktivitas = trim((string) $legacy->arus_kas_aktivitas);
            $nama = trim((string) $legacy->arus_kas_kelompok);

            if ($aktivitas === '' || $nama === '') {
                continue;
            }

            ReferensiKelompokArusKas::firstOrCreate(
                [
                    'aktivitas' => $aktivitas,
                    'nama' => $nama,
                ],
                [
                    'status_aktif' => false,
                ]
            );
        }
    }
}
