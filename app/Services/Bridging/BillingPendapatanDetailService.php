<?php

namespace App\Services\Bridging;

use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\SimrsImportPendapatan;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class BillingPendapatanDetailService
{
    private const IMPORT_INVOICE_PENDAPATAN = 'Invoice Pendapatan';

    public function __construct(
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
    ) {}

    public function getDetail(?string $externalId, ?int $importId): array
    {
        if (filled($externalId)) {
            return $this->getApiDetail((string) $externalId);
        }

        $import = SimrsImportPendapatan::query()->findOrFail($importId);

        if ($import->import_ke !== self::IMPORT_INVOICE_PENDAPATAN) {
            throw new DomainException('Rincian billing tidak tersedia untuk data Jurnal Umum lama.');
        }

        return $this->getLocalInvoiceDetail($import);
    }

    private function getApiDetail(string $externalId): array
    {
        $rows = $this->billingPendapatanApiService->getRincianAkun($externalId);

        return [
            'data' => $rows->all(),
            'grandTotal' => (float) $rows->sum('subtotal'),
            'source' => 'api',
        ];
    }

    private function getLocalInvoiceDetail(SimrsImportPendapatan $import): array
    {
        $invoice = FakturPenjualan::query()
            ->with('rincian.coa:id,kode')
            ->where(function (Builder $query) use ($import): void {
                $query
                    ->where('nomer_rawat', $import->nomer_billing)
                    ->orWhere('nomor_faktur', $import->nomer_billing);
            })
            ->first();

        if ($invoice === null) {
            throw new DomainException('Rincian invoice lokal tidak ditemukan.');
        }

        $rows = $invoice->rincian
            ->map(fn (FakturPenjualanRinci $item): array => [
                'akun' => (string) ($item->coa?->kode ?? ''),
                'job' => filled($item->kode_proyek) ? (string) $item->kode_proyek : null,
                'biaya' => (float) $item->harga,
                'jumlah' => (float) $item->kuantitas,
                'subtotal' => (float) $item->subtotal,
            ])
            ->values();

        return [
            'data' => $rows->all(),
            'grandTotal' => (float) $rows->sum('subtotal'),
            'source' => 'local_invoice',
        ];
    }
}
