<?php

namespace Tests\Unit;

use App\Models\ReferensiKelompokArusKas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferensiKelompokArusKasModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('referensi_kelompok_arus_kas');
        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
            $table->index(['aktivitas', 'nama']);
        });
    }

    public function test_can_create_and_query_scopes(): void
    {
        $aktif = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Penerimaan klaim BPJS Kesehatan',
            'status_aktif' => true,
        ]);

        $nonaktif = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pos Lama',
            'status_aktif' => false,
        ]);

        $this->assertCount(1, ReferensiKelompokArusKas::aktif()->get());
        $this->assertCount(2, ReferensiKelompokArusKas::aktivitas('operasi')->get());
        $this->assertTrue($aktif->status_aktif);
        $this->assertFalse($nonaktif->status_aktif);
    }
}
