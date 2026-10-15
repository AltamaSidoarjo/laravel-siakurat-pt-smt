<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jurnal_umum_template')) {
            Schema::create('jurnal_umum_template', function (Blueprint $table): void {
                $table->id();
                $table->string('nama', 255);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('jurnal_umum_template_rinci')) {
            Schema::create('jurnal_umum_template_rinci', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('jurnal_umum_template_id');
                $table->unsignedInteger('coa_id');
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('kredit', 15, 2)->default(0);
                $table->string('catatan', 255)->nullable();
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();

                $table->foreign('jurnal_umum_template_id')
                    ->references('id')
                    ->on('jurnal_umum_template')
                    ->onDelete('cascade');

                $table->index('jurnal_umum_template_id');
                $table->index('coa_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_umum_template_rinci');
        Schema::dropIfExists('jurnal_umum_template');
    }
};
