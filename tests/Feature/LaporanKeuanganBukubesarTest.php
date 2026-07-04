<?php

namespace Tests\Feature;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Services\Laporan\LaporanKeuanganService;
use ReflectionMethod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporanKeuanganBukubesarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('setting_rba');
        Schema::dropIfExists('tipe_coa');
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

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
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

        Schema::create('setting_rba', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->integer('tahun');
            $table->decimal('total_nominal', 15, 2)->default(0);
            $table->string('catatan')->nullable();
            $table->boolean('is_rinci')->default(false);
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
        $result = $service->getBukubesar('2026-05-01', '2026-05-31', [$inactiveLeaf->id, $zeroOpeningLeaf->id]);

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

    public function test_bukubesar_returns_empty_data_when_no_coa_is_selected(): void
    {
        Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.01',
            'nama' => 'Kas Operasional',
            'is_postable' => true,
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getBukubesar('2026-05-01', '2026-05-31', []);

        $this->assertCount(0, $result['coa']);
        $this->assertCount(0, $result['data']);
    }

    public function test_search_bukubesar_coa_options_filters_leaf_accounts_by_keyword(): void
    {
        $parent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.00',
            'nama' => 'Kas Parent',
            'is_postable' => false,
        ]);

        $matchingLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '100.01',
            'nama' => 'Kas Operasional',
            'is_postable' => true,
        ]);

        Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '200.01',
            'nama' => 'Bank Lain',
            'is_postable' => true,
        ]);

        $service = app(LaporanKeuanganService::class);

        $byName = $service->searchBukubesarCoaOptions('Operasional');
        $byCode = $service->searchBukubesarCoaOptions('100.01');

        $this->assertCount(1, $byName);
        $this->assertSame($matchingLeaf->id, $byName->first()['id']);
        $this->assertCount(1, $byCode);
        $this->assertSame($matchingLeaf->id, $byCode->first()['id']);
    }

    public function test_neraca_standard_accumulates_laba_tahun_berjalan_since_start_of_year(): void
    {
        foreach (['Kasbank', 'Ekuitas', 'Pendapatan', 'Beban'] as $namaTipeCoa) {
            \App\Models\TipeCoa::query()->create([
                'nama' => $namaTipeCoa,
                'status_aktif' => 1,
            ]);
        }

        $kas = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.00',
            'nama' => 'Kas',
            'is_postable' => false,
        ]);

        $kasOperasional = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $kas->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.01',
            'nama' => 'Kas Operasional',
            'is_postable' => true,
        ]);

        $ekuitas = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Ekuitas',
            'kode' => '310.00',
            'nama' => 'Modal',
            'is_postable' => true,
        ]);

        $pendapatanParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.00',
            'nama' => 'Pendapatan',
            'is_postable' => false,
        ]);

        $pendapatanLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanParent->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01',
            'nama' => 'Pendapatan Rawat Jalan',
            'is_postable' => true,
        ]);

        $bebanLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Beban',
            'kode' => '510.01',
            'nama' => 'Beban Operasional',
            'is_postable' => true,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $kasOperasional->id,
            'tanggal' => '2026-01-02',
            'nomer' => 'BB-MODAL-KAS-001',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 500,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Setoran modal ke kas',
        ]);

        BukuBesar::query()->create([
            'coa_id' => $ekuitas->id,
            'tanggal' => '2026-01-02',
            'nomer' => 'BB-EKUITAS-001',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 500,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Setoran modal awal',
        ]);

        foreach ([
            ['2025-12-31', $pendapatanLeaf->id, 999, 'K', 'Pendapatan tahun lalu'],
            ['2026-01-10', $pendapatanLeaf->id, 100, 'K', 'Pendapatan Januari'],
            ['2026-02-15', $pendapatanLeaf->id, 200, 'K', 'Pendapatan Februari'],
            ['2026-05-19', $pendapatanLeaf->id, 50, 'K', 'Pendapatan Mei'],
            ['2026-05-19', $pendapatanParent->id, 1000, 'K', 'Posting parent yang harus diabaikan'],
            ['2026-01-10', $bebanLeaf->id, 30, 'D', 'Beban Januari'],
            ['2026-02-15', $bebanLeaf->id, 20, 'D', 'Beban Februari'],
            ['2026-05-19', $bebanLeaf->id, 10, 'D', 'Beban Mei'],
        ] as [$tanggal, $coaId, $nominal, $tipeMutasi, $keterangan]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'tanggal' => $tanggal,
                'nomer' => 'BB-'.str_replace('-', '', $tanggal).'-'.$coaId,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipeMutasi,
                'keterangan' => $keterangan,
            ]);
        }

        foreach ([
            ['2026-01-10', 100, 'D'],
            ['2026-02-15', 200, 'D'],
            ['2026-05-19', 50, 'D'],
            ['2026-01-10', 30, 'K'],
            ['2026-02-15', 20, 'K'],
            ['2026-05-19', 10, 'K'],
        ] as [$tanggal, $nominal, $tipeMutasi]) {
            BukuBesar::query()->create([
                'coa_id' => $kasOperasional->id,
                'tanggal' => $tanggal,
                'nomer' => 'BB-KAS-'.str_replace('-', '', $tanggal).'-'.$nominal,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipeMutasi,
                'keterangan' => 'Lawan transaksi operasional',
            ]);
        }

        $service = app(LaporanKeuanganService::class);
        $result = $service->getNeracaStandard('2026-05-19');
        $labaRugiRows = $service->getLabaRugiStandard('2026-01-01', '2026-05-19');
        $labaRugiTotal = (float) $labaRugiRows
            ->where('sort_level', 1)
            ->sum(function (array $row) {
                $tipeCoa = (string) ($row['tipe_coa'] ?? '');

                if (in_array($tipeCoa, ['Pendapatan', 'Pendapatan lain'], true)) {
                    return (float) $row['nominal'];
                }

                if (in_array($tipeCoa, ['Beban Pokok Penjualan', 'Beban', 'Beban lain'], true)) {
                    return (float) $row['nominal'] * -1;
                }

                return 0;
            });

        $labaTahunBerjalanRow = collect($result['rows'])->firstWhere('nama_coa', 'Laba Tahun Berjalan');

        $this->assertNotNull($labaTahunBerjalanRow);
        $this->assertSame($labaRugiTotal, (float) $labaTahunBerjalanRow['saldo']);
        $this->assertSame($result['subtotalAktiva'], $result['subtotalPasivaEkuitas']);
    }

    public function test_neraca_per_parent_coa_returns_preorder_subtree_with_accumulated_balances(): void
    {
        foreach (['Kasbank', 'Ekuitas'] as $namaTipeCoa) {
            \App\Models\TipeCoa::query()->create([
                'nama' => $namaTipeCoa,
                'status_aktif' => 1,
            ]);
        }

        $aktivaParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.00',
            'nama' => 'Kas',
            'is_postable' => false,
        ]);

        $aktivaChild = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $aktivaParent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.01',
            'nama' => 'Kas Operasional',
            'is_postable' => false,
        ]);

        $aktivaGrandchild = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $aktivaChild->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.01.01',
            'nama' => 'Kas Kecil',
            'is_postable' => true,
        ]);

        $pasivaParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Ekuitas',
            'kode' => '310.00',
            'nama' => 'Modal',
            'is_postable' => false,
        ]);

        $pasivaChild = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pasivaParent->id,
            'tipe_coa' => 'Ekuitas',
            'kode' => '310.01',
            'nama' => 'Modal Disetor',
            'is_postable' => true,
        ]);

        foreach ([
            [$aktivaParent->id, 100, 'D', 'Posting parent aktiva'],
            [$aktivaChild->id, 50, 'D', 'Posting child aktiva'],
            [$aktivaGrandchild->id, 25, 'D', 'Posting grandchild aktiva'],
            [$pasivaParent->id, 80, 'K', 'Posting parent ekuitas'],
            [$pasivaChild->id, 20, 'K', 'Posting child ekuitas'],
        ] as [$coaId, $nominal, $tipeMutasi, $keterangan]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'tanggal' => '2026-05-21',
                'nomer' => 'BB-NERACA-'.$coaId.'-'.$nominal,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipeMutasi,
                'keterangan' => $keterangan,
            ]);
        }

        $service = app(LaporanKeuanganService::class);

        $aktivaResult = $service->getNeracaPerParentCoa('2026-05-22', $aktivaParent->id);
        $pasivaResult = $service->getNeracaPerParentCoa('2026-05-22', $pasivaParent->id);
        $aktivaRows = collect($aktivaResult['rows'])->values();
        $pasivaRows = collect($pasivaResult['rows'])->values();

        $this->assertSame([$aktivaParent->id, $aktivaChild->id, $aktivaGrandchild->id], $aktivaRows->pluck('coa_id')->all());
        $this->assertSame([0, 1, 2], $aktivaRows->pluck('level')->all());
        $this->assertTrue((bool) $aktivaRows[0]['has_children']);
        $this->assertTrue((bool) $aktivaRows[1]['has_children']);
        $this->assertFalse((bool) $aktivaRows[2]['has_children']);
        $this->assertSame(175.0, (float) $aktivaRows[0]['saldo']);
        $this->assertSame(75.0, (float) $aktivaRows[1]['saldo']);
        $this->assertSame(25.0, (float) $aktivaRows[2]['saldo']);

        $this->assertSame([$pasivaParent->id, $pasivaChild->id], $pasivaRows->pluck('coa_id')->all());
        $this->assertSame('EKUITAS', $pasivaRows[0]['tipe_coa']);
        $this->assertSame(100.0, (float) $pasivaRows[0]['saldo']);
        $this->assertSame(20.0, (float) $pasivaRows[1]['saldo']);
    }

    public function test_neraca_per_parent_coa_returns_empty_rows_for_unknown_coa(): void
    {
        \App\Models\TipeCoa::query()->create([
            'nama' => 'Kasbank',
            'status_aktif' => 1,
        ]);

        $service = app(LaporanKeuanganService::class);
        $result = $service->getNeracaPerParentCoa('2026-05-22', 999999);

        $this->assertNull($result['parent']);
        $this->assertCount(0, $result['rows']);
    }

    public function test_neraca_standard_rounds_small_float_noise_before_marking_balance(): void
    {
        $method = new ReflectionMethod(LaporanKeuanganService::class, 'ringkasStatusNeraca');
        $method->setAccessible(true);
        $result = $method->invoke(app(LaporanKeuanganService::class), 0.33, 0.32, 0.0);

        $this->assertSame(0.01, $result['selisih']);
        $this->assertTrue($result['isBalance']);
    }

    public function test_neraca_standard_marks_material_difference_as_not_balance(): void
    {
        $method = new ReflectionMethod(LaporanKeuanganService::class, 'ringkasStatusNeraca');
        $method->setAccessible(true);
        $result = $method->invoke(app(LaporanKeuanganService::class), 0.33, 0.31, 0.0);

        $this->assertSame(0.02, $result['selisih']);
        $this->assertFalse($result['isBalance']);
    }

    public function test_neraca_standard_view_shows_balance_status_using_two_decimal_difference(): void
    {
        $response = $this->view('laporan.keuangan.neraca-standard', [
            'logoRsUrl' => '',
            'namaRumahSakit' => 'RS Test',
            'page' => 'app',
            'perDate' => '2026-06-18',
            'rows' => collect(),
            'subtotalAktiva' => 0.33,
            'subtotalPasiva' => 0.32,
            'subtotalEkuitas' => 0.00,
            'subtotalPasivaEkuitas' => 0.32,
            'selisih' => 0.01,
            'isBalance' => true,
        ]);

        $response->assertSee('BALANCE | Selisih 0,01', false);
    }

    public function test_laba_rugi_standard_and_per_parent_only_count_leaf_accounts(): void
    {
        foreach (['Kasbank', 'Ekuitas', 'Pendapatan', 'Beban'] as $namaTipeCoa) {
            \App\Models\TipeCoa::query()->create([
                'nama' => $namaTipeCoa,
                'status_aktif' => 1,
            ]);
        }

        $kas = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.00',
            'nama' => 'Kas',
            'is_postable' => false,
        ]);

        $kasOperasional = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $kas->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '110.01',
            'nama' => 'Kas Operasional',
            'is_postable' => true,
        ]);

        $ekuitas = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Ekuitas',
            'kode' => '310.00',
            'nama' => 'Modal',
            'is_postable' => true,
        ]);

        $pendapatanParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.00',
            'nama' => 'Pendapatan',
            'is_postable' => false,
        ]);

        $pendapatanLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanParent->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01',
            'nama' => 'Pendapatan Operasional',
            'is_postable' => false,
        ]);

        $pendapatanGrandchildLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanLeaf->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01.01',
            'nama' => 'Pendapatan Rawat Jalan',
            'is_postable' => true,
        ]);

        $bebanParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Beban',
            'kode' => '510.00',
            'nama' => 'Beban',
            'is_postable' => false,
        ]);

        $bebanLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $bebanParent->id,
            'tipe_coa' => 'Beban',
            'kode' => '510.01',
            'nama' => 'Beban Operasional',
            'is_postable' => true,
        ]);

        foreach ([
            [$kasOperasional->id, '2026-01-02', 500, 'D', 'Setoran modal ke kas'],
            [$ekuitas->id, '2026-01-02', 500, 'K', 'Setoran modal awal'],
            [$pendapatanGrandchildLeaf->id, '2025-12-31', 999, 'K', 'Pendapatan tahun lalu'],
            [$pendapatanGrandchildLeaf->id, '2026-01-10', 100, 'K', 'Pendapatan Januari'],
            [$pendapatanGrandchildLeaf->id, '2026-02-15', 200, 'K', 'Pendapatan Februari'],
            [$pendapatanGrandchildLeaf->id, '2026-05-19', 50, 'K', 'Pendapatan Mei'],
            [$pendapatanParent->id, '2026-05-19', 1000, 'K', 'Posting parent pendapatan yang diabaikan'],
            [$bebanLeaf->id, '2026-01-10', 30, 'D', 'Beban Januari'],
            [$bebanLeaf->id, '2026-02-15', 20, 'D', 'Beban Februari'],
            [$bebanLeaf->id, '2026-05-19', 10, 'D', 'Beban Mei'],
            [$bebanParent->id, '2026-05-19', 400, 'D', 'Posting parent beban yang diabaikan'],
            [$kasOperasional->id, '2026-01-10', 100, 'D', 'Kas masuk Januari'],
            [$kasOperasional->id, '2026-02-15', 200, 'D', 'Kas masuk Februari'],
            [$kasOperasional->id, '2026-05-19', 50, 'D', 'Kas masuk Mei'],
            [$kasOperasional->id, '2026-01-10', 30, 'K', 'Kas keluar Januari'],
            [$kasOperasional->id, '2026-02-15', 20, 'K', 'Kas keluar Februari'],
            [$kasOperasional->id, '2026-05-19', 10, 'K', 'Kas keluar Mei'],
        ] as [$coaId, $tanggal, $nominal, $tipeMutasi, $keterangan]) {
            BukuBesar::query()->create([
                'coa_id' => $coaId,
                'tanggal' => $tanggal,
                'nomer' => 'BB-'.str_replace('-', '', $tanggal).'-'.$coaId.'-'.$nominal,
                'sumber_transaksi' => 'Jurnal Umum',
                'nominal' => $nominal,
                'tipe_mutasi' => $tipeMutasi,
                'keterangan' => $keterangan,
            ]);
        }

        $service = app(LaporanKeuanganService::class);
        $labaRugiStandard = $service->getLabaRugiStandard('2026-01-01', '2026-05-19');
        $neracaStandard = $service->getNeracaStandard('2026-05-19');
        $labaRugiPerParent = $service->getLabaRugiPerParentCoa('2026-01-01', '2026-05-19', $pendapatanParent->id);

        $pendapatanParentRow = $labaRugiStandard
            ->where('sort_level', '>', 0)
            ->firstWhere('coa_id', $pendapatanParent->id);
        $pendapatanChildRow = collect($labaRugiPerParent['rows'])->firstWhere('coa_id', $pendapatanLeaf->id);
        $pendapatanStandardChildRow = $labaRugiStandard
            ->where('sort_level', '>', 0)
            ->firstWhere('coa_id', $pendapatanLeaf->id);
        $pendapatanGrandchildRow = $labaRugiStandard
            ->where('sort_level', '>', 0)
            ->firstWhere('coa_id', $pendapatanGrandchildLeaf->id);
        $pendapatanRootRow = $labaRugiStandard->firstWhere('kode', '4');
        $pendapatanLevelOneTotal = (float) $labaRugiStandard
            ->where('root_order', 1)
            ->where('sort_level', 1)
            ->sum('nominal');

        $this->assertNotNull($pendapatanParentRow);
        $this->assertNotNull($pendapatanChildRow);
        $this->assertNotNull($pendapatanStandardChildRow);
        $this->assertNull($pendapatanGrandchildRow);
        $this->assertGreaterThan(0, (float) $pendapatanChildRow['nominal']);
        $this->assertSame((float) $pendapatanChildRow['nominal'], (float) $pendapatanParentRow['nominal']);
        $this->assertSame((float) $pendapatanParentRow['nominal'], (float) $pendapatanStandardChildRow['nominal']);
        $this->assertSame(2, (int) $pendapatanStandardChildRow['sort_level']);
        $this->assertSame(2, (int) $pendapatanStandardChildRow['level']);
        $this->assertSame((float) $pendapatanRootRow['nominal'], $pendapatanLevelOneTotal);
        $this->assertTrue((bool) $pendapatanChildRow['has_children']);
    }

    public function test_laba_rugi_standard_does_not_duplicate_cross_type_children_between_roots(): void
    {
        foreach (['Pendapatan', 'Pendapatan lain'] as $namaTipeCoa) {
            \App\Models\TipeCoa::query()->create([
                'nama' => $namaTipeCoa,
                'status_aktif' => 1,
            ]);
        }

        $pendapatanLainParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Pendapatan lain',
            'kode' => '800.00',
            'nama' => 'Pendapatan Lain Parent',
            'is_postable' => false,
        ]);

        $crossTypeLeaf = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $pendapatanLainParent->id,
            'tipe_coa' => 'Pendapatan',
            'kode' => '801.03',
            'nama' => 'Bunga Mandiri',
            'is_postable' => true,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $crossTypeLeaf->id,
            'tanggal' => '2026-01-15',
            'nomer' => 'BB-80103-001',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 105152,
            'tipe_mutasi' => 'K',
            'keterangan' => 'Bunga Januari',
        ]);

        $service = app(LaporanKeuanganService::class);
        $rows = $service->getLabaRugiStandard('2026-01-01', '2026-01-31');
        $matchingRows = $rows
            ->where('sort_level', '>', 0)
            ->where('coa_id', $crossTypeLeaf->id)
            ->values();
        $total = (float) $rows
            ->where('sort_level', '>', 0)
            ->sum(function (array $row) {
                $tipeCoa = (string) ($row['tipe_coa'] ?? '');

                return in_array($tipeCoa, ['Pendapatan', 'Pendapatan lain'], true)
                    ? (float) ($row['nominal'] ?? 0)
                    : 0;
            });

        $this->assertCount(1, $matchingRows);
        $this->assertSame(1, $matchingRows->first()['root_order']);
        $this->assertSame(105152.0, $total);
    }
}
