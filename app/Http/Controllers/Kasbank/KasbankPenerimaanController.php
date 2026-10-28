<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Concerns\StreamsCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\StoreKasbankPenerimaanRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPenerimaanRequest;
use App\Models\KasbankPenerimaan;
use App\Services\Kasbank\KasbankPenerimaanService;
use App\Services\Kasbank\KasbankPenerimaanTemplateService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class KasbankPenerimaanController extends Controller
{
    use StreamsCsvExport;

    public function __construct(
        private readonly KasbankPenerimaanService $kasbankPenerimaanService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
        private readonly KasbankPenerimaanTemplateService $templateService,
    ) {}

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('kasbank.penerimaan.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $this->kasbankPenerimaanService->getSummary($startDate, $endDate),
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $user = $request->user();
        $canUpdate = $user?->hasModuleAccess('kasbank.penerimaan', 'update') ?? false;
        $canDelete = $user?->hasModuleAccess('kasbank.penerimaan', 'delete') ?? false;

        $query = $this->kasbankPenerimaanService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (KasbankPenerimaan $kasbankPenerimaan) => optional($kasbankPenerimaan->tanggal)->format('d-m-Y'))
            ->editColumn('total', fn (KasbankPenerimaan $kasbankPenerimaan) => number_format((float) $kasbankPenerimaan->total, 0, ',', '.'))
            ->addColumn('coa_display', fn (KasbankPenerimaan $kasbankPenerimaan) => $kasbankPenerimaan->coa ? ($kasbankPenerimaan->coa->kode . ' - ' . $kasbankPenerimaan->coa->nama) : '-')
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
                        $query->join('coa', 'kasbank_penerimaan.coa_id', '=', 'coa.id')
                            ->orderBy('coa.nama', $dir)
                            ->select('kasbank_penerimaan.*');
                    } elseif ($colName === 'total') {
                        $query->orderBy('total', $dir);
                    } elseif ($colName === 'keterangan') {
                        $query->orderBy('keterangan', $dir);
                    }
                }
            })
            ->addColumn('nomer_link', fn (KasbankPenerimaan $kasbankPenerimaan) => route('kasbank.penerimaan.edit', $kasbankPenerimaan))
            ->addColumn('edit_url', fn (KasbankPenerimaan $kasbankPenerimaan) => route('kasbank.penerimaan.edit', $kasbankPenerimaan))
            ->addColumn('print_url', fn (KasbankPenerimaan $kasbankPenerimaan) => route('kasbank.penerimaan.print', $kasbankPenerimaan))
            ->addColumn('destroy_url', fn (KasbankPenerimaan $kasbankPenerimaan) => route('kasbank.penerimaan.destroy', $kasbankPenerimaan))
            ->addColumn('can_update', fn () => $canUpdate)
            ->addColumn('can_delete', fn () => $canDelete)
            ->toJson();
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate(['startDate' => ['required', 'date_format:Y-m-d'], 'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate']]);

        return $this->streamCsvExport($request, $this->kasbankPenerimaanService->getIndexQuery($data['startDate'], $data['endDate']), 'kasbank-penerimaan', ['Nomor', 'Tanggal', 'Bank', 'Nominal', 'Keterangan'], fn (KasbankPenerimaan $item) => [(string) $item->nomer, optional($item->tanggal)->format('Y-m-d'), (string) ($item->coa?->nama ?? '-'), $this->csvNumber($item->total), (string) $item->keterangan]);
    }

    public function create(Request $request): View
    {
        $selectedTemplate = null;
        $initialKeterangan = old('keterangan');
        $initialCoaId = old('coa_id');
        $initialRincian = null;

        if ($request->filled('template_id')) {
            try {
                $selectedTemplate = $this->templateService->getTemplateWithRincian($request->input('template_id'));
                if ($initialKeterangan === null && ! empty($selectedTemplate->keterangan)) {
                    $initialKeterangan = $selectedTemplate->keterangan;
                }
                if ($initialCoaId === null && ! empty($selectedTemplate->coa_id)) {
                    $initialCoaId = $selectedTemplate->coa_id;
                }
                if (! old('rincian') && $selectedTemplate->rincian->isNotEmpty()) {
                    $initialRincian = $selectedTemplate->rincian->map(fn ($item) => [
                        'coa_id' => $item->coa_id,
                        'nominal' => (int) $item->nominal,
                        'catatan' => $item->catatan ?? '',
                    ])->toArray();
                }
            } catch (\Throwable) {
                // If template not found, continue without template
            }
        }

        return view('kasbank.penerimaan.create', [
            'page' => 'app',
            'coaOptions' => $this->kasbankPenerimaanService->getCoaOptions(),
            'templateOptions' => $this->templateService->getTemplateOptions(),
            'selectedTemplate' => $selectedTemplate,
            'initialKeterangan' => $initialKeterangan ?? '',
            'initialCoaId' => $initialCoaId,
            'initialRincian' => $initialRincian,
        ]);
    }

    public function store(StoreKasbankPenerimaanRequest $request): RedirectResponse
    {
        $kasbankPenerimaan = DB::transaction(fn () => $this->kasbankPenerimaanService->create($request->validated()));

        if ($request->input('action') === 'save_print') {
            return redirect()->route('kasbank.penerimaan.print', $kasbankPenerimaan);
        }

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil dibuat. Nomer: '.$kasbankPenerimaan->nomer);
    }

    public function edit(KasbankPenerimaan $kasbankPenerimaan): View
    {
        return view('kasbank.penerimaan.edit', [
            'page' => 'app',
            'kasbankPenerimaan' => $kasbankPenerimaan->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->kasbankPenerimaanService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPenerimaanRequest $request, KasbankPenerimaan $kasbankPenerimaan): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->kasbankPenerimaanService->update($kasbankPenerimaan, $request->validated()));

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil diperbarui. Nomer: '.$updated->nomer);
    }

    public function destroy(KasbankPenerimaan $kasbankPenerimaan): RedirectResponse
    {
        $nomer = $kasbankPenerimaan->nomer;

        DB::transaction(fn () => $this->kasbankPenerimaanService->delete($kasbankPenerimaan));

        return redirect()
            ->route('kasbank.penerimaan.index')
            ->with('success', 'Data berhasil dihapus. Nomer: '.$nomer);
    }

    public function print(KasbankPenerimaan $kasbankPenerimaan): View
    {
        $printIdentity = $this->preferensiPerusahaanService->getPrintIdentity();

        return view('kasbank.penerimaan.print', [
            'page' => 'app',
            'kasbankPenerimaan' => $kasbankPenerimaan->load(['coa', 'rincian.coa']),
            'namaRumahSakit' => $printIdentity['namaRumahSakit'],
            'printedAt' => Carbon::now(),
            'namaPetugas' => auth()->user()?->name ?? '(Nama Petugas)',
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
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
}
