<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kasbank_penerimaan_template')) {
            Schema::create('kasbank_penerimaan_template', function (Blueprint $table): void {
                $table->id();
                $table->string('nama', 255);
                $table->unsignedBigInteger('coa_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->foreign('coa_id', 'fk_kb_pen_tpl_head_coa')
                    ->references('id')
                    ->on('coa')
                    ->nullOnDelete();

                $table->index('coa_id', 'idx_kb_pen_tpl_head_coa');
            });
        }

        if (! Schema::hasTable('kasbank_penerimaan_template_rinci')) {
            Schema::create('kasbank_penerimaan_template_rinci', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('kasbank_penerimaan_template_id');
                $table->unsignedBigInteger('coa_id');
                $table->decimal('nominal', 15, 2)->default(0);
                $table->string('catatan', 255)->nullable();
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();

                $table->foreign('kasbank_penerimaan_template_id', 'fk_kb_pen_tpl_id')
                    ->references('id')
                    ->on('kasbank_penerimaan_template')
                    ->cascadeOnDelete();

                $table->foreign('coa_id', 'fk_kb_pen_tpl_coa')
                    ->references('id')
                    ->on('coa');

                $table->index('kasbank_penerimaan_template_id', 'idx_kb_pen_tpl_id');
                $table->index('coa_id', 'idx_kb_pen_tpl_coa');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kasbank_penerimaan_template_rinci');
        Schema::dropIfExists('kasbank_penerimaan_template');
    }
};
