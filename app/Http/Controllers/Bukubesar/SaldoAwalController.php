<?php

namespace App\Http\Controllers\Bukubesar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bukubesar\StoreSaldoAwalRequest;
use App\Http\Requests\Bukubesar\UpdateSaldoAwalRequest;
use App\Models\SaldoAwal;
use App\Services\Bukubesar\SaldoAwalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SaldoAwalController extends Controller
{
    public function __construct(
        private readonly SaldoAwalService $saldoAwalService,
    ) {
    }

    public function index(): View
    {
        $saldoAwalList = $this->saldoAwalService->getIndexQuery()->get();

        return view('bukubesar.saldo-awal.index', [
            'page' => 'app',
            'saldoAwalList' => $saldoAwalList,
        ]);
    }

    public function create(): View
    {
        // Default cut-off date: 31 Desember tahun sebelumnya
        $defaultCutoff = Carbon::now()->subYear()->endOfYear()->toDateString();
        $suggestedNomer = $this->saldoAwalService->generateNomer($defaultCutoff);

        return view('bukubesar.saldo-awal.create', [
            'page' => 'app',
            'coaOptions' => $this->saldoAwalService->getCoaOptions(),
            'defaultCutoff' => $defaultCutoff,
            'suggestedNomer' => $suggestedNomer,
        ]);
    }

    public function store(StoreSaldoAwalRequest $request): RedirectResponse
    {
        $userId = (int) auth()->id();

        $saldoAwal = DB::transaction(fn () => $this->saldoAwalService->create($request->validated(), $userId));

        $statusText = $saldoAwal->isLocked() ? 'disimpan dan dikunci (terposting ke Buku Besar)' : 'disimpan sebagai draft';

        return redirect()
            ->route('bukubesar.saldo-awal.index')
            ->with('success', "Saldo Awal {$saldoAwal->nomer} berhasil {$statusText}.");
    }

    public function edit(SaldoAwal $saldoAwal): View
    {
        return view('bukubesar.saldo-awal.edit', [
            'page' => 'app',
            'saldoAwal' => $saldoAwal->load('rincian.coa'),
            'coaOptions' => $this->saldoAwalService->getCoaOptions(),
        ]);
    }

    public function update(UpdateSaldoAwalRequest $request, SaldoAwal $saldoAwal): RedirectResponse
    {
        $userId = (int) auth()->id();

        $updated = DB::transaction(fn () => $this->saldoAwalService->update($saldoAwal, $request->validated(), $userId));

        $statusText = $updated->isLocked() ? 'diperbarui dan dikunci (terposting ke Buku Besar)' : 'diperbarui sebagai draft';

        return redirect()
            ->route('bukubesar.saldo-awal.index')
            ->with('success', "Saldo Awal {$updated->nomer} berhasil {$statusText}.");
    }

    public function lock(Request $request, SaldoAwal $saldoAwal): RedirectResponse
    {
        $userId = (int) auth()->id();

        DB::transaction(fn () => $this->saldoAwalService->lock($saldoAwal, $userId));

        return redirect()
            ->route('bukubesar.saldo-awal.index')
            ->with('success', "Saldo Awal {$saldoAwal->nomer} berhasil dikunci dan diposting ke Buku Besar.");
    }

    public function unlock(Request $request, SaldoAwal $saldoAwal): RedirectResponse
    {
        $userId = (int) auth()->id();

        DB::transaction(fn () => $this->saldoAwalService->unlock($saldoAwal, $userId));

        return redirect()
            ->route('bukubesar.saldo-awal.index')
            ->with('success', "Kunci Saldo Awal {$saldoAwal->nomer} berhasil dibuka (ditarik kembali ke status draft).");
    }

    public function destroy(SaldoAwal $saldoAwal): RedirectResponse
    {
        $nomer = $saldoAwal->nomer;

        DB::transaction(fn () => $this->saldoAwalService->delete($saldoAwal));

        return redirect()
            ->route('bukubesar.saldo-awal.index')
            ->with('success', "Saldo Awal {$nomer} berhasil dihapus.");
    }
}

