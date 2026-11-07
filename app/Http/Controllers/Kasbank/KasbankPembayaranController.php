<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Requests\Kasbank\StoreKasbankPembayaranRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPembayaranRequest;
use App\Http\Requests\Kasbank\ImportKasbankPembayaranRequest;
use App\Models\KasbankPembayaran;
use App\Services\Kasbank\KasbankPembayaranService;
use App\Services\Kasbank\KasbankPembayaranImportService;
use App\Services\Kasbank\KasbankPembayaranImportException;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class KasbankPembayaranController extends Controller
{
    use StreamsCsvExport;
    public function __construct(
        private readonly KasbankPembayaranService $kasbankPembayaranService,
        private readonly KasbankPembayaranImportService $kasbankPembayaranImportService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('kasbank.pembayaran.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $this->kasbankPembayaranService->getSummary($startDate, $endDate),
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $user = $request->user();
        $canUpdate = $user?->hasModuleAccess('kasbank.pembayaran', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('kasbank.pembayaran', 'delete') ?? false;

        $query = $this->kasbankPembayaranService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (KasbankPembayaran $kasbankPembayaran) => optional($kasbankPembayaran->tanggal)->format('d-m-Y'))
            ->editColumn('total', fn (KasbankPembayaran $kasbankPembayaran) => number_format((float) $kasbankPembayaran->total, 0, ',', '.'))
            ->addColumn('coa_display', fn (KasbankPembayaran $kasbankPembayaran) => $kasbankPembayaran->coa ? ($kasbankPembayaran->coa->kode . ' - ' . $kasbankPembayaran->coa->nama) : '-')
            ->filterColumn('tanggal', function ($query, $keyword) {
                if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $keyword)) {
                    $parts = explode('-', $keyword);
                    $query->whereDate('tanggal', "{$parts[2]}-{$parts[1]}-{$parts[0]}");
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword)) {
                    $query->whereDate('tanggal', $keyword);
                } else {
                    $query->where('tanggal', 'like', "%{$keyword}%");
                }
            })
            ->filterColumn('coa_display', function ($query, $keyword) {
                $query->whereHas('coa', function ($q) use ($keyword) {
                    $q->where('kode', 'like', "%{$keyword}%")
                        ->orWhere('nama', 'like', "%{$keyword}%");
                });
            })
            ->order(function ($query) use ($request) {
                $orders = $request->input('order', []);
                if (empty($orders)) {
                    $query->orderByDesc('tanggal')->orderByDesc('id');

                    return;
                }

                $columns = $request->input('columns', []);
                foreach ($orders as $order) {
                    $colIdx = $order['column'] ?? null;
                    $dir = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    $colName = $columns[$colIdx]['name'] ?? $columns[$colIdx]['data'] ?? null;

                    if ($colName === 'nomer') {
                        $query->orderBy('nomer', $dir);
                    } elseif ($colName === 'tanggal') {
                        $query->orderBy('tanggal', $dir)->orderBy('id', $dir);
                    } elseif ($colName === 'coa_id' || $colName === 'coa_display') {
                        $query->join('coa', 'kasbank_pembayaran.coa_id', '=', 'coa.id')
                            ->orderBy('coa.nama', $dir)
                            ->select('kasbank_pembayaran.*');
                    } elseif ($colName === 'total') {
                        $query->orderBy('total', $dir);
                    } elseif ($colName === 'keterangan') {
                        $query->orderBy('keterangan', $dir);
                    }
                }
            })
            ->addColumn('nomer_link', fn (KasbankPembayaran $kasbankPembayaran) => route('kasbank.pembayaran.edit', $kasbankPembayaran))
            ->addColumn('edit_url', fn (KasbankPembayaran $kasbankPembayaran) => route('kasbank.pembayaran.edit', $kasbankPembayaran))
            ->addColumn('print_url', fn (KasbankPembayaran $kasbankPembayaran) => route('kasbank.pembayaran.print', $kasbankPembayaran))
            ->addColumn('destroy_url', fn (KasbankPembayaran $kasbankPembayaran) => route('kasbank.pembayaran.destroy', $kasbankPembayaran))
            ->addColumn('can_update', fn () => $canUpdate)
            ->addColumn('can_delete', fn () => $canDelete)
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $query = $this->kasbankPembayaranService->getIndexQuery($data['startDate'], $data['endDate'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        return $this->streamCsvExport(
            $request,
            $query,
            'kasbank-pembayaran',
            ['Nomor', 'Tanggal', 'Bank', 'Nominal', 'Keterangan'],
            fn (KasbankPembayaran $item) => [
                (string) $item->nomer,
                optional($item->tanggal)->format('Y-m-d'),
                (string) ($item->coa?->nama ?? '-'),
                $this->csvNumber($item->total),
                (string) $item->keterangan,
            ]
        );
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $this->sanitizeDateInput($request->string('startDate')->toString())
            ?? now()->startOfMonth()->toDateString();
        $endDate = $this->sanitizeDateInput($request->string('endDate')->toString())
            ?? now()->toDateString();

        if ($startDate > $endDate) {
            return [$endDate, $endDate];
        }

        return [$startDate, $endDate];
    }

    private function sanitizeDateInput(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', trim($value));
        } catch (Throwable) {
            return null;
        }

        if ($date->format('Y-m-d') !== trim($value)) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    public function create(): View
    {
        return view('kasbank.pembayaran.create', [
            'page' => 'app',
            'coaOptions' => $this->kasbankPembayaranService->getCoaOptions(),
        ]);
    }

    public function store(StoreKasbankPembayaranRequest $request): RedirectResponse
    {
        $kasbankPembayaran = DB::transaction(fn () => $this->kasbankPembayaranService->create($request->validated()));

        if ($request->input('action') === 'save_print') {
            return redirect()->route('kasbank.pembayaran.print', $kasbankPembayaran);
        }

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$kasbankPembayaran->nomer);
    }

    public function importForm(): View
    {
        return view('kasbank.pembayaran.import', [
            'page' => 'app',
            'phpUploadMaxFileSize' => ini_get('upload_max_filesize') ?: 'tidak diketahui',
            'phpPostMaxSize' => ini_get('post_max_size') ?: 'tidak diketahui',
        ]);
    }

    public function importTemplate(): BinaryFileResponse
    {
        $template = $this->kasbankPembayaranImportService->buildTemplate();

        return response()
            ->download(
                $template['output_path'],
                $template['download_name'],
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function import(ImportKasbankPembayaranRequest $request): RedirectResponse
    {
        try {
            $result = DB::transaction(
                fn () => $this->kasbankPembayaranImportService->importFromXlsx($request->file('file'))
            );
        } catch (KasbankPembayaranImportException $exception) {
            return back()->withErrors(['file' => $exception->errors()]);
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->withErrors(['file' => 'File gagal diproses. Pastikan file sesuai template.']);
        }

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', sprintf('Import berhasil. %d pembayaran dibuat (%s).', $result['created'], implode(', ', $result['nomer'])));
    }

    public function edit(KasbankPembayaran $kasbankPembayaran): View
    {
        return view('kasbank.pembayaran.edit', [
            'page' => 'app',
            'kasbankPembayaran' => $kasbankPembayaran->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->kasbankPembayaranService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPembayaranRequest $request, KasbankPembayaran $kasbankPembayaran): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->kasbankPembayaranService->update($kasbankPembayaran, $request->validated()));

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(KasbankPembayaran $kasbankPembayaran): RedirectResponse
    {
        $nomer = $kasbankPembayaran->nomer;

        DB::transaction(fn () => $this->kasbankPembayaranService->delete($kasbankPembayaran));

        return redirect()
            ->route('kasbank.pembayaran.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(KasbankPembayaran $kasbankPembayaran): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('kasbank.pembayaran.print', [
            'page' => 'app',
            'kasbankPembayaran' => $kasbankPembayaran->load(['coa', 'rincian.coa']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }
}
