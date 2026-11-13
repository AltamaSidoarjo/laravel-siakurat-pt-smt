<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator_kinerja_mapping_coa')) {
            return;
        }

        Schema::create('indikator_kinerja_mapping_coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('indikator_id');
            $table->unsignedInteger('coa_id');
            $table->string('peran', 30)->default('numerator');
            $table->boolean('is_excluded')->default(false);
            $table->timestamps();

            $table->foreign('indikator_id')
                ->references('id')
                ->on('indikator_kinerja')
                ->cascadeOnDelete();

            if (Schema::hasTable('coa')) {
                $table->foreign('coa_id')
                    ->references('id')
                    ->on('coa')
                    ->cascadeOnDelete();
            }

            $table->index(['indikator_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_kinerja_mapping_coa');
    }
};
