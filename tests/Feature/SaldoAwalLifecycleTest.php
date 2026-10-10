<?php

namespace Tests\Feature;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\SaldoAwal;
use App\Models\User;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\Bukubesar\SaldoAwalService;
use App\Services\LogAktifitasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaldoAwalLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('saldo_awal_rinci');
        Schema::dropIfExists('saldo_awal');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Admin');
            $table->string('email')->default('admin@example.com');
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload');
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->default(true);
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

        Schema::create('saldo_awal', function (Blueprint $table) {
            $table->id();
            $table->string('nomer', 50)->unique();
            $table->date('tanggal_cutoff');
            $table->text('keterangan')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_kredit', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('saldo_awal_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('saldo_awal_id');
            $table->unsignedBigInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function test_saldo_awal_routes_are_registered_with_correct_middleware(): void
    {
        $routes = [
            'bukubesar.saldo-awal.index' => ['GET', 'module.access:bukubesar.saldo-awal,view'],
            'bukubesar.saldo-awal.create' => ['GET', 'module.access:bukubesar.saldo-awal,create'],
            'bukubesar.saldo-awal.store' => ['POST', 'module.access:bukubesar.saldo-awal,create'],
            'bukubesar.saldo-awal.edit' => ['GET', 'module.access:bukubesar.saldo-awal,update'],
            'bukubesar.saldo-awal.update' => ['PUT', 'module.access:bukubesar.saldo-awal,update'],
            'bukubesar.saldo-awal.lock' => ['POST', 'module.access:bukubesar.saldo-awal,update'],
            'bukubesar.saldo-awal.unlock' => ['POST', 'module.access:bukubesar.saldo-awal,update'],
            'bukubesar.saldo-awal.destroy' => ['DELETE', 'module.access:bukubesar.saldo-awal,delete'],
        ];

        foreach ($routes as $name => [$method, $expectedMiddleware]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} is not registered.");
            $this->assertContains($method, $route->methods());
            $this->assertContains($expectedMiddleware, $route->gatherMiddleware());
        }
    }

    public function test_saldo_awal_service_lifecycle_draft_lock_and_unlock(): void
    {
        $bukuBesarService = new BukuBesarService();
        $logService = new LogAktifitasService();
        $service = new SaldoAwalService($bukuBesarService, $logService);

        $coa1 = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Kasir',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $coa2 = Coa::query()->create([
            'kode' => '3101',
            'nama' => 'Modal Disetor',
            'tipe_coa' => 'Ekuitas',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $userId = 1;

        // 1. Create Draft
        $dataDraft = [
            'nomer' => 'SA-TEST-001',
            'tanggal_cutoff' => '2024-12-31',
            'keterangan' => 'Testing Saldo Awal Draft',
            'action' => 'save_draft',
            'rincian' => [
                ['coa_id' => $coa1->id, 'debit' => 10_000_000, 'kredit' => 0, 'catatan' => 'Kas'],
                ['coa_id' => $coa2->id, 'debit' => 0, 'kredit' => 10_000_000, 'catatan' => 'Modal'],
            ],
        ];

        $saldoAwal = $service->create($dataDraft, $userId);
        $this->assertSame('draft', $saldoAwal->status);
        $this->assertTrue($saldoAwal->isBalanced());
        // In draft, BukuBesar should NOT have entries yet
        $this->assertDatabaseMissing('bukubesar', [
            'sumber_transaksi' => 'Saldo Awal',
            'sumber_id' => $saldoAwal->id,
        ]);

        // 2. Lock & Post to BukuBesar
        $locked = $service->lock($saldoAwal, $userId);
        $this->assertSame('locked', $locked->status);
        $this->assertNotNull($locked->locked_at);

        // BukuBesar must now contain the 2 entries
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $coa1->id,
            'sumber_transaksi' => 'Saldo Awal',
            'sumber_id' => $saldoAwal->id,
            'nominal' => 10_000_000,
            'tipe_mutasi' => 'D',
            'tanggal' => '2024-12-31',
        ]);
        $this->assertDatabaseHas('bukubesar', [
            'coa_id' => $coa2->id,
            'sumber_transaksi' => 'Saldo Awal',
            'sumber_id' => $saldoAwal->id,
            'nominal' => 10_000_000,
            'tipe_mutasi' => 'K',
            'tanggal' => '2024-12-31',
        ]);

        // 3. Unlock returns it to draft and clears BukuBesar
        $unlocked = $service->unlock($locked, $userId);
        $this->assertSame('draft', $unlocked->status);
        $this->assertNull($unlocked->locked_at);
        $this->assertDatabaseMissing('bukubesar', [
            'sumber_transaksi' => 'Saldo Awal',
            'sumber_id' => $saldoAwal->id,
        ]);

        // 4. Delete draft cleans up
        $service->delete($unlocked);
        $this->assertDatabaseMissing('saldo_awal', ['id' => $saldoAwal->id]);
        $this->assertDatabaseMissing('saldo_awal_rinci', ['saldo_awal_id' => $saldoAwal->id]);
    }

    public function test_cannot_lock_unbalanced_saldo_awal(): void
    {
        $bukuBesarService = new BukuBesarService();
        $logService = new LogAktifitasService();
        $service = new SaldoAwalService($bukuBesarService, $logService);

        $coa1 = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Kasir',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $userId = 1;

        $unbalancedData = [
            'nomer' => 'SA-TEST-UNBALANCED',
            'tanggal_cutoff' => '2024-12-31',
            'keterangan' => 'Unbalanced',
            'action' => 'save_lock',
            'rincian' => [
                ['coa_id' => $coa1->id, 'debit' => 5_000_000, 'kredit' => 0],
            ],
        ];

        $this->expectException(ValidationException::class);
        $service->create($unbalancedData, $userId);
    }
}

