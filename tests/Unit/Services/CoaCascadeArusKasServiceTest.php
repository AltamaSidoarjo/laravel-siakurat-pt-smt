<?php

namespace Tests\Unit\Services;

use App\Models\Coa;
use App\Models\LogAktifitas;
use App\Services\Bukubesar\CoaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaCascadeArusKasServiceTest extends TestCase
{
    private CoaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->default(1);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa', 50);
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_postable')->default(false);
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        $this->service = app(CoaService::class);
    }

    public function test_get_cascade_preview_returns_correct_counts_and_skips_kasbank(): void
    {
        $parent = $this->createCoa('5.0.00', 'Beban Operasional', 'Beban', null, 'operasi', 'Beban Usaha');

        // Empty level-1 child
        $child1 = $this->createCoa('5.1.00', 'Beban Layanan', 'Beban', $parent->id, null, null);
        // Existing level-1 child
        $child2 = $this->createCoa('5.2.00', 'Beban Kantor', 'Beban', $parent->id, 'operasi', 'Pos Lama');
        // Kasbank child (must be excluded from preview counts)
        $this->createCoa('5.9.00', 'Kas Beban', 'Kasbank', $parent->id, null, null);

        // Level-2 grandchildren (5 more existing + 1 empty-string) to verify sample cap of 5
        $this->createCoa('5.1.01', 'Beban Empty String', 'Beban', $child1->id, '', '');
        for ($i = 1; $i <= 5; $i++) {
            $this->createCoa("5.2.0{$i}", "Beban Existing {$i}", 'Beban', $child2->id, 'investasi', "Kelompok {$i}");
        }

        // Level-3 great-grandchild (empty)
        $grandChild = Coa::where('kode', '5.2.01')->firstOrFail();
        $this->createCoa('5.2.01.01', 'Beban Cicit', 'Beban', $grandChild->id, null, null);

        $preview = $this->service->getCascadePreview((int) $parent->id);

        $this->assertSame(9, $preview['total_descendants']);
        $this->assertSame(3, $preview['empty_count']);
        $this->assertSame(6, $preview['existing_count']);
        $this->assertCount(5, $preview['existing_samples']);
        $this->assertSame([
            'kode' => '5.2.00',
            'nama' => 'Beban Kantor',
            'aktivitas' => 'operasi',
            'kelompok' => 'Pos Lama',
        ], $preview['existing_samples'][0]);
    }

    public function test_get_cascade_preview_returns_zeros_when_no_descendants(): void
    {
        $leaf = $this->createCoa('5.1.01', 'Beban Tunggal', 'Beban', null, 'operasi', 'Beban Usaha');

        $preview = $this->service->getCascadePreview((int) $leaf->id);

        $this->assertSame([
            'total_descendants' => 0,
            'empty_count' => 0,
            'existing_count' => 0,
            'existing_samples' => [],
        ], $preview);
    }

    public function test_cascade_overwrite_all_updates_all_non_kasbank_descendants_recursively_and_logs_summary(): void
    {
        $parent = $this->createCoa('5.0.00', 'Beban Induk', 'Beban', null, 'operasi', 'Pembayaran Beban Usaha');
        $child = $this->createCoa('5.1.00', 'Beban Anak', 'Beban', $parent->id, null, null);
        $grandChild = $this->createCoa('5.1.01', 'Beban Cucu', 'Beban', $child->id, 'investasi', 'Pos Berbeda');
        $greatGrandChild = $this->createCoa('5.1.01.01', 'Beban Cicit', 'Beban', $grandChild->id, null, null);
        $kasbankChild = $this->createCoa('5.1.99', 'Kas Sub Unit', 'Kasbank', $child->id, null, null);
        $unrelated = $this->createCoa('4.0.00', 'Pendapatan', 'Pendapatan', null, 'operasi', 'Penerimaan Pasien');

        $affected = $this->service->cascadeArusKas($parent, 'overwrite_all');

        $this->assertSame(3, $affected);
        $this->assertSame('operasi', $child->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $child->fresh()->arus_kas_kelompok);
        $this->assertSame('operasi', $grandChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $grandChild->fresh()->arus_kas_kelompok);
        $this->assertSame('operasi', $greatGrandChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $greatGrandChild->fresh()->arus_kas_kelompok);

        // Kasbank and unrelated accounts must stay untouched
        $this->assertNull($kasbankChild->fresh()->arus_kas_aktivitas);
        $this->assertNull($kasbankChild->fresh()->arus_kas_kelompok);
        $this->assertSame('Penerimaan Pasien', $unrelated->fresh()->arus_kas_kelompok);

        // Exactly 1 summary log entry
        $logs = LogAktifitas::query()->where('modul', 'COA')->where('tipe', 'cascade_arus_kas')->get();
        $this->assertCount(1, $logs);

        $payload = json_decode($logs->first()->payload, true);
        $this->assertSame([
            'parent_id' => $parent->id,
            'parent_kode' => '5.0.00',
            'mode' => 'overwrite_all',
            'aktivitas' => 'operasi',
            'kelompok' => 'Pembayaran Beban Usaha',
            'affected_count' => 3,
        ], $payload['new']);
    }

    public function test_cascade_fill_empty_only_updates_empty_descendants_and_preserves_existing(): void
    {
        $parent = $this->createCoa('5.0.00', 'Beban Induk', 'Beban', null, 'operasi', 'Pembayaran Beban Usaha');
        $emptyChild = $this->createCoa('5.1.00', 'Beban Kosong', 'Beban', $parent->id, null, null);
        $emptyStringChild = $this->createCoa('5.2.00', 'Beban Empty String', 'Beban', $parent->id, '', '');
        $existingChild = $this->createCoa('5.3.00', 'Beban Khusus', 'Beban', $parent->id, 'pendanaan', 'Pos Khusus');
        $emptyGrandChild = $this->createCoa('5.3.01', 'Beban Cucu Kosong', 'Beban', $existingChild->id, null, null);
        $kasbankChild = $this->createCoa('5.9.00', 'Kas Sub', 'Kasbank', $parent->id, null, null);

        $affected = $this->service->cascadeArusKas($parent, 'fill_empty');

        $this->assertSame(3, $affected);
        $this->assertSame('operasi', $emptyChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $emptyChild->fresh()->arus_kas_kelompok);
        $this->assertSame('operasi', $emptyStringChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $emptyStringChild->fresh()->arus_kas_kelompok);
        $this->assertSame('operasi', $emptyGrandChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban Usaha', $emptyGrandChild->fresh()->arus_kas_kelompok);

        // Existing custom mapping preserved
        $this->assertSame('pendanaan', $existingChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pos Khusus', $existingChild->fresh()->arus_kas_kelompok);

        // Kasbank remains null
        $this->assertNull($kasbankChild->fresh()->arus_kas_aktivitas);
        $this->assertNull($kasbankChild->fresh()->arus_kas_kelompok);

        $this->assertSame(1, LogAktifitas::query()->where('modul', 'COA')->where('tipe', 'cascade_arus_kas')->count());
    }

    public function test_cascade_returns_zero_and_does_nothing_for_kasbank_or_empty_parent(): void
    {
        $kasbankParent = $this->createCoa('1.1.00', 'Kasbank Induk', 'Kasbank', null, 'operasi', 'Should Be Ignored');
        $kasbankChild = $this->createCoa('1.1.01', 'Anak Kasbank', 'Beban', $kasbankParent->id, null, null);

        $this->assertSame(0, $this->service->cascadeArusKas($kasbankParent, 'overwrite_all'));
        $this->assertNull($kasbankChild->fresh()->arus_kas_aktivitas);

        $emptyParent = $this->createCoa('5.0.00', 'Beban Tanpa Arus Kas', 'Beban', null, null, null);
        $emptyParentChild = $this->createCoa('5.1.00', 'Anak Beban', 'Beban', $emptyParent->id, 'operasi', 'Pos Lama');

        $this->assertSame(0, $this->service->cascadeArusKas($emptyParent, 'overwrite_all'));
        $this->assertSame('operasi', $emptyParentChild->fresh()->arus_kas_aktivitas);
        $this->assertSame('Pos Lama', $emptyParentChild->fresh()->arus_kas_kelompok);

        $this->assertSame(0, LogAktifitas::query()->where('modul', 'COA')->where('tipe', 'cascade_arus_kas')->count());
    }

    public function test_get_parent_options_includes_arus_kas_attributes(): void
    {
        $parent = $this->createCoa('5.0.00', 'Beban Induk', 'Beban', null, 'operasi', 'Pembayaran Beban');
        $this->createCoa('5.1.00', 'Beban Anak', 'Beban', $parent->id, null, null);

        $options = $this->service->getParentOptions();
        $found = $options->firstWhere('id', $parent->id);

        $this->assertNotNull($found);
        $this->assertSame('Beban', $found->tipe_coa);
        $this->assertSame('operasi', $found->arus_kas_aktivitas);
        $this->assertSame('Pembayaran Beban', $found->arus_kas_kelompok);
    }

    public function test_update_invokes_cascade_only_when_requested(): void
    {
        $parent = $this->createCoa('5.0.00', 'Beban Induk', 'Beban', null, null, null);
        $child = $this->createCoa('5.1.00', 'Beban Anak', 'Beban', $parent->id, null, null);

        // Update without cascade_to_children
        $this->service->update($parent, [
            'status_aktif' => 1,
            'parent_id' => null,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Operasional',
            'kode' => '5.0.00',
            'nama' => 'Beban Induk',
        ]);

        $this->assertNull($child->fresh()->arus_kas_aktivitas);

        // Update with cascade_to_children & cascade_mode
        $this->service->update($parent->fresh(), [
            'status_aktif' => 1,
            'parent_id' => null,
            'tipe_coa' => 'Beban',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Beban Operasional',
            'kode' => '5.0.00',
            'nama' => 'Beban Induk',
            'cascade_to_children' => true,
            'cascade_mode' => 'overwrite_all',
        ]);

        $this->assertSame('operasi', $child->fresh()->arus_kas_aktivitas);
        $this->assertSame('Beban Operasional', $child->fresh()->arus_kas_kelompok);
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipeCoa,
        ?int $parentId,
        ?string $aktivitas,
        ?string $kelompok,
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $parentId,
            'tipe_coa' => $tipeCoa,
            'arus_kas_aktivitas' => $aktivitas,
            'arus_kas_kelompok' => $kelompok,
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => false,
        ]);
    }
}
