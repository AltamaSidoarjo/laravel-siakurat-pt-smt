<?php

namespace Tests\Unit;

use Database\Seeders\PelaksanaSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PelaksanaSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pelaksana', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('no_proyek')->unique();
            $table->string('nama_pelaksana');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function test_seeder_inserts_99_rows_and_is_idempotent(): void
    {
        $seeder = new PelaksanaSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertSame(99, DB::table('pelaksana')->count());
        $this->assertDatabaseHas('pelaksana', ['no_proyek' => '1_DESI FITRI', 'nama_pelaksana' => 'Desi Fitri, dr.']);
        $this->assertDatabaseHas('pelaksana', ['no_proyek' => '3_ANA NURLAILI', 'nama_pelaksana' => 'Ana Nurlaili,Apoteker']);
        $this->assertDatabaseMissing('pelaksana', ['no_proyek' => 'N/A']);
    }
}
