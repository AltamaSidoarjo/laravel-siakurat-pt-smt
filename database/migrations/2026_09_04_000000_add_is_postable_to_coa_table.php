<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coa', function (Blueprint $table): void {
            $table->boolean('is_postable')->default(false)->after('deskripsi');
        });

        DB::table('coa as candidate')
            ->leftJoin('coa as child', 'child.parent_coa', '=', 'candidate.id')
            ->where('candidate.status_aktif', 1)
            ->whereNull('child.id')
            ->pluck('candidate.id')
            ->chunk(500)
            ->each(function ($ids): void {
                DB::table('coa')
                    ->whereIn('id', $ids->all())
                    ->update(['is_postable' => true]);
            });
    }

    public function down(): void
    {
        Schema::table('coa', function (Blueprint $table): void {
            $table->dropColumn('is_postable');
        });
    }
};
