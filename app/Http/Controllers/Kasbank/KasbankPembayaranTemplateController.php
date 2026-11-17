<?php

namespace App\Http\Controllers\Kasbank;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasbank\StoreKasbankPembayaranTemplateRequest;
use App\Http\Requests\Kasbank\UpdateKasbankPembayaranTemplateRequest;
use App\Models\KasbankPembayaranTemplate;
use App\Services\Kasbank\KasbankPembayaranTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class KasbankPembayaranTemplateController extends Controller
{
    public function __construct(
        private readonly KasbankPembayaranTemplateService $templateService,
    ) {}

    public function index(): View
    {
        return view('kasbank.pembayaran-template.index', [
            'page' => 'app',
        ]);
    }

    public function loadData(): JsonResponse
    {
        $query = $this->templateService->getIndexQuery();

        return DataTables::eloquent($query)
            ->addColumn('coa_display', fn (KasbankPembayaranTemplate $template) => $template->coa?->nama ?? '-')
            ->addColumn('rincian_count', fn (KasbankPembayaranTemplate $template) => (int) $template->rincian_count)
            ->addColumn('total_nominal', fn (KasbankPembayaranTemplate $template) => number_format((float) $template->rincian->sum('nominal'), 0, ',', '.'))
            ->addColumn('action', function (KasbankPembayaranTemplate $template) {
                $useUrl = route('kasbank.pembayaran.create', ['template_id' => $template->id]);
                $editUrl = route('kasbank.pembayaran-template.edit', $template);
                $deleteUrl = route('kasbank.pembayaran-template.destroy', $template);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                return <<<HTML
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <a href="{$useUrl}" class="btn btn-sm btn-outline-success text-nowrap" title="Buat Pembayaran Dari Template Ini">
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
        return view('kasbank.pembayaran-template.create', [
            'page' => 'app',
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function store(StoreKasbankPembayaranTemplateRequest $request): RedirectResponse
    {
        $template = $this->templateService->create($request->validated());

        return redirect()
            ->route('kasbank.pembayaran-template.index')
            ->with('success', 'Template Kasbank Pembayaran berhasil dibuat: '.$template->nama);
    }

    public function edit(KasbankPembayaranTemplate $kasbankPembayaranTemplate): View
    {
        return view('kasbank.pembayaran-template.edit', [
            'page' => 'app',
            'template' => $kasbankPembayaranTemplate->load(['coa', 'rincian.coa']),
            'coaOptions' => $this->templateService->getCoaOptions(),
        ]);
    }

    public function update(UpdateKasbankPembayaranTemplateRequest $request, KasbankPembayaranTemplate $kasbankPembayaranTemplate): RedirectResponse
    {
        $updated = $this->templateService->update($kasbankPembayaranTemplate, $request->validated());

        return redirect()
            ->route('kasbank.pembayaran-template.index')
            ->with('success', 'Template Kasbank Pembayaran berhasil diperbarui: '.$updated->nama);
    }

    public function destroy(KasbankPembayaranTemplate $kasbankPembayaranTemplate): RedirectResponse
    {
        $nama = $kasbankPembayaranTemplate->nama;
        $this->templateService->delete($kasbankPembayaranTemplate);

        return redirect()
            ->route('kasbank.pembayaran-template.index')
            ->with('success', 'Template Kasbank Pembayaran berhasil dihapus: '.$nama);
    }

    public function detail(KasbankPembayaranTemplate $kasbankPembayaranTemplate): JsonResponse
    {
        $kasbankPembayaranTemplate->load([
            'coa:id,kode,nama',
            'rincian' => fn ($q) => $q->orderBy('urutan')->orderBy('id'),
            'rincian.coa:id,kode,nama',
        ]);

        return response()->json([
            'id' => $kasbankPembayaranTemplate->id,
            'nama' => $kasbankPembayaranTemplate->nama,
            'coa_id' => $kasbankPembayaranTemplate->coa_id,
            'coa_nama' => $kasbankPembayaranTemplate->coa?->nama ?? '',
            'keterangan' => $kasbankPembayaranTemplate->keterangan,
            'rincian' => $kasbankPembayaranTemplate->rincian->map(fn ($item) => [
                'coa_id' => $item->coa_id,
                'coa_kode' => $item->coa?->kode ?? '',
                'coa_nama' => $item->coa?->nama ?? '',
                'nominal' => (float) $item->nominal,
                'catatan' => $item->catatan ?? '',
            ]),
        ]);
    }
}
