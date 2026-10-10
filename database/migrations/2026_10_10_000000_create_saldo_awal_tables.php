<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULE_CODE = 'bukubesar.saldo-awal';

    public function up(): void
    {
        if (! Schema::hasTable('saldo_awal')) {
            Schema::create('saldo_awal', function (Blueprint $table) {
                $table->id();
                $table->string('nomer', 50)->unique();
                $table->date('tanggal_cutoff');
                $table->text('keterangan')->nullable();
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_kredit', 15, 2)->default(0);
                $table->string('status', 20)->default('draft'); // draft, locked
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('saldo_awal_rinci')) {
            Schema::create('saldo_awal_rinci', function (Blueprint $table) {
                $table->id();
                $table->foreignId('saldo_awal_id')->constrained('saldo_awal')->cascadeOnDelete();
                $table->foreignId('coa_id')->constrained('coa')->restrictOnDelete();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('kredit', 15, 2)->default(0);
                $table->string('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('access_modules') && Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            $timestamp = now();

            DB::table('access_modules')->updateOrInsert(
                ['kode' => self::MODULE_CODE],
                [
                    'nama' => 'Saldo Awal',
                    'group_nama' => 'Bukubesar',
                    'urutan' => 25,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );

            $moduleId = DB::table('access_modules')->where('kode', self::MODULE_CODE)->value('id');
            if ($moduleId !== null) {
                $jurnalUmumModuleId = DB::table('access_modules')->where('kode', 'bukubesar.jurnal-umum')->value('id');

                $roles = DB::table('roles')->get();
                $existingRoleIds = DB::table('role_permissions')
                    ->where('access_module_id', $moduleId)
                    ->pluck('role_id');

                $permissions = [];
                foreach ($roles as $role) {
                    if ($existingRoleIds->contains($role->id)) {
                        continue;
                    }

                    $jurnalPerm = $jurnalUmumModuleId
                        ? DB::table('role_permissions')
                            ->where('role_id', $role->id)
                            ->where('access_module_id', $jurnalUmumModuleId)
                            ->first()
                        : null;

                    $permissions[] = [
                        'role_id' => $role->id,
                        'access_module_id' => $moduleId,
                        'can_view' => (bool) ($role->is_system || ($jurnalPerm?->can_view ?? false)),
                        'can_create' => (bool) ($role->is_system || ($jurnalPerm?->can_create ?? false)),
                        'can_update' => (bool) ($role->is_system || ($jurnalPerm?->can_update ?? false)),
                        'can_delete' => (bool) ($role->is_system || ($jurnalPerm?->can_delete ?? false)),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($permissions !== []) {
                    DB::table('role_permissions')->insert($permissions);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('access_modules')) {
            $moduleId = DB::table('access_modules')->where('kode', self::MODULE_CODE)->value('id');
            if ($moduleId !== null) {
                if (Schema::hasTable('role_permissions')) {
                    DB::table('role_permissions')->where('access_module_id', $moduleId)->delete();
                }
                DB::table('access_modules')->where('id', $moduleId)->delete();
            }
        }

        Schema::dropIfExists('saldo_awal_rinci');
        Schema::dropIfExists('saldo_awal');
    }
};

