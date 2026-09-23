<?php

namespace Tests\Feature;

use App\Http\Requests\Kasbank\BukuBankRequest;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Services\Kasbank\BukuBankService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BukuBankTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->nullable();
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
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

    public function test_buku_bank_only_uses_leaf_kasbank_accounts_and_calculates_balances(): void
    {
        $parent = $this->createCoa('100.00', 'Kas Parent', 'Kasbank');
        $cash = $this->createCoa('100.01', 'Kas Lama', 'kAsBaNk', $parent->id, 0);
        $revenue = $this->createCoa('400.01', 'Pendapatan', 'Pendapatan');

        $this->postLedgerRow($cash, '2026-04-30', 'D', 100, 'AWAL-D');
        $this->postLedgerRow($cash, '2026-04-30', 'K', 25, 'AWAL-K');
        $this->postLedgerRow($cash, '2026-05-02', 'D', 40, 'MASUK');
        $this->postLedgerRow($cash, '2026-05-03', 'K', 10, 'KELUAR');
        $this->postLedgerRow($parent, '2026-05-02', 'D', 999, 'PARENT');
        $this->postLedgerRow($revenue, '2026-05-02', 'D', 999, 'NON-KASBANK');

        $result = app(BukuBankService::class)->getBukuBank(
            '2026-05-01',
            '2026-05-31',
            [$cash->id, $parent->id, $revenue->id],
        );

        $this->assertCount(1, $result);
        $this->assertSame($cash->id, $result->first()['coa_id']);
        $this->assertSame(['SALDO AWAL', 'Jurnal Umum', 'Jurnal Umum'], $result->first()['rows']->pluck('sumber_transaksi')->all());
        $this->assertSame([75.0, 115.0, 105.0], $result->first()['rows']->pluck('saldo_berjalan')->all());
    }

    public function test_search_only_returns_leaf_kasbank_accounts_matching_keyword(): void
    {
        $parent = $this->createCoa('110.00', 'Bank Parent', 'Kasbank');
        $leaf = $this->createCoa('110.01', 'Bank Operasional', 'Kasbank', $parent->id);
        $this->createCoa('410.01', 'Pendapatan Operasional', 'Pendapatan');

        $result = app(BukuBankService::class)->searchCoaOptions('Operasional');

        $this->assertCount(1, $result);
        $this->assertSame($leaf->id, $result->first()['id']);
    }

    public function test_page_options_include_all_leaf_kasbank_accounts(): void
    {
        $parent = $this->createCoa('110.00', 'Bank Parent', 'Kasbank');
        $firstLeaf = $this->createCoa('110.01', 'Bank Pertama', 'Kasbank', $parent->id);
        $secondLeaf = $this->createCoa('110.02', 'Bank Kedua', 'Kasbank', $parent->id, 0);
        $this->createCoa('410.01', 'Pendapatan', 'Pendapatan');

        $result = app(BukuBankService::class)->getCoaOptions();

        $this->assertSame([$firstLeaf->id, $secondLeaf->id], $result->pluck('id')->all());
    }

    public function test_filter_rejects_an_end_date_before_the_start_date(): void
    {
        $request = new BukuBankRequest;
        $validator = Validator::make([
            'startDate' => '2026-05-31',
            'endDate' => '2026-05-01',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('endDate', $validator->errors()->toArray());
    }

    private function createCoa(
        string $code,
        string $name,
        string $type,
        ?int $parentId = null,
        int $active = 1,
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => $active,
            'parent_coa' => $parentId,
            'tipe_coa' => $type,
            'kode' => $code,
            'nama' => $name,
            'is_postable' => true,
        ]);
    }

    private function postLedgerRow(Coa $coa, string $date, string $mutation, float $amount, string $number): void
    {
        BukuBesar::query()->create([
            'coa_id' => $coa->id,
            'tanggal' => $date,
            'nomer' => $number,
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => $amount,
            'tipe_mutasi' => $mutation,
            'keterangan' => $number,
        ]);
    }
}
