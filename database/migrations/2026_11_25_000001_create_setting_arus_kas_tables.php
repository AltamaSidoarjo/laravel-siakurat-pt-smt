<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('setting_arus_kas')) {
            Schema::create('setting_arus_kas', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('kode_pos', 80)->unique();
                $table->string('nama_pos', 150);
                $table->string('kelompok_bagian', 40);
                $table->string('sifat_pos', 40);
                $table->boolean('pakai_prefix_perubahan')->default(false);
                $table->unsignedInteger('urutan')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['kelompok_bagian', 'is_active', 'urutan'], 'setting_arus_kas_bagian_active_urutan_idx');
            });
        }

        if (! Schema::hasTable('setting_arus_kas_coa')) {
            Schema::create('setting_arus_kas_coa', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('setting_arus_kas_id');
                $table->unsignedBigInteger('coa_id')->unique();
                $table->timestamps();

                $table->foreign('setting_arus_kas_id')
                    ->references('id')
                    ->on('setting_arus_kas')
                    ->cascadeOnDelete();

                if (Schema::hasTable('coa')) {
                    $table->foreign('coa_id')
                        ->references('id')
                        ->on('coa')
                        ->cascadeOnDelete();
                }
            });
        }

        if (! Schema::hasTable('setting_arus_kas_pengecualian')) {
            Schema::create('setting_arus_kas_pengecualian', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('coa_id')->unique();
                $table->string('alasan', 255)->nullable();
                $table->timestamps();

                if (Schema::hasTable('coa')) {
                    $table->foreign('coa_id')
                        ->references('id')
                        ->on('coa')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_arus_kas_pengecualian');
        Schema::dropIfExists('setting_arus_kas_coa');
        Schema::dropIfExists('setting_arus_kas');
    }
};
