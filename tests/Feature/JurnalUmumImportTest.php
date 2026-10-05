<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\JurnalUmum;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AccessModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class JurnalUmumImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['bukubesar', 'jurnal_umum_rinci', 'jurnal_umum', 'coa', 'log_aktifitas', 'role_permissions', 'access_modules', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('access_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('group_nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('access_module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('nama_lengkap')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('email')->unique();
            $table->unsignedInteger('role_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_aktif')->nullable();
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->string('deskripsi')->nullable();
            $table->boolean('is_postable')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomer');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coa_id');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->string('tipe_mutasi', 1);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        $timestamp = now();
        AccessModule::query()->insert(
            collect(AccessModuleRegistry::all())
                ->map(fn (array $module) => [...$module, 'created_at' => $timestamp, 'updated_at' => $timestamp])
                ->all()
        );

        // COA leaf aktif yang dipakai di test
        Coa::query()->insert([
            ['status_aktif' => 1, 'parent_coa' => null, 'kode' => '1101', 'nama' => 'Kas', 'is_postable' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['status_aktif' => 1, 'parent_coa' => null, 'kode' => '5101', 'nama' => 'Beban Listrik', 'is_postable' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }

    public function test_import_valid_xlsx_creates_jurnal_and_bukubesar(): void
    {
        $user = $this->makeUserWithCreate();

        $file = $this->makeXlsx([
            ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'],
            ['JU-100', '2026-10-01', 'Bayar listrik', '5101', 500000, 0, 'Beban'],
            ['JU-100', '2026-10-01', 'Bayar listrik', '1101', 0, 500000, 'Kas keluar'],
        ]);

        $this->actingAs($user)
            ->post(route('bukubesar.jurnal-umum.import.store'), ['file' => $file])
            ->assertRedirect(route('bukubesar.jurnal-umum.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('jurnal_umum', ['nomer' => 'JU-100', 'debit' => 500000, 'kredit' => 500000]);
        $this->assertSame(2, DB::table('jurnal_umum_rinci')->count());
        $this->assertSame(2, DB::table('bukubesar')->where('sumber_transaksi', 'Jurnal Umum')->count());
    }

    public function test_import_unbalanced_jurnal_is_rejected_all_or_nothing(): void
    {
        $user = $this->makeUserWithCreate();

        $file = $this->makeXlsx([
            ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'],
            ['JU-200', '2026-10-01', 'Timpang', '5101', 500000, 0, ''],
            ['JU-200', '2026-10-01', 'Timpang', '1101', 0, 400000, ''],
        ]);

        $this->actingAs($user)
            ->from(route('bukubesar.jurnal-umum.import.form'))
            ->post(route('bukubesar.jurnal-umum.import.store'), ['file' => $file])
            ->assertRedirect(route('bukubesar.jurnal-umum.import.form'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('jurnal_umum', 0);
        $this->assertDatabaseCount('bukubesar', 0);
    }

    public function test_import_unknown_kode_coa_is_rejected(): void
    {
        $user = $this->makeUserWithCreate();

        $file = $this->makeXlsx([
            ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'],
            ['JU-300', '2026-10-01', 'Salah coa', '9999', 500000, 0, ''],
            ['JU-300', '2026-10-01', 'Salah coa', '1101', 0, 500000, ''],
        ]);

        $this->actingAs($user)
            ->from(route('bukubesar.jurnal-umum.import.form'))
            ->post(route('bukubesar.jurnal-umum.import.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('jurnal_umum', 0);
    }

    public function test_import_duplicate_nomer_in_db_is_rejected(): void
    {
        $user = $this->makeUserWithCreate();

        JurnalUmum::query()->create([
            'nomer' => 'JU-400', 'tanggal' => '2026-09-01', 'keterangan' => 'lama', 'debit' => 100, 'kredit' => 100,
        ]);

        $file = $this->makeXlsx([
            ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'],
            ['JU-400', '2026-10-01', 'Duplikat', '5101', 500000, 0, ''],
            ['JU-400', '2026-10-01', 'Duplikat', '1101', 0, 500000, ''],
        ]);

        $this->actingAs($user)
            ->from(route('bukubesar.jurnal-umum.import.form'))
            ->post(route('bukubesar.jurnal-umum.import.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, JurnalUmum::query()->count());
    }

    public function test_user_without_create_permission_cannot_import(): void
    {
        $user = $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'bukubesar.jurnal-umum' => ['view' => true],
        ]);

        $file = $this->makeXlsx([
            ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'],
            ['JU-500', '2026-10-01', 'x', '5101', 1, 0, ''],
            ['JU-500', '2026-10-01', 'x', '1101', 0, 1, ''],
        ]);

        $this->actingAs($user)
            ->post(route('bukubesar.jurnal-umum.import.store'), ['file' => $file])
            ->assertForbidden();
    }

    public function test_template_download_returns_xlsx(): void
    {
        $user = $this->makeUserWithCreate();

        $this->actingAs($user)
            ->get(route('bukubesar.jurnal-umum.import.template'))
            ->assertOk()
            ->assertDownload('template-import-jurnal-umum.xlsx');
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'ju_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function makeUserWithCreate(): User
    {
        return $this->makeUserWithPermissions([
            'home' => ['view' => true],
            'bukubesar.jurnal-umum' => ['view' => true, 'create' => true],
        ]);
    }

    private function makeUserWithPermissions(array $permissionsByModule, string $email = 'tester@example.com'): User
    {
        $role = Role::query()->create([
            'nama' => 'Role '.uniqid(),
            'kode' => 'role_'.uniqid(),
            'deskripsi' => null,
            'is_system' => false,
        ]);

        foreach (AccessModule::query()->get() as $module) {
            $selected = $permissionsByModule[$module->kode] ?? [];

            RolePermission::query()->create([
                'role_id' => $role->id,
                'access_module_id' => $module->id,
                'can_view' => (bool) ($selected['view'] ?? false),
                'can_create' => (bool) ($selected['create'] ?? false),
                'can_update' => (bool) ($selected['update'] ?? false),
                'can_delete' => (bool) ($selected['delete'] ?? false),
            ]);
        }

        return User::query()->create([
            'name' => 'Tester',
            'nama_lengkap' => 'Tester Lengkap',
            'jabatan' => 'Tester QA',
            'email' => $email,
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
    }
}
