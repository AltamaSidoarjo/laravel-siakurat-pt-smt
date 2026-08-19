<?php

namespace Tests\Feature;

use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoaParentValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bukubesar');
        Schema::dropIfExists('log_aktifitas');
        Schema::dropIfExists('tipe_coa');
        Schema::dropIfExists('coa');

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

        Schema::create('tipe_coa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->integer('status_aktif')->default(1);
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

        Schema::create('log_aktifitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_user')->nullable();
            $table->string('modul');
            $table->string('tipe');
            $table->text('payload')->nullable();
            $table->timestamps();
        });

    }

    public function test_create_form_filters_out_leaf_coa_that_already_has_transactions(): void
    {
        $blockedParent = $this->createCoaLeafWithTransaction('110.01', 'Kas Transaksi');
        $allowedParent = $this->createCoaWithChild('120.00', 'Parent Existing');

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bukubesar.coa.create'));

        $response
            ->assertOk()
            ->assertDontSee($blockedParent->kode.' - '.$blockedParent->nama)
            ->assertSee($allowedParent->kode.' - '.$allowedParent->nama);
    }

    public function test_edit_form_excludes_current_coa_and_descendants_from_parent_options(): void
    {
        $parent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '210.00',
            'nama' => 'Parent Edit',
            'is_postable' => false,
        ]);

        $child = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '210.01',
            'nama' => 'Child Edit',
            'is_postable' => false,
        ]);

        $grandChild = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $child->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '210.01.01',
            'nama' => 'Grandchild Edit',
            'is_postable' => true,
        ]);

        $otherParent = $this->createCoaWithChild('220.00', 'Parent Lain');

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bukubesar.coa.edit', $parent));

        $response
            ->assertOk()
            ->assertDontSee($parent->kode.' - '.$parent->nama)
            ->assertDontSee($child->kode.' - '.$child->nama)
            ->assertDontSee($grandChild->kode.' - '.$grandChild->nama)
            ->assertSee($otherParent->kode.' - '.$otherParent->nama);
    }

    public function test_store_rejects_leaf_coa_with_existing_bukubesar_transaction_as_parent(): void
    {
        $blockedParent = $this->createCoaLeafWithTransaction('110.01', 'Kas Transaksi');

        $response = $this
            ->from(route('bukubesar.coa.create'))
            ->actingAs($this->makeUser())
            ->post(route('bukubesar.coa.store'), [
                'status_aktif' => 1,
                'parent_id' => $blockedParent->id,
                'tipe_coa' => 'Kasbank',
                'kode' => '110.02',
                'nama' => 'Kas Baru',
                'deskripsi' => 'Test',
            ]);

        $response
            ->assertRedirect(route('bukubesar.coa.create'))
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('coa', [
            'kode' => '110.02',
            'nama' => 'Kas Baru',
        ]);
    }

    public function test_update_rejects_leaf_coa_with_existing_bukubesar_transaction_as_parent(): void
    {
        $blockedParent = $this->createCoaLeafWithTransaction('110.01', 'Kas Transaksi');
        $editableCoa = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '130.01',
            'nama' => 'Akun Edit',
            'is_postable' => false,
        ]);

        $response = $this
            ->from(route('bukubesar.coa.edit', $editableCoa))
            ->actingAs($this->makeUser())
            ->put(route('bukubesar.coa.update', $editableCoa), [
                'status_aktif' => 1,
                'parent_id' => $blockedParent->id,
                'tipe_coa' => 'Kasbank',
                'kode' => '130.01',
                'nama' => 'Akun Edit',
                'deskripsi' => 'Updated',
            ]);

        $response
            ->assertRedirect(route('bukubesar.coa.edit', $editableCoa))
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(null, $editableCoa->fresh()->parent_coa);
    }

    public function test_parent_coa_can_still_be_edited_when_it_has_children(): void
    {
        $parent = $this->createCoaWithChild('310.00', 'Parent Lama');

        $response = $this
            ->actingAs($this->makeUser())
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 0,
                'parent_id' => null,
                'tipe_coa' => 'Kewajiban',
                'kode' => '310.99',
                'nama' => 'Parent Baru',
                'deskripsi' => 'Sudah diperbarui',
            ]);

        $response->assertRedirect(route('bukubesar.coa.index'));

        $this->assertDatabaseHas('coa', [
            'id' => $parent->id,
            'status_aktif' => 0,
            'parent_coa' => null,
            'tipe_coa' => 'Kewajiban',
            'kode' => '310.99',
            'nama' => 'Parent Baru',
            'deskripsi' => 'Sudah diperbarui',
        ]);
    }

    public function test_update_rejects_descendant_as_parent_to_prevent_cycle(): void
    {
        $parent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '410.00',
            'nama' => 'Parent Cycle',
            'is_postable' => false,
        ]);

        $child = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '410.01',
            'nama' => 'Child Cycle',
            'is_postable' => false,
        ]);

        $grandChild = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $child->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '410.01.01',
            'nama' => 'Grandchild Cycle',
            'is_postable' => true,
        ]);

        $response = $this
            ->from(route('bukubesar.coa.edit', $parent))
            ->actingAs($this->makeUser())
            ->put(route('bukubesar.coa.update', $parent), [
                'status_aktif' => 1,
                'parent_id' => $grandChild->id,
                'tipe_coa' => 'Kasbank',
                'kode' => '410.00',
                'nama' => 'Parent Cycle',
                'deskripsi' => 'Mencoba loop',
            ]);

        $response
            ->assertRedirect(route('bukubesar.coa.edit', $parent))
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(null, $parent->fresh()->parent_coa);
    }

    public function test_update_allows_parent_that_already_has_children_even_if_it_has_transactions(): void
    {
        $allowedParent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '120.00',
            'nama' => 'Parent Existing',
            'is_postable' => false,
        ]);

        Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $allowedParent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => '120.01',
            'nama' => 'Child Existing',
            'is_postable' => true,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $allowedParent->id,
            'tanggal' => '2026-05-10',
            'nomer' => 'BB-ALLOWED',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 100,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Parent transaction',
        ]);

        $editableCoa = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => '130.01',
            'nama' => 'Akun Edit',
            'is_postable' => false,
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->put(route('bukubesar.coa.update', $editableCoa), [
                'status_aktif' => 1,
                'parent_id' => $allowedParent->id,
                'tipe_coa' => 'Kasbank',
                'kode' => '130.01',
                'nama' => 'Akun Edit',
                'deskripsi' => 'Updated',
            ]);

        $response->assertRedirect(route('bukubesar.coa.index'));

        $this->assertSame($allowedParent->id, $editableCoa->fresh()->parent_coa);
    }

    public function test_store_persists_cash_flow_mapping_and_validates_group_pair(): void
    {
        $user = $this->makeUser();

        $invalid = $this->actingAs($user)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01',
            'nama' => 'Pendapatan Pasien',
            'arus_kas_aktivitas' => 'operasi',
        ]);
        $invalid->assertSessionHasErrors('arus_kas_kelompok');

        $valid = $this->actingAs($user)->post(route('bukubesar.coa.store'), [
            'status_aktif' => 1,
            'tipe_coa' => 'Pendapatan',
            'kode' => '410.01',
            'nama' => 'Pendapatan Pasien',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan pasien',
        ]);

        $valid->assertRedirect(route('bukubesar.coa.index'));
        $this->assertDatabaseHas('coa', [
            'kode' => '410.01',
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Penerimaan pasien',
        ]);
    }

    private function createCoaLeafWithTransaction(string $kode, string $nama): Coa
    {
        $coa = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => true,
        ]);

        BukuBesar::query()->create([
            'coa_id' => $coa->id,
            'tanggal' => '2026-05-10',
            'nomer' => 'BB-001',
            'sumber_transaksi' => 'Jurnal Umum',
            'nominal' => 100,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Test transaksi',
        ]);

        return $coa;
    }

    private function createCoaWithChild(string $kode, string $nama): Coa
    {
        $parent = Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => null,
            'tipe_coa' => 'Kasbank',
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => false,
        ]);

        Coa::query()->create([
            'status_aktif' => 1,
            'parent_coa' => $parent->id,
            'tipe_coa' => 'Kasbank',
            'kode' => $kode.'.01',
            'nama' => $nama.' Child',
            'is_postable' => true,
        ]);

        return $parent;
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }
}
