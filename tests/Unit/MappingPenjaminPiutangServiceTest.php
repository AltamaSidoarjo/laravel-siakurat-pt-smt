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

    public function test_available_options_exclude_mapped_guarantors_and_only_offer_valid_receivables(): void
    {
        $valid = $this->createCoa('1031', 'Piutang Valid', 'Piutang Usaha');
        $this->createCoa('1032', 'Piutang Nonaktif', 'Piutang Usaha', false);
        $this->createCoa('1033', 'Piutang Nonpostable', 'Piutang Usaha', true, false);
        $this->createCoa('4101', 'Pendapatan', 'Pendapatan');
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
        $this->assertSame([$valid->id], $this->service->getCoaOptions()->pluck('id')->all());
    }

    public function test_create_uses_canonical_billing_name_and_delete_is_logged(): void
    {
        $coa = $this->createCoa('1031', 'Piutang BPJS', 'Piutang Usaha');
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
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => $aktif ? 1 : 0,
            'tipe_coa' => $tipe,
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => $postable,
        ]);
    }
}
