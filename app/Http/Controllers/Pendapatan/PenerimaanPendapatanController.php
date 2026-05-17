<?php

namespace App\Http\Controllers\Pendapatan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pendapatan\StorePenerimaanPendapatanRequest;
use App\Http\Requests\Pendapatan\UpdatePenerimaanPendapatanRequest;
use App\Models\PenerimaanPenjualan;
use App\Services\Pendapatan\PenerimaanPendapatanService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PenerimaanPendapatanController extends Controller
{
    public function __construct(
        private readonly PenerimaanPendapatanService $penerimaanPendapatanService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(): View
    {
        return view('pendapatan.penerimaan.index', [
            'page' => 'app',
        ]);
    }

    public function loadData(): JsonResponse
    {
        $query = $this->penerimaanPendapatanService->getIndexQuery();

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (PenerimaanPenjualan $item) => optional($item->tanggal)->format('Y-m-d'))
            ->addColumn('pelanggan_display', fn (PenerimaanPenjualan $item) => trim(($item->pelanggan?->kode_pelanggan ?? '').' - '.($item->pelanggan?->nama_pelanggan ?? '')))
            ->addColumn('akun_bank_display', fn (PenerimaanPenjualan $item) => trim(($item->akunBank?->kode ?? '').' - '.($item->akunBank?->nama ?? '')))
            ->addColumn('jumlah_pembayaran_display', fn (PenerimaanPenjualan $item) => number_format((float) $item->jumlah_pembayaran, 0, ',', '.'))
            ->addColumn('nomer_link', fn (PenerimaanPenjualan $item) => route('pendapatan.penerimaan.edit', $item))
            ->toJson();
    }

    public function create(): View
    {
        return view('pendapatan.penerimaan.create', [
            'page' => 'app',
            'coaOptions' => $this->penerimaanPendapatanService->getCoaOptions(),
            'pelangganOptions' => $this->penerimaanPendapatanService->getPelangganOptions(),
        ]);
    }

    public function store(StorePenerimaanPendapatanRequest $request): RedirectResponse
    {
        $actor = auth()->user()?->name ?? auth()->user()?->email ?? 'system';
        $penerimaanPenjualan = DB::transaction(fn () => $this->penerimaanPendapatanService->create($request->validated(), $actor));

        if ($request->input('submit_action') === 'save-print') {
            return redirect()->route('pendapatan.penerimaan.print', $penerimaanPenjualan);
        }

        return redirect()
            ->route('pendapatan.penerimaan.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$penerimaanPenjualan->nomer);
    }

    public function edit(PenerimaanPenjualan $penerimaanPenjualan): View
    {
        return view('pendapatan.penerimaan.edit', [
            'page' => 'app',
            'penerimaanPendapatan' => $penerimaanPenjualan->load(['pelanggan', 'akunBank', 'akunPiutang', 'rincian.fakturPenjualan']),
            'coaOptions' => $this->penerimaanPendapatanService->getCoaOptions(),
        ]);
    }

    public function update(UpdatePenerimaanPendapatanRequest $request, PenerimaanPenjualan $penerimaanPenjualan): RedirectResponse
    {
        $actor = auth()->user()?->name ?? auth()->user()?->email ?? 'system';
        $updated = DB::transaction(fn () => $this->penerimaanPendapatanService->update($penerimaanPenjualan, $request->validated(), $actor));

        if ($request->input('submit_action') === 'save-print') {
            return redirect()->route('pendapatan.penerimaan.print', $updated);
        }

        return redirect()
            ->route('pendapatan.penerimaan.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(PenerimaanPenjualan $penerimaanPenjualan): RedirectResponse
    {
        $nomer = $penerimaanPenjualan->nomer;

        DB::transaction(fn () => $this->penerimaanPendapatanService->delete($penerimaanPenjualan));

        return redirect()
            ->route('pendapatan.penerimaan.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(PenerimaanPenjualan $penerimaanPenjualan): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('pendapatan.penerimaan.print', [
            'page' => 'app',
            'penerimaanPendapatan' => $penerimaanPenjualan->load(['pelanggan', 'akunBank', 'akunPiutang', 'rincian.fakturPenjualan']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }

    public function apiGetInvByPelanggan(Request $request): JsonResponse
    {
        $pelangganId = (int) $request->integer('id');
        $pelanggan = $this->penerimaanPendapatanService->getPelangganOptions()
            ->firstWhere('id', $pelangganId);

        if (! $pelanggan) {
            abort(404);
        }

        $fakturs = $this->penerimaanPendapatanService->getAvailableInvoicesByPelanggan($pelangganId);

        return response()->json([
            'data' => [
                'pelanggan' => [
                    'id' => $pelanggan->id,
                    'nama_pelanggan' => $pelanggan->nama_pelanggan,
                    'kode_pelanggan' => $pelanggan->kode_pelanggan,
                    'faktur_penjualans' => $fakturs->map(fn ($item) => [
                        'id' => $item->id,
                        'nomor_faktur' => $item->nomor_faktur,
                        'tanggal_faktur' => optional($item->tanggal_faktur)->format('Y-m-d'),
                        'grandtotal' => (float) $item->grandtotal,
                        'sudah_terbayar' => (float) $item->sudah_terbayar,
                        'nama_pasien' => $item->nama_pasien,
                        'nomer_rekam_medis' => $item->nomer_rekam_medis,
                    ])->values(),
                ],
            ],
        ]);
    }
}
