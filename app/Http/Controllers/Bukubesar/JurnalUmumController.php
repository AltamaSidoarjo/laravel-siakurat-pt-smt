<?php

namespace App\Http\Controllers\Bukubesar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bukubesar\StoreJurnalUmumRequest;
use App\Http\Requests\Bukubesar\UpdateJurnalUmumRequest;
use App\Models\JurnalUmum;
use App\Services\Bukubesar\JurnalUmumService;
use App\Services\PreferensiPerusahaanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class JurnalUmumController extends Controller
{
    public function __construct(
        private readonly JurnalUmumService $jurnalUmumService,
        private readonly PreferensiPerusahaanService $preferensiPerusahaanService,
    ) {
    }

    public function index(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return view('bukubesar.jurnal-umum.index', [
            'page' => 'app',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function loadData(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = $this->jurnalUmumService->getIndexQuery($startDate, $endDate);

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn (JurnalUmum $jurnalUmum) => optional($jurnalUmum->tanggal)->format('Y-m-d'))
            ->editColumn('debit', fn (JurnalUmum $jurnalUmum) => number_format((float) $jurnalUmum->debit, 0, ',', '.'))
            ->filterColumn('tanggal', function ($query, $keyword) {
                $query->whereDate('tanggal', $keyword);
            })
            ->addColumn('nomer_link', fn (JurnalUmum $jurnalUmum) => route('bukubesar.jurnal-umum.edit', $jurnalUmum))
            ->toJson();
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
        } catch (\Throwable) {
            return null;
        }

        if ($date->format('Y-m-d') !== trim($value)) {
            return null;
        }

        return (int) $date->year >= 2000
            ? $date->toDateString()
            : null;
    }

    public function create(): View
    {
        return view('bukubesar.jurnal-umum.create', [
            'page' => 'app',
            'coaOptions' => $this->jurnalUmumService->getCoaOptions(),
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
            'namaPetugas' => session('auth.preview_user.username', '(Nama Petugas)'),
            'ttdDirektur' => $printIdentity['ttdDirektur'],
            'ttdKabag' => $printIdentity['ttdKabag'],
        ]);
    }
}
