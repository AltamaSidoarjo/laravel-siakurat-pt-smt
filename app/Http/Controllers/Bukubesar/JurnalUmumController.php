<?php

namespace App\Http\Controllers\Bukubesar;

use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bukubesar\ImportJurnalUmumRequest;
use App\Http\Requests\Bukubesar\StoreJurnalUmumRequest;
use App\Http\Requests\Bukubesar\UpdateJurnalUmumRequest;
use App\Models\JurnalUmum;
use App\Services\Bukubesar\JurnalUmumImportException;
use App\Services\Bukubesar\JurnalUmumImportService;
use App\Services\Bukubesar\JurnalUmumService;
use App\Services\Bukubesar\JurnalUmumTemplateService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class JurnalUmumController extends Controller
{
    use StreamsCsvExport;

    public function __construct(
        private readonly JurnalUmumService $jurnalUmumService,
        private readonly JurnalUmumImportService $jurnalUmumImportService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
        private readonly JurnalUmumTemplateService $templateService,
    ) {}

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bukubesar.jurnal-umum.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $this->jurnalUmumService->getSummary($startDate, $endDate),
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $user = $request->user();
        $canUpdate = $user?->hasModuleAccess('bukubesar.jurnal-umum', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('bukubesar.jurnal-umum', 'delete') ?? false;

        $query = $this->jurnalUmumService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (JurnalUmum $jurnalUmum) => optional($jurnalUmum->tanggal)->format('d-m-Y'))
            ->editColumn('debit', fn (JurnalUmum $jurnalUmum) => number_format((float) $jurnalUmum->debit, 0, ',', '.'))
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
                    } elseif ($colName === 'debit') {
                        $query->orderBy('debit', $dir);
                    } elseif ($colName === 'keterangan') {
                        $query->orderBy('keterangan', $dir);
                    }
                }
            })
            ->addColumn('nomer_link', fn (JurnalUmum $jurnalUmum) => route('bukubesar.jurnal-umum.edit', $jurnalUmum))
            ->addColumn('edit_url', fn (JurnalUmum $jurnalUmum) => route('bukubesar.jurnal-umum.edit', $jurnalUmum))
            ->addColumn('print_url', fn (JurnalUmum $jurnalUmum) => route('bukubesar.jurnal-umum.print', $jurnalUmum))
            ->addColumn('destroy_url', fn (JurnalUmum $jurnalUmum) => route('bukubesar.jurnal-umum.destroy', $jurnalUmum))
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

        $query = $this->jurnalUmumService->getIndexQuery($data['startDate'], $data['endDate'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        return $this->streamCsvExport(
            $request,
            $query,
            'jurnal-umum',
            ['Nomor', 'Tanggal', 'Nominal', 'Keterangan'],
            fn (JurnalUmum $item) => [
                (string) $item->nomer,
                optional($item->tanggal)->format('Y-m-d'),
                $this->csvNumber($item->debit),
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

        return (int) $date->year >= 2000
            ? $date->toDateString()
            : null;
    }

    public function create(Request $request): View
    {
        $selectedTemplate = null;
        $initialKeterangan = old('keterangan');
        $initialRincian = null;

        if ($request->filled('template_id')) {
            try {
                $selectedTemplate = $this->templateService->getTemplateWithRincian($request->input('template_id'));
                if ($initialKeterangan === null && ! empty($selectedTemplate->keterangan)) {
                    $initialKeterangan = $selectedTemplate->keterangan;
                }
                if (! old('rincian') && $selectedTemplate->rincian->isNotEmpty()) {
                    $initialRincian = $selectedTemplate->rincian->map(fn ($item) => [
                        'coa_id' => $item->coa_id,
                        'debit' => (int) $item->debit,
                        'kredit' => (int) $item->kredit,
                        'catatan' => $item->catatan ?? '',
                    ])->toArray();
                }
            } catch (Throwable) {
                // If template not found, continue without template
            }
        }

        return view('bukubesar.jurnal-umum.create', [
            'page' => 'app',
            'coaOptions' => $this->jurnalUmumService->getCoaOptions(),
            'templateOptions' => $this->templateService->getTemplateOptions(),
            'selectedTemplate' => $selectedTemplate,
            'initialKeterangan' => $initialKeterangan ?? '',
            'initialRincian' => $initialRincian,
        ]);
    }

    public function store(StoreJurnalUmumRequest $request): RedirectResponse
    {
        $jurnalUmum = DB::transaction(fn () => $this->jurnalUmumService->create($request->validated()));

        if ($request->input('action') === 'save_print') {
            return redirect()->route('bukubesar.jurnal-umum.print', $jurnalUmum);
        }

        return redirect()
            ->route('bukubesar.jurnal-umum.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$jurnalUmum->nomer);
    }

    public function importForm(): View
    {
        return view('bukubesar.jurnal-umum.import', [
            'page' => 'app',
            'phpUploadMaxFileSize' => ini_get('upload_max_filesize') ?: 'tidak diketahui',
            'phpPostMaxSize' => ini_get('post_max_size') ?: 'tidak diketahui',
        ]);
    }

    public function importTemplate(): BinaryFileResponse
    {
        $template = $this->jurnalUmumImportService->buildTemplate();

        return response()
            ->download(
                $template['output_path'],
                $template['download_name'],
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function import(ImportJurnalUmumRequest $request): RedirectResponse
    {
        try {
            $result = DB::transaction(
                fn () => $this->jurnalUmumImportService->importFromXlsx($request->file('file'))
            );
        } catch (JurnalUmumImportException $exception) {
            return back()->withErrors(['file' => $exception->errors()]);
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->withErrors(['file' => 'File gagal diproses. Pastikan file sesuai template.']);
        }

        return redirect()
            ->route('bukubesar.jurnal-umum.index')
            ->with('success', sprintf('Import berhasil. %d jurnal dibuat (%s).', $result['created'], implode(', ', $result['nomer'])));
    }

    public function edit(JurnalUmum $jurnalUmum): View
    {
        return view('bukubesar.jurnal-umum.edit', [
            'page' => 'app',
            'jurnalUmum' => $jurnalUmum->load('rincian.coa'),
            'coaOptions' => $this->jurnalUmumService->getCoaOptions(),
        ]);
    }

    public function update(UpdateJurnalUmumRequest $request, JurnalUmum $jurnalUmum): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->jurnalUmumService->update($jurnalUmum, $request->validated()));

        return redirect()
            ->route('bukubesar.jurnal-umum.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(JurnalUmum $jurnalUmum): RedirectResponse
    {
        $nomer = $jurnalUmum->nomer;

        DB::transaction(fn () => $this->jurnalUmumService->delete($jurnalUmum));

        return redirect()
            ->route('bukubesar.jurnal-umum.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(JurnalUmum $jurnalUmum): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('bukubesar.jurnal-umum.print', [
            'page' => 'app',
            'jurnalUmum' => $jurnalUmum->load('rincian.coa'),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }
}
