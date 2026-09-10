<?php

namespace App\Http\Controllers\Pendapatan;

use App\Exceptions\BillingApiException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Requests\Pendapatan\StoreInvoicePendapatanRequest;
use App\Http\Requests\Pendapatan\UpdateInvoicePendapatanRequest;
use App\Models\FakturPenjualan;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\Pendapatan\InvoicePendapatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class InvoicePendapatanController extends Controller
{
    use StreamsCsvExport;
    public function __construct(
        private readonly InvoicePendapatanService $invoicePendapatanService,
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('pendapatan.invoice.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->invoicePendapatanService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal_faktur', fn (FakturPenjualan $fakturPenjualan) => optional($fakturPenjualan->tanggal_faktur)->format('Y-m-d'))
            ->addColumn('nomer_link', fn (FakturPenjualan $fakturPenjualan) => route('pendapatan.invoice.read', $fakturPenjualan))
            ->addColumn('nominal', fn (FakturPenjualan $fakturPenjualan) => number_format((float) $fakturPenjualan->grandtotal, 0, ',', '.'))
            ->addColumn('sudah_bayar', fn (FakturPenjualan $fakturPenjualan) => number_format((float) $fakturPenjualan->sudah_terbayar, 0, ',', '.'))
            ->addColumn('kurang_bayar', fn (FakturPenjualan $fakturPenjualan) => number_format((float) ($fakturPenjualan->grandtotal - $fakturPenjualan->sudah_terbayar), 0, ',', '.'))
            ->addColumn('status_text', function (FakturPenjualan $fakturPenjualan) {
                return (float) $fakturPenjualan->sudah_terbayar >= (float) $fakturPenjualan->grandtotal
                    ? 'Sudah Lunas'
                    : 'Belum Lunas';
            })
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate(['startDate' => ['required', 'date_format:Y-m-d'], 'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate']]);
        return $this->streamCsvExport($request, $this->invoicePendapatanService->getIndexQuery($data['startDate'], $data['endDate']), 'invoice-pendapatan', ['Nomor', 'Tanggal', 'Dokter', 'No. RM', 'Pasien', 'Poli', 'Penjamin', 'Nominal', 'Sudah bayar', 'Kurang bayar', 'Status'], fn (FakturPenjualan $item) => [(string) $item->nomor_faktur, optional($item->tanggal_faktur)->format('Y-m-d'), (string) $item->dokter, (string) $item->nomer_rekam_medis, (string) $item->nama_pasien, (string) $item->poli, (string) $item->penjamin, $this->csvNumber($item->grandtotal), $this->csvNumber($item->sudah_terbayar), $this->csvNumber($item->grandtotal - $item->sudah_terbayar), (float) $item->sudah_terbayar >= (float) $item->grandtotal ? 'Sudah Lunas' : 'Belum Lunas']);
    }

    public function read(FakturPenjualan $fakturPenjualan): View
    {
        $fakturPenjualan->load(['pelanggan', 'akunPiutang', 'rincian.coa', 'rincian.pelaksana']);

        return view('pendapatan.invoice.read', [
            'page' => 'app',
            'invoicePendapatan' => $fakturPenjualan,
            'isImported' => $this->invoicePendapatanService->isImported($fakturPenjualan),
            'canMutate' => (float) $fakturPenjualan->sudah_terbayar <= 0
                && ! $fakturPenjualan->penerimaanPenjualanRincis()->exists(),
        ]);
    }

    public function create(): View
    {
        return view('pendapatan.invoice.create', [
            'page' => 'app',
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreInvoicePendapatanRequest $request): RedirectResponse
    {
        $invoice = $this->invoicePendapatanService->create($request->validated(), $this->actor());

        return redirect()->route('pendapatan.invoice.read', $invoice)
            ->with('success', 'Invoice pendapatan berhasil dibuat.');
    }

    public function edit(FakturPenjualan $fakturPenjualan): View|RedirectResponse
    {
        try {
            $this->invoicePendapatanService->ensureMutable($fakturPenjualan);
        } catch (RuntimeException $exception) {
            return redirect()->route('pendapatan.invoice.read', $fakturPenjualan)->with('error', $exception->getMessage());
        }

        $fakturPenjualan->load(['pelanggan', 'akunPiutang', 'rincian.coa', 'rincian.pelaksana']);

        return view('pendapatan.invoice.edit', [
            'page' => 'app',
            'invoicePendapatan' => $fakturPenjualan,
            'isImported' => $this->invoicePendapatanService->isImported($fakturPenjualan),
            ...$this->formOptions($fakturPenjualan),
        ]);
    }

    public function update(UpdateInvoicePendapatanRequest $request, FakturPenjualan $fakturPenjualan): RedirectResponse
    {
        try {
            $invoice = $this->invoicePendapatanService->update($fakturPenjualan, $request->validated(), $this->actor());
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('pendapatan.invoice.read', $invoice)
            ->with('success', 'Invoice pendapatan berhasil diperbarui.');
    }

    public function destroy(FakturPenjualan $fakturPenjualan): RedirectResponse
    {
        try {
            $this->invoicePendapatanService->delete($fakturPenjualan, $this->actor());
        } catch (RuntimeException $exception) {
            return redirect()->route('pendapatan.invoice.read', $fakturPenjualan)->with('error', $exception->getMessage());
        }

        return redirect()->route('pendapatan.invoice.index')->with('success', 'Invoice pendapatan berhasil dihapus.');
    }

    private function formOptions(?FakturPenjualan $invoice = null): array
    {
        $dokterOptions = collect();
        $poliOptions = collect();
        $apiOptionsError = null;

        try {
            $dokterOptions = $this->billingPendapatanApiService->getDokterOptions();
            $poliOptions = $this->billingPendapatanApiService->getSpesialisOptions();
        } catch (BillingApiException $exception) {
            $apiOptionsError = $exception->getMessage();
        }

        return [
            'pelangganOptions' => $this->invoicePendapatanService->getPelangganOptions($invoice),
            'receivableCoaOptions' => $this->invoicePendapatanService->getReceivableCoaOptions(),
            'revenueCoaOptions' => $this->invoicePendapatanService->getRevenueCoaOptions(),
            'pelaksanaOptions' => $this->invoicePendapatanService->getPelaksanaOptions($invoice),
            'dokterOptions' => $dokterOptions,
            'poliOptions' => $poliOptions,
            'apiOptionsError' => $apiOptionsError,
        ];
    }

    private function actor(): string
    {
        return auth()->user()?->name ?? auth()->user()?->email ?? 'system';
    }
}
