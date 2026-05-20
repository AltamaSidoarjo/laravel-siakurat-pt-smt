<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bukubesar')) {
            return;
        }

        Schema::table('bukubesar', function (Blueprint $table) {
            if (! $this->hasIndex('bukubesar', 'bukubesar_coa_id_tanggal_index')) {
                $table->index(['coa_id', 'tanggal'], 'bukubesar_coa_id_tanggal_index');
            }

            if (! $this->hasIndex('bukubesar', 'bukubesar_tanggal_index')) {
                $table->index('tanggal', 'bukubesar_tanggal_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bukubesar')) {
            return;
        }

        Schema::table('bukubesar', function (Blueprint $table) {
            if ($this->hasIndex('bukubesar', 'bukubesar_coa_id_tanggal_index')) {
                $table->dropIndex('bukubesar_coa_id_tanggal_index');
            }

            if ($this->hasIndex('bukubesar', 'bukubesar_tanggal_index')) {
                $table->dropIndex('bukubesar_tanggal_index');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'mysql' => collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn (object $index) => ($index->Key_name ?? null) === $indexName),
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $index) => ($index->name ?? null) === $indexName),
            default => false,
        };
    }
};
