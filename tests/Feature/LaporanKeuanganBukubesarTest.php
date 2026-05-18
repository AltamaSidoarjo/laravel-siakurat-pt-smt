<?php

namespace Tests\Feature;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Services\Laporan\LaporanKeuanganService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanKeuanganBukubesarTest extends TestCase
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
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function test_bukubesar_keeps_leaf_coa_options_and_shows_zero_opening_balance_row(): void
    {
        $inactiveLeaf = Coa::query()->create([
            'status_aktif' => 0,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.02',
            'nama' => 'Kas Lama',
            'is_postable' => true,
        ]);

        $zeroOpeningLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.03',
            'nama' => 'Kas Nol',
            'is_postable' => true,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $inactiveLeaf->id,
            'tanggal' => '2026-04-15',
            'nomer' => 'BB-001',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 100,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Saldo awal debit',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $inactiveLeaf->id,
            'tanggal' => '2026-04-20',
            'nomer' => 'BB-002',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 40,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Saldo awal kredit',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $inactiveLeaf->id,
            'tanggal' => '2026-05-10',
            'nomer' => 'BB-003',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 10,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Mutasi periode',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $zeroOpeningLeaf->id,
            'tanggal' => '2026-04-12',
            'nomer' => 'BB-004',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 50,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Saldo awal debit',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $zeroOpeningLeaf->id,
            'tanggal' => '2026-04-18',
            'nomer' => 'BB-005',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 50,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Saldo awal kredit',
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getBukubesar('2026-05-01', '2026-05-31');

        $this->assertContains($inactiveLeaf->id, collect($result['coa'])->pluck('id')->all());

        $inactiveRows = collect($result['data'])->firstWhere('coa_id', $inactiveLeaf->id);
        $this->assertNotNull($inactiveRows);
        $this->assertSame('SALDO AWAL', $inactiveRows['rows'][0]['sumber_transaksi']);
        $this->assertSame(60.0, $inactiveRows['rows'][0]['saldo_berjalan']);

        $zeroOpeningRows = collect($result['data'])->firstWhere('coa_id', $zeroOpeningLeaf->id);
        $this->assertNotNull($zeroOpeningRows);
        $this->assertCount(1, $zeroOpeningRows['rows']);
        $this->assertSame('SALDO AWAL', $zeroOpeningRows['rows'][0]['sumber_transaksi']);
        $this->assertSame('', $zeroOpeningRows['rows'][0]['nomer']);
        $this->assertSame(0, $zeroOpeningRows['rows'][0]['debit']);
        $this->assertSame(0, $zeroOpeningRows['rows'][0]['kredit']);
        $this->assertSame(0.0, $zeroOpeningRows['rows'][0]['saldo_berjalan']);
    }
}
