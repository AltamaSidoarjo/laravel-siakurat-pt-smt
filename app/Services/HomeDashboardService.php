<?php

namespace App\Services;

use App\Models\SimrsImportPendapatan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class HomeDashboardService
{
    public function getKunjunganHarian(string $startDate, string $endDate): Collection
    {
        return $this->jalankanQueryAman(fn () => SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('tanggal_reg, COUNT(*) as total')
            ->groupBy('tanggal_reg')
            ->orderBy('tanggal_reg')
            ->get()
            ->map(fn (SimrsImportPendapatan $row) => [
                'tanggal' => optional($row->tanggal_reg)->format('Y-m-d'),
                'total' => (int) $row->total,
            ]));
    }

    public function getDistribusiPoli(string $startDate, string $endDate): Collection
    {
        return $this->jalankanQueryAman(fn () => SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('COALESCE(NULLIF(TRIM(poli), ""), "Tanpa Poli") as label_poli, COUNT(*) as total')
            ->groupBy('label_poli')
            ->orderByDesc('total')
            ->get()
            ->map(fn (SimrsImportPendapatan $row) => [
                'poli' => (string) $row->label_poli,
                'total' => (int) $row->total,
            ]));
    }

    public function getTopDokter(string $startDate, string $endDate): Collection
    {
        return $this->jalankanQueryAman(fn () => SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('COALESCE(NULLIF(TRIM(dokter), ""), "Tanpa Dokter") as label_dokter, COUNT(*) as total')
            ->groupBy('label_dokter')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (SimrsImportPendapatan $row) => [
                'dokter' => (string) $row->label_dokter,
                'total' => (int) $row->total,
            ]));
    }

    public function getPendapatanHarian(string $startDate, string $endDate): Collection
    {
        return $this->jalankanQueryAman(fn () => SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('tanggal_reg, SUM(total_tagihan) as pendapatan')
            ->groupBy('tanggal_reg')
            ->orderBy('tanggal_reg')
            ->get()
            ->map(fn (SimrsImportPendapatan $row) => [
                'tanggal' => optional($row->tanggal_reg)->format('Y-m-d'),
                'pendapatan' => (float) $row->pendapatan,
            ]));
    }

    public function getKomposisiPenjamin(string $startDate, string $endDate): Collection
    {
        return $this->jalankanQueryAman(fn () => SimrsImportPendapatan::query()
            ->betweenDates($startDate, $endDate)
            ->selectRaw('COALESCE(NULLIF(TRIM(penjamin), ""), "-") as label_penjamin, COUNT(*) as total')
            ->groupBy('label_penjamin')
            ->orderByDesc('total')
            ->get()
            ->map(fn (SimrsImportPendapatan $row) => [
                'penjamin' => (string) $row->label_penjamin,
                'total' => (int) $row->total,
            ]));
    }

    private function jalankanQueryAman(callable $callback): Collection
    {
        try {
            return $callback();
        } catch (QueryException) {
            return collect();
        }
    }
}
