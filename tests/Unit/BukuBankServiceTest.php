<?php

namespace Tests\Unit;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Services\Kasbank\BukuBankService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BukuBankServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function test_report_only_uses_leaf_kasbank_coa_and_includes_inactive_accounts(): void
    {
        $parent = $this->createCoa('100', 'Kas Parent', 'Kasbank');
        $active = $this->createCoa('101', 'Kas Aktif', 'kAsBaNk', 1, $parent->id);
        $inactive = $this->createCoa('102', 'Bank Lama', 'Kasbank', 0);
        $this->createCoa('400', 'Pendapatan', 'Pendapatan');

        $options = app(BukuBankService::class)->getCoaOptions();

        $this->assertSame([$active->id, $inactive->id], $options->pluck('id')->all());
    }

    public function test_report_calculates_opening_and_running_balance_in_date_and_id_order(): void
    {
        $cash = $this->createCoa('101', 'Kas Utama', 'Kasbank');
        $this->postLedger($cash->id, '2026-08-31', 'AWAL', 'D', 100);
        $this->postLedger($cash->id, '2026-09-02', 'TRX-2A', 'K', 30);
        $this->postLedger($cash->id, '2026-09-01', 'TRX-1', 'D', 50);
        $this->postLedger($cash->id, '2026-09-02', 'TRX-2B', 'D', 10);

        $report = app(BukuBankService::class)->getReport('2026-09-01', '2026-09-30');
        $rows = $report->first()['rows'];

        $this->assertSame(['', 'TRX-1', 'TRX-2A', 'TRX-2B'], $rows->pluck('nomer')->all());
        $this->assertSame([100.0, 150.0, 120.0, 130.0], $rows->pluck('saldo_berjalan')->all());
    }

    public function test_empty_filter_shows_all_kasbank_accounts_and_selected_filter_limits_accounts(): void
    {
        $cash = $this->createCoa('101', 'Kas', 'Kasbank');
        $bank = $this->createCoa('102', 'Bank', 'Kasbank');

        $service = app(BukuBankService::class);

        $this->assertSame([$cash->id, $bank->id], $service->getReport('2026-09-01', '2026-09-30')->pluck('coa_id')->all());
        $this->assertSame([$bank->id], $service->getReport('2026-09-01', '2026-09-30', [$bank->id])->pluck('coa_id')->all());
        $this->assertSame('SALDO AWAL', $service->getReport('2026-09-01', '2026-09-30', [$bank->id])->first()['rows']->first()['sumber_transaksi']);
    }

    private function createCoa(string $code, string $name, string $type, int $active = 1, ?int $parentId = null): Coa
    {
        return Coa::query()->create([
            'status_aktif' => $active,
            'parent_coa' => $parentId,
            'tipe_coa' => $type,
            'kode' => $code,
            'nama' => $name,
            'is_postable' => true,
        ]);
    }

    private function postLedger(int $coaId, string $date, string $number, string $mutation, float $amount): void
    {
        BukuBesar::query()->create([
            'coa_id' => $coaId,
            'tanggal' => $date,
            'nomer' => $number,
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => $amount,
            'tipe_mutasi' => $mutation,
            'keterangan' => $number,
        ]);
    }
}
