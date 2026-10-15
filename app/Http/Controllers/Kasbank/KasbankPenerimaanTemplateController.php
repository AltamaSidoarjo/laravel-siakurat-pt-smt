<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\StoreKasbankPenerimaanTemplateRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPenerimaanTemplateRequest;
use App\Models\KasbankPenerimaanTemplate;
use App\Services\Kasbank\KasbankPenerimaanTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class KasbankPenerimaanTemplateController extends Controller
{
    public function __construct(
        private readonly KasbankPenerimaanTemplateService $templateService,
    ) {}

    public function index(): View
    {
        return view('kasbank.penerimaan-template.index', [
            'page' => 'app',
        ]);
    }

    public function loadData(): JsonResponse
    {
        $query = $this->templateService->getIndexQuery();

        return DataTables::eloquent($query)
            ->addColumn('coa_display', fn (KasbankPenerimaanTemplate $template) => $template->coa?->nama ?? '-')
            ->addColumn('rincian_count', fn (KasbankPenerimaanTemplate $template) => (int) $template->rincian_count)
            ->addColumn('total_nominal', fn (KasbankPenerimaanTemplate $template) => number_format((float) $template->rincian->sum('nominal'), 0, ',', '.'))
            ->addColumn('action', function (KasbankPenerimaanTemplate $template) {
                $useUrl = route('kasbank.penerimaan.create', ['template_id' => $template->id]);
                $editUrl = route('kasbank.penerimaan-template.edit', $template);
                $deleteUrl = route('kasbank.penerimaan-template.destroy', $template);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                return <<<HTML
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <a href="{$useUrl}" class="btn btn-sm btn-outline-success text-nowrap" title="Buat Penerimaan Dari Template Ini">
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
        return view('kasbank.penerimaan-template.create', [
            'page' => 'app',
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function store(StoreKasbankPenerimaanTemplateRequest $request): RedirectResponse
    {
        $template = $this->templateService->create($request->validated());

        return redirect()
            ->route('kasbank.penerimaan-template.index')
            ->with('success', 'Template Kasbank Penerimaan berhasil dibuat: '.$template->nama);
    }

    public function edit(KasbankPenerimaanTemplate $kasbankPenerimaanTemplate): View
    {
        return view('kasbank.penerimaan-template.edit', [
            'page' => 'app',
            'template' => $kasbankPenerimaanTemplate->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPenerimaanTemplateRequest $request, KasbankPenerimaanTemplate $kasbankPenerimaanTemplate): RedirectResponse
    {
        $updated = $this->templateService->update($kasbankPenerimaanTemplate, $request->validated());

        return redirect()
            ->route('kasbank.penerimaan-template.index')
            ->with('success', 'Template Kasbank Penerimaan berhasil diperbarui: '.$updated->nama);
    }

    public function destroy(KasbankPenerimaanTemplate $kasbankPenerimaanTemplate): RedirectResponse
    {
        $nama = $kasbankPenerimaanTemplate->nama;
        $this->templateService->delete($kasbankPenerimaanTemplate);

        return redirect()
            ->route('kasbank.penerimaan-template.index')
            ->with('success', 'Template Kasbank Penerimaan berhasil dihapus: '.$nama);
    }

    public function detail(KasbankPenerimaanTemplate $kasbankPenerimaanTemplate): JsonResponse
    {
        $kasbankPenerimaanTemplate->load([
            'coa:id,kode,nama',
            'rincian' => fn ($q) => $q->orderBy('urutan')->orderBy('id'),
            'rincian.coa:id,kode,nama',
        ]);

        return response()->json([
            'id' => $kasbankPenerimaanTemplate->id,
            'nama' => $kasbankPenerimaanTemplate->nama,
            'coa_id' => $kasbankPenerimaanTemplate->coa_id,
            'coa_nama' => $kasbankPenerimaanTemplate->coa?->nama ?? '',
            'keterangan' => $kasbankPenerimaanTemplate->keterangan,
            'rincian' => $kasbankPenerimaanTemplate->rincian->map(fn ($item) => [
                'coa_id' => $item->coa_id,
                'coa_kode' => $item->coa?->kode ?? '',
                'coa_nama' => $item->coa?->nama ?? '',
                'nominal' => (float) $item->nominal,
                'catatan' => $item->catatan ?? '',
            ]),
        ]);
    }
}
