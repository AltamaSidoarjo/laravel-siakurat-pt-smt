<?php

namespace App\Http\Controllers\Bukubesar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bukubesar\StoreJurnalUmumTemplateRequest;
use App\Http\Requests\Bukubesar\UpdateJurnalUmumTemplateRequest;
use App\Models\JurnalUmumTemplate;
use App\Services\Bukubesar\JurnalUmumTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class JurnalUmumTemplateController extends Controller
{
    public function __construct(
        private readonly JurnalUmumTemplateService $templateService,
    ) {}

    public function index(): View
    {
        return view('bukubesar.jurnal-umum-template.index', [
            'page' => 'app',
        ]);
    }

    public function loadData(): JsonResponse
    {
        $query = $this->templateService->getIndexQuery();

        return DataTables::eloquent($query)
            ->addColumn('rincian_count', fn (JurnalUmumTemplate $template) => (int) $template->rincian_count)
            ->addColumn('total_debit', fn (JurnalUmumTemplate $template) => number_format((float) $template->rincian->sum('debit'), 0, ',', '.'))
            ->addColumn('total_kredit', fn (JurnalUmumTemplate $template) => number_format((float) $template->rincian->sum('kredit'), 0, ',', '.'))
            ->addColumn('action', function (JurnalUmumTemplate $template) {
                $useUrl = route('bukubesar.jurnal-umum.create', ['template_id' => $template->id]);
                $editUrl = route('bukubesar.jurnal-umum-template.edit', $template);
                $deleteUrl = route('bukubesar.jurnal-umum-template.destroy', $template);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                return <<<HTML
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <a href="{$useUrl}" class="btn btn-sm btn-outline-success text-nowrap" title="Buat Jurnal Dari Template Ini">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Gunakan
                        </a>
                        <a href="{$editUrl}" class="btn btn-sm btn-warning text-dark" title="Edit Template">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                        <form action="{$deleteUrl}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus template ini?')">
                            {$csrf}
                            {$method}
                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus Template">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </form>
                    </div>
                HTML;
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('bukubesar.jurnal-umum-template.create', [
            'page' => 'app',
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function store(StoreJurnalUmumTemplateRequest $request): RedirectResponse
    {
        $template = $this->templateService->create($request->validated());

        return redirect()
            ->route('bukubesar.jurnal-umum-template.index')
            ->with('success', 'Template Jurnal berhasil dibuat: '.$template->nama);
    }

    public function edit(JurnalUmumTemplate $jurnalUmumTemplate): View
    {
        return view('bukubesar.jurnal-umum-template.edit', [
            'page' => 'app',
            'template' => $jurnalUmumTemplate->load('rincian.coa'),
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function update(UpdateJurnalUmumTemplateRequest $request, JurnalUmumTemplate $jurnalUmumTemplate): RedirectResponse
    {
        $updated = $this->templateService->update($jurnalUmumTemplate, $request->validated());

        return redirect()
            ->route('bukubesar.jurnal-umum-template.index')
            ->with('success', 'Template Jurnal berhasil diperbarui: '.$updated->nama);
    }

    public function destroy(JurnalUmumTemplate $jurnalUmumTemplate): RedirectResponse
    {
        $nama = $jurnalUmumTemplate->nama;
        $this->templateService->delete($jurnalUmumTemplate);

        return redirect()
            ->route('bukubesar.jurnal-umum-template.index')
            ->with('success', 'Template Jurnal berhasil dihapus: '.$nama);
    }

    public function detail(JurnalUmumTemplate $jurnalUmumTemplate): JsonResponse
    {
        $jurnalUmumTemplate->load(['rincian' => fn ($q) => $q->orderBy('urutan')->orderBy('id'), 'rincian.coa']);

        return response()->json([
            'id' => $jurnalUmumTemplate->id,
            'nama' => $jurnalUmumTemplate->nama,
            'keterangan' => $jurnalUmumTemplate->keterangan,
            'rincian' => $jurnalUmumTemplate->rincian->map(fn ($item) => [
                'coa_id' => $item->coa_id,
                'coa_kode' => $item->coa?->kode ?? '',
                'coa_nama' => $item->coa?->nama ?? '',
                'debit' => (float) $item->debit,
                'kredit' => (float) $item->kredit,
                'catatan' => $item->catatan ?? '',
            ]),
        ]);
    }
}
