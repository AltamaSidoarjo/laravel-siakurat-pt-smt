<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'nama_lengkap')) {
                $table->string('nama_lengkap')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'jabatan')) {
                $table->string('jabatan')->nullable()->after('nama_lengkap');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'jabatan')) {
                $table->dropColumn('jabatan');
            }

            if (Schema::hasColumn('users', 'nama_lengkap')) {
                $table->dropColumn('nama_lengkap');
            }
        });
    }
};
