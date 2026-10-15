<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\JurnalUmumTemplate;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JurnalUmumTemplateTest extends TestCase
{
    private User $user;

    private Coa $coaKas;

    private Coa $coaBeban;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $role = Role::query()->create([
            'nama' => 'Staff Akuntansi',
            'kode' => 'staff-akuntansi',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'bukubesar.jurnal-umum',
            'nama' => 'Jurnal Umum',
            'group_nama' => 'Bukubesar',
            'urutan' => 20,
        ]);

        RolePermission::query()->create([
            'role_id' => $role->id,
            'access_module_id' => $module->id,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $this->user = User::query()->create([
            'name' => 'Akuntan Test',
            'email' => 'akuntan@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $this->coaKas = Coa::query()->create([
            'kode' => '1101',
            'nama' => 'Kas Utama',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaBeban = Coa::query()->create([
            'kode' => '5101',
            'nama' => 'Beban Listrik',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('jurnal_umum_template_rinci');
        Schema::dropIfExists('jurnal_umum_template');
        Schema::dropIfExists('jurnal_umum_rinci');
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('coa');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('access_modules');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->string('profile_picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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
            $table->id();
            $table->string('nama_user');
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->unique();
            $table->string('nama');
            $table->tinyInteger('status_aktif')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->unsignedInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('arus_kas_aktivitas')->nullable();
            $table->string('arus_kas_kelompok')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum_template', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_umum_template_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jurnal_umum_template_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('catatan', 255)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('jurnal_umum_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jurnal_umum_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bukubesar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('sumber_id');
            $table->date('tanggal');
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->string('nomer')->nullable();
            $table->string('sumber_transaksi')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->char('tipe_mutasi', 1)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function test_unauthorized_user_cannot_access_template_module(): void
    {
        $unauthorizedUser = User::query()->create([
            'name' => 'User Biasa',
            'email' => 'biasa@example.com',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);

        $this->actingAs($unauthorizedUser)
            ->get(route('bukubesar.jurnal-umum-template.index'))
            ->assertStatus(403);
    }

    public function test_authorized_user_can_view_template_index_and_loaddata(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Listrik Bulanan',
            'keterangan' => 'Pembayaran tagihan listrik PLN',
        ]);

        $this->actingAs($this->user)
            ->get(route('bukubesar.jurnal-umum-template.index'))
            ->assertOk()
            ->assertSee('Template Jurnal Umum');

        $response = $this->actingAs($this->user)
            ->get(route('bukubesar.jurnal-umum-template.load-data'));

        $response->assertOk()
            ->assertJsonPath('data.0.nama', 'Template Listrik Bulanan');
    }

    public function test_can_create_template_with_multiple_rincian(): void
    {
        $payload = [
            'nama' => 'Template Beban Rutin',
            'keterangan' => 'Rincian beban rutin bulanan',
            'rincian' => [
                [
                    'coa_id' => $this->coaBeban->id,
                    'debit' => 500000,
                    'kredit' => 0,
                    'catatan' => 'Beban listrik',
                ],
                [
                    'coa_id' => $this->coaKas->id,
                    'debit' => 0,
                    'kredit' => 500000,
                    'catatan' => 'Kas utama',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bukubesar.jurnal-umum-template.store'), $payload);

        $response->assertRedirect(route('bukubesar.jurnal-umum-template.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('jurnal_umum_template', [
            'nama' => 'Template Beban Rutin',
            'keterangan' => 'Rincian beban rutin bulanan',
        ]);

        $this->assertDatabaseCount('jurnal_umum_template_rinci', 2);
    }

    public function test_can_create_template_with_zero_amounts_for_account_structure(): void
    {
        $payload = [
            'nama' => 'Template Kerangka Akun Tanpa Nominal',
            'keterangan' => null,
            'rincian' => [
                [
                    'coa_id' => $this->coaBeban->id,
                    'debit' => 0,
                    'kredit' => 0,
                    'catatan' => null,
                ],
                [
                    'coa_id' => $this->coaKas->id,
                    'debit' => 0,
                    'kredit' => 0,
                    'catatan' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bukubesar.jurnal-umum-template.store'), $payload);

        $response->assertRedirect(route('bukubesar.jurnal-umum-template.index'));

        $this->assertDatabaseHas('jurnal_umum_template', [
            'nama' => 'Template Kerangka Akun Tanpa Nominal',
        ]);
        $this->assertDatabaseCount('jurnal_umum_template_rinci', 2);
    }

    public function test_can_fetch_template_detail_json(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Contoh',
            'keterangan' => 'Memo template',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'debit' => 250000,
            'kredit' => 0,
            'catatan' => 'Memo baris',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('bukubesar.jurnal-umum-template.detail', $template));

        $response->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('nama', 'Template Contoh')
            ->assertJsonPath('keterangan', 'Memo template')
            ->assertJsonPath('rincian.0.coa_id', $this->coaBeban->id)
            ->assertJsonPath('rincian.0.coa_kode', '5101')
            ->assertJsonPath('rincian.0.debit', 250000);
    }

    public function test_can_update_template_and_its_rincian(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Lama',
            'keterangan' => 'Keterangan lama',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'debit' => 100000,
            'kredit' => 0,
            'urutan' => 1,
        ]);

        $payload = [
            'nama' => 'Template Baru',
            'keterangan' => 'Keterangan baru',
            'rincian' => [
                [
                    'coa_id' => $this->coaKas->id,
                    'debit' => 750000,
                    'kredit' => 0,
                    'catatan' => 'Catatan baru',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->put(route('bukubesar.jurnal-umum-template.update', $template), $payload);

        $response->assertRedirect(route('bukubesar.jurnal-umum-template.index'));

        $this->assertDatabaseHas('jurnal_umum_template', [
            'id' => $template->id,
            'nama' => 'Template Baru',
            'keterangan' => 'Keterangan baru',
        ]);

        $this->assertDatabaseMissing('jurnal_umum_template_rinci', [
            'coa_id' => $this->coaBeban->id,
        ]);

        $this->assertDatabaseHas('jurnal_umum_template_rinci', [
            'jurnal_umum_template_id' => $template->id,
            'coa_id' => $this->coaKas->id,
            'debit' => 750000,
        ]);
    }

    public function test_can_delete_template_and_cascade_delete_rincian(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Dihapus',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaKas->id,
            'debit' => 50000,
            'kredit' => 0,
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('bukubesar.jurnal-umum-template.destroy', $template));

        $response->assertRedirect(route('bukubesar.jurnal-umum-template.index'));

        $this->assertDatabaseMissing('jurnal_umum_template', ['id' => $template->id]);
        $this->assertDatabaseCount('jurnal_umum_template_rinci', 0);
    }

    public function test_create_jurnal_umum_page_prefills_template_data_when_template_id_provided(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Gaji',
            'keterangan' => 'Beban Gaji Karyawan',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'debit' => 3000000,
            'kredit' => 0,
            'catatan' => 'Gaji bulanan',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('bukubesar.jurnal-umum.create', ['template_id' => $template->id]));

        $response->assertOk()
            ->assertViewHas('selectedTemplate', fn ($t) => $t->id === $template->id)
            ->assertViewHas('initialKeterangan', 'Beban Gaji Karyawan')
            ->assertViewHas('initialRincian', function ($rincian) {
                return count($rincian) === 1 && $rincian[0]['coa_id'] === $this->coaBeban->id && $rincian[0]['debit'] === 3000000;
            });
    }

    public function test_can_store_jurnal_umum_using_template_data(): void
    {
        $template = JurnalUmumTemplate::query()->create([
            'nama' => 'Template Operasional',
            'keterangan' => 'Biaya Operasional',
        ]);

        $template->rincian()->createMany([
            [
                'coa_id' => $this->coaBeban->id,
                'debit' => 1500000,
                'kredit' => 0,
                'catatan' => 'Beban Operasional',
                'urutan' => 1,
            ],
            [
                'coa_id' => $this->coaKas->id,
                'debit' => 0,
                'kredit' => 1500000,
                'catatan' => 'Kas',
                'urutan' => 2,
            ],
        ]);

        $jurnalPayload = [
            'nomer' => 'JU-2026-10-001',
            'tanggal' => '2026-10-15',
            'keterangan' => $template->keterangan,
            'debit' => 1500000,
            'kredit' => 1500000,
            'rincian' => [
                [
                    'coa_id' => $this->coaBeban->id,
                    'debit' => 1500000,
                    'kredit' => 0,
                    'catatan' => 'Beban Operasional',
                ],
                [
                    'coa_id' => $this->coaKas->id,
                    'debit' => 0,
                    'kredit' => 1500000,
                    'catatan' => 'Kas',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('bukubesar.jurnal-umum.store'), $jurnalPayload);

        $response->assertRedirect(route('bukubesar.jurnal-umum.index'));

        $this->assertDatabaseHas('jurnal_umum', [
            'nomer' => 'JU-2026-10-001',
            'debit' => 1500000,
            'kredit' => 1500000,
        ]);

        $this->assertDatabaseCount('jurnal_umum_rinci', 2);
        $this->assertDatabaseCount('bukubesar', 2);

        // Verify template remains unaffected
        $this->assertDatabaseHas('jurnal_umum_template', ['id' => $template->id]);
        $this->assertDatabaseCount('jurnal_umum_template_rinci', 2);
    }
}
