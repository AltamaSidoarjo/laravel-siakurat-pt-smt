<?php

namespace App\Support;

class AccessModuleRegistry
{
    public static function all(): array
    {
        return [
            ['kode' => 'home', 'nama' => 'Home', 'group_nama' => 'Dashboard', 'urutan' => 10],
            ['kode' => 'bukubesar.jurnal-umum', 'nama' => 'Jurnal Umum', 'group_nama' => 'Bukubesar', 'urutan' => 20],
            ['kode' => 'bukubesar.coa', 'nama' => 'COA', 'group_nama' => 'Bukubesar', 'urutan' => 30],
            ['kode' => 'kasbank.penerimaan', 'nama' => 'Kasbank Penerimaan', 'group_nama' => 'Kasbank', 'urutan' => 40],
            ['kode' => 'kasbank.pembayaran', 'nama' => 'Kasbank Pembayaran', 'group_nama' => 'Kasbank', 'urutan' => 50],
            ['kode' => 'bridging.pendapatan', 'nama' => 'Bridging Pendapatan', 'group_nama' => 'Bridging', 'urutan' => 60],
            ['kode' => 'bridging.pendapatan-obat', 'nama' => 'Bridging Pendapatan Obat', 'group_nama' => 'Bridging', 'urutan' => 70],
            ['kode' => 'bridging.pembelian', 'nama' => 'Bridging Pembelian', 'group_nama' => 'Bridging', 'urutan' => 80],
            ['kode' => 'pendapatan.invoice', 'nama' => 'Invoice Pendapatan', 'group_nama' => 'Pendapatan', 'urutan' => 90],
            ['kode' => 'pendapatan.penerimaan', 'nama' => 'Penerimaan Pendapatan', 'group_nama' => 'Pendapatan', 'urutan' => 100],
            ['kode' => 'pembelian.invoice', 'nama' => 'Invoice Pembelian', 'group_nama' => 'Pembelian', 'urutan' => 110],
            ['kode' => 'pembelian.pembayaran', 'nama' => 'Pembayaran Pembelian', 'group_nama' => 'Pembelian', 'urutan' => 120],
            ['kode' => 'laporan.keuangan', 'nama' => 'Laporan Keuangan', 'group_nama' => 'Laporan', 'urutan' => 130],
            ['kode' => 'laporan.pendapatan', 'nama' => 'Laporan Pendapatan', 'group_nama' => 'Laporan', 'urutan' => 140],
            ['kode' => 'pengaturan.mapping-pendapatan', 'nama' => 'Mapping Pendapatan', 'group_nama' => 'Pengaturan', 'urutan' => 150],
            ['kode' => 'pengaturan.mapping-general', 'nama' => 'Mapping General', 'group_nama' => 'Pengaturan', 'urutan' => 160],
            ['kode' => 'pengaturan.setting-rba', 'nama' => 'Setting RBA', 'group_nama' => 'Pengaturan', 'urutan' => 170],
            ['kode' => 'pengaturan.preferensi', 'nama' => 'Preferensi', 'group_nama' => 'Pengaturan', 'urutan' => 180],
            ['kode' => 'pengaturan.pengguna', 'nama' => 'Pengguna', 'group_nama' => 'Pengaturan', 'urutan' => 190],
            ['kode' => 'pengaturan.role-akses', 'nama' => 'Role Akses', 'group_nama' => 'Pengaturan', 'urutan' => 200],
        ];
    }
}
