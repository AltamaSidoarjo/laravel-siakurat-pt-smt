<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Requests\Pembelian\StoreInvoicePembelianRequest;
use App\Http\Requests\Pembelian\UpdateInvoicePembelianRequest;
use App\Models\FakturPembelian;
use App\Services\Pembelian\InvoicePembelianService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class InvoicePembelianController extends Controller
{
    use StreamsCsvExport;

    public function __construct(
        private readonly InvoicePembelianService $invoicePembelianService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        return view('pembelian.invoice.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        $startDate = $request->string('startDate')->toString() ?: now()->startOfMonth()->toDateString();
        $endDate = $request->string('endDate')->toString() ?: now()->toDateString();

        $query = $this->invoicePembelianService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal_faktur', fn (FakturPembelian $fakturPembelian) => optional($fakturPembelian->tanggal_faktur)->format('Y-m-d'))
            ->editColumn('tanggal_jatuh_tempo', fn (FakturPembelian $fakturPembelian) => optional($fakturPembelian->tanggal_jatuh_tempo)->format('Y-m-d'))
            ->addColumn('nomer_link', fn (FakturPembelian $fakturPembelian) => route('pembelian.invoice.read', $fakturPembelian))
            ->addColumn('nama_supplier', fn (FakturPembelian $fakturPembelian) => $fakturPembelian->supplier?->nama_supplier)
            ->addColumn('grandtotal_display', fn (FakturPembelian $fakturPembelian) => number_format((float) $fakturPembelian->grandtotal, 0, ',', '.'))
            ->addColumn('sudah_terbayar_display', fn (FakturPembelian $fakturPembelian) => number_format((float) $fakturPembelian->sudah_terbayar, 0, ',', '.'))
            ->addColumn('status_text', function (FakturPembelian $fakturPembelian) {
                return (int) $fakturPembelian->sudah_terbayar >= (int) $fakturPembelian->grandtotal
                    ? 'Sudah Lunas'
                    : 'Belum Lunas';
            })
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        return $this->streamCsvExport(
            $request,
            $this->invoicePembelianService->getIndexQuery($data['startDate'], $data['endDate']),
            'invoice-pembelian',
            ['Nomor faktur', 'Tanggal faktur', 'Tgl jatuh tempo', 'Supplier', 'Kode bangsal', 'Kategori faktur', 'Grandtotal', 'Sudah terbayar', 'Status'],
            fn (FakturPembelian $item) => [
                (string) $item->nomer_faktur,
                optional($item->tanggal_faktur)->format('Y-m-d'),
                optional($item->tanggal_jatuh_tempo)->format('Y-m-d'),
                (string) ($item->supplier?->nama_supplier ?? ''),
                (string) $item->kode_bangsal,
                (string) $item->kategori_faktur,
                $this->csvNumber($item->grandtotal),
                $this->csvNumber($item->sudah_terbayar),
                (float) $item->sudah_terbayar >= (float) $item->grandtotal ? 'Sudah Lunas' : 'Belum Lunas',
            ]
        );
    }

    public function read(FakturPembelian $fakturPembelian): View
    {
        $fakturPembelian->load(['supplier', 'akunHutang', 'rincian.coa']);

        return view('pembelian.invoice.read', [
            'page' => 'app',
            'invoicePembelian' => $fakturPembelian,
            'canMutate' => (float) $fakturPembelian->sudah_terbayar <= 0
                && ! $fakturPembelian->pembayaranPembelianRincis()->exists(),
        ]);
    }

    public function create(): View
    {
        return view('pembelian.invoice.create', [
            'page' => 'app',
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreInvoicePembelianRequest $request): RedirectResponse
    {
        $invoice = $this->invoicePembelianService->create($request->validated(), $this->actor());

        return redirect()->route('pembelian.invoice.read', $invoice)
            ->with('success', 'Invoice pembelian berhasil dibuat.');
    }

    public function edit(FakturPembelian $fakturPembelian): View|RedirectResponse
    {
        try {
            $this->invoicePembelianService->ensureMutable($fakturPembelian);
        } catch (RuntimeException $exception) {
            return redirect()->route('pembelian.invoice.read', $fakturPembelian)->with('error', $exception->getMessage());
        }

        $fakturPembelian->load(['supplier', 'akunHutang', 'rincian.coa']);

        return view('pembelian.invoice.edit', [
            'page' => 'app',
            'invoicePembelian' => $fakturPembelian,
            ...$this->formOptions($fakturPembelian),
        ]);
    }

    public function update(UpdateInvoicePembelianRequest $request, FakturPembelian $fakturPembelian): RedirectResponse
    {
        try {
            $invoice = $this->invoicePembelianService->update($fakturPembelian, $request->validated(), $this->actor());
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('pembelian.invoice.read', $invoice)
            ->with('success', 'Invoice pembelian berhasil diperbarui.');
    }

    public function destroy(FakturPembelian $fakturPembelian): RedirectResponse
    {
        try {
            $this->invoicePembelianService->delete($fakturPembelian, $this->actor());
        } catch (RuntimeException $exception) {
            return redirect()->route('pembelian.invoice.read', $fakturPembelian)->with('error', $exception->getMessage());
        }

        return redirect()->route('pembelian.invoice.index')->with('success', 'Invoice pembelian berhasil dihapus.');
    }

    public function print(FakturPembelian $fakturPembelian): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('pembelian.invoice.print', [
            'page' => 'app',
            'invoicePembelian' => $fakturPembelian->load(['supplier', 'rincian.coa']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
        ]);
    }

    private function formOptions(?FakturPembelian $invoice = null): array
    {
        return [
            'supplierOptions' => $this->invoicePembelianService->getSupplierOptions($invoice),
            'payableCoaOptions' => $this->invoicePembelianService->getPayableCoaOptions(),
            'purchasingCoaOptions' => $this->invoicePembelianService->getPurchasingCoaOptions(),
        ];
    }

    private function actor(): string
    {
        return auth()->user()?->name ?? auth()->user()?->email ?? 'system';
    }
}
