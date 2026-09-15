<?php

namespace Tests\Unit;

use App\Models\Coa;
use App\Models\MappingPenjaminPiutang;
use App\Services\Bridging\BillingPendapatanApiService;
use App\Services\LogAktifitasService;
use App\Services\Pengaturan\MappingPenjaminPiutangService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MappingPenjaminPiutangServiceTest extends TestCase
{
    private BillingPendapatanApiService $billingService;

    private LogAktifitasService $logService;

    private MappingPenjaminPiutangService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('mapping_penjamin_piutang');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('penjamin_id')->unique();
            $table->string('nama_penjamin');
            $table->unsignedInteger('coa_id');
            $table->timestamps();
        });

        $this->billingService = Mockery::mock(BillingPendapatanApiService::class);
        $this->logService = Mockery::mock(LogAktifitasService::class);
        $this->service = new MappingPenjaminPiutangService($this->billingService, $this->logService);
    }

    public function test_available_guarantor_options_exclude_mapped_guarantors(): void
    {
        $valid = $this->createCoa('1031', 'Piutang Valid', 'Piutang Usaha');
        MappingPenjaminPiutang::query()->create([
            'penjamin_id' => '1',
            'nama_penjamin' => 'Umum',
            'coa_id' => $valid->id,
        ]);
        $this->billingService->shouldReceive('getPenjaminOptions')->once()->andReturn(collect([
            ['id' => '1', 'nama' => 'Umum'],
            ['id' => '2', 'nama' => 'BPJS'],
        ]));

        $this->assertSame(['2'], $this->service->getAvailablePenjaminOptions()->pluck('id')->all());
    }

    public function test_coa_options_offer_all_active_leaf_accounts_regardless_of_type_and_postable_flag(): void
    {
        $parent = $this->createCoa('1000', 'Aset Lancar', 'Aset', true, false);
        $child = $this->createCoa('1001', 'Kas', 'Kasbank', true, false, $parent->id);
        $receivable = $this->createCoa('1031', 'Piutang Valid', 'Piutang Usaha');
        $revenue = $this->createCoa('4101', 'Pendapatan', 'Pendapatan', true, false);
        $this->createCoa('5101', 'Beban Nonaktif', 'Beban', false, false);

        $this->assertSame(
            [$child->id, $receivable->id, $revenue->id],
            $this->service->getCoaOptions()->pluck('id')->all(),
        );
    }

    public function test_create_uses_canonical_billing_name_and_delete_is_logged(): void
    {
        $coa = $this->createCoa('4101', 'Pendapatan BPJS', 'Pendapatan', true, false);
        $this->billingService->shouldReceive('getPenjaminOptions')->once()->andReturn(collect([
            ['id' => '002', 'nama' => 'BPJS Kesehatan'],
        ]));
        $this->logService->shouldReceive('log')->twice();

        $mapping = $this->service->create(['penjamin_id' => '002', 'coa_id' => $coa->id]);

        $this->assertSame('BPJS Kesehatan', $mapping->nama_penjamin);
        $this->assertSame($coa->id, $mapping->coa_id);

        $this->service->delete($mapping);
        $this->assertDatabaseMissing('mapping_penjamin_piutang', ['penjamin_id' => '002']);
    }

    public function test_create_rejects_inactive_coa(): void
    {
        $coa = $this->createCoa('5101', 'Beban Nonaktif', 'Beban', false, false);
        $this->billingService->shouldReceive('getPenjaminOptions')->once()->andReturn(collect([
            ['id' => '002', 'nama' => 'BPJS Kesehatan'],
        ]));
        $this->logService->shouldNotReceive('log');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Akun harus merupakan COA aktif yang tidak memiliki akun turunan.');

        $this->service->create(['penjamin_id' => '002', 'coa_id' => $coa->id]);
    }

    public function test_create_rejects_parent_coa(): void
    {
        $parent = $this->createCoa('1000', 'Aset Lancar', 'Aset', true, false);
        $this->createCoa('1001', 'Kas', 'Kasbank', true, false, $parent->id);
        $this->billingService->shouldReceive('getPenjaminOptions')->once()->andReturn(collect([
            ['id' => '002', 'nama' => 'BPJS Kesehatan'],
        ]));
        $this->logService->shouldNotReceive('log');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Akun harus merupakan COA aktif yang tidak memiliki akun turunan.');

        $this->service->create(['penjamin_id' => '002', 'coa_id' => $parent->id]);
    }

    public function test_create_rejects_unknown_billing_guarantor(): void
    {
        $coa = $this->createCoa('1031', 'Piutang BPJS', 'Piutang Usaha');
        $this->billingService->shouldReceive('getPenjaminOptions')->once()->andReturn(collect());
        $this->logService->shouldNotReceive('log');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Penjamin tidak ditemukan pada Billing API.');

        $this->service->create(['penjamin_id' => 'missing', 'coa_id' => $coa->id]);
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipe,
        bool $aktif = true,
        bool $postable = true,
        ?int $parentCoa = null,
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => $aktif ? 1 : 0,
            'parent_coa' => $parentCoa,
            'tipe_coa' => $tipe,
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => $postable,
        ]);
    }
}
