<?php

namespace Tests\Feature;

use App\Models\AccessModule;
use App\Models\Coa;
use App\Models\KasbankPembayaranTemplate;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KasbankPembayaranTemplateTest extends TestCase
{
    private User $user;

    private Coa $coaBank;

    private Coa $coaBeban;

    private Coa $coaAtk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTables();

        $role = Role::query()->create([
            'nama' => 'Staff Keuangan',
            'kode' => 'staff-keuangan',
        ]);

        $module = AccessModule::query()->create([
            'kode' => 'kasbank.pembayaran',
            'nama' => 'Kasbank Pembayaran',
            'group_nama' => 'Kasbank',
            'urutan' => 41,
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
            'name' => 'Kasir Keuangan',
            'email' => 'keuangan@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $this->coaBank = Coa::query()->create([
            'kode' => '1102',
            'nama' => 'Bank BSI Operasional',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaBeban = Coa::query()->create([
            'kode' => '5101',
            'nama' => 'Beban Listrik dan Air',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);

        $this->coaAtk = Coa::query()->create([
            'kode' => '5102',
            'nama' => 'Beban ATK & Cetakan',
            'status_aktif' => 1,
            'is_postable' => true,
        ]);
    }

    private function prepareTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('kasbank_pembayaran_template_rinci');
        Schema::dropIfExists('kasbank_pembayaran_template');
        Schema::dropIfExists('kasbank_pembayaran_rinci');
        Schema::dropIfExists('kasbank_pembayaran');
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

        Schema::create('kasbank_pembayaran_template', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran_template_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kasbank_pembayaran_template_id');
            $table->unsignedBigInteger('coa_id');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('catatan', 255)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->string('nomer')->unique();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('kasbank_pembayaran_rinci', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kasbank_pembayaran_id');
            $table->unsignedInteger('coa_id');
            $table->decimal('nominal', 15, 2)->default(0);
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
            ->get(route('kasbank.pembayaran-template.index'))
            ->assertStatus(403);
    }

    public function test_authorized_user_can_view_template_index_and_loaddata(): void
    {
        KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Pembayaran Listrik dan Air',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Tagihan rutin bulanan listrik dan air',
        ]);

        $this->actingAs($this->user)
            ->get(route('kasbank.pembayaran-template.index'))
            ->assertOk()
            ->assertSee('Template Kasbank Pembayaran');

        $response = $this->actingAs($this->user)
            ->get(route('kasbank.pembayaran-template.load-data'));

        $response->assertOk()
            ->assertJsonPath('data.0.nama', 'Template Pembayaran Listrik dan Air')
            ->assertJsonPath('data.0.coa_display', 'Bank BSI Operasional');
    }

    public function test_can_create_template_with_multiple_rincian(): void
    {
        $payload = [
            'nama' => 'Template Beban Operasional',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Pembayaran tagihan rutin bulanan',
            'rincian' => [
                [
                    'coa_id' => $this->coaBeban->id,
                    'nominal' => 2500000,
                    'catatan' => 'PLN & PDAM',
                ],
                [
                    'coa_id' => $this->coaAtk->id,
                    'nominal' => 750000,
                    'catatan' => 'Kertas & formulir',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('kasbank.pembayaran-template.store'), $payload);

        $response->assertRedirect(route('kasbank.pembayaran-template.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('kasbank_pembayaran_template', [
            'nama' => 'Template Beban Operasional',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Pembayaran tagihan rutin bulanan',
        ]);

        $this->assertDatabaseCount('kasbank_pembayaran_template_rinci', 2);
    }

    public function test_can_create_template_with_zero_amounts_and_null_bank(): void
    {
        $payload = [
            'nama' => 'Template Kerangka Akun Tanpa Nominal',
            'coa_id' => null,
            'keterangan' => null,
            'rincian' => [
                [
                    'coa_id' => $this->coaBeban->id,
                    'nominal' => 0,
                    'catatan' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('kasbank.pembayaran-template.store'), $payload);

        $response->assertRedirect(route('kasbank.pembayaran-template.index'));

        $this->assertDatabaseHas('kasbank_pembayaran_template', [
            'nama' => 'Template Kerangka Akun Tanpa Nominal',
            'coa_id' => null,
        ]);
        $this->assertDatabaseCount('kasbank_pembayaran_template_rinci', 1);
    }

    public function test_can_fetch_template_detail_json(): void
    {
        $template = KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Contoh Pembayaran',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Memo template pembayaran operasional',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'nominal' => 1750000,
            'catatan' => 'Tagihan listrik gedung A',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('kasbank.pembayaran-template.detail', $template));

        $response->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('nama', 'Template Contoh Pembayaran')
            ->assertJsonPath('coa_id', $this->coaBank->id)
            ->assertJsonPath('coa_nama', 'Bank BSI Operasional')
            ->assertJsonPath('keterangan', 'Memo template pembayaran operasional')
            ->assertJsonPath('rincian.0.coa_id', $this->coaBeban->id)
            ->assertJsonPath('rincian.0.coa_kode', '5101')
            ->assertJsonPath('rincian.0.nominal', 1750000);
    }

    public function test_can_update_template_and_its_rincian(): void
    {
        $template = KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Lama',
            'coa_id' => null,
            'keterangan' => 'Keterangan lama',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'nominal' => 500000,
            'urutan' => 1,
        ]);

        $payload = [
            'nama' => 'Template Baru Diperbarui',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Keterangan baru',
            'rincian' => [
                [
                    'coa_id' => $this->coaAtk->id,
                    'nominal' => 850000,
                    'catatan' => 'Belanja ATK baru',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->put(route('kasbank.pembayaran-template.update', $template), $payload);

        $response->assertRedirect(route('kasbank.pembayaran-template.index'));

        $this->assertDatabaseHas('kasbank_pembayaran_template', [
            'id' => $template->id,
            'nama' => 'Template Baru Diperbarui',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Keterangan baru',
        ]);

        $this->assertDatabaseMissing('kasbank_pembayaran_template_rinci', [
            'coa_id' => $this->coaBeban->id,
        ]);

        $this->assertDatabaseHas('kasbank_pembayaran_template_rinci', [
            'kasbank_pembayaran_template_id' => $template->id,
            'coa_id' => $this->coaAtk->id,
            'nominal' => 850000,
        ]);
    }

    public function test_can_delete_template_and_cascade_delete_rincian(): void
    {
        $template = KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Dihapus',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaAtk->id,
            'nominal' => 100000,
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('kasbank.pembayaran-template.destroy', $template));

        $response->assertRedirect(route('kasbank.pembayaran-template.index'));

        $this->assertDatabaseMissing('kasbank_pembayaran_template', ['id' => $template->id]);
        $this->assertDatabaseCount('kasbank_pembayaran_template_rinci', 0);
    }

    public function test_create_kasbank_pembayaran_page_prefills_template_data_when_template_id_provided(): void
    {
        $template = KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Tagihan Listrik',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Pembayaran listrik bulanan',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaBeban->id,
            'nominal' => 3200000,
            'catatan' => 'PLN Pascabayar',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('kasbank.pembayaran.create', ['template_id' => $template->id]));

        $response->assertOk()
            ->assertViewHas('selectedTemplate', fn ($t) => $t->id === $template->id)
            ->assertViewHas('initialCoaId', $this->coaBank->id)
            ->assertViewHas('initialKeterangan', 'Pembayaran listrik bulanan')
            ->assertViewHas('initialRincian', function ($rincian) {
                return count($rincian) === 1 && $rincian[0]['coa_id'] === $this->coaBeban->id && $rincian[0]['nominal'] === 3200000;
            });
    }

    public function test_can_store_kasbank_pembayaran_using_template_data(): void
    {
        $template = KasbankPembayaranTemplate::query()->create([
            'nama' => 'Template Pembelian ATK',
            'coa_id' => $this->coaBank->id,
            'keterangan' => 'Pembayaran ATK rutin kantor',
        ]);

        $template->rincian()->create([
            'coa_id' => $this->coaAtk->id,
            'nominal' => 650000,
            'catatan' => 'Pembelian kertas HVS dan toner',
            'urutan' => 1,
        ]);

        $pembayaranPayload = [
            'nomer' => 'BKK-2026-10-001',
            'coa_id' => $template->coa_id,
            'tanggal' => '2026-10-15',
            'keterangan' => $template->keterangan,
            'total' => 650000,
            'rincian' => [
                [
                    'coa_id' => $this->coaAtk->id,
                    'nominal' => 650000,
                    'catatan' => 'Pembelian kertas HVS dan toner',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('kasbank.pembayaran.store'), $pembayaranPayload);

        $response->assertRedirect(route('kasbank.pembayaran.index'));

        $this->assertDatabaseHas('kasbank_pembayaran', [
            'nomer' => 'BKK-2026-10-001',
            'coa_id' => $this->coaBank->id,
            'total' => 650000,
        ]);

        $this->assertDatabaseCount('kasbank_pembayaran_rinci', 1);
        $this->assertDatabaseCount('bukubesar', 2);

        // Verify template remains intact
        $this->assertDatabaseHas('kasbank_pembayaran_template', ['id' => $template->id]);
        $this->assertDatabaseCount('kasbank_pembayaran_template_rinci', 1);
    }
}
